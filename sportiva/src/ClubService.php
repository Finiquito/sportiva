<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

final class ClubService extends Repo
{
    /** Palabras que no pueden ser el slug de un club (chocarían con /app/club/<sección>). */
    public const RESERVADOS = ['invitar','directorio','perfil','ayuda','familias','equipos', 'personas', 'invitaciones', 'lugares', 'temporada', 'ajustes'];

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
        if (in_array($slug, self::RESERVADOS, true)) {
            $slug .= '-club';
        }
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

    public function nombreEquipo(string $id): ?string
    {
        $r = $this->fila('SELECT nombre FROM sportiva_equipos WHERE id = ?', [$id]);
        return $r['nombre'] ?? null;
    }

    // ---- panel de la directiva ----------------------------------------------------------------

    /** @return array{equipos: int, jugadores: int, familias: int, entrenadores: int, directiva: int} */
    public function resumen(string $club): array
    {
        $n = fn(string $sql, array $p = []) => (int) ($this->fila($sql, $p)['n'] ?? 0);
        return [
            'equipos' => $n('SELECT COUNT(*) AS n FROM sportiva_equipos WHERE club_id = ? AND archivado = 0', [$club]),
            'jugadores' => $n('SELECT COUNT(*) AS n FROM sportiva_jugadores WHERE club_id = ?', [$club]),
            'familias' => $n('SELECT COUNT(DISTINCT aj.persona_id) AS n FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id WHERE aj.titular = 1 AND j.club_id = ?', [$club]),
            'entrenadores' => $n("SELECT COUNT(DISTINCT persona_id) AS n FROM sportiva_membresias WHERE club_id = ? AND estado = 'activo' AND rol IN ('entrenador', 'encargado')", [$club]),
            'directiva' => $n("SELECT COUNT(DISTINCT persona_id) AS n FROM sportiva_membresias WHERE club_id = ? AND estado = 'activo' AND rol = 'directiva'", [$club]),
        ];
    }

    /** Equipos activos con la cantidad de jugadores y los nombres del cuerpo técnico. @return array<int, array<string, mixed>> */
    public function equiposConConteo(string $club): array
    {
        $r = $this->filas('SELECT e.*, (SELECT COUNT(*) FROM sportiva_jugadores j WHERE j.equipo_id = e.id) AS jugadores FROM sportiva_equipos e WHERE e.club_id = ? AND e.archivado = 0 ORDER BY e.nombre', [$club]);
        foreach ($r as &$e) {
            $e['staff'] = array_column($this->staffDe($e['id']), 'nombre');
        }
        return $r;
    }

    public function equipo(string $id): ?array
    {
        return $this->fila('SELECT e.*, (SELECT COUNT(*) FROM sportiva_jugadores j WHERE j.equipo_id = e.id) AS jugadores FROM sportiva_equipos e WHERE e.id = ?', [$id]);
    }

    public function actualizarEquipo(string $id, string $nombre, string $temporada): void
    {
        $this->ejecutar('UPDATE sportiva_equipos SET nombre = ?, temporada = ? WHERE id = ?', [trim($nombre), trim($temporada) ?: null, $id]);
    }

    /** Un equipo solo se archiva si ya no tiene jugadores (si no, quedarían sin equipo). */
    public function archivarEquipo(string $id): bool
    {
        if ((int) ($this->equipo($id)['jugadores'] ?? 1) > 0) {
            return false;
        }
        $this->ejecutar('UPDATE sportiva_equipos SET archivado = 1 WHERE id = ?', [$id]);
        return true;
    }

    /** Cuerpo técnico de un equipo. @return array<int, array<string, mixed>> */
    public function staffDe(string $equipo): array
    {
        return $this->filas('SELECT p.id, p.nombre, p.email, s.rol FROM sportiva_equipo_staff s JOIN sportiva_personas p ON p.id = s.persona_id WHERE s.equipo_id = ? ORDER BY p.nombre', [$equipo]);
    }

    /** Temporada actual por defecto (de agosto a julio): «2026 – 2027». */
    public static function temporadaPorDefecto(): string
    {
        $a = (int) date('Y');
        $a = (int) date('n') >= 8 ? $a : $a - 1;
        return $a . ' – ' . ($a + 1);
    }

    public function temporada(string $club): string
    {
        return (new Ajustes($this->pdo))->get('temporada', '', $club) ?: self::temporadaPorDefecto();
    }

    public function guardarTemporada(string $club, string $t): void
    {
        (new Ajustes($this->pdo))->set('temporada', mb_substr(trim($t), 0, 16), $club);
    }
}
