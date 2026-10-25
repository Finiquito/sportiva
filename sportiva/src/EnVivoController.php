<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Reporte en vivo de un partido: «Estoy en la cancha», marcador por deporte, etapas, novedades y hora de regreso.
 * Solo reporta un papá a la vez (el staff del equipo siempre puede). Los toques del marcador responden JSON; lo demás va por ajax del layout.
 */
final class EnVivoController extends AppBase
{
    private function svc(): EnVivoService
    {
        return new EnVivoService($this->pdo);
    }

    private function zona(): string
    {
        return (string) ($this->club['zona'] ?? 'Europe/Paris');
    }

    private function dep(): string
    {
        return EnVivoService::deporte($this->club['deporte'] ?? null);
    }

    /** Partido publicado del club, o null. */
    private function partido(string $id): ?array
    {
        $e = (new EventoService($this->pdo))->buscar($id);
        return $e !== null && $e['club_id'] === $this->club['id'] && $e['tipo'] === 'partido' && $e['estado'] === 'publicado' ? $e : null;
    }

    private function esStaffDe(array $e): bool
    {
        return self::esCoach($this->rol) && in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true);
    }

    /** Quien puede reportar: el staff del equipo o una familia con un hijo citado (los jugadores de solo lectura y la directiva no). */
    private function puedeReportar(array $e): bool
    {
        if ($this->esStaffDe($e)) {
            return true;
        }
        if ($this->rol !== 'apoderado') {
            return false;
        }
        $hijos = array_column((new PersonaService($this->pdo))->hijos($this->persona['id'], $this->club['id']), 'id');
        return array_intersect($hijos, (new EventoService($this->pdo))->convocadosIds($e['id'])) !== [];
    }

    private function volver(string $id, ?string $msg = null, string $tipo = 'success'): void
    {
        $this->ctx->redirect('/app/evento/' . $id, $msg, $tipo);
    }

    private function aqui(string $id, ?string $msg = null, string $tipo = 'success'): void
    {
        $this->ctx->redirect('/app/evento/' . $id . '/envivo', $msg, $tipo);
    }

    /**
     * Quien puede publicar ahora: sesión, partido, permiso, CSRF, ventana abierta y, si es familia, ser el reportero.
     * Devuelve [evento, esStaff] o un texto de error de idioma.
     * @return array{0: array<string, mixed>, 1: bool}|string|null null = redirigir a la agenda
     */
    private function autorizar(string $id, bool $conCsrf = true): array|string|null
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return null;
        }
        $e = $this->partido($id);
        if ($e === null || !$this->puedeReportar($e) || ($conCsrf && !$this->csrfOk())) {
            return null;
        }
        if (!EnVivoService::abierto((string) $e['inicio'], Tiempo::ahora($this->zona()))) {
            return 'envivo.cerrado';
        }
        $staff = $this->esStaffDe($e);
        if (!$staff) {
            $svc = $this->svc();
            if (!$svc->esPresente($id, $this->persona['id'])) {
                return 'envivo.error.no_presente';
            }
            $rep = $svc->reportero($e);
            if ($rep !== null && $rep['persona_id'] !== $this->persona['id']) {
                return 'envivo.error.ocupado';
            }
        }
        return [$e, $staff];
    }

    /** «Estoy en la cancha» (o dejar de estarlo). */
    public function presente(string $id): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->partido($id);
        if ($e === null || !$this->puedeReportar($e) || !$this->csrfOk()) {
            $this->ir('/app/agenda');
            return;
        }
        $svc = $this->svc();
        if (!EnVivoService::abierto((string) $e['inicio'], Tiempo::ahora($this->zona()))) {
            $this->volver($id, $this->i18n->t('envivo.cerrado'), 'error');
            return;
        }
        $quedar = !$svc->esPresente($id, $this->persona['id']);
        $rep = $svc->reportero($e);
        if ($quedar && !$this->esStaffDe($e) && $rep !== null && $rep['persona_id'] !== $this->persona['id']) {
            $this->volver($id, $this->i18n->t('envivo.ocupado', ['quien' => $rep['nombre']]), 'error');
            return;
        }
        $svc->marcarPresente($id, $this->persona['id'], $quedar);
        if ($quedar) {
            $this->avisarInicio($e);
        }
        if ($quedar && !empty($_POST['ir'])) {
            $this->ir('/app/evento/' . $id . '/envivo');
            return;
        }
        $this->volver($id, $this->i18n->t($quedar ? 'envivo.presente_si' : 'envivo.presente_no'));
    }

    public function formulario(string $id): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->partido($id);
        if ($e === null || !$this->puedeReportar($e)) {
            \Flight::notFound();
            return;
        }
        $this->pagina($e, [], []);
    }

    private function pagina(array $e, array $d, array $err): void
    {
        $svc = $this->svc();
        $dep = $this->dep();
        $staff = $this->esStaffDe($e);
        $rep = $svc->reportero($e);
        $presente = $svc->esPresente($e['id'], $this->persona['id']);
        $ahora = Tiempo::ahora($this->zona());
        $env = $svc->vista($e, $ahora, $dep);
        echo $this->vista()->pagina('evento-envivo.latte', [
            'activa' => 'agenda', 'e' => $e, 'act' => $svc->actual($e['id']), 'd' => $d, 'errores' => $err, 'dep' => $dep, 'novedades' => EnVivoService::NOVEDADES[$dep] ?? EnVivoService::NOVEDADES['futbol'],
            'presente' => $presente, 'abierto' => EnVivoService::abierto((string) $e['inicio'], $ahora),
            'env' => $env, 'puede' => $staff, 'puedeRep' => true, 'reportero' => $rep,
            'soyReportero' => !$staff && $presente && ($rep === null || $rep['persona_id'] === $this->persona['id']),
        ]);
    }

    /** Un toque en el marcador (+1, +3, − corregir). Responde JSON con los números nuevos. */
    public function marcador(string $id): void
    {
        $a = $this->autorizar($id);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        if (!is_array($a)) {
            http_response_code(403);
            echo json_encode(['ok' => false]);
            return;
        }
        [$e] = $a;
        $svc = $this->svc();
        $dep = $this->dep();
        $accion = (string) ($_POST['accion'] ?? '');
        $m = $svc->tocar($id, $dep, $accion);
        if ($m === null) {
            http_response_code(409);
            echo json_encode(['ok' => false]);
            return;
        }
        $svc->marcarPresente($id, $this->persona['id'], true);
        $this->avisarInicio($e);
        // solo el fútbol avisa cada gol (pocos y importantes); en handball, básquet y vóley los puntos sueltos no mandan notificaciones
        if ($dep === 'futbol' && str_contains($accion, '+') && $svc->puedeAvisarGol($id)) {
            $titulo = (string) $e['titulo'];
            $n = (int) $m['nuestro'];
            $r = (int) $m['rival'];
            $this->notificar(
                (new AvisoService($this->pdo))->adultosDelEvento($id),
                'agenda',
                fn(I18n $i) => [$i->t('aviso.envivo.marcador', ['titulo' => $titulo, 'nuestro' => $n, 'rival' => $r]), ''],
                '/app/evento/' . $id,
                ['id' => $id, 'motivo' => 'marcador']
            );
        }
        $cur = ['nos' => $m['nuestro'], 'riv' => $m['rival']];
        foreach ($m['cols'] as $c) {
            if ($c['actual']) {
                $cur = ['nos' => $c['nos'], 'riv' => $c['riv']];
            }
        }
        echo json_encode(['ok' => true, 'nuestro' => $m['nuestro'], 'rival' => $m['rival'], 'cur' => $cur, 'total' => $m['total'], 'cerrable' => (bool) $m['cerrable']]);
    }

    /** Cambio de etapa: comenzar, entretiempo, 2.º tiempo, cerrar set, fin de cuarto, terminar. */
    public function etapa(string $id): void
    {
        $a = $this->autorizar($id);
        if ($a === null) {
            $this->ir('/app/agenda');
            return;
        }
        if (is_string($a)) {
            $this->aqui($id, $this->i18n->t($a), 'error');
            return;
        }
        [$e] = $a;
        $svc = $this->svc();
        $dep = $this->dep();
        [$err, $aviso] = $svc->avanzar($id, $dep, (string) ($_POST['accion'] ?? ''), $this->persona['id']);
        if ($err !== null) {
            $this->aqui($id, $this->i18n->t($err), 'error');
            return;
        }
        $svc->marcarPresente($id, $this->persona['id'], true);
        $this->avisarInicio($e);
        if ($aviso !== null) {
            $this->avisarEtapa($e, $aviso);
        }
        $this->aqui($id, $this->i18n->t('envivo.listo'));
    }

    /** Novedad informativa con nota. */
    public function novedad(string $id): void
    {
        $a = $this->autorizar($id);
        if ($a === null) {
            $this->ir('/app/agenda');
            return;
        }
        if (is_string($a)) {
            $this->aqui($id, $this->i18n->t($a), 'error');
            return;
        }
        [$e] = $a;
        $err = $this->svc()->novedad($id, $this->persona['id'], $this->dep(), (string) ($_POST['tipo'] ?? ''), (string) ($_POST['texto'] ?? ''));
        if ($err !== null) {
            $this->aqui($id, $this->i18n->t($err), 'error');
            return;
        }
        $this->svc()->marcarPresente($id, $this->persona['id'], true);
        $this->avisarInicio($e);
        $this->aqui($id, $this->i18n->t('envivo.publicado'));
    }

    /** Hora estimada de regreso: avisa a las familias solo si cambió. */
    public function regreso(string $id): void
    {
        $a = $this->autorizar($id);
        if ($a === null) {
            $this->ir('/app/agenda');
            return;
        }
        if (is_string($a)) {
            $this->aqui($id, $this->i18n->t($a), 'error');
            return;
        }
        [$e] = $a;
        $svc = $this->svc();
        [$err, $cambio] = $svc->regreso($id, $this->persona['id'], (string) ($_POST['regreso'] ?? ''), (string) $e['inicio']);
        if ($err !== null) {
            $this->aqui($id, $this->i18n->t($err), 'error');
            return;
        }
        if ($cambio) {
            $svc->marcarPresente($id, $this->persona['id'], true);
            $this->avisarInicio($e);
            $hora = Tiempo::hora((string) $svc->actual($id)['regreso']);
            $titulo = (string) $e['titulo'];
            $this->notificar(
                (new AvisoService($this->pdo))->adultosDelEvento($id),
                'agenda',
                fn(I18n $i) => [$i->t('aviso.envivo.regreso', ['titulo' => $titulo, 'hora' => $hora]), ''],
                '/app/evento/' . $id,
                ['id' => $id, 'motivo' => 'regreso']
            );
        }
        $this->aqui($id, $this->i18n->t($cambio ? 'envivo.regreso_ok' : 'envivo.sin_cambio'));
    }

    /** Una sola vez por partido: avisa a las familias que ya hay alguien reportando. */
    private function avisarInicio(array $e): void
    {
        $svc = $this->svc();
        if ($svc->inicioAvisado($e['id'])) {
            return;
        }
        $svc->marcarInicioAvisado($e['id']);
        $titulo = (string) $e['titulo'];
        $quien = (string) $this->persona['nombre'];
        $this->notificar(
            (new AvisoService($this->pdo))->adultosDelEvento($e['id']),
            'agenda',
            fn(I18n $i) => [$i->t('aviso.envivo.inicio', ['titulo' => $titulo]), $i->t('aviso.envivo.inicio_cuerpo', ['quien' => $quien])],
            '/app/evento/' . $e['id'],
            ['id' => $e['id'], 'motivo' => 'envivo']
        );
    }

    /** Avisos de etapa: set cerrado (vóley) y final. Los goles y los puntos sueltos no avisan aquí. */
    private function avisarEtapa(array $e, array $av): void
    {
        $titulo = (string) $e['titulo'];
        $motivo = (string) $av['motivo'];
        $dep = $this->dep();
        $textos = function (I18n $i) use ($titulo, $av, $motivo, $dep): array {
            $a = (int) ($av['nos'] ?? 0);
            $b = (int) ($av['riv'] ?? 0);
            if ($motivo === 'set') {
                return [$i->t('aviso.envivo.set', ['titulo' => $titulo, 'n' => $av['n'], 'a' => $a, 'b' => $b]), $i->t('aviso.envivo.set_cuerpo', ['a' => $av['sets'][0], 'b' => $av['sets'][1]])];
            }
            if ($dep === 'voley') {
                return [$i->t('aviso.envivo.final_sets', ['titulo' => $titulo, 'a' => $av['sets'][0], 'b' => $av['sets'][1]]), ''];
            }
            return [$i->t('aviso.envivo.final', ['titulo' => $titulo, 'nuestro' => $a, 'rival' => $b]), ''];
        };
        $this->notificar((new AvisoService($this->pdo))->adultosDelEvento($e['id']), 'agenda', $textos, '/app/evento/' . $e['id'], ['id' => $e['id'], 'motivo' => $motivo]);
    }

    public function confirmar(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->partido($id);
        if ($e === null || !$this->esStaffDe($e) || !$this->csrfOk()) {
            $this->ir('/app/agenda');
            return;
        }
        $ok = $this->svc()->confirmar($id, $this->persona['id']);
        $this->volver($id, $this->i18n->t($ok ? 'envivo.confirmado_ok' : 'envivo.error.sin_final'), $ok ? 'success' : 'error');
    }
}
