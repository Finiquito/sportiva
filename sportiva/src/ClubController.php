<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Panel de la directiva: resumen del club, equipos, lugares, temporada y ajustes. */
final class ClubController extends AppBase
{
    public const ZONAS = ['Europe/Paris', 'Europe/Madrid', 'Europe/London', 'Europe/Brussels', 'Europe/Zurich', 'Europe/Berlin', 'Europe/Rome', 'Europe/Lisbon', 'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/Bogota', 'America/Lima', 'America/Mexico_City', 'America/Montevideo', 'America/Toronto', 'America/New_York', 'Africa/Casablanca', 'UTC'];

    private function svc(): ClubService
    {
        return new ClubService($this->pdo);
    }

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

    public function inicio(): void
    {
        if (!$this->dentro()) {
            return;
        }
        $p = new PersonaService($this->pdo);
        $personas = $p->delClub($this->club['id']);
        echo $this->vista()->pagina('club.latte', [
            'activa' => 'club', 'r' => $this->svc()->resumen($this->club['id']), 'lugares' => count((new LugarService($this->pdo))->delClub($this->club['id'])),
            'sinEntrar' => count(array_filter($personas, fn($x) => !$x['activo'])), 'autorizados' => count($personas), 'temporada' => $this->svc()->temporada($this->club['id']),
        ]);
    }

    // ---- equipos ---------------------------------------------------------------------------------

    public function equipos(array $err = [], array $d = []): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('club-equipos.latte', ['activa' => 'club', 'equipos' => $this->svc()->equiposConConteo($this->club['id']), 'r' => $this->svc()->resumen($this->club['id']), 'temporada' => $this->svc()->temporada($this->club['id']), 'errores' => $err, 'd' => $d]);
        }
    }

    public function crearEquipo(): void
    {
        if (!$this->dentro()) {
            return;
        }
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $svc = $this->svc();
        if (!$this->csrfOk()) {
            $this->ir('/app/club/equipos');
            return;
        }
        $err = [];
        if ($nombre === '' || mb_strlen($nombre) > 80) {
            $err['nombre'] = 'club.error.nombre';
        } elseif (in_array(mb_strtolower($nombre), array_map(fn($e) => mb_strtolower((string) $e['nombre']), $svc->equipos($this->club['id'])), true)) {
            $err['nombre'] = 'club.error.equipo_repetido';
        }
        if ($err) {
            $this->equipos($err, $_POST);
            return;
        }
        $id = $svc->crearEquipo($this->club['id'], $nombre, $svc->temporada($this->club['id']));
        $this->ctx->redirect('/app/club/equipos/' . $id, $this->i18n->t('club.equipo_creado'));
    }

    private function equipoPropio(string $id): ?array
    {
        $e = $this->svc()->equipo($id);
        return $e !== null && $e['club_id'] === $this->club['id'] ? $e : null;
    }

    public function equipo(string $id): void
    {
        if (!$this->dentro()) {
            return;
        }
        $e = $this->equipoPropio($id);
        if ($e === null) {
            \Flight::notFound();
            return;
        }
        $staff = $this->svc()->staffDe($id);
        $enEquipo = array_column($staff, 'id');
        $posibles = array_values(array_filter((new PersonaService($this->pdo))->delClub($this->club['id'], 'entrenadores'), fn($p) => !in_array($p['id'], $enEquipo, true)));
        echo $this->vista()->pagina('club-equipo.latte', ['activa' => 'club', 'e' => $e, 'staff' => $staff, 'posibles' => $posibles, 'errores' => []]);
    }

    public function actualizarEquipo(string $id): void
    {
        if (!$this->dentro()) {
            return;
        }
        $e = $this->equipoPropio($id);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($e !== null && $this->csrfOk() && $nombre !== '' && mb_strlen($nombre) <= 80) {
            $this->svc()->actualizarEquipo($id, $nombre, mb_substr((string) ($_POST['temporada'] ?? ''), 0, 16));
            $this->ctx->redirect('/app/club/equipos/' . $id, $this->i18n->t('evento.guardado'));
            return;
        }
        $this->ir('/app/club/equipos' . ($e ? '/' . $id : ''));
    }

    public function archivarEquipo(string $id): void
    {
        if (!$this->dentro()) {
            return;
        }
        if ($this->equipoPropio($id) !== null && $this->csrfOk()) {
            $ok = $this->svc()->archivarEquipo($id);
            $this->ctx->redirect($ok ? '/app/club/equipos' : '/app/club/equipos/' . $id, $this->i18n->t($ok ? 'club.equipo_archivado' : 'club.error.equipo_con_jugadores'), $ok ? 'success' : 'error');
            return;
        }
        $this->ir('/app/club/equipos');
    }

    public function agregarStaff(string $id): void
    {
        if (!$this->dentro()) {
            return;
        }
        $pid = (string) ($_POST['persona'] ?? '');
        if ($this->equipoPropio($id) !== null && $this->csrfOk()) {
            $p = new PersonaService($this->pdo);
            $roles = $p->rolesEn($pid, $this->club['id']);
            if (array_intersect(['entrenador', 'encargado'], $roles) !== []) {
                $p->asignarStaff($id, $pid, in_array('entrenador', $roles, true) ? 'entrenador' : 'encargado');
            }
        }
        $this->ir('/app/club/equipos/' . $id);
    }

    public function quitarStaff(string $id, string $persona): void
    {
        if ($this->dentro() && $this->equipoPropio($id) !== null && $this->csrfOk()) {
            (new PersonaService($this->pdo))->quitarStaff($id, $persona);
        }
        $this->ir('/app/club/equipos/' . $id);
    }

    // ---- lugares ---------------------------------------------------------------------------------

    public function lugares(): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('club-lugares.latte', ['activa' => 'club', 'lugares' => (new LugarService($this->pdo))->delClub($this->club['id'])]);
        }
    }

    private function lugarPropio(string $id): ?array
    {
        $l = (new LugarService($this->pdo))->buscar($id);
        return $l !== null && $l['club_id'] === $this->club['id'] ? $l : null;
    }

    private function formularioLugar(?array $l, array $d, array $err): void
    {
        $lat = (string) ($d['lat'] ?? '');
        $lon = (string) ($d['lon'] ?? '');
        $svc = new LugarService($this->pdo);
        echo $this->vista()->pagina('club-lugar.latte', [
            'activa' => 'club', 'l' => $l, 'd' => $d + ['local' => $l ? (int) $svc->esLocal($l['id']) : ($d['local'] ?? 0)], 'errores' => $err, 'enUso' => $l ? $svc->enUso($l['id']) : 0,
            'mapa' => Mapa::valido($lat, $lon) ? Mapa::html($lat, $lon, 16, (string) ($d['nombre'] ?? ''), $this->i18n, (string) ($d['direccion'] ?? '')) : '', 'enlaces' => Mapa::valido($lat, $lon) ? Mapa::enlaces($lat, $lon) : null,
        ]);
    }

    public function lugarNuevo(): void
    {
        if ($this->dentro()) {
            $this->formularioLugar(null, ['club_local' => (string) $this->club['nombre'], 'lat' => '', 'lon' => ''], []);
        }
    }

    public function lugarEditar(string $id): void
    {
        if (!$this->dentro()) {
            return;
        }
        $l = $this->lugarPropio($id);
        if ($l === null) {
            \Flight::notFound();
            return;
        }
        $this->formularioLugar($l, $l + [], []);
    }

    public function guardarLugar(?string $id = null): void
    {
        if (!$this->dentro()) {
            return;
        }
        $l = $id !== null ? $this->lugarPropio($id) : null;
        if (!$this->csrfOk() || ($id !== null && $l === null)) {
            $this->ir('/app/club/lugares');
            return;
        }
        $d = [
            'nombre' => trim((string) ($_POST['nombre'] ?? '')), 'club_local' => trim((string) ($_POST['club_local'] ?? '')) ?: null, 'direccion' => trim((string) ($_POST['direccion'] ?? '')) ?: null,
            'lat' => trim((string) ($_POST['lat'] ?? '')), 'lon' => trim((string) ($_POST['lon'] ?? '')),
        ];
        if ($d['lat'] === '' && $d['lon'] === '' && ($co = Mapa::coordenadasDeEnlace((string) ($_POST['enlace'] ?? ''))) !== null) {
            [$d['lat'], $d['lon']] = $co;
        }
        $err = [];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 160) {
            $err['nombre'] = 'club.error.nombre';
        }
        if (($d['lat'] !== '' || $d['lon'] !== '') && !Mapa::valido($d['lat'], $d['lon'])) {
            $err['lat'] = 'club.error.coordenadas';
        }
        if ($err) {
            $this->formularioLugar($l, $d, $err);
            return;
        }
        $svc = new LugarService($this->pdo);
        $d['lat'] = $d['lat'] !== '' ? $d['lat'] : null;
        $d['lon'] = $d['lon'] !== '' ? $d['lon'] : null;
        if ($l === null) {
            $lid = $svc->crear($this->club['id'], $d);
        } else {
            $svc->actualizar($l['id'], $d);
            $lid = $l['id'];
        }
        if (!empty($_POST['predeterminado'])) {
            $svc->predeterminar($this->club['id'], $lid);
        }
        $svc->marcarLocal($this->club['id'], $lid, !empty($_POST['local']));
        $this->ctx->redirect('/app/club/lugares', $this->i18n->t('club.lugar_guardado'));
    }

    public function eliminarLugar(string $id): void
    {
        if ($this->dentro() && ($l = $this->lugarPropio($id)) !== null && $this->csrfOk()) {
            $ok = (new LugarService($this->pdo))->eliminar($id);
            $this->ctx->redirect('/app/club/lugares', $this->i18n->t($ok ? 'club.lugar_borrado' : 'club.error.lugar_en_uso'), $ok ? 'success' : 'error');
            return;
        }
        $this->ir('/app/club/lugares');
    }

    // ---- temporada y ajustes ----------------------------------------------------------------------

    public function temporada(): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('club-temporada.latte', ['activa' => 'club', 'temporada' => $this->svc()->temporada($this->club['id']), 'r' => $this->svc()->resumen($this->club['id'])]);
        }
    }

    public function guardarTemporada(): void
    {
        if ($this->dentro()) {
            if ($this->csrfOk() && trim((string) ($_POST['temporada'] ?? '')) !== '') {
                $this->svc()->guardarTemporada($this->club['id'], (string) $_POST['temporada']);
            }
            $this->ctx->redirect('/app/club/temporada', $this->i18n->t('evento.guardado'));
        }
    }

    private function formularioAjustes(array $d, array $err): void
    {
        echo $this->vista()->pagina('club-ajustes.latte', ['activa' => 'club', 'd' => $d, 'errores' => $err, 'idiomas' => I18n::disponibles(), 'zonas' => self::ZONAS, 'deportes' => ['futbol', 'voley', 'basquet', 'handball']]);
    }

    public function ajustes(): void
    {
        if ($this->dentro()) {
            $this->formularioAjustes($this->club + [], []);
        }
    }

    public function guardarAjustes(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/club/ajustes');
            return;
        }
        $d = $this->club;
        foreach (['nombre', 'ciudad', 'telefono', 'direccion', 'descripcion', 'lat', 'lon'] as $k) {
            $d[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        $d['deporte'] = in_array($_POST['deporte'] ?? '', ['futbol', 'voley', 'basquet', 'handball'], true) ? $_POST['deporte'] : $this->club['deporte'];
        $d['idioma'] = I18n::esValido((string) ($_POST['idioma'] ?? '')) ? $_POST['idioma'] : $this->club['idioma'];
        $d['zona'] = in_array($_POST['zona'] ?? '', timezone_identifiers_list(), true) ? $_POST['zona'] : $this->club['zona'];
        $d['publico'] = !empty($_POST['publico']) ? 1 : 0;
        $d['exigir_app'] = !empty($_POST['exigir_app']) ? 1 : 0;
        $err = [];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 160) {
            $err['nombre'] = 'club.error.nombre';
        }
        if (($d['lat'] !== '' || $d['lon'] !== '') && !Mapa::valido($d['lat'], $d['lon'])) {
            $err['lat'] = 'club.error.coordenadas';
        }
        if ($err) {
            $this->formularioAjustes($d, $err);
            return;
        }
        foreach (['ciudad', 'telefono', 'direccion', 'descripcion', 'lat', 'lon'] as $k) {
            $d[$k] = $d[$k] !== '' ? $d[$k] : null;
        }
        $this->svc()->actualizar($this->club['id'], $d);
        $this->ctx->redirect('/app/club/ajustes', $this->i18n->t('evento.guardado'));
    }
}
