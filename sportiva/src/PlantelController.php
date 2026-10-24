<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Plantel para entrenadores y encargados: jugadores, sus familias y las solicitudes que hay que aprobar. */
final class PlantelController extends AppBase
{
    private function svc(): PlantelService
    {
        return new PlantelService($this->pdo);
    }

    /** @return array<int, string> */
    private function ids(): array
    {
        return array_column($this->gestionables(), 'id');
    }

    /** Jugador del club en un equipo que esta persona gestiona, o null. */
    private function jugadorGestionable(string $id): ?array
    {
        $j = $this->svc()->jugador($id);
        return $j !== null && $j['club_id'] === $this->club['id'] && in_array($j['equipo_id'], $this->ids(), true) ? $j : null;
    }

    private function entrar(): bool
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return false;
        }
        return true;
    }

    public function inicio(): void
    {
        if (!$this->entrar()) {
            return;
        }
        $eqs = $this->gestionables();
        $sel = (string) ($_GET['equipo'] ?? '');
        $equipo = null;
        foreach ($eqs as $q) {
            if ($q['id'] === $sel || ($equipo === null && $sel === '')) {
                $equipo = $q;
            }
        }
        $equipo ??= $eqs[0] ?? null;
        $svc = $this->svc();
        echo $this->vista()->pagina('plantel.latte', [
            'activa' => 'plantel', 'equipos' => $eqs, 'equipo' => $equipo,
            'jugadores' => $equipo ? $svc->delEquipo($equipo['id']) : [],
            'pendientes' => count($svc->pendientes($this->club['id'], array_column($eqs, 'id'))),
            'locales' => (new LugarService($this->pdo))->locales($this->club['id']),
            'otrosLugares' => array_values(array_filter((new LugarService($this->pdo))->delClub($this->club['id']), fn($l) => !(new LugarService($this->pdo))->esLocal($l['id']))),
        ]);
    }

    /** Agrega un lugar donde somos local: uno ya existente o uno nuevo (con enlace de Google Maps opcional). */
    public function localAgregar(): void
    {
        if (!$this->entrar()) {
            return;
        }
        if ($this->csrfOk()) {
            $ls = new LugarService($this->pdo);
            $id = (string) ($_POST['lugar_id'] ?? '');
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            if ($id === '' && $nombre !== '') {
                $lat = '';
                $lon = '';
                if (($c = Mapa::coordenadasDeEnlace((string) ($_POST['enlace'] ?? ''))) !== null) {
                    [$lat, $lon] = $c;
                }
                $id = $ls->crear($this->club['id'], ['nombre' => $nombre, 'direccion' => trim((string) ($_POST['direccion'] ?? '')) ?: null, 'lat' => $lat ?: null, 'lon' => $lon ?: null]);
            }
            if ($id !== '') {
                $ls->marcarLocal($this->club['id'], $id, true);
            }
        }
        $this->ir('/app/plantel#locales');
    }

    public function localQuitar(string $id): void
    {
        if ($this->entrar() && $this->csrfOk()) {
            (new LugarService($this->pdo))->marcarLocal($this->club['id'], $id, false);
        }
        $this->ir('/app/plantel#locales');
    }

    private function formularioNuevo(array $d, array $err): void
    {
        echo $this->vista()->pagina('plantel-nuevo.latte', ['activa' => 'plantel', 'equipos' => $this->gestionables(), 'd' => $d, 'errores' => $err, 'familias' => $this->svc()->familias($this->club['id'])]);
    }

    public function nuevo(): void
    {
        if ($this->entrar()) {
            $this->formularioNuevo(['equipo_id' => (string) ($_GET['equipo'] ?? ''), 'familia' => (string) ($_GET['familia'] ?? 'nueva')], []);
        }
    }

    public function crear(): void
    {
        if (!$this->entrar()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/plantel');
            return;
        }
        $svc = $this->svc();
        $familia = (string) ($_POST['familia'] ?? 'nueva');
        [$hijo, $err] = $svc->normalizarHijo($_POST, $this->club['id']);
        if (!$err && !in_array($hijo['equipo_id'], $this->ids(), true)) {
            $err['equipo_id'] = 'plantel.error.equipo';
        }
        if ($familia !== 'nueva') {
            if ($svc->hijosDeTitular($familia, $this->club['id']) === []) {
                $err['familia'] = 'plantel.error.familia';
            }
            if ($err) {
                $this->formularioNuevo($_POST, $err);
                return;
            }
            $jid = $svc->cargarEnFamilia($this->club['id'], $hijo, $familia);
            $this->ctx->redirect('/app/plantel/jugador/' . $jid, $this->i18n->t('plantel.cargado_familia'));
            return;
        }
        [$adulto, $e2] = $svc->normalizarAdulto(['nombre' => $_POST['nombre_adulto'] ?? '', 'email' => $_POST['email'] ?? '', 'telefono' => $_POST['telefono'] ?? '']);
        $err += array_combine(array_map(fn($k) => 'adulto_' . $k, array_keys($e2)), $e2);
        if ($err) {
            $this->formularioNuevo($_POST, $err);
            return;
        }
        $existia = $svc->hijosDeTitular((string) ((new PersonaService($this->pdo))->porCorreo($adulto['email'])['id'] ?? ''), $this->club['id']) !== [];
        $r = $svc->cargar($this->club['id'], $hijo, $adulto);
        if (!$existia) {
            (new Correo($this->ctx, $this->pdo))->invitacion($adulto['email'], $adulto['nombre'], (string) $this->club['nombre'], (string) $this->persona['nombre'], $this->i18nDe($adulto['email']));
        }
        $this->ctx->redirect('/app/plantel/jugador/' . $r['jugador'], $this->i18n->t($existia ? 'plantel.cargado_familia' : 'plantel.cargado'));
    }

    private function i18nDe(string $email): I18n
    {
        $p = (new PersonaService($this->pdo))->porCorreo($email);
        foreach ([$p['idioma'] ?? null, $this->club['idioma'] ?? null] as $c) {
            if ($c && I18n::esValido($c)) {
                return new I18n($c);
            }
        }
        return new I18n('fr');
    }

    public function ficha(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $j = $this->jugadorGestionable($id);
        if ($j === null) {
            \Flight::notFound();
            return;
        }
        $svc = $this->svc();
        $titular = $svc->titularDe($id);
        echo $this->vista()->pagina('plantel-jugador.latte', [
            'activa' => 'plantel', 'j' => $j, 'adultos' => $svc->adultos($id), 'titular' => $titular, 'equipos' => $this->gestionables(),
            'errores' => [], 'd' => [], 'editar' => isset($_GET['editar']), 'agregar' => isset($_GET['agregar']),
        ]);
    }

    private function fichaConErrores(array $j, array $d, array $err, bool $editar, bool $agregar): void
    {
        $svc = $this->svc();
        echo $this->vista()->pagina('plantel-jugador.latte', [
            'activa' => 'plantel', 'j' => $j, 'adultos' => $svc->adultos($j['id']), 'titular' => $svc->titularDe($j['id']), 'equipos' => $this->gestionables(),
            'errores' => $err, 'd' => $d, 'editar' => $editar, 'agregar' => $agregar,
        ]);
    }

    public function actualizar(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $j = $this->jugadorGestionable($id);
        if ($j === null || !$this->csrfOk()) {
            $this->ir('/app/plantel');
            return;
        }
        $svc = $this->svc();
        [$d, $err] = $svc->normalizarHijo($_POST, $this->club['id']);
        if (!$err && !in_array($d['equipo_id'], $this->ids(), true)) {
            $err['equipo_id'] = 'plantel.error.equipo';
        }
        if ($err) {
            $this->fichaConErrores($j, $_POST, $err, true, false);
            return;
        }
        $svc->actualizarJugador($id, $d, true);
        $this->ctx->redirect('/app/plantel/jugador/' . $id, $this->i18n->t('evento.guardado'));
    }

    public function agregarAdulto(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $j = $this->jugadorGestionable($id);
        if ($j === null || !$this->csrfOk()) {
            $this->ir('/app/plantel');
            return;
        }
        $svc = $this->svc();
        $titular = $svc->titularDe($id);
        if ($titular === null) {
            $this->ir('/app/plantel/jugador/' . $id);
            return;
        }
        [$d, $err] = $svc->normalizarAdulto($_POST);
        if (!$err && count($svc->adultosDeFamilia($titular, $this->club['id'])) + 1 >= PlantelService::MAX_ADULTOS) {
            $err['email'] = 'plantel.error.max';
        }
        if (!$err && $this->yaEnFamilia($svc, $id, $d['email'])) {
            $err['email'] = 'plantel.error.repetido';
        }
        if ($err) {
            $this->fichaConErrores($j, $_POST, $err, false, true);
            return;
        }
        [, $nueva] = $svc->agregarAdulto($titular, $this->club['id'], $d);
        (new Correo($this->ctx, $this->pdo))->invitacion($d['email'], $d['nombre'], (string) $this->club['nombre'], (string) $this->persona['nombre'], $this->i18nDe($d['email']));
        $this->ctx->redirect('/app/plantel/jugador/' . $id, $this->i18n->t('plantel.adulto_agregado'));
    }

    private function yaEnFamilia(PlantelService $svc, string $jugador, string $email): bool
    {
        foreach ($svc->adultos($jugador) as $a) {
            if (PersonaService::correo((string) $a['email']) === $email) {
                return true;
            }
        }
        return false;
    }

    public function quitarAdulto(string $id, string $persona): void
    {
        if (!$this->entrar()) {
            return;
        }
        $j = $this->jugadorGestionable($id);
        $svc = $this->svc();
        $titular = $j ? $svc->titularDe($id) : null;
        if ($j !== null && $titular !== null && $this->csrfOk()) {
            $svc->quitarAdulto($titular, $this->club['id'], $persona);
            $this->ctx->redirect('/app/plantel/jugador/' . $id, $this->i18n->t('plantel.adulto_quitado'));
            return;
        }
        $this->ir('/app/plantel');
    }

    public function solicitudes(): void
    {
        if (!$this->entrar()) {
            return;
        }
        echo $this->vista()->pagina('plantel-solicitudes.latte', ['activa' => 'plantel', 'lista' => $this->svc()->pendientes($this->club['id'], $this->ids())]);
    }

    public function resolver(string $id, string $accion): void
    {
        if (!$this->entrar()) {
            return;
        }
        $svc = $this->svc();
        $s = $svc->solicitud($id);
        if ($s === null || $s['club_id'] !== $this->club['id'] || !$svc->puedeResolver($s, $this->ids()) || !$this->csrfOk()) {
            $this->ir('/app/plantel/solicitudes');
            return;
        }
        if ($accion === 'aprobar') {
            $r = $svc->aprobar($id, $this->persona['id']);
            if ($r !== null && $r['persona'] !== null && $s['email']) {
                (new Correo($this->ctx, $this->pdo))->invitacion((string) $s['email'], (string) $s['nombre'], (string) $this->club['nombre'], (string) $this->persona['nombre'], $this->i18nDe((string) $s['email']));
            }
            $this->notificar([(string) $s['solicitante_id']], 'plantel', fn(I18n $i) => [$i->t('aviso.solicitud.aprobada'), (string) $s['nombre']], '/app/familia');
            $this->ctx->redirect('/app/plantel/solicitudes', $this->i18n->t('plantel.aprobada'));
            return;
        }
        $svc->rechazar($id, $this->persona['id']);
        $this->notificar([(string) $s['solicitante_id']], 'plantel', fn(I18n $i) => [$i->t('aviso.solicitud.rechazada'), (string) $s['nombre']], '/app/familia');
        $this->ctx->redirect('/app/plantel/solicitudes', $this->i18n->t('plantel.rechazada'));
    }
}
