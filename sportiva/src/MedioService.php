<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Fotos y videos de los eventos. Control del espacio: cuota por equipo, videos de hasta N segundos y M MB que caducan.
 * Los archivos se guardan fuera del web-root (MedioArchivos) y se sirven solo a quien corresponde (MedioController).
 */
final class MedioService extends Repo
{
    /** @return array{cuota_mb: int, video_mb: int, video_seg: int, video_dias: int} */
    public function limites(): array
    {
        $a = new Ajustes($this->pdo);
        return [
            'cuota_mb' => max(100, (int) ($a->get('medios_cuota_mb') ?: 2048)), 'video_mb' => max(5, min(200, (int) ($a->get('medios_video_mb') ?: 50))),
            'video_seg' => max(5, min(300, (int) ($a->get('medios_video_seg') ?: 60))), 'video_dias' => max(7, (int) ($a->get('medios_video_dias') ?: 90)),
        ];
    }

    public function guardarLimites(array $in): void
    {
        $a = new Ajustes($this->pdo);
        foreach (['cuota_mb', 'video_mb', 'video_seg', 'video_dias'] as $k) {
            if (isset($in[$k]) && (int) $in[$k] > 0) {
                $a->set('medios_' . $k, (string) (int) $in[$k]);
            }
        }
    }

    public function buscar(string $id): ?array
    {
        return $this->fila(
            'SELECT m.*, p.nombre AS autor, ev.titulo AS evento, ev.inicio AS evento_inicio FROM sportiva_medios m
             JOIN sportiva_personas p ON p.id = m.subido_por JOIN sportiva_eventos ev ON ev.id = m.evento_id WHERE m.id = ?',
            [$id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function delEvento(string $evento, int $limite = 200): array
    {
        return $this->filas('SELECT m.*, p.nombre AS autor FROM sportiva_medios m JOIN sportiva_personas p ON p.id = m.subido_por WHERE m.evento_id = ? ORDER BY m.created_at DESC, m.id DESC LIMIT ' . (int) $limite, [$evento]);
    }

    public function cuantos(string $evento): int
    {
        return (int) ($this->fila('SELECT COUNT(*) AS n FROM sportiva_medios WHERE evento_id = ?', [$evento])['n'] ?? 0);
    }

    public function usoEquipoBytes(string $equipo): int
    {
        return (int) ($this->fila('SELECT COALESCE(SUM(bytes), 0) AS n FROM sportiva_medios WHERE equipo_id = ?', [$equipo])['n'] ?? 0);
    }

    /** ¿Entra otro archivo de `$bytes` en la cuota del equipo? */
    public function hayEspacio(string $equipo, int $bytes): bool
    {
        return $this->usoEquipoBytes($equipo) + $bytes <= $this->limites()['cuota_mb'] * 1024 * 1024;
    }

    /** @param array<string, mixed> $d */
    public function crear(string $id, array $d): void
    {
        $caduca = $d['tipo'] === 'video' ? Tiempo::ahora()->modify('+' . $this->limites()['video_dias'] . ' days')->format('Y-m-d H:i:s') : null;
        $this->ejecutar(
            'INSERT INTO sportiva_medios (id, club_id, equipo_id, evento_id, tipo, mime, bytes, ancho, alto, duracion, tiene_poster, subido_por, created_at, expira_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $d['club_id'], $d['equipo_id'], $d['evento_id'], $d['tipo'], $d['mime'], $d['bytes'], $d['ancho'] ?? null, $d['alto'] ?? null, $d['duracion'] ?? null, (int) ($d['tiene_poster'] ?? 0), $d['subido_por'], Tiempo::sello(), $caduca]
        );
    }

    public function borrar(string $id): void
    {
        foreach (['f', 'm', 'v'] as $v) {
            if (($r = MedioArchivos::ruta($id, $v)) !== null) {
                @unlink($r);
            }
        }
        $this->ejecutar('DELETE FROM sportiva_medios WHERE id = ?', [$id]);
    }

    /** Anterior y siguiente dentro del mismo evento. @return array{0: ?string, 1: ?string} */
    public function vecinos(array $m): array
    {
        $ids = array_column($this->filas('SELECT id FROM sportiva_medios WHERE evento_id = ? ORDER BY created_at DESC, id DESC', [$m['evento_id']]), 'id');
        $i = array_search($m['id'], $ids, true);
        return $i === false ? [null, null] : [$ids[$i - 1] ?? null, $ids[$i + 1] ?? null];
    }

    /** Eventos con fotos o videos nuevos. @param array<int, string>|null $equipos @return array<int, array<string, mixed>> */
    public function novedades(string $club, ?array $equipos, string $desde): array
    {
        $sql = "SELECT m.evento_id, ev.titulo, eq.nombre AS equipo, MAX(m.created_at) AS ultimo, SUM(CASE WHEN m.tipo = 'foto' THEN 1 ELSE 0 END) AS fotos, SUM(CASE WHEN m.tipo = 'video' THEN 1 ELSE 0 END) AS videos
                FROM sportiva_medios m JOIN sportiva_eventos ev ON ev.id = m.evento_id JOIN sportiva_equipos eq ON eq.id = m.equipo_id WHERE m.club_id = ? AND m.created_at >= ?";
        $par = [$club, $desde];
        if ($equipos !== null) {
            if ($equipos === []) {
                return [];
            }
            $sql .= ' AND m.equipo_id IN (' . implode(',', array_fill(0, count($equipos), '?')) . ')';
            $par = array_merge($par, $equipos);
        }
        return $this->filas($sql . ' GROUP BY m.evento_id, ev.titulo, eq.nombre ORDER BY ultimo DESC LIMIT 8', $par);
    }

    /** ¿Hubo alguna subida en este evento desde `$desde` (para no avisar en cada foto)? */
    public function subidaReciente(string $evento, string $desde): bool
    {
        return $this->fila('SELECT id FROM sportiva_medios WHERE evento_id = ? AND created_at >= ?', [$evento, $desde]) !== null;
    }

    /** Uso por equipo para el admin. @return array<int, array<string, mixed>> */
    public function usoPorEquipo(): array
    {
        return $this->filas(
            'SELECT eq.id, eq.nombre, c.nombre AS club, COALESCE(SUM(m.bytes), 0) AS bytes, COUNT(m.id) AS total
             FROM sportiva_equipos eq JOIN sportiva_clubes c ON c.id = eq.club_id LEFT JOIN sportiva_medios m ON m.equipo_id = eq.id GROUP BY eq.id, eq.nombre, c.nombre ORDER BY bytes DESC, c.nombre, eq.nombre'
        );
    }

    /** Borra los videos vencidos y las subidas a medias de más de un día. @return int videos borrados */
    public function purgar(): int
    {
        $n = 0;
        foreach ($this->filas("SELECT id FROM sportiva_medios WHERE tipo = 'video' AND expira_en IS NOT NULL AND expira_en <= ?", [Tiempo::sello()]) as $m) {
            $this->borrar($m['id']);
            $n++;
        }
        $tmp = Almacen::dir('medios-tmp');
        foreach (glob($tmp . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (filemtime($d) < time() - 86400) {
                MedioArchivos::borrarCarpeta($d);
            }
        }
        return $n;
    }

    /** Videos que caducan en los próximos días y aún no se avisó. @return array<int, array<string, mixed>> */
    public function porCaducar(int $dias = 7): array
    {
        return $this->filas(
            "SELECT m.*, ev.titulo AS evento FROM sportiva_medios m JOIN sportiva_eventos ev ON ev.id = m.evento_id
             WHERE m.tipo = 'video' AND m.caducidad_avisada = 0 AND m.expira_en IS NOT NULL AND m.expira_en <= ? AND m.expira_en > ?",
            [Tiempo::ahora()->modify('+' . $dias . ' days')->format('Y-m-d H:i:s'), Tiempo::sello()]
        );
    }

    public function caducidadAvisada(string $id): void
    {
        $this->ejecutar('UPDATE sportiva_medios SET caducidad_avisada = 1 WHERE id = ?', [$id]);
    }
}
