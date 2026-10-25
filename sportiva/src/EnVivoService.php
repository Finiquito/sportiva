<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Reporte en vivo de un partido: quien está en la cancha publica novedades, el marcador y la hora estimada de regreso.
 * Hay una sola versión vigente (`sportiva_envivo`) y un historial de publicaciones (`sportiva_envivo_notas`).
 * La app no dice «en vivo» si nadie está reportando.
 */
final class EnVivoService extends Repo
{
    /** Botones rápidos por deporte (el club decide el deporte). «descanso» y «reanuda» cambian de nombre según el deporte. */
    public const BOTONES = [
        'futbol'  => ['llegamos', 'comenzo', 'descanso', 'reanuda', 'termino', 'vuelta'],
        'voley'   => ['llegamos', 'comenzo', 'descanso', 'termino', 'vuelta'],
        'basquet' => ['llegamos', 'comenzo', 'descanso', 'reanuda', 'termino', 'vuelta'],
    ];
    public const TIPOS = ['llegamos', 'comenzo', 'descanso', 'reanuda', 'termino', 'vuelta'];
    /** El reporte se abre 3 h antes del partido y se cierra 12 h después de su inicio. */
    public const ANTES = 3;
    public const DESPUES = 12;
    /** Si nadie publica en este tiempo, el partido deja de mostrarse «en vivo». */
    public const SIN_NOVEDAD_HORAS = 4;

    public static function deporte(?string $d): string
    {
        return match ($d) {
            'voley', 'voley-playa' => 'voley',
            'basquet' => 'basquet',
            default => 'futbol',
        };
    }

    /** Ventana en la que se puede reportar. */
    public static function abierto(string $inicio, \DateTimeImmutable $ahora): bool
    {
        if (Tiempo::sinFecha($inicio) || Tiempo::sinHora($inicio)) {
            return false;
        }
        $i = new \DateTimeImmutable($inicio, $ahora->getTimezone());
        return $ahora >= $i->modify('-' . self::ANTES . ' hours') && $ahora <= $i->modify('+' . self::DESPUES . ' hours');
    }

    /** @return array<string, mixed>|null */
    public function actual(string $evento): ?array
    {
        return $this->fila('SELECT * FROM sportiva_envivo WHERE evento_id = ?', [$evento]);
    }

    public function esPresente(string $evento, string $persona): bool
    {
        return $this->fila('SELECT id FROM sportiva_envivo_presentes WHERE evento_id = ? AND persona_id = ?', [$evento, $persona]) !== null;
    }

    public function marcarPresente(string $evento, string $persona, bool $si): void
    {
        $ya = $this->esPresente($evento, $persona);
        if ($si && !$ya) {
            $this->ejecutar('INSERT INTO sportiva_envivo_presentes (id, evento_id, persona_id, created_at) VALUES (?, ?, ?, ?)', [$this->id(), $evento, $persona, Tiempo::sello()]);
        } elseif (!$si && $ya) {
            $this->ejecutar('DELETE FROM sportiva_envivo_presentes WHERE evento_id = ? AND persona_id = ?', [$evento, $persona]);
        }
    }

    public function cuantosPresentes(string $evento): int
    {
        return (int) ($this->fila('SELECT COUNT(*) AS n FROM sportiva_envivo_presentes WHERE evento_id = ?', [$evento])['n'] ?? 0);
    }

    /**
     * Valida lo que se publica. Devuelve [datos, error].
     * @param array<string, mixed> $in @param array<string, mixed>|null $act versión vigente
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    public function normalizar(array $in, string $inicio, ?array $act, string $deporte): array
    {
        $tipo = (string) ($in['tipo'] ?? '');
        if ($tipo !== '' && !in_array($tipo, self::BOTONES[$deporte] ?? self::BOTONES['futbol'], true)) {
            return [[], 'envivo.error.tipo'];
        }
        $marc = static function ($v): ?int {
            $v = trim((string) $v);
            return $v !== '' && ctype_digit($v) && (int) $v <= 99 ? (int) $v : null;
        };
        $nuestro = $marc($in['nuestro'] ?? '');
        $rival = $marc($in['rival'] ?? '');
        if (($nuestro === null) !== ($rival === null) && trim((string) ($in['nuestro'] ?? '')) . trim((string) ($in['rival'] ?? '')) !== '') {
            return [[], 'envivo.error.marcador'];
        }
        $regreso = null;
        $h = trim((string) ($in['regreso'] ?? ''));
        if ($h !== '') {
            $dia = Tiempo::dia($inicio);
            $regreso = EventoService::unir($dia, $h);
            if ($regreso === null) {
                return [[], 'envivo.error.regreso'];
            }
        }
        $texto = trim((string) ($in['texto'] ?? ''));
        if (mb_strlen($texto) > 200) {
            return [[], 'envivo.error.texto'];
        }
        $cambiaMarcador = $nuestro !== null && ($nuestro !== (int) ($act['nuestro'] ?? 0) || $rival !== (int) ($act['rival'] ?? 0));
        $cambiaRegreso = $regreso !== null && ($act === null || ($act['regreso'] ?? null) !== $regreso);
        if ($tipo === '' && $texto === '' && !$cambiaMarcador && !$cambiaRegreso) {
            return [[], 'envivo.error.vacio'];
        }
        return [[
            'tipo' => $tipo !== '' ? $tipo : ($cambiaMarcador ? 'marcador' : ($texto !== '' ? 'nota' : 'regreso')),
            'texto' => $texto !== '' ? $texto : null,
            'nuestro' => $cambiaMarcador ? $nuestro : null, 'rival' => $cambiaMarcador ? $rival : null,
            'regreso' => $cambiaRegreso ? $regreso : null,
        ], null];
    }

    /**
     * Guarda una publicación y actualiza la versión vigente.
     * @param array<string, mixed> $d salida de normalizar() @return array<string, mixed> versión vigente nueva
     */
    public function publicar(string $evento, string $persona, array $d): array
    {
        $ahora = Tiempo::sello();
        $act = $this->actual($evento) ?? ['estado' => 'previo', 'periodo' => 0, 'nuestro' => 0, 'rival' => 0, 'regreso' => null, 'regreso_por' => null, 'regreso_at' => null, 'confirmado' => 0, 'confirmado_por' => null];
        $estado = (string) $act['estado'];
        $periodo = (int) $act['periodo'];
        switch ($d['tipo']) {
            case 'comenzo':
                $estado = 'jugando';
                $periodo = 1;
                break;
            case 'descanso':
                $estado = 'descanso';
                break;
            case 'reanuda':
                $estado = 'jugando';
                $periodo = max(2, $periodo + 1);
                break;
            case 'termino':
                $estado = 'terminado';
                break;
        }
        // en vóley no hay «reanuda»: después del fin del set, «comenzó» abre el siguiente
        if ($d['tipo'] === 'comenzo' && (string) $act['estado'] === 'descanso') {
            $periodo = max(2, (int) $act['periodo'] + 1);
        }
        $nuestro = $d['nuestro'] ?? (int) $act['nuestro'];
        $rival = $d['rival'] ?? (int) $act['rival'];
        $cambioMarcador = $d['nuestro'] !== null;
        $regreso = $d['regreso'] ?? $act['regreso'];
        $confirmado = $cambioMarcador || $d['tipo'] === 'termino' ? 0 : (int) $act['confirmado'];
        $this->ejecutar(
            'INSERT INTO sportiva_envivo_notas (id, evento_id, persona_id, tipo, texto, nuestro, rival, regreso, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$this->id(), $evento, $persona, $d['tipo'], $d['texto'], $d['nuestro'], $d['rival'], $d['regreso'], $ahora]
        );
        if ($this->actual($evento) === null) {
            $this->ejecutar('INSERT INTO sportiva_envivo (evento_id, estado, periodo, nuestro, rival, regreso, regreso_por, regreso_at, confirmado, confirmado_por, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$evento, $estado, $periodo, $nuestro, $rival, $regreso, $d['regreso'] ? $persona : null, $d['regreso'] ? $ahora : null, 0, null, $ahora]);
        } else {
            $this->ejecutar(
                'UPDATE sportiva_envivo SET estado = ?, periodo = ?, nuestro = ?, rival = ?, regreso = ?, regreso_por = ?, regreso_at = ?, confirmado = ?, confirmado_por = ?, updated_at = ? WHERE evento_id = ?',
                [$estado, $periodo, $nuestro, $rival, $regreso, $d['regreso'] ? $persona : $act['regreso_por'], $d['regreso'] ? $ahora : $act['regreso_at'], $confirmado, $confirmado ? $act['confirmado_por'] : null, $ahora, $evento]
            );
        }
        return $this->actual($evento) ?? [];
    }

    /** El entrenador confirma el resultado final. */
    public function confirmar(string $evento, string $persona): bool
    {
        $a = $this->actual($evento);
        if ($a === null || $a['estado'] !== 'terminado') {
            return false;
        }
        $this->ejecutar('UPDATE sportiva_envivo SET confirmado = 1, confirmado_por = ?, updated_at = ? WHERE evento_id = ?', [$persona, Tiempo::sello(), $evento]);
        return true;
    }

    /**
     * Partidos que empezaron hace poco, con quienes marcaron «Estoy en la cancha» y aún no recibieron el pedido de marcador.
     * @return array<int, array{evento: array<string, mixed>, personas: array<int, string>}>
     */
    public function porPedir(string $club, string $desde, string $hasta): array
    {
        $r = [];
        foreach ($this->filas("SELECT id, titulo, inicio FROM sportiva_eventos WHERE club_id = ? AND tipo = 'partido' AND estado = 'publicado' AND inicio >= ? AND inicio <= ? AND inicio NOT LIKE '%23:59:59'", [$club, $desde, $hasta]) as $e) {
            $p = array_column($this->filas(
                'SELECT pr.persona_id FROM sportiva_envivo_presentes pr WHERE pr.evento_id = ? AND pr.persona_id NOT IN (SELECT persona_id FROM sportiva_envivo_avisados WHERE evento_id = ?)',
                [$e['id'], $e['id']]
            ), 'persona_id');
            if ($p !== []) {
                $r[] = ['evento' => $e, 'personas' => $p];
            }
        }
        return $r;
    }

    public function pedido(string $evento, string $persona): void
    {
        if ($this->fila('SELECT evento_id FROM sportiva_envivo_avisados WHERE evento_id = ? AND persona_id = ?', [$evento, $persona]) === null) {
            $this->ejecutar('INSERT INTO sportiva_envivo_avisados (evento_id, persona_id) VALUES (?, ?)', [$evento, $persona]);
        }
    }

    /** Historial, lo más reciente primero. @return array<int, array<string, mixed>> */
    public function notas(string $evento, int $limite = 30): array
    {
        return $this->filas(
            'SELECT n.*, p.nombre AS autor FROM sportiva_envivo_notas n JOIN sportiva_personas p ON p.id = n.persona_id WHERE n.evento_id = ? ORDER BY n.created_at DESC, n.id DESC LIMIT ' . (int) $limite,
            [$evento]
        );
    }

    /**
     * Lo que muestra la tarjeta del partido: 'envivo' (hay quien reporta), 'sin' (nadie reporta) o 'fin'. null = no se muestra.
     * @param array<string, mixed> $e evento @return array<string, mixed>|null
     */
    public function vista(array $e, \DateTimeImmutable $ahora): ?array
    {
        $act = $this->actual($e['id']);
        $abierto = self::abierto((string) $e['inicio'], $ahora);
        if ($act === null && !$abierto) {
            return null;
        }
        $notas = $act ? $this->notas($e['id']) : [];
        $estado = 'sin';
        if ($act && $act['estado'] === 'terminado') {
            $estado = 'fin';
        } elseif ($act && $notas !== [] && $this->cuantosPresentes($e['id']) > 0) {
            $ult = new \DateTimeImmutable((string) $notas[0]['created_at'], $ahora->getTimezone());
            if ($ahora <= $ult->modify('+' . self::SIN_NOVEDAD_HORAS . ' hours')) {
                $estado = 'envivo';
            }
        }
        return ['estado' => $estado, 'act' => $act, 'notas' => $notas, 'abierto' => $abierto, 'presentes' => $this->cuantosPresentes($e['id'])];
    }
}
