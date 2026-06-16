<?php
declare(strict_types=1);

// ============================================================
// controllers/TarifarioController.php — Solo Administrador
// ============================================================

class TarifarioController
{
    private PDO $db;

    public function __construct()
    {
        Auth::checkRole([ROL_ADMIN]);
        $this->db = Database::getInstance();
    }

    public function listar(): array
    {
        return $this->db->query(
            "SELECT t.*, u.nombre AS actualizado_por_nombre
             FROM   tarifario_base t
             LEFT JOIN usuarios u ON u.id = t.actualizado_por
             ORDER  BY t.id"
        )->fetchAll();
    }

    public function actualizar(int $id, float $nuevaTarifa, string $moneda): bool
    {
        $user = Auth::user();
        $stmt = $this->db->prepare(
            "UPDATE tarifario_base
             SET    tarifa_usd = :tarifa, moneda_cobro = :mon,
                    actualizado_por = :uid, updated_at = NOW()
             WHERE  id = :id"
        );
        return $stmt->execute([
            ':tarifa' => $nuevaTarifa,
            ':mon'    => $moneda,
            ':uid'    => $user['id'],
            ':id'     => $id,
        ]);
    }

    public function crear(array $data): int
    {
        $user = Auth::user();
        $stmt = $this->db->prepare(
            "INSERT INTO tarifario_base
             (concepto_servicio, unidad_medida, origen, destino, tarifa_usd, moneda_cobro, vigente_desde, actualizado_por)
             VALUES (:conc, :uni, :orig, :dest, :tar, :mon, CURDATE(), :uid)"
        );
        $stmt->execute([
            ':conc' => $data['concepto_servicio'],
            ':uni'  => $data['unidad_medida'],
            ':orig' => $data['origen'],
            ':dest' => $data['destino'],
            ':tar'  => (float) $data['tarifa_usd'],
            ':mon'  => $data['moneda_cobro'],
            ':uid'  => $user['id'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        // Marcar como vencido en lugar de borrar
        return $this->db->prepare(
            "UPDATE tarifario_base SET vigente_hasta = CURDATE() WHERE id = :id"
        )->execute([':id' => $id]);
    }
}
