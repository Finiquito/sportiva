<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

final class ClubService extends Repo
{
    public function todos(): array
    {
        return $this->filas('SELECT * FROM sportiva_clubes ORDER BY nombre');
    }

    public function publicos(): array
    {
        return $this->filas('SELECT * FROM sportiva_clubes WHERE publico = 1 ORDER BY nombre');
    }

    public function buscar(string $id): ?array
    {
        return $this->fila('SELECT * FROM sportiva_clubes WHERE id = ?', [$id]);
    }

    public function porSlug(string $slug): ?array
    {
        return $this->fila('SELECT * FROM sportiva_clubes WHERE slug = ?', [$slug]);
    }

    public static function slugDe(string $nombre): string
    {
        $s = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre));
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
        return $s !== '' ? $s : 'club';
    }

    /** @param array<string, mixed> $d */
    public function crear(array $d): string
    {
        $id = $this->id();
        $slug = self::slugDe((string) ($d['slug'] ?? '') ?: (string) $d['nombre']);
        $base = $slug;
        for ($n = 2; $this->porSlug($slug) !== null; $n++) {
            $slug = $base . '-' . $n;
        }
        $this->ejecutar(
            'INSERT INTO sportiva_clubes (id, nombre, slug, ciudad, deporte, idioma, zona, telefono, direccion, descripcion, lat, lon, publico, exigir_app, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, trim((string) $d['nombre']), $slug, $d['ciudad'] ?? null, $d['deporte'] ?? 'futbol', $d['idioma'] ?? 'fr',
                $d['zona'] ?? 'Europe/Paris', $d['telefono'] ?? null, $d['direccion'] ?? null, $d['descripcion'] ?? null,
                $d['lat'] ?? null, $d['lon'] ?? null, (int) ($d['publico'] ?? 1), (int) ($d['exigir_app'] ?? 1), Tiempo::sello(),
            ]
        );
        return $id;
    }

    /** @param array<string, mixed> $d */
    public function actualizar(string $id, array $d): void
    {
        $this->ejecutar(
            'UPDATE sportiva_clubes SET nombre = ?, ciudad = ?, deporte = ?, idioma = ?, zona = ?, telefono = ?, direccion = ?, descripcion = ?, lat = ?, lon = ?, publico = ?, exigir_app = ? WHERE id = ?',
            [
                trim((string) $d['nombre']), $d['ciudad'] ?? null, $d['deporte'] ?? 'futbol', $d['idioma'] ?? 'fr', $d['zona'] ?? 'Europe/Paris',
                $d['telefono'] ?? null, $d['direccion'] ?? null, $d['descripcion'] ?? null, $d['lat'] ?? null, $d['lon'] ?? null,
                (int) ($d['publico'] ?? 1), (int) ($d['exigir_app'] ?? 1), $id,
            ]
        );
    }

    public function equipos(string $club): array
    {
        return $this->filas('SELECT * FROM sportiva_equipos WHERE club_id = ? AND archivado = 0 ORDER BY nombre', [$club]);
    }

    public function crearEquipo(string $club, string $nombre, string $temporada = ''): string
    {
        $id = $this->id();
        $this->ejecutar('INSERT INTO sportiva_equipos (id, club_id, nombre, temporada, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $club, trim($nombre), $temporada ?: null, Tiempo::sello()]);
        return $id;
    }
}
