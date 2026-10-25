<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Reporte en vivo de un partido: «Estoy en la cancha», novedades, marcador y hora de regreso. */
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
        $svc->marcarPresente($id, $this->persona['id'], $quedar);
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
        $act = $svc->actual($e['id']);
        $dep = EnVivoService::deporte($this->club['deporte'] ?? null);
        echo $this->vista()->pagina('evento-envivo.latte', [
            'activa' => 'agenda', 'e' => $e, 'act' => $act, 'd' => $d, 'errores' => $err, 'dep' => $dep, 'botones' => EnVivoService::BOTONES[$dep],
            'presente' => $svc->esPresente($e['id'], $this->persona['id']), 'abierto' => EnVivoService::abierto((string) $e['inicio'], Tiempo::ahora($this->zona())),
        ]);
    }

    public function publicar(string $id): void
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
        $staff = $this->esStaffDe($e);
        if (!EnVivoService::abierto((string) $e['inicio'], Tiempo::ahora($this->zona()))) {
            $this->volver($id, $this->i18n->t('envivo.cerrado'), 'error');
            return;
        }
        if (!$staff && !$svc->esPresente($id, $this->persona['id'])) {
            $this->volver($id, $this->i18n->t('envivo.error.no_presente'), 'error');
            return;
        }
        $dep = EnVivoService::deporte($this->club['deporte'] ?? null);
        $act = $svc->actual($id);
        [$d, $err] = $svc->normalizar($_POST, (string) $e['inicio'], $act, $dep);
        if ($err !== null) {
            $this->pagina($e, $_POST, ['general' => $err]);
            return;
        }
        $svc->marcarPresente($id, $this->persona['id'], true);
        $nuevo = $svc->publicar($id, $this->persona['id'], $d);
        $this->avisarFamilias($e, $d, $nuevo);
        $this->volver($id, $this->i18n->t('envivo.publicado'));
    }

    /** Solo se avisa a las familias si cambia el marcador, la hora de regreso o termina el partido. */
    private function avisarFamilias(array $e, array $d, array $v): void
    {
        $final = $d['tipo'] === 'termino';
        $marcador = $d['nuestro'] !== null || $final;
        if (!$marcador && $d['regreso'] === null) {
            return;
        }
        $titulo = (string) $e['titulo'];
        $n = (int) $v['nuestro'];
        $r = (int) $v['rival'];
        $regreso = $v['regreso'] ? Tiempo::hora((string) $v['regreso']) : null;
        $motivo = $final ? 'final' : ($marcador ? 'marcador' : 'regreso');
        $clave = ['final' => 'aviso.envivo.final', 'marcador' => 'aviso.envivo.marcador', 'regreso' => 'aviso.envivo.regreso'][$motivo];
        $nota = $d['texto'];
        $this->notificar(
            (new AvisoService($this->pdo))->adultosDelEvento($e['id']),
            'agenda',
            fn(I18n $i) => [
                $i->t($clave, ['titulo' => $titulo, 'nuestro' => $n, 'rival' => $r, 'hora' => (string) $regreso]),
                $nota ?: ($regreso && $motivo !== 'regreso' ? $i->t('aviso.envivo.cuerpo_regreso', ['hora' => $regreso]) : ''),
            ],
            '/app/evento/' . $e['id'],
            ['id' => $e['id'], 'motivo' => $motivo]
        );
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
