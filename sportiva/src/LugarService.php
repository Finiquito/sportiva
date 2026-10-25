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
        $this->ejecutar('DELETE FROM sportiva_lugares_locales WHERE lugar_id = ?', [$id]);
        $this->ejecutar('DELETE FROM sportiva_lugares WHERE id = ?', [$id]);
        return true;
    }

    public function esLocal(?string $id): bool
    {
        return $id !== null && $id !== '' && $this->fila('SELECT lugar_id FROM sportiva_lugares_locales WHERE lugar_id = ?', [$id]) !== null;
    }

    public function marcarLocal(string $club, string $id, bool $local): void
    {
        $this->ejecutar('DELETE FROM sportiva_lugares_locales WHERE lugar_id = ?', [$id]);
        if ($local && $this->fila('SELECT id FROM sportiva_lugares WHERE id = ? AND club_id = ?', [$id, $club]) !== null) {
            $this->ejecutar('INSERT INTO sportiva_lugares_locales (lugar_id, club_id) VALUES (?, ?)', [$id, $club]);
        }
    }

    /** Lugares donde el club es local. @return array<int, array<string, mixed>> */
    public function locales(string $club): array
    {
        return $this->filas('SELECT l.* FROM sportiva_lugares l JOIN sportiva_lugares_locales x ON x.lugar_id = l.id WHERE l.club_id = ? ORDER BY l.nombre', [$club]);
    }

    public function ponerCoordenadas(string $id, string $lat, string $lon): void
    {
        $this->ejecutar('UPDATE sportiva_lugares SET lat = ?, lon = ? WHERE id = ?', [$lat, $lon, $id]);
    }
}
