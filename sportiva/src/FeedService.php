<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Feed: avisos del entrenador y la directiva (por equipo o para todo el club), con comentarios de las familias. */
final class FeedService extends Repo
{
    public const MAX_TEXTO = 2000;
    public const MAX_COMENTARIO = 600;

    /** Equipos cuyo feed ve una persona: null = todos (encargado, directiva). @return array<int, string>|null */
    public function equiposDe(string $rol, string $persona, string $club, array $gestionables): ?array
    {
        return match ($rol) {
            'apoderado' => array_column($this->filas('SELECT DISTINCT j.equipo_id FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id WHERE aj.persona_id = ? AND j.club_id = ?', [$persona, $club]), 'equipo_id'),
            'jugador' => array_column($this->filas('SELECT equipo_id FROM sportiva_jugadores WHERE persona_id = ? AND club_id = ?', [$persona, $club]), 'equipo_id'),
            'entrenador' => array_column($gestionables, 'id'),
            default => null,
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function posts(string $club, ?array $equipos, string $vista, int $limite = 40): array
    {
        $sql = 'SELECT p.*, a.nombre AS autor, e.nombre AS equipo, (SELECT COUNT(*) FROM sportiva_comentarios c WHERE c.post_id = p.id) AS comentarios
                FROM sportiva_posts p JOIN sportiva_personas a ON a.id = p.autor_id LEFT JOIN sportiva_equipos e ON e.id = p.equipo_id WHERE p.club_id = ?';
        $par = [$club];
        if ($vista === 'club') {
            $sql .= ' AND p.equipo_id IS NULL';
        } elseif ($equipos === null) {
            $sql .= ' AND p.equipo_id IS NOT NULL';
        } elseif ($equipos === []) {
            return [];
        } else {
            $sql .= ' AND p.equipo_id IN (' . implode(',', array_fill(0, count($equipos), '?')) . ')';
            $par = array_merge($par, $equipos);
        }
        return $this->filas($sql . ' ORDER BY p.fijado DESC, p.created_at DESC LIMIT ' . (int) $limite, $par);
    }

    /** Convocatorias publicadas hace poco (aparecen en el feed del equipo). @return array<int, array<string, mixed>> */
    public function convocatorias(string $club, ?array $equipos, string $desde): array
    {
        $sql = "SELECT ev.id, ev.titulo, ev.tipo, ev.inicio, ev.fin, ev.hora_cancha, ev.hora_encuentro, ev.created_at, eq.nombre AS equipo, l.nombre AS lugar FROM sportiva_eventos ev JOIN sportiva_equipos eq ON eq.id = ev.equipo_id LEFT JOIN sportiva_lugares l ON l.id = ev.lugar_id
                WHERE ev.club_id = ? AND ev.estado = 'publicado' AND ev.created_at >= ? AND ev.inicio >= ?";
        $par = [$club, $desde, $desde];
        if ($equipos !== null) {
            if ($equipos === []) {
                return [];
            }
            $sql .= ' AND ev.equipo_id IN (' . implode(',', array_fill(0, count($equipos), '?')) . ')';
            $par = array_merge($par, $equipos);
        }
        return $this->filas($sql . ' ORDER BY ev.created_at DESC LIMIT 10', $par);
    }

    /** Partidos terminados hace poco, con su marcador (aparecen en el feed). @return array<int, array<string, mixed>> */
    public function resultados(string $club, ?array $equipos, string $desde): array
    {
        $sql = "SELECT ev.id, ev.titulo, ev.rival, ev.inicio, eq.nombre AS equipo, v.nuestro, v.rival AS goles_rival, v.confirmado, v.updated_at
                FROM sportiva_envivo v JOIN sportiva_eventos ev ON ev.id = v.evento_id JOIN sportiva_equipos eq ON eq.id = ev.equipo_id
                WHERE ev.club_id = ? AND ev.estado = 'publicado' AND v.estado = 'terminado' AND v.updated_at >= ?";
        $par = [$club, $desde];
        if ($equipos !== null) {
            if ($equipos === []) {
                return [];
            }
            $sql .= ' AND ev.equipo_id IN (' . implode(',', array_fill(0, count($equipos), '?')) . ')';
            $par = array_merge($par, $equipos);
        }
        return $this->filas($sql . ' ORDER BY v.updated_at DESC LIMIT 10', $par);
    }

    public function post(string $id): ?array
    {
        return $this->fila('SELECT p.*, a.nombre AS autor, e.nombre AS equipo FROM sportiva_posts p JOIN sportiva_personas a ON a.id = p.autor_id LEFT JOIN sportiva_equipos e ON e.id = p.equipo_id WHERE p.id = ?', [$id]);
    }

    /** ¿Esta persona puede ver el post? @param array<int, string>|null $equipos */
    public function visible(array $p, string $club, ?array $equipos): bool
    {
        return $p['club_id'] === $club && ($p['equipo_id'] === null || $equipos === null || in_array($p['equipo_id'], $equipos, true));
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} */
    public function normalizar(array $in): array
    {
        $d = ['titulo' => trim((string) ($in['titulo'] ?? '')), 'texto' => trim((string) ($in['texto'] ?? '')), 'destino' => (string) ($in['destino'] ?? ''), 'fijado' => !empty($in['fijado']) ? '1' : '0'];
        $e = [];
        if ($d['texto'] === '' || mb_strlen($d['texto']) > self::MAX_TEXTO) {
            $e['texto'] = 'feed.error.texto';
        }
        if (mb_strlen($d['titulo']) > 160) {
            $e['titulo'] = 'feed.error.titulo';
        }
        return [$d, $e];
    }

    public function crear(string $club, ?string $equipo, string $autor, array $d): string
    {
        $id = $this->id();
        $this->ejecutar('INSERT INTO sportiva_posts (id, club_id, equipo_id, autor_id, titulo, texto, fijado, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$id, $club, $equipo, $autor, $d['titulo'] ?: null, $d['texto'], (int) $d['fijado'], Tiempo::sello()]);
        return $id;
    }

    public function fijar(string $id, bool $fijado): void
    {
        $this->ejecutar('UPDATE sportiva_posts SET fijado = ? WHERE id = ?', [(int) $fijado, $id]);
    }

    public function borrar(string $id): void
    {
        $this->ejecutar('DELETE FROM sportiva_comentarios WHERE post_id = ?', [$id]);
        $this->ejecutar('DELETE FROM sportiva_posts WHERE id = ?', [$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function comentarios(string $post): array
    {
        return $this->filas('SELECT c.*, p.nombre AS autor FROM sportiva_comentarios c JOIN sportiva_personas p ON p.id = c.persona_id WHERE c.post_id = ? ORDER BY c.created_at, c.id', [$post]);
    }

    /** @return string|null id del comentario, o null si el texto no sirve */
    public function comentar(string $post, string $persona, string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '' || mb_strlen($texto) > self::MAX_COMENTARIO) {
            return null;
        }
        $id = $this->id();
        $this->ejecutar('INSERT INTO sportiva_comentarios (id, post_id, persona_id, texto, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $post, $persona, $texto, Tiempo::sello()]);
        return $id;
    }

    public function comentario(string $id): ?array
    {
        return $this->fila('SELECT * FROM sportiva_comentarios WHERE id = ?', [$id]);
    }

    public function borrarComentario(string $id): void
    {
        $this->ejecutar('DELETE FROM sportiva_comentarios WHERE id = ?', [$id]);
    }
}
