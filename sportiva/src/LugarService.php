<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

final class LugarService extends Repo
{
    public function delClub(string $club): array
    {
        return $this->filas('SELECT * FROM sportiva_lugares WHERE club_id = ? ORDER BY predeterminado DESC, nombre', [$club]);
    }

    public function buscar(string $id): ?array
    {
        return $this->fila('SELECT * FROM sportiva_lugares WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public function crear(string $club, array $d): string
    {
        $id = $this->id();
        $this->ejecutar(
            'INSERT INTO sportiva_lugares (id, club_id, nombre, direccion, lat, lon, club_local, predeterminado, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $club, trim((string) $d['nombre']), $d['direccion'] ?? null, $d['lat'] ?? null, $d['lon'] ?? null, $d['club_local'] ?? null, (int) ($d['predeterminado'] ?? 0), Tiempo::sello()]
        );
        return $id;
    }
}
