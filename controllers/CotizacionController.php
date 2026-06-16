<?php
declare(strict_types=1);

// ============================================================
// controllers/CotizacionController.php
// ============================================================

class CotizacionController
{
    private PDO $db;
    private MotorCotizacion $motor;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->motor = new MotorCotizacion();
    }

    // --------------------------------------------------------
    // CREAR cotización (Vendedor / Admin)
    // --------------------------------------------------------
    public function crear(array $input): array
    {
        Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR]);

        $resultado = $this->motor->calcular($input);
        if (!$resultado['ok']) {
            return $resultado;
        }

        $user        = Auth::user();
        $numeroCot   = $this->generarNumero();
        $fechaEmision= date('Y-m-d');
        $validez     = date('Y-m-d', strtotime('+21 days'));

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO cotizaciones
                    (cliente_id, vendedor_id, numero_cotizacion, fecha_emision,
                     validez_oferta, tipo_carga, servicio, origen, destino,
                     peso_kg, volumen_m3, wm_aplicado, valor_mercaderia,
                     tiene_marcas, viene_paletizado, es_apilable,
                     total_usd, total_bs, estado)
                    VALUES
                    (:cli, :vend, :num, :femis, :valid, :tcarga, :serv,
                     :orig, :dest, :peso, :vol, :wm, :vmerc,
                     :marcas, :palet, :apil, :tusd, :tbs, 'Borrador')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':cli'   => (int) $input['cliente_id'],
                ':vend'  => $user['id'],
                ':num'   => $numeroCot,
                ':femis' => $fechaEmision,
                ':valid' => $input['validez_oferta'] ?? $validez,
                ':tcarga'=> $input['tipo_carga']  ?? 'Mercadería General',
                ':serv'  => $input['servicio']    ?? 'LCL',
                ':orig'  => $input['origen']      ?? '',
                ':dest'  => $input['destino']     ?? '',
                ':peso'  => $resultado['wm'] > 0 ? (float)($input['peso_kg'] ?? 0) : 0,
                ':vol'   => (float) ($input['volumen_m3'] ?? 0),
                ':wm'    => $resultado['wm'],
                ':vmerc' => (float) ($input['valor_mercaderia'] ?? 0),
                ':marcas'=> (int) ($input['tiene_marcas']     ?? 1),
                ':palet' => (int) ($input['viene_paletizado'] ?? 1),
                ':apil'  => (int) ($input['es_apilable']      ?? 1),
                ':tusd'  => $resultado['total_usd'],
                ':tbs'   => $resultado['total_bs'],
            ]);
            $cotId = (int) $this->db->lastInsertId();

            // Insertar detalles
            $sqlDet = "INSERT INTO detalles_cotizacion
                       (cotizacion_id, orden, concepto, cantidad, costo_unitario, costo_calculado, moneda, es_recargo)
                       VALUES (:cid, :ord, :conc, :cant, :cunit, :ccalc, :mon, :rec)";
            $stDet  = $this->db->prepare($sqlDet);
            foreach ($resultado['detalles'] as $i => $d) {
                $stDet->execute([
                    ':cid'   => $cotId,
                    ':ord'   => $i + 1,
                    ':conc'  => $d['concepto'],
                    ':cant'  => $d['cantidad'],
                    ':cunit' => $d['costo_unitario'],
                    ':ccalc' => $d['costo_calculado'],
                    ':mon'   => $d['moneda'],
                    ':rec'   => (int) $d['es_recargo'],
                ]);
            }

            // Log de estado inicial
            $this->logEstado($cotId, null, 'Borrador', 'Cotización creada', $user['id']);

            $this->db->commit();

            return array_merge($resultado, [
                'cotizacion_id'    => $cotId,
                'numero_cotizacion'=> $numeroCot,
            ]);

        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()];
        }
    }

    // --------------------------------------------------------
    // LISTAR cotizaciones según rol
    // --------------------------------------------------------
    public function listar(array $filtros = []): array
    {
        Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR, ROL_OPERADOR, ROL_CLIENTE]);
        $user = Auth::user();

        $sql = "SELECT c.id, c.numero_cotizacion, c.fecha_emision, c.validez_oferta,
                       c.tipo_carga, c.origen, c.destino, c.wm_aplicado,
                       c.total_usd, c.total_bs, c.estado, c.created_at,
                       cl.nombre_razon AS cliente_nombre,
                       u.nombre        AS vendedor_nombre
                FROM   cotizaciones c
                JOIN   clientes cl ON cl.id = c.cliente_id
                JOIN   usuarios u  ON u.id  = c.vendedor_id
                WHERE  1=1 ";
        $params = [];

        // Filtro por rol
        if (Auth::isVendedor()) {
            $sql .= " AND c.vendedor_id = :uid ";
            $params[':uid'] = $user['id'];
        } elseif (Auth::isCliente()) {
            $sql .= " AND cl.usuario_id = :uid ";
            $params[':uid'] = $user['id'];
        } elseif (Auth::isOperador()) {
            $sql .= " AND c.estado IN ('Aprobada','En Tránsito','En Puerto','Desconsolidado') ";
        }

        // Filtros opcionales
        if (!empty($filtros['estado'])) {
            $sql .= " AND c.estado = :est ";
            $params[':est'] = $filtros['estado'];
        }
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND c.cliente_id = :cli ";
            $params[':cli'] = (int) $filtros['cliente_id'];
        }

        $sql .= " ORDER BY c.created_at DESC LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // --------------------------------------------------------
    // OBTENER una cotización con sus detalles
    // --------------------------------------------------------
    public function obtener(int $id): array|false
    {
        Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR, ROL_OPERADOR, ROL_CLIENTE]);
        $user = Auth::user();

        $stmt = $this->db->prepare(
            "SELECT c.*, cl.nombre_razon, cl.nit_ci, cl.email AS cliente_email,
                    u.nombre AS vendedor_nombre
             FROM   cotizaciones c
             JOIN   clientes cl ON cl.id = c.cliente_id
             JOIN   usuarios u  ON u.id  = c.vendedor_id
             WHERE  c.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $cot = $stmt->fetch();
        if (!$cot) return false;

        // Verificar acceso por rol
        if (Auth::isVendedor() && (int)$cot['vendedor_id'] !== $user['id']) return false;
        if (Auth::isCliente()) {
            // El cliente solo puede ver sus propias cotizaciones
            $stC = $this->db->prepare("SELECT usuario_id FROM clientes WHERE id = :id");
            $stC->execute([':id' => $cot['cliente_id']]);
            $cli = $stC->fetch();
            if (!$cli || (int)$cli['usuario_id'] !== $user['id']) return false;
        }

        // Cargar detalles
        $stDet = $this->db->prepare(
            "SELECT * FROM detalles_cotizacion WHERE cotizacion_id = :id ORDER BY orden"
        );
        $stDet->execute([':id' => $id]);
        $cot['detalles'] = $stDet->fetchAll();

        // Log de estados
        $stLog = $this->db->prepare(
            "SELECT l.*, u.nombre FROM log_estados l
             JOIN usuarios u ON u.id = l.usuario_id
             WHERE l.cotizacion_id = :id ORDER BY l.created_at DESC"
        );
        $stLog->execute([':id' => $id]);
        $cot['log_estados'] = $stLog->fetchAll();

        return $cot;
    }

    // --------------------------------------------------------
    // ACTUALIZAR ESTADO (Operador / Admin)
    // --------------------------------------------------------
    public function actualizarEstado(int $id, string $nuevoEstado, string $nota = ''): bool
    {
        Auth::checkRole([ROL_ADMIN, ROL_OPERADOR]);

        $estadosValidos = ['Borrador','Enviada','Aprobada','En Tránsito','En Puerto','Desconsolidado','Finalizada','Cancelada'];
        if (!in_array($nuevoEstado, $estadosValidos, true)) return false;

        $user = Auth::user();

        $stmt = $this->db->prepare("SELECT estado FROM cotizaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $actual = $stmt->fetchColumn();

        $this->db->prepare(
            "UPDATE cotizaciones SET estado = :est, notas_operacion = CONCAT(IFNULL(notas_operacion,''), :nota) WHERE id = :id"
        )->execute([':est' => $nuevoEstado, ':nota' => ($nota ? "\n[" . date('d/m/Y H:i') . "] {$nota}" : ''), ':id' => $id]);

        $this->logEstado($id, $actual ?: null, $nuevoEstado, $nota, $user['id']);
        return true;
    }

    // --------------------------------------------------------
    // GENERADOR DE NÚMERO DE COTIZACIÓN (COT.GEMZ/AA-XXXX)
    // --------------------------------------------------------
    private function generarNumero(): string
    {
        $año  = date('y');
        $stmt = $this->db->query(
            "SELECT COUNT(*)+1 AS seq FROM cotizaciones WHERE YEAR(fecha_emision) = YEAR(CURDATE())"
        );
        $seq  = (int) $stmt->fetchColumn();
        return sprintf('COT.GEMZ/%s-%04d', $año, $seq);
    }

    // --------------------------------------------------------
    // LOG INTERNO
    // --------------------------------------------------------
    private function logEstado(int $cotId, ?string $anterior, string $nuevo, string $nota, int $userId): void
    {
        $this->db->prepare(
            "INSERT INTO log_estados (cotizacion_id, usuario_id, estado_anterior, estado_nuevo, nota)
             VALUES (:cid, :uid, :ant, :nue, :nota)"
        )->execute([':cid' => $cotId, ':uid' => $userId, ':ant' => $anterior, ':nue' => $nuevo, ':nota' => $nota]);
    }
}
