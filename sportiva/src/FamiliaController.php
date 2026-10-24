<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** «Mi familia» del apoderado. El titular (el que cargó el entrenador) agrega adultos e hijos; el entrenador lo aprueba. */
final class FamiliaController extends AppBase
{
    private function svc(): PlantelService
    {
        return new PlantelService($this->pdo);
    }

    private function entrar(): bool
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return false;
        }
        if ($this->rol !== 'apoderado') {
            $this->ir('/app/mas');
            return false;
        }
        return true;
    }

    private function esTitular(): bool
    {
        return $this->svc()->hijosDeTitular($this->persona['id'], $this->club['id']) !== [];
    }

    private function mostrar(array $extra = []): void
    {
        $svc = $this->svc();
        $club = $this->club['id'];
        $yo = $this->persona['id'];
        $titular = $this->esTitular();
        $hijos = (new PersonaService($this->pdo))->hijos($yo, $club);
        if ($titular) {
            $adultos = array_merge([['id' => $yo, 'nombre' => $this->persona['nombre'], 'email' => $this->persona['email'], 'telefono' => $this->persona['telefono'], 'titular' => 1]], array_map(fn($a) => $a + ['titular' => 0], $svc->adultosDeFamilia($yo, $club)));
        } else {
            $adultos = [];
            foreach ($hijos as $h) {
                foreach ($svc->adultos($h['id']) as $a) {
                    $adultos[$a['id']] = $a;
                }
            }
            $adultos = array_values($adultos);
        }
        echo $this->vista()->pagina('familia.latte', $extra + [
            'activa' => 'mas', 'titular' => $titular, 'adultos' => $adultos, 'hijos' => $hijos, 'pendientes' => $titular ? $svc->solicitudesDe($yo, $club) : [],
        ]);
    }

    public function inicio(): void
    {
        if ($this->entrar()) {
            $this->mostrar();
        }
    }

    private function formulario(string $tipo, array $d, array $err): void
    {
        echo $this->vista()->pagina('familia-form.latte', ['activa' => 'mas', 'tipo' => $tipo, 'd' => $d, 'errores' => $err, 'equipos' => (new ClubService($this->pdo))->equipos($this->club['id']), 'j' => null]);
    }

    public function formAdulto(): void
    {
        if ($this->entrar() && $this->exigirTitular()) {
            $this->formulario('adulto', [], []);
        }
    }

    public function formHijo(): void
    {
        if ($this->entrar() && $this->exigirTitular()) {
            $this->formulario('hijo', [], []);
        }
    }

    private function exigirTitular(): bool
    {
        if ($this->esTitular()) {
            return true;
        }
        $this->ir('/app/familia');
        return false;
    }

    /** Avisa por correo a quienes aprueban. */
    private function avisarAprobadores(?string $equipo = null): void
    {
        $correo = new Correo($this->ctx, $this->pdo);
        $eq = $equipo ?? ($this->svc()->hijosDeTitular($this->persona['id'], $this->club['id'])[0]['equipo_id'] ?? null);
        foreach ($this->svc()->aprobadores($this->club['id'], $eq) as $a) {
            $idioma = $a['idioma'] && I18n::esValido((string) $a['idioma']) ? (string) $a['idioma'] : (string) ($this->club['idioma'] ?? 'fr');
            $correo->solicitudNueva((string) $a['email'], (string) $a['nombre'], (string) $this->persona['nombre'], (string) $this->club['nombre'], new I18n($idioma));
        }
    }

    private function cupo(): bool
    {
        return $this->svc()->pendientesDe($this->persona['id'], $this->club['id']) < PlantelService::MAX_PENDIENTES;
    }

    public function guardarAdulto(): void
    {
        if (!$this->entrar() || !$this->exigirTitular()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/familia');
            return;
        }
        $svc = $this->svc();
        [$d, $err] = $svc->normalizarAdulto($_POST);
        $yo = $this->persona['id'];
        $club = $this->club['id'];
        if (!$err) {
            $correos = array_map(fn($a) => PersonaService::correo((string) $a['email']), $svc->adultosDeFamilia($yo, $club));
            $correos[] = PersonaService::correo((string) $this->persona['email']);
            foreach ($svc->solicitudesDe($yo, $club) as $s) {
                $correos[] = (string) $s['email'];
            }
            if (in_array($d['email'], $correos, true)) {
                $err['email'] = 'plantel.error.repetido';
            } elseif (count($svc->adultosDeFamilia($yo, $club)) + 1 >= PlantelService::MAX_ADULTOS || !$this->cupo()) {
                $err['email'] = 'plantel.error.max';
            }
        }
        if ($err) {
            $this->formulario('adulto', $_POST, $err);
            return;
        }
        $svc->solicitar($club, 'adulto', $yo, $d);
        $this->avisarAprobadores();
        $this->ctx->redirect('/app/familia', $this->i18n->t('familia.enviada'));
    }

    public function guardarHijo(): void
    {
        if (!$this->entrar() || !$this->exigirTitular()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/familia');
            return;
        }
        $svc = $this->svc();
        [$d, $err] = $svc->normalizarHijo($_POST, $this->club['id']);
        if (!$err && !$this->cupo()) {
            $err['nombre'] = 'plantel.error.max';
        }
        if ($err) {
            $this->formulario('hijo', $_POST, $err);
            return;
        }
        $svc->solicitar($this->club['id'], 'hijo', $this->persona['id'], $d);
        $this->avisarAprobadores($d['equipo_id']);
        $this->ctx->redirect('/app/familia', $this->i18n->t('familia.enviada'));
    }

    public function cancelar(string $id): void
    {
        if ($this->entrar() && $this->exigirTitular() && $this->csrfOk()) {
            $this->svc()->cancelar($id, $this->persona['id']);
            $this->ctx->redirect('/app/familia', $this->i18n->t('familia.cancelada'));
            return;
        }
        $this->ir('/app/familia');
    }

    public function quitarAdulto(string $persona): void
    {
        if ($this->entrar() && $this->exigirTitular() && $this->csrfOk()) {
            $this->svc()->quitarAdulto($this->persona['id'], $this->club['id'], $persona);
            $this->ctx->redirect('/app/familia', $this->i18n->t('plantel.adulto_quitado'));
            return;
        }
        $this->ir('/app/familia');
    }

    /** Un hijo de esta familia (el apoderado es titular), o null. */
    private function hijoPropio(string $id): ?array
    {
        $j = $this->svc()->jugador($id);
        return $j !== null && $j['club_id'] === $this->club['id'] && $this->svc()->esTitular($this->persona['id'], $id) ? $j : null;
    }

    public function editarHijo(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $j = $this->hijoPropio($id);
        if ($j === null) {
            $this->ir('/app/familia');
            return;
        }
        echo $this->vista()->pagina('familia-form.latte', ['activa' => 'mas', 'tipo' => 'editar', 'd' => ['nombre' => $j['nombre'], 'nacimiento' => $j['nacimiento'] ?? ''], 'errores' => [], 'equipos' => [], 'j' => $j]);
    }

    public function actualizarHijo(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $j = $this->hijoPropio($id);
        if ($j === null || !$this->csrfOk()) {
            $this->ir('/app/familia');
            return;
        }
        $svc = $this->svc();
        [$d, $err] = $svc->normalizarHijo($_POST, $this->club['id'], false);
        if ($err) {
            echo $this->vista()->pagina('familia-form.latte', ['activa' => 'mas', 'tipo' => 'editar', 'd' => $_POST, 'errores' => $err, 'equipos' => [], 'j' => $j]);
            return;
        }
        $svc->actualizarJugador($id, $d, false);
        $this->ctx->redirect('/app/familia', $this->i18n->t('evento.guardado'));
    }
}
