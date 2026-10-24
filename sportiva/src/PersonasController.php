<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Personas del club, invitaciones y directorio de contactos. */
final class PersonasController extends AppBase
{
    public const ROLES_INVITABLES = ['entrenador', 'encargado', 'directiva'];

    private function dentro(): bool
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return false;
        }
        if ($this->rol !== 'directiva') {
            $this->ir('/app/mas');
            return false;
        }
        return true;
    }

    private function svc(): PersonaService
    {
        return new PersonaService($this->pdo);
    }

    // ---- personas ----------------------------------------------------------------------------

    public function lista(): void
    {
        if (!$this->dentro()) {
            return;
        }
        $g = in_array($_GET['g'] ?? '', ['entrenadores', 'familias', 'directiva'], true) ? (string) $_GET['g'] : 'todos';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        echo $this->vista()->pagina('club-personas.latte', ['activa' => 'club', 'personas' => $this->svc()->delClub($this->club['id'], $g, $q), 'g' => $g, 'q' => $q]);
    }

    private function propia(string $id): ?array
    {
        $p = $this->svc()->buscar($id);
        return $p !== null && $this->svc()->rolesEn($id, $this->club['id']) !== [] ? $p : null;
    }

    public function ficha(string $id): void
    {
        if (!$this->dentro()) {
            return;
        }
        $p = $this->propia($id);
        if ($p === null) {
            \Flight::notFound();
            return;
        }
        $roles = $this->svc()->rolesEn($id, $this->club['id']);
        $esStaff = array_intersect(['entrenador', 'encargado'], $roles) !== [];
        $mios = array_column($this->svc()->equiposStaff($id, $this->club['id']), 'id');
        $equipos = array_values(array_filter((new ClubService($this->pdo))->equipos($this->club['id']), fn($e) => !in_array($e['id'], $mios, true)));
        echo $this->vista()->pagina('club-persona.latte', [
            'activa' => 'club', 'p' => $p, 'roles' => $roles, 'esStaff' => $esStaff, 'staff' => $this->svc()->equiposStaff($id, $this->club['id']), 'equipos' => $equipos,
            'hijos' => in_array('apoderado', $roles, true) ? $this->svc()->hijos($id, $this->club['id']) : [], 'yo' => $id === $this->persona['id'],
            'faltan' => array_values(array_diff(self::ROLES_INVITABLES, $roles)), 'activo' => $this->svc()->haEntrado($id),
        ]);
    }

    public function actualizar(string $id, string $accion, ?string $extra = null): void
    {
        if (!$this->dentro()) {
            return;
        }
        $p = $this->propia($id);
        if ($p === null || !$this->csrfOk()) {
            $this->ir('/app/club/personas');
            return;
        }
        $s = $this->svc();
        $cid = (string) $this->club['id'];
        $err = null;
        if ($accion === 'rol') {
            $rol = (string) ($_POST['rol'] ?? '');
            if (in_array($rol, self::ROLES_INVITABLES, true)) {
                $s->dar($id, $cid, $rol);
            }
        } elseif ($accion === 'quitar-rol') {
            $rol = (string) $extra;
            $directivos = count($s->delClub($cid, 'directiva'));
            if ($rol === 'directiva' && $directivos <= 1) {
                $err = 'club.error.ultima_directiva';
            } else {
                $s->quitarRol($id, $cid, $rol);
            }
        } elseif ($accion === 'equipo') {
            $e = (new ClubService($this->pdo))->equipo((string) ($_POST['equipo'] ?? ''));
            $roles = $s->rolesEn($id, $cid);
            if ($e !== null && $e['club_id'] === $cid && array_intersect(['entrenador', 'encargado'], $roles) !== []) {
                $s->asignarStaff($e['id'], $id, in_array('entrenador', $roles, true) ? 'entrenador' : 'encargado');
            }
        } elseif ($accion === 'quitar-equipo') {
            $e = (new ClubService($this->pdo))->equipo((string) $extra);
            if ($e !== null && $e['club_id'] === $cid) {
                $s->quitarStaff($e['id'], $id);
            }
        } elseif ($accion === 'datos') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            if ($nombre !== '' && mb_strlen($nombre) <= 160) {
                $s->actualizarDatos($id, $nombre, mb_substr((string) ($_POST['telefono'] ?? ''), 0, 40));
            }
        }
        if ($s->rolesEn($id, $cid) === []) {
            $this->ctx->redirect('/app/club/personas', $this->i18n->t('club.persona_quitada'));
            return;
        }
        $this->ctx->redirect('/app/club/personas/' . $id, $err ? $this->i18n->t($err) : $this->i18n->t('evento.guardado'), $err ? 'error' : 'success');
    }

    // ---- invitaciones -------------------------------------------------------------------------

    private function equiposPorNombre(): array
    {
        $m = [];
        foreach ((new ClubService($this->pdo))->equipos($this->club['id']) as $e) {
            $m[mb_strtolower(trim((string) $e['nombre']))] = $e['id'];
        }
        return $m;
    }

    /** Invita (o suma un rol a) una persona; devuelve su id o null si el correo no sirve. */
    private function invitarUna(string $email, string $nombre, string $rol, array $equipos): ?string
    {
        $email = PersonaService::correo($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($rol, self::ROLES_INVITABLES, true)) {
            return null;
        }
        $s = $this->svc();
        $p = $s->porCorreo($email);
        $nuevo = $p === null;
        $pid = $p['id'] ?? $s->crear($email, $nombre !== '' ? $nombre : strstr($email, '@', true), $this->club['idioma']);
        $s->dar($pid, (string) $this->club['id'], $rol);
        if (in_array($rol, ['entrenador', 'encargado'], true)) {
            foreach ($equipos as $eid) {
                $s->asignarStaff($eid, $pid, $rol);
            }
        }
        if ($nuevo || !$s->haEntrado($pid)) {
            $this->enviarInvitacion($s->buscar($pid));
        }
        return $pid;
    }

    private function enviarInvitacion(array $p): bool
    {
        $i = new I18n($p['idioma'] && I18n::esValido($p['idioma']) ? $p['idioma'] : $this->club['idioma']);
        return (new Correo($this->ctx, $this->pdo))->invitacion($p['email'], $p['nombre'], (string) $this->club['nombre'], (string) $this->persona['nombre'], $i);
    }

    public function formInvitar(array $err = [], array $d = []): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('club-invitar.latte', ['activa' => 'club', 'equipos' => (new ClubService($this->pdo))->equipos($this->club['id']), 'roles' => self::ROLES_INVITABLES, 'errores' => $err, 'd' => $d]);
        }
    }

    public function invitar(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/club/invitar');
            return;
        }
        $email = (string) ($_POST['email'] ?? '');
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $rol = (string) ($_POST['rol'] ?? 'entrenador');
        $err = [];
        if (!filter_var(PersonaService::correo($email), FILTER_VALIDATE_EMAIL)) {
            $err['email'] = 'entrar.error.correo';
        }
        if ($nombre === '' || mb_strlen($nombre) > 160) {
            $err['nombre'] = 'club.error.nombre';
        }
        if (!in_array($rol, self::ROLES_INVITABLES, true)) {
            $err['rol'] = 'club.error.rol';
        }
        if ($err) {
            $this->formInvitar($err, $_POST);
            return;
        }
        $propios = array_column((new ClubService($this->pdo))->equipos($this->club['id']), 'id');
        $equipos = array_values(array_intersect((array) ($_POST['equipos'] ?? []), $propios));
        $pid = $this->invitarUna($email, $nombre, $rol, $equipos);
        $this->ctx->redirect($pid ? '/app/club/personas/' . $pid : '/app/club/invitar', $this->i18n->t('club.invitacion_enviada'));
    }

    public function invitaciones(): void
    {
        if (!$this->dentro()) {
            return;
        }
        $todas = $this->svc()->delClub($this->club['id']);
        $pendientes = array_values(array_filter($todas, fn($p) => !$p['activo']));
        echo $this->vista()->pagina('club-invitaciones.latte', [
            'activa' => 'club', 'activos' => count($todas) - count($pendientes), 'pendientes' => $pendientes, 'total' => count($todas), 'resultado' => $_GET['r'] ?? null,
        ]);
    }

    public function reenviar(string $id): void
    {
        if (!$this->dentro()) {
            return;
        }
        $p = $this->propia($id);
        if ($p !== null && $this->csrfOk() && !$this->svc()->haEntrado($id)) {
            $ok = $this->enviarInvitacion($p);
            $this->ctx->redirect('/app/club/invitaciones', $this->i18n->t($ok ? 'club.invitacion_reenviada' : 'club.error.correo'), $ok ? 'success' : 'error');
            return;
        }
        $this->ir('/app/club/invitaciones');
    }

    /** Carga por lista pegada: una persona por línea, «correo;nombre;rol;equipo». */
    public function cargarLista(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/club/invitaciones');
            return;
        }
        $porNombre = $this->equiposPorNombre();
        $ok = 0;
        $mal = 0;
        foreach (array_slice(preg_split('/\R/', (string) ($_POST['lista'] ?? '')) ?: [], 0, 100) as $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $c = array_map('trim', preg_split('/[;\t,]/', $linea) ?: []);
            $rol = mb_strtolower($c[2] ?? '') ?: 'entrenador';
            $eq = isset($c[3], $porNombre[mb_strtolower($c[3])]) ? [$porNombre[mb_strtolower($c[3])]] : [];
            $this->invitarUna($c[0] ?? '', $c[1] ?? '', $rol, $eq) !== null ? $ok++ : $mal++;
        }
        $this->ctx->redirect('/app/club/invitaciones', $this->i18n->t('club.lista_resultado', ['ok' => $ok, 'mal' => $mal]), $mal > 0 && $ok === 0 ? 'error' : 'success');
    }

    // ---- directorio ---------------------------------------------------------------------------

    public function directorio(): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        if ($this->rol === 'directiva' || $this->rol === 'encargado') {
            $equipos = (new ClubService($this->pdo))->equipos($this->club['id']);
        } elseif ($this->rol === 'entrenador') {
            $equipos = $this->gestionables();
        } else {
            $this->ir('/app/mas');
            return;
        }
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $sel = (string) ($_GET['equipo'] ?? '');
        $ids = array_column($equipos, 'id');
        $filtro = in_array($sel, $ids, true) ? [$sel] : $ids;
        $d = $this->svc()->directorio($this->club['id'], $filtro, $q);
        $dir = $this->rol === 'directiva' ? $this->svc()->delClub($this->club['id'], 'directiva', $q) : [];
        echo $this->vista()->pagina('directorio.latte', ['activa' => $this->rol === 'directiva' ? 'directorio' : 'plantel', 'equipos' => $equipos, 'sel' => in_array($sel, $ids, true) ? $sel : '', 'q' => $q, 'familias' => $d['familias'], 'staff' => $d['staff'], 'directiva' => $dir]);
    }
}
