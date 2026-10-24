<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Turnos de traslado: el entrenador los carga (o los reparte) y las familias piden sumarse. */
final class TurnoController extends AppBase
{
    private function svc(): TurnoService
    {
        return new TurnoService($this->pdo);
    }

    private function evento(string $id): ?array
    {
        $e = (new EventoService($this->pdo))->buscar($id);
        return $e !== null && $e['club_id'] === $this->club['id'] ? $e : null;
    }

    private function puedeGestionar(array $e): bool
    {
        return self::esCoach($this->rol) && in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true);
    }

    private function volver(string $id): void
    {
        $this->ir('/app/evento/' . $id . '#turnos');
    }

    public function agregar(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->evento($id);
        $fam = (string) ($_POST['familia'] ?? '');
        if ($e !== null && $this->puedeGestionar($e) && $this->csrfOk() && $this->svc()->esElegible($id, $fam)) {
            $this->svc()->agregar($id, $fam, $this->persona['id']);
        }
        $this->volver($id);
    }

    public function resolver(string $id, string $familia, string $accion): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->evento($id);
        if ($e !== null && $this->puedeGestionar($e) && $this->csrfOk()) {
            match ($accion) {
                'aceptar' => $this->svc()->aceptar($id, $familia),
                'quitar', 'rechazar' => $this->svc()->quitar($id, $familia),
                default => null,
            };
        }
        $this->volver($id);
    }

    /** «Yo también llevo»: una familia con hijos convocados pide sumarse. */
    public function pedir(string $id): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $e = $this->evento($id);
        if ($e !== null && $e['estado'] === 'publicado' && $this->rol === 'apoderado' && $this->csrfOk()) {
            $fams = $this->svc()->familiasDe($this->persona['id'], $id);
            if ($fams !== []) {
                $this->svc()->agregar($id, $fams[0], $this->persona['id'], 'pendiente');
                $this->ctx->redirect('/app/evento/' . $id . '#turnos', $this->i18n->t('turno.pedido'));
                return;
            }
        }
        $this->volver($id);
    }

    public function panel(): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $eqs = $this->gestionables();
        $desde = Tiempo::ahora((string) $this->club['zona'])->format('Y-m-d H:i:s');
        echo $this->vista()->pagina('turnos.latte', ['activa' => 'agenda', 'equipos' => $eqs, 'partidos' => $this->svc()->proximos($this->club['id'], array_column($eqs, 'id'), $desde)]);
    }

    public function repartir(): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $equipo = (string) ($_POST['equipo_id'] ?? '');
        if ($this->csrfOk() && in_array($equipo, array_column($this->gestionables(), 'id'), true)) {
            $desde = Tiempo::ahora((string) $this->club['zona'])->format('Y-m-d H:i:s');
            $n = $this->svc()->repartir($this->club['id'], $equipo, (int) ($_POST['por_partido'] ?? 2), $desde, $this->persona['id']);
            $this->ctx->redirect('/app/turnos', $this->i18n->n('turno.repartidos', $n));
            return;
        }
        $this->ir('/app/turnos');
    }
}
