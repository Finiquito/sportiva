<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Consultas privadas: las familias escriben (Mi actividad) y el cuerpo técnico responde (Consultas). */
final class ConsultaController extends AppBase
{
    private function svc(): ConsultaService
    {
        return new ConsultaService($this->pdo);
    }

    private function entrar(): bool
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return false;
        }
        return true;
    }

    /** @return array<int, string>|null */
    private function equiposStaff(): ?array
    {
        return $this->rol === 'encargado' ? null : array_column($this->gestionables(), 'id');
    }

    /** 'familia', 'staff' o null según quién es esta persona respecto de la consulta. */
    private function lado(array $c): ?string
    {
        if ($this->rol === 'apoderado' && (new EventoService($this->pdo))->esApoderadoDe($this->persona['id'], $c['jugador_id'])) {
            return 'familia';
        }
        if (self::esCoach($this->rol) && ($this->rol === 'encargado' || in_array($c['equipo_id'], array_column($this->gestionables(), 'id'), true))) {
            return 'staff';
        }
        return null;
    }

    /** Mi actividad (familias): consultas y mis asistencias. */
    public function actividad(): void
    {
        if (!$this->entrar()) {
            return;
        }
        if ($this->rol !== 'apoderado') {
            $this->ir(self::esCoach($this->rol) ? '/app/consultas' : '/app/agenda');
            return;
        }
        $desde = Tiempo::ahora((string) $this->club['zona'])->format('Y-m-d') . ' 00:00:00';
        echo $this->vista()->pagina('actividad.latte', [
            'activa' => 'actividad', 'consultas' => $this->svc()->deFamilia($this->persona['id'], $this->club['id']),
            'asistencias' => array_slice((new EventoService($this->pdo))->paraApoderado($this->persona['id'], $this->club['id'], $desde), 0, 8),
        ]);
    }

    /** Consultas del cuerpo técnico. */
    public function lista(): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $vista = ($_GET['v'] ?? '') === 'todas' ? 'todas' : 'pendientes';
        $svc = $this->svc();
        echo $this->vista()->pagina('consultas.latte', [
            'activa' => 'consultas', 'vista' => $vista, 'lista' => $svc->deStaff($this->club['id'], $this->equiposStaff(), $vista === 'pendientes'),
            'pendientes' => count($svc->deStaff($this->club['id'], $this->equiposStaff(), true)),
        ]);
    }

    private function formulario(array $d, array $err): void
    {
        echo $this->vista()->pagina('consulta-form.latte', ['activa' => 'actividad', 'd' => $d, 'errores' => $err, 'hijos' => (new PersonaService($this->pdo))->hijos($this->persona['id'], $this->club['id'])]);
    }

    public function nueva(): void
    {
        if ($this->entrar()) {
            if ($this->rol !== 'apoderado') {
                $this->ir('/app/agenda');
                return;
            }
            $this->formulario(['jugador_id' => (string) ($_GET['jugador'] ?? ''), 'evento_id' => (string) ($_GET['evento'] ?? '')], []);
        }
    }

    public function crear(): void
    {
        if (!$this->entrar()) {
            return;
        }
        if ($this->rol !== 'apoderado' || !$this->csrfOk()) {
            $this->ir('/app/agenda');
            return;
        }
        $svc = $this->svc();
        $this->textoDeFoto();
        [$d, $err] = $svc->normalizar($_POST);
        $ev = new EventoService($this->pdo);
        $hijo = $this->hijoPropio($d['jugador_id']);
        if ($hijo === null) {
            $err['jugador_id'] = 'consulta.error.hijo';
        }
        if ($d['evento_id'] !== '') {
            $e = $ev->buscar($d['evento_id']);
            if ($e === null || $e['club_id'] !== $this->club['id'] || ($hijo !== null && !$ev->estaConvocado($d['evento_id'], $d['jugador_id']))) {
                $d['evento_id'] = '';
            }
        }
        if ($err) {
            $this->formulario($_POST, $err);
            return;
        }
        $id = $svc->crear($this->club['id'], $this->persona['id'], $d);
        $foto = (new AdjuntoService($this->pdo))->guardar($this->club['id'], 'mensaje', $svc->mensajes($id)[0]['id'], $this->persona['id'], $_FILES['foto'] ?? null);
        $this->avisarStaff($id, $hijo['equipo_id']);
        $this->terminar('/app/consulta/' . $id, 'consulta.enviada', $foto['error']);
    }

    /** Un mensaje solo con foto: se pone un texto breve para que no quede vacío. */
    private function textoDeFoto(): void
    {
        if (trim((string) ($_POST['texto'] ?? '')) === '' && AdjuntoService::hayArchivo($_FILES['foto'] ?? null)) {
            $_POST['texto'] = $this->i18n->t('adjunto.solo_foto');
        }
    }

    /** Redirige; si la foto falló avisa que el mensaje sí salió. */
    private function terminar(string $url, ?string $claveOk, ?string $errorFoto): void
    {
        if ($errorFoto !== null) {
            $this->ctx->redirect($url, $this->i18n->t($errorFoto) . ' ' . $this->i18n->t('adjunto.sin_foto'), 'error');
            return;
        }
        $claveOk !== null ? $this->ctx->redirect($url, $this->i18n->t($claveOk)) : $this->ir($url);
    }

    /** Hijo de esta persona en este club o null. */
    private function hijoPropio(string $id): ?array
    {
        foreach ((new PersonaService($this->pdo))->hijos($this->persona['id'], $this->club['id']) as $h) {
            if ($h['id'] === $id) {
                return $h;
            }
        }
        return null;
    }

    private function avisarStaff(string $consulta, string $equipo): void
    {
        $c = $this->svc()->buscar($consulta);
        $quien = (string) $this->persona['nombre'];
        $this->notificar((new AvisoService($this->pdo))->staffDelEquipo($this->club['id'], $equipo), 'consultas', fn(I18n $i) => [$i->t('aviso.consulta.nueva', ['quien' => $quien]), (string) $c['asunto']], '/app/consulta/' . $consulta);
    }

    public function ver(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $svc = $this->svc();
        $c = $svc->buscar($id);
        $lado = $c !== null && $c['club_id'] === $this->club['id'] ? $this->lado($c) : null;
        if ($lado === null) {
            \Flight::notFound();
            return;
        }
        $svc->leida($id, $lado);
        $ms = $svc->mensajes($id);
        echo $this->vista()->pagina('consulta.latte', ['activa' => $lado === 'staff' ? 'consultas' : 'actividad', 'c' => $c, 'mensajes' => $ms, 'adj' => (new AdjuntoService($this->pdo))->de('mensaje', array_column($ms, 'id')), 'lado' => $lado, 'errores' => [], 'texto' => '']);
    }

    public function responder(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $svc = $this->svc();
        $c = $svc->buscar($id);
        $lado = $c !== null && $c['club_id'] === $this->club['id'] ? $this->lado($c) : null;
        if ($lado === null || !$this->csrfOk()) {
            $this->ir('/app/agenda');
            return;
        }
        $this->textoDeFoto();
        [$d, $err] = $svc->normalizar(['texto' => $_POST['texto'] ?? ''], false);
        if ($err) {
            $ms = $svc->mensajes($id);
            echo $this->vista()->pagina('consulta.latte', ['activa' => $lado === 'staff' ? 'consultas' : 'actividad', 'c' => $c, 'mensajes' => $ms, 'adj' => (new AdjuntoService($this->pdo))->de('mensaje', array_column($ms, 'id')), 'lado' => $lado, 'errores' => $err, 'texto' => (string) ($_POST['texto'] ?? '')]);
            return;
        }
        $mid = $svc->mensaje($id, $this->persona['id'], $lado, $d['texto']);
        $foto = (new AdjuntoService($this->pdo))->guardar($this->club['id'], 'mensaje', $mid, $this->persona['id'], $_FILES['foto'] ?? null);
        if ($lado === 'staff') {
            $quien = (string) $this->persona['nombre'];
            $adultos = array_column((new PlantelService($this->pdo))->adultos($c['jugador_id']), 'id');
            $this->notificar(array_merge($adultos, [(string) $c['creada_por']]), 'consultas', fn(I18n $i) => [$i->t('aviso.consulta.respuesta', ['quien' => $quien]), (string) $c['asunto']], '/app/consulta/' . $id);
        } else {
            $this->avisarStaff($id, $c['equipo_id']);
        }
        $this->terminar('/app/consulta/' . $id . '#abajo', null, $foto['error']);
    }
}
