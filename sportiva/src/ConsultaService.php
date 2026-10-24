<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Consultas privadas: una familia escribe al cuerpo técnico sobre un hijo; solo la ven la familia y los entrenadores del equipo. */
final class ConsultaService extends Repo
{
    public const MAX_TEXTO = 2000;

    public function buscar(string $id): ?array
    {
        return $this->fila(
            'SELECT c.*, j.nombre AS jugador, j.equipo_id, e.nombre AS equipo, p.nombre AS familia, ev.titulo AS evento
             FROM sportiva_consultas c JOIN sportiva_jugadores j ON j.id = c.jugador_id JOIN sportiva_equipos e ON e.id = j.equipo_id
             JOIN sportiva_personas p ON p.id = c.creada_por LEFT JOIN sportiva_eventos ev ON ev.id = c.evento_id WHERE c.id = ?',
            [$id]
        );
    }

    /** De la familia: las que creó o las de sus hijos. @return array<int, array<string, mixed>> */
    public function deFamilia(string $persona, string $club): array
    {
        return $this->filas(
            'SELECT c.*, j.nombre AS jugador, e.nombre AS equipo, (SELECT m.texto FROM sportiva_mensajes m WHERE m.consulta_id = c.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS ultimo
             FROM sportiva_consultas c JOIN sportiva_jugadores j ON j.id = c.jugador_id JOIN sportiva_equipos e ON e.id = j.equipo_id
             WHERE c.club_id = ? AND (c.creada_por = ? OR c.jugador_id IN (SELECT jugador_id FROM sportiva_apoderados_jugadores WHERE persona_id = ?))
             ORDER BY c.updated_at DESC',
            [$club, $persona, $persona]
        );
    }

    /** Del cuerpo técnico. @param array<int, string>|null $equipos null = todo el club @return array<int, array<string, mixed>> */
    public function deStaff(string $club, ?array $equipos, bool $soloPendientes): array
    {
        $sql = 'SELECT c.*, j.nombre AS jugador, e.nombre AS equipo, p.nombre AS familia, (SELECT m.texto FROM sportiva_mensajes m WHERE m.consulta_id = c.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS ultimo
                FROM sportiva_consultas c JOIN sportiva_jugadores j ON j.id = c.jugador_id JOIN sportiva_equipos e ON e.id = j.equipo_id JOIN sportiva_personas p ON p.id = c.creada_por
                WHERE c.club_id = ?';
        $par = [$club];
        if ($equipos !== null) {
            if ($equipos === []) {
                return [];
            }
            $sql .= ' AND j.equipo_id IN (' . implode(',', array_fill(0, count($equipos), '?')) . ')';
            $par = array_merge($par, $equipos);
        }
        if ($soloPendientes) {
            $sql .= " AND c.ultimo_de = 'familia'";
        }
        return $this->filas($sql . ' ORDER BY c.updated_at DESC', $par);
    }

    /** @return array<int, array<string, mixed>> */
    public function mensajes(string $consulta): array
    {
        return $this->filas('SELECT m.*, p.nombre AS autor FROM sportiva_mensajes m JOIN sportiva_personas p ON p.id = m.persona_id WHERE m.consulta_id = ? ORDER BY m.created_at, m.id', [$consulta]);
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} */
    public function normalizar(array $in, bool $conAsunto = true): array
    {
        $d = ['asunto' => trim((string) ($in['asunto'] ?? '')), 'texto' => trim((string) ($in['texto'] ?? '')), 'jugador_id' => (string) ($in['jugador_id'] ?? ''), 'evento_id' => (string) ($in['evento_id'] ?? '')];
        $e = [];
        if ($conAsunto && ($d['asunto'] === '' || mb_strlen($d['asunto']) > 160)) {
            $e['asunto'] = 'consulta.error.asunto';
        }
        if ($d['texto'] === '' || mb_strlen($d['texto']) > self::MAX_TEXTO) {
            $e['texto'] = 'consulta.error.texto';
        }
        return [$d, $e];
    }

    public function crear(string $club, string $persona, array $d): string
    {
        $id = $this->id();
        $t = Tiempo::sello();
        $this->ejecutar('INSERT INTO sportiva_consultas (id, club_id, jugador_id, evento_id, creada_por, asunto, ultimo_de, nueva_staff, nueva_familia, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$id, $club, $d['jugador_id'], $d['evento_id'] ?: null, $persona, $d['asunto'], 'familia', 1, 0, $t, $t]);
        $this->mensaje($id, $persona, 'familia', $d['texto'], false);
        return $id;
    }

    /** Agrega un mensaje y marca la consulta como no leída para el otro lado. */
    public function mensaje(string $consulta, string $persona, string $lado, string $texto, bool $actualizar = true): void
    {
        $t = Tiempo::sello();
        $this->ejecutar('INSERT INTO sportiva_mensajes (id, consulta_id, persona_id, lado, texto, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$this->id(), $consulta, $persona, $lado, trim($texto), $t]);
        if ($actualizar) {
            $this->ejecutar('UPDATE sportiva_consultas SET ultimo_de = ?, nueva_staff = ?, nueva_familia = ?, updated_at = ? WHERE id = ?', [$lado, $lado === 'familia' ? 1 : 0, $lado === 'staff' ? 1 : 0, $t, $consulta]);
        }
    }

    public function leida(string $consulta, string $lado): void
    {
        $this->ejecutar('UPDATE sportiva_consultas SET ' . ($lado === 'staff' ? 'nueva_staff' : 'nueva_familia') . ' = 0 WHERE id = ?', [$consulta]);
    }

    /** Cantidad sin leer para un lado. */
    public function sinLeer(string $club, string $lado, ?string $persona = null, ?array $equipos = null): int
    {
        return count(array_filter($lado === 'staff' ? $this->deStaff($club, $equipos, false) : $this->deFamilia((string) $persona, $club), fn($c) => (int) $c[$lado === 'staff' ? 'nueva_staff' : 'nueva_familia'] === 1));
    }
}
