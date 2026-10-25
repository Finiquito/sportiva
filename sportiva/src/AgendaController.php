<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Agenda, convocatoria y asistencia: familias, jugador (solo lectura) y entrenadores. */
final class AgendaController extends AppBase
{
    private function eventos(): EventoService
    {
        return new EventoService($this->pdo);
    }

    private function zona(): string
    {
        return (string) ($this->club['zona'] ?? 'Europe/Paris');
    }

    private function hoy(): string
    {
        return Tiempo::ahora($this->zona())->format('Y-m-d') . ' 00:00:00';
    }

    /** Eventos del rol actual entre dos fechas (`$hasta` exclusivo), listos para mostrar en tarjetas. @return array<int, array<string, mixed>>|null null si el rol no tiene agenda */
    private function filasEntre(string $desde, ?string $hasta = null): ?array
    {
        $ev = $this->eventos();
        if ($this->rol === 'apoderado') {
            $filas = $ev->paraApoderado($this->persona['id'], $this->club['id'], $desde, $hasta);
        } elseif ($this->rol === 'jugador') {
            $filas = $ev->paraJugador($this->persona['id'], $this->club['id'], $desde, $hasta);
        } elseif (self::esCoach($this->rol)) {
            $filas = $ev->delClubDesde($this->club['id'], $desde, array_column($this->gestionables(), 'id'), false, $hasta);
            foreach ($filas as &$f) {
                $f['resumen'] = $ev->resumen($f['id']);
            }
            unset($f);
        } else {
            return null;
        }
        $mios = $this->rol === 'apoderado' ? (new TurnoService($this->pdo))->eventosDeTurno($this->persona['id'], $this->club['id'], $desde) : [];
        foreach ($filas as &$f) {
            $f['toca_llevar'] = isset($mios[$f['id']]);
            $f['compartir'] = Mapa::textoCompartir($f, $this->i18n, $this->urlAbsoluta('/app/evento/' . $f['id']));
        }
        unset($f);
        return $filas;
    }

    /**
     * Mes para el calendario: semanas de lunes a domingo, con puntos por tipo de evento en cada día.
     * @return array<string, mixed>
     */
    private function calendario(string $mes, ?string $sel, string $hoy): array
    {
        $ini = new \DateTimeImmutable($mes . '-01');
        $sig = $ini->modify('+1 month');
        $porDia = [];
        foreach ($this->filasEntre($ini->format('Y-m-d') . ' 00:00:00', $sig->format('Y-m-d') . ' 00:00:00') ?? [] as $f) {
            $porDia[Tiempo::dia($f['inicio'])][$f['tipo']] = ($porDia[Tiempo::dia($f['inicio'])][$f['tipo']] ?? 0) + 1;
        }
        $semanas = [];
        $sem = array_fill(0, ((int) $ini->format('N')) - 1, null);
        for ($d = $ini; $d < $sig; $d = $d->modify('+1 day')) {
            $f = $d->format('Y-m-d');
            $sem[] = ['fecha' => $f, 'n' => (int) $d->format('j'), 'hoy' => $f === $hoy, 'sel' => $f === $sel, 'tipos' => array_keys($porDia[$f] ?? []), 'total' => array_sum($porDia[$f] ?? [])];
            if (count($sem) === 7) {
                $semanas[] = $sem;
                $sem = [];
            }
        }
        if ($sem !== []) {
            $semanas[] = array_pad($sem, 7, null);
        }
        $i = $this->i18n;
        return [
            'mes' => $mes, 'titulo' => $i->lista('fmt.meses')[(int) $ini->format('n') - 1] . ' ' . $ini->format('Y'), 'semanas' => $semanas,
            'prev' => $ini->modify('-1 month')->format('Y-m'), 'sig' => $sig->format('Y-m'), 'primerDia' => array_key_first($porDia),
        ];
    }

    public function agenda(): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $desde = $this->hoy();
        $hoyD = substr($desde, 0, 10);
        $filas = $this->filasEntre($desde);
        if ($filas === null) {
            $this->ir('/app/feed');
            return;
        }
        $hijos = $this->rol === 'apoderado' ? (new PersonaService($this->pdo))->hijos($this->persona['id'], $this->club['id']) : [];
        $vista = ($_GET['vista'] ?? '') === 'mes' ? 'mes' : 'lista';
        $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['mes'] ?? '')) ? (string) $_GET['mes'] : substr($hoyD, 0, 7);
        $dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['dia'] ?? '')) && strtotime((string) $_GET['dia']) !== false ? (string) $_GET['dia'] : null;
        $cal = $this->calendario($mes, $dia ?? ($vista === 'mes' && $mes === substr($hoyD, 0, 7) ? $hoyD : null), $hoyD);
        $base = ['activa' => 'agenda', 'hijos' => $hijos, 'coach' => self::esCoach($this->rol), 'zona' => $this->zona(), 'cal' => $cal, 'vista' => $vista, 'diaSel' => null, 'delDia' => [], 'proximo' => [], 'dias' => [], 'oculto' => 0, 'masDias' => 20];
        // Un día elegido (del calendario): todo lo de ese día, sin tope.
        $elegido = $dia ?? ($vista === 'mes' ? ($mes === substr($hoyD, 0, 7) ? $hoyD : $cal['primerDia']) : null);
        if ($elegido !== null && ($dia !== null || $vista === 'mes')) {
            $sigD = (new \DateTimeImmutable($elegido))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            $base['diaSel'] = $elegido;
            $base['delDia'] = $this->filasEntre($elegido . ' 00:00:00', $sigD) ?? [];
            echo $this->vista()->pagina('agenda.latte', $base);
            return;
        }
        // El próximo evento (de cualquier tipo) queda fijo arriba. Debajo, solo los próximos días y pocos entrenamientos:
        // una planificación de todo el año no puede llenar la agenda.
        $resto = [];
        $proximo = [];
        $oculto = 0;
        $dias_ = max(10, min(90, (int) round(((int) ($_GET['dias'] ?? 10)) / 10) * 10));
        $limite = (new \DateTimeImmutable($hoyD))->modify('+' . $dias_ . ' days')->format('Y-m-d') . ' 23:59:59';
        $topeEntrenos = 2 * intdiv($dias_, 10);
        $sinFecha = array_values(array_filter($filas, fn($f) => Tiempo::sinFecha($f['inicio'])));
        $filas = array_values(array_filter($filas, fn($f) => !Tiempo::sinFecha($f['inicio'])));
        $primero = $filas[0]['id'] ?? null;
        $entrenos = [];
        foreach ($filas as $f) {
            if ($f['id'] === $primero) {
                $proximo[] = $f;
                continue;
            }
            $dentro = $f['inicio'] <= $limite;
            if ($dentro && $f['tipo'] === 'entreno') {
                $entrenos[$f['id']] = true;
                $dentro = count($entrenos) <= $topeEntrenos;
            }
            if ($dentro) {
                $resto[Tiempo::dia($f['inicio'])][] = $f;
            } else {
                $oculto++;
            }
        }
        echo $this->vista()->pagina('agenda.latte', [
            'proximo' => $proximo, 'pendientes' => $sinFecha, 'dias' => $resto, 'oculto' => $oculto, 'masDias' => min(90, $dias_ + 10),
        ] + $base);
    }

    public function evento(string $id): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $ev = $this->eventos();
        $e = $ev->buscar($id);
        if ($e === null || $e['club_id'] !== $this->club['id']) {
            \Flight::notFound();
            return;
        }
        $conv = $ev->convocados($id);
        $mios = [];
        if ($this->rol === 'apoderado') {
            $ids = array_column((new PersonaService($this->pdo))->hijos($this->persona['id'], $this->club['id']), 'id');
            $mios = array_values(array_filter($conv, fn($c) => in_array($c['id'], $ids, true)));
            if ($mios === [] && $e['estado'] !== 'publicado') {
                \Flight::notFound();
                return;
            }
        } elseif ($this->rol === 'jugador') {
            $j = (new PersonaService($this->pdo))->jugadorDe($this->persona['id'], $this->club['id']);
            $mios = $j ? array_values(array_filter($conv, fn($c) => $c['id'] === $j['id'])) : [];
        }
        $puede = self::esCoach($this->rol) && in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true);
        $enlaces = $e['lugar'] ? Mapa::enlacesLugar($e) : null;
        $tu = new TurnoService($this->pdo);
        $turnos = $tu->delEvento($id);
        $misFam = $this->rol === 'apoderado' ? $tu->familiasDe($this->persona['id'], $id) : [];
        $mioConf = false;
        $mioPend = false;
        foreach ($turnos as $t) {
            if (in_array($t['familia_id'], $misFam, true)) {
                $mioConf = $mioConf || $t['estado'] === 'confirmado';
                $mioPend = $mioPend || $t['estado'] === 'pendiente';
            }
        }
        $env = null;
        $sv = new EnVivoService($this->pdo);
        if ($e['tipo'] === 'partido' && $e['estado'] === 'publicado') {
            $env = $sv->vista($e, Tiempo::ahora($this->zona()));
        }
        echo $this->vista()->pagina('evento.latte', [
            'env' => $env, 'dep' => EnVivoService::deporte($this->club['deporte'] ?? null), 'puedeRep' => $puede || ($this->rol === 'apoderado' && $mios !== []),
            'soyPresente' => $env !== null && $sv->esPresente($id, $this->persona['id']),
            'medios' => (new MedioService($this->pdo))->delEvento($id, 6), 'mediosTotal' => (new MedioService($this->pdo))->cuantos($id), 'puedeMedios' => in_array($this->rol, ['apoderado', 'jugador', 'directiva'], true) || $puede,
            'turnos' => $turnos, 'turnoMio' => $mioConf, 'turnoPendiente' => $mioPend, 'puedePedir' => $misFam !== [] && !$mioConf && !$mioPend && $e['estado'] === 'publicado',
            'elegibles' => $puede ? $tu->elegibles($id) : [],
            'activa' => 'agenda', 'e' => $e, 'conv' => $conv, 'mios' => $mios, 'resumen' => $ev->resumen($id), 'puede' => $puede,
            'mapa' => Mapa::valido($e['lugar_lat'], $e['lugar_lon']) ? Mapa::html((string) $e['lugar_lat'], (string) $e['lugar_lon'], 16, (string) $e['lugar'], $this->i18n, (string) ($e['lugar_direccion'] ?? '')) : '',
            'enlaces' => $enlaces, 'zona' => $this->zona(),
            'compartir' => Mapa::textoCompartir($e, $this->i18n, $this->urlAbsoluta('/app/evento/' . $id)), 'serie' => $puede ? (new SerieService($this->pdo))->deEvento($id) : null,
        ]);
    }

    /** Un entrenador agrega la ubicación del lugar de la citación pegando un enlace de Google Maps. */
    public function guardarUbicacion(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->eventos()->buscar($id);
        if ($e === null || $e['club_id'] !== $this->club['id'] || !$e['lugar_id'] || !in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true) || !$this->csrfOk()) {
            $this->ir('/app/agenda');
            return;
        }
        $c = Mapa::coordenadasDeEnlace((string) ($_POST['enlace'] ?? ''));
        if ($c === null) {
            $this->ctx->redirect('/app/evento/' . $id, $this->i18n->t('mapa.enlace_invalido'), 'error');
            return;
        }
        (new LugarService($this->pdo))->ponerCoordenadas((string) $e['lugar_id'], $c[0], $c[1]);
        $this->ctx->redirect('/app/evento/' . $id, $this->i18n->t('mapa.ubicacion_guardada'));
    }

    public function responder(string $id): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $ev = $this->eventos();
        $e = $ev->buscar($id);
        $jugador = (string) ($_POST['jugador'] ?? '');
        $resp = (string) ($_POST['respuesta'] ?? '');
        if ($e !== null && $e['club_id'] === $this->club['id'] && $this->csrfOk()) {
            $permitido = ($this->rol === 'apoderado' && $ev->esApoderadoDe($this->persona['id'], $jugador))
                || (self::esCoach($this->rol) && in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true));
            if ($permitido) {
                $ev->responder($id, $jugador, $resp, $this->persona['id']);
            }
        }
        $volver = (string) ($_POST['volver'] ?? '');
        $this->ir(str_starts_with($volver, '/app/') ? $volver : '/app/evento/' . $id);
    }

    /** Avisa a las familias de los convocados. */
    private function avisarEvento(string $id, array $d, string $clave): void
    {
        $titulo = (string) $d['titulo'];
        $inicio = (string) $d['inicio'];
        $motivo = ['aviso.evento.nuevo' => 'nueva', 'aviso.evento.lugar' => 'lugar', 'aviso.evento.hora' => 'hora', 'aviso.evento.cancelado' => 'cancelado', 'aviso.evento.confirmado' => 'confirmado'][$clave] ?? 'otro';
        $this->notificar((new AvisoService($this->pdo))->adultosDelEvento($id), 'agenda', fn(I18n $i) => [$i->t($clave, ['titulo' => $titulo]), Tiempo::cuando($inicio, $i)], '/app/evento/' . $id, ['id' => $id, 'motivo' => $motivo]);
    }

    // ---- entrenadores: crear / editar / cancelar ------------------------------------------------

    /** Jugadores de cada categoría para elegir a quién citar. @return array<int, array<string, mixed>> */
    private function plantel(): array
    {
        $ev = $this->eventos();
        $r = [];
        foreach ((new ClubService($this->pdo))->equipos($this->club['id']) as $q) {
            $q['jugadores'] = $ev->jugadoresDe($q['id']);
            $r[] = $q;
        }
        return $r;
    }

    /** Fechas de la repetición con su estado (se hace / se salta y por qué). @param array<string, mixed> $in @return array<string, array{hacer: bool, motivo: string}> */
    private function previewDe(array $in): array
    {
        if (empty($in['repetir'])) {
            return [];
        }
        $visto = array_flip(array_filter((array) ($in['visto'] ?? []), 'is_string'));
        $hacer = (array) ($in['hacer'] ?? []);
        $motivos = (array) ($in['motivo'] ?? []);
        $r = [];
        foreach (SerieService::generar((string) ($in['fecha'] ?? ''), (array) ($in['dias'] ?? []), (int) ($in['meses'] ?? 1)) as $f) {
            $salta = isset($visto[$f]) && !isset($hacer[$f]);
            $r[$f] = ['hacer' => !$salta, 'motivo' => $salta ? mb_substr(trim((string) ($motivos[$f] ?? '')), 0, 160) : ''];
        }
        return $r;
    }

    private function formulario(?array $e, array $datos, array $errores, array $preview = []): void
    {
        $lug = new LugarService($this->pdo);
        if (!isset($datos['jugadores_sel'])) {
            $datos['jugadores_sel'] = $e ? $this->eventos()->convocadosIds($e['id']) : ($datos['jugadores'] ?? []);
        }
        echo $this->vista()->pagina('evento-form.latte', [
            'activa' => 'agenda', 'e' => $e, 'd' => $datos, 'errores' => $errores,
            'equipos' => $this->gestionables(), 'todosEquipos' => (new ClubService($this->pdo))->equipos($this->club['id']),
            'lugares' => $lug->delClub($this->club['id']), 'locales' => array_column($lug->locales($this->club['id']), 'id'), 'plantel' => $this->plantel(), 'preview' => $preview,
        ]);
    }

    public function nuevo(): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $eq = $this->gestionables();
        $this->formulario(null, [
            'tipo' => 'partido', 'fecha' => '', 'hora_partido' => '', 'cancha_antes' => '45', 'hay_encuentro' => '', 'equipo_id' => $eq[0]['id'] ?? '',
            'titulo' => '', 'rival' => '', 'lugar_id' => '', 'encuentro_texto' => '', 'nota' => '', 'extra' => [], 'dias' => [], 'meses' => '2',
            'jugadores_sel' => array_column($this->eventos()->jugadoresDe($eq[0]['id'] ?? ''), 'id'),
        ], []);
    }

    /** @return array<string, mixed> */
    private function entrada(): array
    {
        $d = $_POST;
        unset($d['csrf']);
        $d['extra'] = array_values(array_filter((array) ($_POST['extra'] ?? []), 'is_string'));
        if (!empty($_POST['jugadores_presente'])) {
            $d['jugadores'] = array_values(array_filter((array) ($_POST['jugadores'] ?? []), 'is_string'));
        }
        return $d;
    }

    /** Si piden «lugar nuevo», lo crea y devuelve su id. */
    private function lugarEnLinea(array &$in): void
    {
        $nombre = trim((string) ($in['lugar_nombre'] ?? ''));
        if (empty($in['lugar_id']) && $nombre !== '') {
            $lat = trim((string) ($in['lugar_lat'] ?? ''));
            $lon = trim((string) ($in['lugar_lon'] ?? ''));
            if (!Mapa::valido($lat, $lon) && ($c = Mapa::coordenadasDeEnlace((string) ($in['lugar_enlace'] ?? ''))) !== null) {
                [$lat, $lon] = $c;
            }
            $ok = Mapa::valido($lat, $lon);
            $ls = new LugarService($this->pdo);
            $in['lugar_id'] = $ls->crear($this->club['id'], [
                'nombre' => $nombre, 'direccion' => trim((string) ($in['lugar_direccion'] ?? '')) ?: null, 'lat' => $ok ? $lat : null, 'lon' => $ok ? $lon : null,
            ]);
            if (!empty($in['lugar_local'])) {
                $ls->marcarLocal($this->club['id'], $in['lugar_id'], true);
            }
        }
    }

    public function crear(): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $in = $this->entrada();
        if (($in['tipo'] ?? '') !== 'entreno') {
            unset($in['repetir']);
        }
        $equipo = (string) ($in['equipo_id'] ?? '');
        $permit = array_column($this->gestionables(), 'id');
        if (!$this->csrfOk() || !in_array($equipo, $permit, true)) {
            $this->ir('/app/agenda');
            return;
        }
        $this->lugarEnLinea($in);
        $svc = $this->eventos();
        [$d, $err] = $svc->normalizar($in);
        $jug = isset($in['jugadores']) ? $svc->jugadoresDelClub($this->club['id'], $in['jugadores']) : null;
        if ($jug === []) {
            $err['jugadores'] = 'evento.error.convocados';
        }
        $extra = array_values(array_intersect($in['extra'], array_column((new ClubService($this->pdo))->equipos($this->club['id']), 'id')));
        $preview = $this->previewDe($in);
        if ($err !== []) {
            $this->formulario(null, $in, $err, $preview);
            return;
        }
        if ($preview !== []) {
            $ok = array_filter($preview, fn($p) => $p['hacer']);
            if (($in['accion'] ?? '') === 'ver' || empty($in['visto'])) {
                $this->formulario(null, $in, [], $preview);
                return;
            }
            if ($ok === []) {
                $this->formulario(null, $in, ['repetir' => 'evento.error.sin_fechas'], $preview);
                return;
            }
            $fechas = [];
            foreach ($preview as $f => $p) {
                $fechas[$f] = $p['hacer'] ? null : $p['motivo'];
            }
            $dias = array_values(array_filter(array_map('intval', (array) ($in['dias'] ?? [])), fn($x) => $x >= 1 && $x <= 7));
            [$sid, $creados] = (new SerieService($this->pdo))->crear($this->club['id'], $equipo, SerieService::plantilla($d, $jug, $extra), $dias, $fechas, $this->persona['id'], $svc);
            $primero = $d['titulo'];
            $n = count($creados);
            $desde = array_key_first($ok);
            $hasta = array_key_last($ok);
            $this->notificar($svc->adultosDe($jug ?? array_column($svc->jugadoresDe($equipo), 'id')), 'agenda', fn(I18n $i) => [$i->t('aviso.serie.nueva', ['titulo' => $primero]), $i->t('aviso.serie.nueva_cuerpo', ['n' => $n, 'desde' => Tiempo::diaCorto($desde . ' 00:00:00', $i), 'hasta' => Tiempo::diaCorto($hasta . ' 00:00:00', $i)])], '/app/serie/' . $sid);
            $this->ctx->redirect('/app/serie/' . $sid, $this->i18n->t('evento.serie_creada', ['n' => $n]));
            return;
        }
        $id = $svc->crear($this->club['id'], $equipo, $d, $this->persona['id'], $extra, $jug);
        $this->avisarEvento($id, $d, 'aviso.evento.nuevo');
        $this->ctx->redirect('/app/evento/' . $id, $this->i18n->t('evento.publicado'));
    }

    public function editar(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->eventos()->buscar($id);
        if ($e === null || $e['club_id'] !== $this->club['id'] || !in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true)) {
            \Flight::notFound();
            return;
        }
        $d = [
            'tipo' => $e['tipo'], 'titulo' => $e['titulo'], 'rival' => $e['rival'] ?? '', 'conf' => Tiempo::sinFecha($e['inicio']) ? 'fecha' : (Tiempo::sinHora($e['inicio']) ? 'hora' : ''), 'fecha' => Tiempo::sinFecha($e['inicio']) ? '' : Tiempo::dia($e['inicio']), 'hora_partido' => Tiempo::hora($e['inicio']),
            'hora_fin' => Tiempo::hora($e['fin']), 'hora_cancha' => Tiempo::hora($e['hora_cancha']), 'hay_encuentro' => $e['hora_encuentro'] ? '1' : '',
            'hora_encuentro' => Tiempo::hora($e['hora_encuentro']), 'lugar_id' => $e['lugar_id'], 'encuentro_texto' => $e['encuentro_texto'] ?? '', 'nota' => $e['nota'] ?? '',
            'equipo_id' => $e['equipo_id'], 'extra' => [],
        ];
        $this->formulario($e, $d, []);
    }

    public function actualizar(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $svc = $this->eventos();
        $e = $svc->buscar($id);
        if ($e === null || $e['club_id'] !== $this->club['id'] || !in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true) || !$this->csrfOk()) {
            $this->ir('/app/agenda');
            return;
        }
        $in = $this->entrada();
        $this->lugarEnLinea($in);
        [$d, $err] = $svc->normalizar($in);
        if ($err !== []) {
            $this->formulario($e, $in, $err);
            return;
        }
        $antes = $svc->convocadosIds($id);
        $jug = isset($in['jugadores']) ? $svc->jugadoresDelClub($this->club['id'], $in['jugadores']) : null;
        if ($jug === []) {
            $this->formulario($e, $in, ['jugadores' => 'evento.error.convocados']);
            return;
        }
        $svc->actualizar($id, $d);
        if ($jug !== null) {
            $svc->convocar($id, $this->club['id'], [$e['equipo_id']], $jug);
            $titulo = (string) $d['titulo'];
            $inicio = (string) $d['inicio'];
            foreach (['aviso.evento.citado' => array_diff($jug, $antes), 'aviso.evento.descitado' => array_diff($antes, $jug)] as $clave => $ids) {
                if ($ids !== []) {
                    $this->notificar($svc->adultosDe(array_values($ids)), 'agenda', fn(I18n $i) => [$i->t($clave, ['titulo' => $titulo]), Tiempo::cuando($inicio, $i)], '/app/evento/' . $id, ['id' => $id, 'motivo' => $clave === 'aviso.evento.citado' ? 'citado' : 'descitado']);
                }
            }
        }
        if ($d['lugar_id'] !== $e['lugar_id']) {
            $this->avisarEvento($id, $d, 'aviso.evento.lugar');
        } elseif (($d['inicio'] !== $e['inicio'] || $d['hora_cancha'] !== $e['hora_cancha'] || $d['hora_encuentro'] !== $e['hora_encuentro']) && (Tiempo::sinFecha($e['inicio']) || Tiempo::sinHora($e['inicio'])) && !Tiempo::sinFecha($d['inicio']) && !Tiempo::sinHora($d['inicio'])) {
            $this->avisarEvento($id, $d, 'aviso.evento.confirmado');
        } elseif ($d['inicio'] !== $e['inicio'] || $d['hora_cancha'] !== $e['hora_cancha'] || $d['hora_encuentro'] !== $e['hora_encuentro']) {
            $this->avisarEvento($id, $d, 'aviso.evento.hora');
        }
        $this->ctx->redirect('/app/evento/' . $id, $this->i18n->t('evento.guardado'));
    }

    public function cancelar(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $svc = $this->eventos();
        $e = $svc->buscar($id);
        if ($e !== null && $e['club_id'] === $this->club['id'] && in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true) && $this->csrfOk()) {
            $svc->cancelar($id);
            $this->avisarEvento($id, ['titulo' => $e['titulo'], 'inicio' => $e['inicio']], 'aviso.evento.cancelado');
        }
        $this->ctx->redirect('/app/agenda', $this->i18n->t('evento.cancelado'));
    }
}
