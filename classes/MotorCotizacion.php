<?php
declare(strict_types=1);

// ============================================================
// classes/MotorCotizacion.php — Motor de Cálculo LCL
// ============================================================

class MotorCotizacion
{
    private array  $tarifas  = [];
    private array  $alertas  = [];
    private array  $detalles = [];
    private float  $totalUsd = 0.0;
    private float  $totalBs  = 0.0;
    private string $origen   = '';
    private string $destino  = '';

    public function __construct()
    {
        $this->cargarTarifas();
    }

    // --------------------------------------------------------
    // Cargar tarifas vigentes desde la BD
    // --------------------------------------------------------
    private function cargarTarifas(): void
    {
        $db   = Database::getInstance();
        $stmt = $db->query(
            "SELECT concepto_servicio, origen, destino, tarifa_usd, moneda_cobro
             FROM   tarifario_base
             WHERE  vigente_hasta IS NULL OR vigente_hasta >= CURDATE()
             ORDER  BY id"
        );
        $this->tarifas = $stmt->fetchAll();
    }

    // --------------------------------------------------------
    // PUNTO DE ENTRADA PRINCIPAL
    // Input: array con los datos del formulario
    // Output: array con detalles, totales y alertas
    // --------------------------------------------------------
    public function calcular(array $input): array
    {
        $this->alertas  = [];
        $this->detalles = [];
        $this->totalUsd = 0.0;
        $this->totalBs  = 0.0;
        $this->origen   = trim($input['origen'] ?? '');
        $this->destino  = trim($input['destino'] ?? '');

        // 1. Validar IMO
        if (!$this->validarIMO($input)) {
            return $this->respuestaError('Carga IMO rechazada por política de seguridad.');
        }

        // 2. Extraer y normalizar inputs
        $pesoKg    = max(0.0, (float) ($input['peso_kg']     ?? 0));
        $volM3     = max(0.0, (float) ($input['volumen_m3']  ?? 0));
        $largoFt   = max(0.0, (float) ($input['largo_pies']  ?? 0));
        $pesoBulto = max(0.0, (float) ($input['peso_bulto_lbs'] ?? 0));
        $altura    = max(0.0, (float) ($input['altura_m']    ?? 0));
        $valMerc   = max(0.0, (float) ($input['valor_mercaderia'] ?? 0));
        $origen    = trim($input['origen'] ?? '');
        $marcas    = (bool) ($input['tiene_marcas']     ?? true);
        $paletizado= (bool) ($input['viene_paletizado'] ?? true);

        // 3. Regla W/M
        $wm = $this->calcularWM($pesoKg, $volM3);

        // 4. Partidas en USD (obligatorio pago en USD)
        $this->calcularFleteMaritimo($wm, $origen);
        $this->calcularCollectFee();
        $this->calcularDesconsolidacion($wm, $pesoKg, $volM3);
        $this->calcularCargosOrigen();
        $this->calcularInbondFee($origen);
        $this->calcularSED($valMerc, $origen);

        // 5. Partidas en Bolivianos
        $this->calcularFleteTerrestre($wm);
        $this->calcularHandling();
        $this->calcularApertura();
        $this->calcularServicioLogistico();

        // 6. Recargos especiales
        $this->calcularExtraLargo($largoFt);
        $this->calcularSobrepeso($pesoBulto);
        $this->verificarAlturaHC($altura);
        $this->verificarMarcas($marcas);
        $this->verificarPaletizado($paletizado, $pesoKg);

        return [
            'ok'          => true,
            'wm'          => round($wm, 2),
            'detalles'    => $this->detalles,
            'total_usd'   => round($this->totalUsd, 2),
            'total_bs'    => round($this->totalBs, 2),
            'total_equiv' => round($this->totalUsd + ($this->totalBs / TC_USD_BS), 2),
            'alertas'     => $this->alertas,
        ];
    }

    // --------------------------------------------------------
    // REGLA W/M
    // --------------------------------------------------------
    private function calcularWM(float $pesoKg, float $volM3): float
    {
        $pesoT = $pesoKg / 1000;
        if ($pesoKg > PESO_MIN_WM_KG || $volM3 > VOL_MIN_WM_M3) {
            return max($pesoT, $volM3);
        }
        return max($pesoT, $volM3); // Aplica igual, mínimos se controlan en desconsolidación
    }

    // --------------------------------------------------------
    // FLETE MARÍTIMO (USD)
    // --------------------------------------------------------
    private function calcularFleteMaritimo(float $wm, string $origen): void
    {
        $tarifa = $this->getTarifa('Flete Marítimo LCL', $this->origen);
        $costo  = round($wm * $tarifa, 2);
        $this->addDetalle('Flete Marítimo EXW LCL/LCL', $wm, $tarifa, $costo, 'USD');
    }

    // --------------------------------------------------------
    // COLLECT FEE (USD)
    // --------------------------------------------------------
    private function calcularCollectFee(): void
    {
        $costo = $this->getTarifa('Collect Fee', $this->origen);
        $this->addDetalle('Collect Fee', 1, $costo, $costo, 'USD');
    }

    // --------------------------------------------------------
    // DESCONSOLIDACIÓN (USD) — con mínimos
    // --------------------------------------------------------
    private function calcularDesconsolidacion(float $wm, float $pesoKg, float $volM3): void
    {
        $esMinimo = ($pesoKg <= KG_MINIMO_DESC || $volM3 <= M3_MINIMO_DESC);
        if ($esMinimo) {
            $costo  = $this->getTarifa('Desconsolidación Mínimo');
            $concepto = 'Desconsolidación (tarifa mínima ≤2500 kg / ≤2.9 m³)';
            $this->alertas[] = ['tipo' => 'info', 'msg' => 'Carga bajo mínimo: se aplica tarifa mínima de desconsolidación.'];
            $this->addDetalle($concepto, 1, $costo, $costo, 'USD');
        } else {
            $tarifa = $this->getTarifa('Desconsolidación');
            $costo  = round($wm * $tarifa, 2);
            $this->addDetalle('Desconsolidación', $wm, $tarifa, $costo, 'USD');
        }
    }

    // --------------------------------------------------------
    // CARGOS EN ORIGEN (USD)
    // --------------------------------------------------------
    private function calcularCargosOrigen(): void
    {
        $costo = $this->getTarifa('Cargos en Origen', $this->origen);
        $this->addDetalle('Cargos en Origen', 1, $costo, $costo, 'USD');
    }

    // --------------------------------------------------------
    // INBOND FEE — Solo si origen es Canadá (USD)
    // --------------------------------------------------------
    private function calcularInbondFee(string $origen): void
    {
        if (stripos($origen, 'canad') !== false) {
            $costo = INBOND_CANADA;
            $this->addDetalle('Inbond Fee (Canadá)', 1, $costo, $costo, 'USD');
            $this->alertas[] = ['tipo' => 'info', 'msg' => 'Origen Canadá: se aplica Inbond Fee USD ' . INBOND_CANADA];
        }
    }

    // --------------------------------------------------------
    // SED — Si valor mercadería > USD 2500 y origen USA/Canadá (USD)
    // --------------------------------------------------------
    private function calcularSED(float $valMerc, string $origen): void
    {
        $origenSED = stripos($origen, 'canad') !== false || stripos($origen, 'usa') !== false
                  || stripos($origen, 'miami') !== false || stripos($origen, 'estados') !== false;
        if ($origenSED && $valMerc > VALOR_SED_USD) {
            $costo = COSTO_SED;
            $this->addDetalle('SED (valor mercadería >USD 2,500)', 1, $costo, $costo, 'USD');
        }
    }

    // --------------------------------------------------------
    // FLETE TERRESTRE (BS)
    // --------------------------------------------------------
    private function calcularFleteTerrestre(float $wm): void
    {
        $tarifaUsd = $this->getTarifa('Flete Terrestre', null, $this->destino);
        $costoUsd  = round($wm * $tarifaUsd, 2);
        $costoBs   = round($costoUsd * TC_USD_BS, 2);
        $this->addDetalle('Flete Terrestre (Iquique → Bolivia)', $wm, $tarifaUsd, $costoBs, 'BS');
    }

    // --------------------------------------------------------
    // HANDLING (BS)
    // --------------------------------------------------------
    private function calcularHandling(): void
    {
        $usd  = $this->getTarifa('Handling');
        $bs   = round($usd * TC_USD_BS, 2);
        $this->addDetalle('Handling', 1, $usd, $bs, 'BS');
    }

    // --------------------------------------------------------
    // APERTURA (BS)
    // --------------------------------------------------------
    private function calcularApertura(): void
    {
        $usd = $this->getTarifa('Apertura de expediente');
        $bs  = round($usd * TC_USD_BS, 2);
        $this->addDetalle('Apertura de expediente', 1, $usd, $bs, 'BS');
    }

    // --------------------------------------------------------
    // SERVICIO LOGÍSTICO (BS)
    // --------------------------------------------------------
    private function calcularServicioLogistico(): void
    {
        $usd = $this->getTarifa('Servicio Logístico');
        $bs  = round($usd * TC_USD_BS, 2);
        $this->addDetalle('Servicio Logístico', 1, $usd, $bs, 'BS');
    }

    // --------------------------------------------------------
    // RECARGO: EXTRA LARGO (USD)
    // --------------------------------------------------------
    private function calcularExtraLargo(float $largoFt): void
    {
        if ($largoFt > LARGO_EXTRA_PIES) {
            $pieExtra = $largoFt - LARGO_EXTRA_PIES;
            $costo    = round($pieExtra * COSTO_EXTRA_PIE, 2);
            $this->addDetalle(
                sprintf('Extra Largo (+%.1f pies sobre %d pies)', $pieExtra, LARGO_EXTRA_PIES),
                $pieExtra, COSTO_EXTRA_PIE, $costo, 'USD', true
            );
            $this->alertas[] = ['tipo' => 'warning', 'msg' => "Extra largo: {$largoFt} pies → recargo USD {$costo}"];
        }
    }

    // --------------------------------------------------------
    // RECARGO: SOBREPESO OWS (USD)
    // --------------------------------------------------------
    private function calcularSobrepeso(float $lbs): void
    {
        if ($lbs > PESO_OWS_LBS) {
            $costo = COSTO_OWS_FIJO + COSTO_OWS_FORK;
            $this->addDetalle(
                sprintf('OWS Sobrepeso >%d lbs (USD %.0f fijo + USD %.0f forklift)', PESO_OWS_LBS, COSTO_OWS_FIJO, COSTO_OWS_FORK),
                1, $costo, $costo, 'USD', true
            );
            $this->alertas[] = ['tipo' => 'warning', 'msg' => "Sobrepeso {$lbs} lbs → OWS USD {$costo}"];
        }
    }

    // --------------------------------------------------------
    // ALERTA: HIGH CUBE
    // --------------------------------------------------------
    private function verificarAlturaHC(float $alturaM): void
    {
        if ($alturaM > ALTURA_HC_M) {
            $this->alertas[] = [
                'tipo' => 'warning',
                'msg'  => "Carga {$alturaM}m > " . ALTURA_HC_M . "m: requiere contenedor High Cube. Se aplicarán recargos adicionales por espacio muerto.",
            ];
        }
    }

    // --------------------------------------------------------
    // RECARGO: SIN MARCAS DE ORIGEN (USD)
    // --------------------------------------------------------
    private function verificarMarcas(bool $tieneMarcas): void
    {
        if (!$tieneMarcas) {
            $costo = COSTO_MARCAS;
            $this->addDetalle(
                'Aclaración al Manifiesto (sin marcas de origen — TPA, ASPB, Aduana)',
                1, $costo, $costo, 'USD', true
            );
            $this->alertas[] = ['tipo' => 'danger', 'msg' => "Sin marcas de origen: se aplica USD {$costo} por aclaración al manifiesto."];
        }
    }

    // --------------------------------------------------------
    // ALERTA: SIN PALETIZAR (Ley 20001 Chile)
    // --------------------------------------------------------
    private function verificarPaletizado(bool $paletizado, float $pesoKg): void
    {
        if (!$paletizado && $pesoKg > 25) {
            $this->alertas[] = [
                'tipo' => 'warning',
                'msg'  => 'Ley 20001 Chile: carga >' . $pesoKg . ' kg sin paletizar. Se cobrará paletizaje obligatorio en puerto según origen.',
            ];
        }
    }

    // --------------------------------------------------------
    // FILTRO IMO
    // --------------------------------------------------------
    private function validarIMO(array $input): bool
    {
        $claseIMO   = (int) ($input['clase_imo']   ?? 0);
        $flashPoint = (float) ($input['flash_point'] ?? 0);

        // Clases bloqueadas: 1, 5, 6, 7
        if (in_array($claseIMO, [1, 5, 6, 7], true)) {
            $this->alertas[] = [
                'tipo' => 'danger',
                'msg'  => "Clase IMO {$claseIMO} bloqueada. No se acepta sin aprobación de gerencia y MSDS completo.",
            ];
            return false;
        }
        // Clase 3 con flash point < -18°C
        if ($claseIMO === 3 && $flashPoint < -18.0) {
            $this->alertas[] = [
                'tipo' => 'danger',
                'msg'  => "Clase IMO 3 con flash point {$flashPoint}°C < -18°C: RECHAZADA.",
            ];
            return false;
        }
        return true;
    }

    // --------------------------------------------------------
    // HELPERS
    // --------------------------------------------------------
    private function getTarifa(string $concepto, ?string $customOrigen = null, ?string $customDestino = null): float
    {
        $bestMatch = null;
        $bestScore = -1;

        foreach ($this->tarifas as $t) {
            if (strcasecmp($t['concepto_servicio'], $concepto) !== 0) {
                continue;
            }

            $score = 0;

            // Check origin match if requested
            if ($customOrigen !== null && $t['origen'] !== 'Cualquiera' && $t['origen'] !== 'Bolivia') {
                $cleanTOrigen = strtolower(trim(str_replace(['CN', 'China', 'USA', 'EEUU', 'US'], '', $t['origen'])));
                $cleanOrigen = strtolower(trim(str_replace(['CN', 'China', 'USA', 'EEUU', 'US'], '', $customOrigen)));
                if (strcasecmp($t['origen'], $customOrigen) === 0) {
                    $score += 10;
                } elseif (stripos($customOrigen, $t['origen']) !== false || stripos($t['origen'], $customOrigen) !== false) {
                    $score += 5;
                } elseif ($cleanTOrigen !== '' && (stripos($cleanOrigen, $cleanTOrigen) !== false || stripos($cleanTOrigen, $cleanOrigen) !== false)) {
                    $score += 4;
                } else {
                    continue; // No match on origin
                }
            } else {
                $score += 1;
            }

            // Check destination match if requested
            if ($customDestino !== null && $t['destino'] !== 'Cualquiera' && $t['destino'] !== 'Bolivia') {
                $cleanTDest = strtolower(trim(str_replace(['Bolivia'], '', $t['destino'])));
                $cleanDest = strtolower(trim(str_replace(['Bolivia'], '', $customDestino)));
                if (strcasecmp($t['destino'], $customDestino) === 0) {
                    $score += 10;
                } elseif (stripos($customDestino, $t['destino']) !== false || stripos($t['destino'], $customDestino) !== false) {
                    $score += 5;
                } elseif ($cleanTDest !== '' && (stripos($cleanDest, $cleanTDest) !== false || stripos($cleanTDest, $cleanDest) !== false)) {
                    $score += 4;
                } else {
                    continue; // No match on destination
                }
            } else {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $t;
            }
        }

        return $bestMatch ? (float)$bestMatch['tarifa_usd'] : 0.0;
    }

    private function addDetalle(
        string $concepto,
        float  $cantidad,
        float  $costoUnit,
        float  $costoCalc,
        string $moneda,
        bool   $esRecargo = false
    ): void {
        $this->detalles[] = [
            'concepto'       => $concepto,
            'cantidad'       => $cantidad,
            'costo_unitario' => $costoUnit,
            'costo_calculado'=> $costoCalc,
            'moneda'         => $moneda,
            'es_recargo'     => $esRecargo,
        ];
        if ($moneda === 'USD') {
            $this->totalUsd += $costoCalc;
        } else {
            $this->totalBs  += $costoCalc;
        }
    }

    private function respuestaError(string $msg): array
    {
        return [
            'ok'      => false,
            'error'   => $msg,
            'alertas' => $this->alertas,
        ];
    }
}
