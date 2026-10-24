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

    /** @param array<string, mixed> $d */
    public function actualizar(string $id, array $d): void
    {
        $this->ejecutar(
            'UPDATE sportiva_lugares SET nombre = ?, direccion = ?, lat = ?, lon = ?, club_local = ? WHERE id = ?',
            [trim((string) $d['nombre']), $d['direccion'] ?? null, $d['lat'] ?? null, $d['lon'] ?? null, $d['club_local'] ?? null, $id]
        );
    }

    /** Deja un solo lugar como predeterminado en el club. */
    public function predeterminar(string $club, string $id): void
    {
        $this->ejecutar('UPDATE sportiva_lugares SET predeterminado = 0 WHERE club_id = ?', [$club]);
        $this->ejecutar('UPDATE sportiva_lugares SET predeterminado = 1 WHERE id = ? AND club_id = ?', [$id, $club]);
    }

    public function enUso(string $id): int
    {
        return (int) ($this->fila('SELECT COUNT(*) AS n FROM sportiva_eventos WHERE lugar_id = ? OR encuentro_lugar_id = ?', [$id, $id])['n'] ?? 0);
    }

    /** Borra el lugar si ningún evento lo usa. */
    public function eliminar(string $id): bool
    {
        if ($this->enUso($id) > 0) {
            return false;
        }
        $this->ejecutar('DELETE FROM sportiva_lugares WHERE id = ?', [$id]);
        return true;
    }
}
