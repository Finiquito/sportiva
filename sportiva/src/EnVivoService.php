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
    /** Novedades informativas por deporte (los tiempos de fútbol y handball son solo notas; el marcador y las etapas tienen sus propios controles). */
    public const NOVEDADES = ['futbol' => ['llegamos', 'descanso', 'reanuda', 'vuelta'], 'handball' => ['llegamos', 'descanso', 'reanuda', 'vuelta'], 'basquet' => ['llegamos', 'vuelta'], 'voley' => ['llegamos', 'vuelta']];
    /** Vóley: al mejor de 3 sets de 25 puntos (15 el set decisivo), con 2 de diferencia, como en el tenis. Los demás deportes llevan solo el marcador del partido. */
    public const SETS = 3;
    public const PUNTOS_SET = 25;
    public const PUNTOS_DECISIVO = 15;
    /** Cuánto suma cada toque según el deporte. */
    public const TOQUES = ['futbol' => [1], 'handball' => [1], 'voley' => [1], 'basquet' => [1, 2, 3]];
    /** El reporte se abre 3 h antes del partido y se cierra 12 h después de su inicio. */
    public const ANTES = 3;
    public const DESPUES = 12;
    /** Si nadie publica en este tiempo, el partido deja de mostrarse «en vivo». */
    public const SIN_NOVEDAD_HORAS = 4;
    /** Los goles seguidos no avisan más de una vez por este tiempo (segundos). */
    public const AVISO_GOL_SEG = 60;

    public static function deporte(?string $d): string
    {
        return match ($d) {
            'voley', 'voley-playa' => 'voley',
            'basquet' => 'basquet',
            'handball' => 'handball',
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

    /** Inactividad (minutos) después de la cual un papá deja de ser «el reportero» y otro puede tomar su lugar. */
    public const REPORTERO_MIN = 60;

    /**
     * El papá o la mamá que está reportando ahora, o null. Solo uno a la vez: el último que marcó «Estoy en la cancha» y sigue activo
     * (marcó o publicó hace menos de REPORTERO_MIN minutos). El staff nunca cuenta: puede publicar siempre.
     * @param array<string, mixed> $e evento @return array{persona_id: string, nombre: string}|null
     */
    public function reportero(array $e): ?array
    {
        $staff = (new AvisoService($this->pdo))->staffDelEquipo((string) $e['club_id'], (string) $e['equipo_id']);
        $limite = (new \DateTimeImmutable(Tiempo::sello()))->modify('-' . self::REPORTERO_MIN . ' minutes')->format('Y-m-d H:i:s');
        foreach ($this->filas('SELECT pr.persona_id, pr.created_at, p.nombre FROM sportiva_envivo_presentes pr JOIN sportiva_personas p ON p.id = pr.persona_id WHERE pr.evento_id = ? ORDER BY pr.created_at DESC, pr.id DESC', [$e['id']]) as $r) {
            if (in_array($r['persona_id'], $staff, true)) {
                continue;
            }
            $ult = $this->fila('SELECT MAX(created_at) AS t FROM sportiva_envivo_notas WHERE evento_id = ? AND persona_id = ?', [$e['id'], $r['persona_id']])['t'] ?? null;
            if (max((string) $r['created_at'], (string) $ult) >= $limite) {
                return ['persona_id' => $r['persona_id'], 'nombre' => $r['nombre']];
            }
        }
        return null;
    }

    /** ¿Ya se avisó a las familias que alguien está reportando? (una sola vez por partido). */
    public function inicioAvisado(string $evento): bool
    {
        return $this->fila("SELECT evento_id FROM sportiva_envivo_avisados WHERE evento_id = ? AND persona_id = 'inicio'", [$evento]) !== null;
    }

    public function marcarInicioAvisado(string $evento): void
    {
        $this->pedido($evento, 'inicio');
    }

    /** De estos partidos, cuáles se están reportando ahora (no terminados y con novedades recientes). @param array<int, string> $ids @return array<string, true> */
    public function enCurso(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $limite = (new \DateTimeImmutable(Tiempo::sello()))->modify('-' . self::SIN_NOVEDAD_HORAS . ' hours')->format('Y-m-d H:i:s');
        $r = [];
        foreach ($this->filas("SELECT evento_id FROM sportiva_envivo WHERE estado <> 'terminado' AND updated_at >= ? AND evento_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$limite], array_values($ids))) as $f) {
            $r[$f['evento_id']] = true;
        }
        return $r;
    }

    public function cuantosPresentes(string $evento): int
    {
        return (int) ($this->fila('SELECT COUNT(*) AS n FROM sportiva_envivo_presentes WHERE evento_id = ?', [$evento])['n'] ?? 0);
    }

    // ---- marcador por deporte -------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> periodos cerrados, en orden */
    public function periodos(string $evento): array
    {
        return $this->filas('SELECT numero, nuestro, rival FROM sportiva_envivo_periodos WHERE evento_id = ? ORDER BY numero', [$evento]);
    }

    private function cerrarPeriodo(string $evento, int $numero, int $nuestro, int $rival): void
    {
        $this->ejecutar('DELETE FROM sportiva_envivo_periodos WHERE evento_id = ? AND numero = ?', [$evento, $numero]);
        $this->ejecutar('INSERT INTO sportiva_envivo_periodos (evento_id, numero, nuestro, rival) VALUES (?, ?, ?, ?)', [$evento, $numero, $nuestro, $rival]);
    }

    /** Versión vigente, con valores por defecto si todavía no hay reporte. @return array<string, mixed> */
    private function vigente(string $evento): array
    {
        return $this->actual($evento) ?? ['evento_id' => $evento, 'estado' => 'previo', 'periodo' => 0, 'nuestro' => 0, 'rival' => 0, 'regreso' => null, 'regreso_por' => null, 'regreso_at' => null, 'confirmado' => 0, 'confirmado_por' => null, 'updated_at' => Tiempo::sello()];
    }

    private function guardarVigente(string $evento, array $a, bool $nuevo): void
    {
        $ahora = Tiempo::sello();
        if ($nuevo) {
            $this->ejecutar('INSERT INTO sportiva_envivo (evento_id, estado, periodo, nuestro, rival, regreso, regreso_por, regreso_at, confirmado, confirmado_por, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$evento, $a['estado'], $a['periodo'], $a['nuestro'], $a['rival'], $a['regreso'], $a['regreso_por'], $a['regreso_at'], (int) $a['confirmado'], $a['confirmado_por'], $ahora]);
        } else {
            $this->ejecutar('UPDATE sportiva_envivo SET estado = ?, periodo = ?, nuestro = ?, rival = ?, regreso = ?, regreso_por = ?, regreso_at = ?, confirmado = ?, confirmado_por = ?, updated_at = ? WHERE evento_id = ?', [$a['estado'], $a['periodo'], $a['nuestro'], $a['rival'], $a['regreso'], $a['regreso_por'], $a['regreso_at'], (int) $a['confirmado'], $a['confirmado_por'], $ahora, $evento]);
        }
    }

    /** Puntos que hace falta tener para poder cerrar el set (25, o 15 en el set decisivo). */
    public static function metaSet(int $set): int
    {
        return $set >= self::SETS ? self::PUNTOS_DECISIVO : self::PUNTOS_SET;
    }

    /**
     * Todo lo que se dibuja del marcador, según el deporte.
     * Vóley (por sets, como el tenis): `cols` = un set por columna (los cerrados y el que está en juego) y `total` = sets ganados.
     * Fútbol, handball y básquet: un solo marcador del partido (`cols` vacío, `total` = goles o puntos).
     * `nuestro` y `rival`: lo que se toca ahora (en vóley los puntos del set en juego).
     * @return array<string, mixed>
     */
    public function marcador(string $evento, string $dep): array
    {
        $a = $this->vigente($evento);
        $estado = (string) $a['estado'];
        $periodo = (int) $a['periodo'];
        $nos = (int) $a['nuestro'];
        $riv = (int) $a['rival'];
        $voley = $dep === 'voley';
        $cols = [];
        $ganados = [0, 0];
        if ($voley) {
            foreach ($this->periodos($evento) as $p) {
                $cols[] = ['n' => (int) $p['numero'], 'nos' => (int) $p['nuestro'], 'riv' => (int) $p['rival'], 'actual' => false];
                if ((int) $p['nuestro'] > (int) $p['rival']) {
                    $ganados[0]++;
                } elseif ((int) $p['rival'] > (int) $p['nuestro']) {
                    $ganados[1]++;
                }
            }
            if ($estado === 'jugando') {
                $cols[] = ['n' => $periodo, 'nos' => $nos, 'riv' => $riv, 'actual' => true];
            }
        }
        return [
            'dep' => $dep, 'estado' => $estado, 'periodo' => $periodo, 'nuestro' => $nos, 'rival' => $riv, 'cols' => $cols,
            'total' => $voley ? ['nos' => $ganados[0], 'riv' => $ganados[1]] : ['nos' => $nos, 'riv' => $riv],
            'cerrable' => $voley && $estado === 'jugando' && max($nos, $riv) >= self::metaSet($periodo) && abs($nos - $riv) >= 2,
            'sets' => $voley ? $ganados : null,
            'marcable' => $estado === 'jugando', 'toques' => self::TOQUES[$dep] ?? [1],
        ];
    }

    /** Botones de etapa: [accion, k = clave del texto, v = variables, seguro = clave del «¿estás seguro?» o null, principal]. Solo el vóley cierra sets; los demás empiezan y terminan. @return array<int, array<string, mixed>> */
    public function etapas(array $m): array
    {
        $e = $m['estado'];
        if ($e === 'terminado') {
            return [];
        }
        if ($e === 'previo') {
            return [['accion' => 'comenzar', 'k' => 'envivo.m.comenzar', 'v' => [], 'seguro' => null, 'principal' => true]];
        }
        $terminar = ['accion' => 'terminar', 'k' => 'envivo.m.terminar', 'v' => [], 'seguro' => 'envivo.m.seguro.terminar', 'principal' => $m['dep'] !== 'voley'];
        if ($m['dep'] === 'voley') {
            return [['accion' => 'cerrar_set', 'k' => 'envivo.m.cerrar_set', 'v' => ['n' => (int) $m['periodo']], 'seguro' => 'envivo.m.seguro.cerrar_set', 'principal' => $m['cerrable']], $terminar];
        }
        return [$terminar];
    }

    private function nota(string $evento, string $persona, string $tipo, ?string $texto = null, ?int $nuestro = null, ?int $rival = null, ?string $regreso = null): void
    {
        $this->ejecutar(
            'INSERT INTO sportiva_envivo_notas (id, evento_id, persona_id, tipo, texto, nuestro, rival, regreso, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$this->id(), $evento, $persona, $tipo, $texto, $nuestro, $rival, $regreso, Tiempo::sello()]
        );
    }

    /**
     * Un toque en el marcador (+1 punto, +3 de básquet, − corregir). Devuelve el marcador nuevo o null si no corresponde.
     * Cada deporte tiene sus toques y solo se anota con el partido en juego.
     * @return array<string, mixed>|null
     */
    public function tocar(string $evento, string $dep, string $accion): ?array
    {
        if (!preg_match('/^(nos|riv)([+-])(\d)$/', $accion, $m)) {
            return null;
        }
        $delta = (int) $m[3] * ($m[2] === '-' ? -1 : 1);
        $a = $this->vigente($evento);
        if ($a['estado'] !== 'jugando' || !in_array(abs($delta), self::TOQUES[$dep] ?? [1], true) || ($delta < 0 && abs($delta) !== 1)) {
            return null;
        }
        $campo = $m[1] === 'nos' ? 'nuestro' : 'rival';
        $a[$campo] = max(0, min(199, (int) $a[$campo] + $delta));
        $this->guardarVigente($evento, $a, false);
        return $this->marcador($evento, $dep);
    }

    /**
     * Cambia de etapa: comenzar, cerrar set (vóley) o terminar el partido.
     * Devuelve [error|null, aviso|null]; el aviso dice qué se debe notificar a las familias.
     * @return array{0: string|null, 1: array<string, mixed>|null}
     */
    public function avanzar(string $evento, string $dep, string $accion, string $persona): array
    {
        $existe = $this->actual($evento) !== null;
        $a = $this->vigente($evento);
        $est = (string) $a['estado'];
        $n = (int) $a['periodo'];
        $nos = (int) $a['nuestro'];
        $riv = (int) $a['rival'];
        $voley = $dep === 'voley';
        $aviso = null;
        switch ($accion) {
            case 'comenzar':
                if ($est !== 'previo') {
                    return ['envivo.error.etapa', null];
                }
                $a['estado'] = 'jugando';
                $a['periodo'] = 1;
                $this->nota($evento, $persona, 'comenzo');
                break;
            case 'cerrar_set':
                if (!$voley || $est !== 'jugando') {
                    return ['envivo.error.etapa', null];
                }
                if ($nos === 0 && $riv === 0) {
                    return ['envivo.error.set_vacio', null];
                }
                $ganados = $this->marcador($evento, $dep)['sets'];
                $this->cerrarPeriodo($evento, $n, $nos, $riv);
                $this->nota($evento, $persona, 'set', (string) $n, $nos, $riv);
                if ($nos !== $riv) {
                    $ganados[$nos > $riv ? 0 : 1]++;
                }
                $a['nuestro'] = 0;
                $a['rival'] = 0;
                if (max($ganados) >= intdiv(self::SETS, 2) + 1) {
                    $a['estado'] = 'terminado';
                    $a['confirmado'] = 0;
                    $this->nota($evento, $persona, 'termino', null, $ganados[0], $ganados[1]);
                    $aviso = ['motivo' => 'final', 'sets' => $ganados, 'n' => $n, 'nos' => $nos, 'riv' => $riv];
                } else {
                    $a['periodo'] = $n + 1;
                    $aviso = ['motivo' => 'set', 'n' => $n, 'nos' => $nos, 'riv' => $riv, 'sets' => $ganados];
                }
                break;
            case 'terminar':
                if ($est !== 'jugando') {
                    return ['envivo.error.etapa', null];
                }
                if ($voley && ($nos > 0 || $riv > 0)) {
                    $this->cerrarPeriodo($evento, $n, $nos, $riv);
                }
                $a['estado'] = 'terminado';
                $a['confirmado'] = 0;
                if ($voley) {
                    $a['nuestro'] = 0;
                    $a['rival'] = 0;
                }
                $tot = $this->marcador($evento, $dep)['total'];
                $this->nota($evento, $persona, 'termino', null, $tot['nos'], $tot['riv']);
                $aviso = ['motivo' => 'final', 'sets' => $voley ? [$tot['nos'], $tot['riv']] : null, 'nos' => $tot['nos'], 'riv' => $tot['riv']];
                break;
            default:
                return ['envivo.error.etapa', null];
        }
        $this->guardarVigente($evento, $a, !$existe);
        return [null, $aviso];
    }

    /** Novedad informativa (llegamos, salimos de vuelta) con nota opcional. Devuelve el error o null. */
    public function novedad(string $evento, string $persona, string $dep, string $tipo, string $texto): ?string
    {
        $texto = trim($texto);
        if ($tipo !== '' && !in_array($tipo, self::NOVEDADES[$dep] ?? self::NOVEDADES['futbol'], true)) {
            return 'envivo.error.tipo';
        }
        if (mb_strlen($texto) > 200) {
            return 'envivo.error.texto';
        }
        if ($tipo === '' && $texto === '') {
            return 'envivo.error.vacio';
        }
        $existe = $this->actual($evento) !== null;
        $this->nota($evento, $persona, $tipo !== '' ? $tipo : 'nota', $texto !== '' ? $texto : null);
        if (!$existe) {
            $this->guardarVigente($evento, $this->vigente($evento), true);
        }
        return null;
    }

    /** Cambia la hora estimada de regreso. Devuelve [error|null, cambió?]. @return array{0: string|null, 1: bool} */
    public function regreso(string $evento, string $persona, string $hora, string $inicio): array
    {
        $r = EventoService::unir(Tiempo::dia($inicio), trim($hora));
        if ($r === null) {
            return ['envivo.error.regreso', false];
        }
        $existe = $this->actual($evento) !== null;
        $a = $this->vigente($evento);
        if (($a['regreso'] ?? null) === $r) {
            return [null, false];
        }
        $a['regreso'] = $r;
        $a['regreso_por'] = $persona;
        $a['regreso_at'] = Tiempo::sello();
        $this->nota($evento, $persona, 'regreso', null, null, null, $r);
        $this->guardarVigente($evento, $a, !$existe);
        return [null, true];
    }

    /** ¿Hace falta avisar este gol? (no más de uno por minuto). */
    public function puedeAvisarGol(string $evento): bool
    {
        $f = $this->fila('SELECT aviso_marcador_at AS t FROM sportiva_envivo_ctl WHERE evento_id = ?', [$evento]);
        $ahora = Tiempo::sello();
        if ($f !== null && $f['t'] && strtotime($ahora . ' UTC') - strtotime((string) $f['t'] . ' UTC') < self::AVISO_GOL_SEG) {
            return false;
        }
        $this->ejecutar('DELETE FROM sportiva_envivo_ctl WHERE evento_id = ?', [$evento]);
        $this->ejecutar('INSERT INTO sportiva_envivo_ctl (evento_id, aviso_marcador_at) VALUES (?, ?)', [$evento, $ahora]);
        return true;
    }

    /** Resultado para mostrar fuera de la ficha (feed): en vóley son los sets ganados. @return array{0: int, 1: int} */
    public function resultado(string $evento, string $dep): array
    {
        $t = $this->marcador($evento, $dep)['total'];
        return [(int) $t['nos'], (int) $t['riv']];
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
    public function vista(array $e, \DateTimeImmutable $ahora, string $dep = 'futbol'): ?array
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
        $m = $this->marcador($e['id'], $dep);
        return ['estado' => $estado, 'act' => $act, 'notas' => $notas, 'abierto' => $abierto, 'presentes' => $this->cuantosPresentes($e['id']), 'm' => $m, 'etapas' => $this->etapas($m)];
    }
}
