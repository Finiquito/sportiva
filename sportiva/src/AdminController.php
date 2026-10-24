<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

use TypeDock\Core\PluginContext;

/** /admin/sportiva — solo para el admin técnico: clubes, equipos, personas y correo (SMTP). */
final class AdminController
{
    public function __construct(private readonly PluginContext $ctx) {}

    private function pdo(): \PDO
    {
        return $this->ctx->db()->pdo();
    }

    private function ver(string $tpl, array $d = []): void
    {
        $this->ctx->view('templates/admin/' . $tpl, $d + [
            'flash_success' => $this->ctx->getFlash('success'), 'flash_error' => $this->ctx->getFlash('error'),
            'idiomas' => I18n::disponibles(),
        ]);
    }

    private function base(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        return $host === '' ? '' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $host);
    }

    public function medios(): void
    {
        $m = new MedioService($this->pdo());
        $this->ver('medios.latte', ['lim' => $m->limites(), 'uso' => $m->usoPorEquipo(), 'csrf' => Sesion::csrf(), 'gd' => FotoService::disponible()]);
    }

    public function guardarMedios(): void
    {
        (new MedioService($this->pdo()))->guardarLimites($_POST);
        $this->ctx->redirect($this->ctx->adminUrl('medios'), 'Límites guardados.');
    }

    public function avisos(): void
    {
        $nt = new Notificador($this->ctx, $this->pdo());
        $av = new AvisoService($this->pdo());
        $this->ver('avisos.latte', [
            'push' => WebPush::disponible(), 'claves' => WebPush::disponible() && (new Ajustes($this->pdo()))->get('vapid_publica') !== '',
            'subs' => (int) $this->pdo()->query('SELECT COUNT(*) FROM sportiva_push_subs')->fetchColumn(), 'cola' => $av->cantidadEnCola(),
            'cron' => $this->base() . '/app/cron/' . $nt->tokenCron(), 'csrf' => Sesion::csrf(), 'curl' => function_exists('curl_init'),
        ]);
    }

    public function accionAvisos(): void
    {
        $nt = new Notificador($this->ctx, $this->pdo());
        $a = (string) ($_POST['accion'] ?? '');
        if ($a === 'claves') {
            $nt->claves(true);
            $this->ctx->redirect($this->ctx->adminUrl('avisos'), 'Claves nuevas creadas. Cada persona debe volver a activar los avisos en su dispositivo.');
            return;
        }
        if ($a === 'procesar') {
            $r = $nt->recordatorios();
            $e = $nt->entregar(100);
            $mt = $nt->mantenimiento();
            $this->ctx->redirect($this->ctx->adminUrl('avisos'), "Listo: {$r} recordatorios, {$e['push']} avisos push y {$e['correo']} correos enviados, {$mt['borrados']} videos vencidos borrados.");
            return;
        }
        $this->ctx->redirect($this->ctx->adminUrl('avisos'));
    }

    public function diagnostico(): void
    {
        $this->ver('diagnostico.latte', ['log' => Diagnostico::leer(), 'version' => (json_decode((string) @file_get_contents(dirname(__DIR__) . '/plugin.json'), true)['version'] ?? '?'), 'php' => PHP_VERSION]);
    }

    public function vaciarDiagnostico(): void
    {
        Diagnostico::vaciar();
        $this->ctx->redirect($this->ctx->adminUrl('diagnostico'), 'Registro vaciado.');
    }

    public function index(): void
    {
        $this->ver('index.latte', ['clubes' => (new ClubService($this->pdo()))->todos()]);
    }

    public function crear(): void
    {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '') {
            $this->ctx->redirect($this->ctx->adminUrl(''), 'Falta el nombre del club.', 'error');
            return;
        }
        $id = (new ClubService($this->pdo()))->crear($_POST);
        $this->ctx->redirect($this->ctx->adminUrl('clubes/' . $id), 'Club creado.');
    }

    public function editar(string $id): void
    {
        $svc = new ClubService($this->pdo());
        $club = $svc->buscar($id);
        if ($club === null) {
            $this->ctx->redirect($this->ctx->adminUrl(''), 'No existe ese club.', 'error');
            return;
        }
        $pdo = $this->pdo();
        $personas = $pdo->prepare('SELECT p.id, p.nombre, p.email, m.rol FROM sportiva_membresias m JOIN sportiva_personas p ON p.id = m.persona_id WHERE m.club_id = ? ORDER BY m.rol, p.nombre');
        $personas->execute([$id]);
        $this->ver('club.latte', ['club' => $club, 'equipos' => $svc->equipos($id), 'personas' => $personas->fetchAll(), 'roles' => PersonaService::ROLES]);
    }

    public function actualizar(string $id): void
    {
        $svc = new ClubService($this->pdo());
        if ($svc->buscar($id) === null || trim((string) ($_POST['nombre'] ?? '')) === '') {
            $this->ctx->redirect($this->ctx->adminUrl(''), 'No se pudo guardar.', 'error');
            return;
        }
        $d = $_POST;
        $d['publico'] = !empty($_POST['publico']) ? 1 : 0;
        $d['exigir_app'] = !empty($_POST['exigir_app']) ? 1 : 0;
        $svc->actualizar($id, $d);
        $this->ctx->redirect($this->ctx->adminUrl('clubes/' . $id), 'Guardado.');
    }

    public function equipo(string $id): void
    {
        $n = trim((string) ($_POST['nombre'] ?? ''));
        if ($n !== '' && (new ClubService($this->pdo()))->buscar($id) !== null) {
            (new ClubService($this->pdo()))->crearEquipo($id, $n, trim((string) ($_POST['temporada'] ?? '')));
        }
        $this->ctx->redirect($this->ctx->adminUrl('clubes/' . $id), 'Equipo agregado.');
    }

    /**
     * Agrega una persona con su rol. Para apoderados: «hijos» = una línea por hijo, «Nombre; AAAA-MM-DD».
     * Para entrenador/encargado: equipo opcional (staff).
     */
    public function persona(string $id): void
    {
        $club = (new ClubService($this->pdo()))->buscar($id);
        $svc = new PersonaService($this->pdo());
        $email = PersonaService::correo((string) ($_POST['email'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $rol = (string) ($_POST['rol'] ?? '');
        if ($club === null || !filter_var($email, FILTER_VALIDATE_EMAIL) || $nombre === '' || !in_array($rol, PersonaService::ROLES, true)) {
            $this->ctx->redirect($this->ctx->adminUrl('clubes/' . $id), 'Revisa nombre, correo y rol.', 'error');
            return;
        }
        $p = $svc->porCorreo($email);
        $pid = $p['id'] ?? $svc->crear($email, $nombre, $club['idioma']);
        $svc->dar($pid, $id, $rol);
        $equipo = (string) ($_POST['equipo_id'] ?? '');
        if ($equipo !== '' && in_array($rol, ['entrenador', 'encargado'], true)) {
            $svc->asignarStaff($equipo, $pid, $rol);
        }
        if ($rol === 'apoderado' && $equipo !== '') {
            foreach (preg_split('/\R/', (string) ($_POST['hijos'] ?? '')) ?: [] as $linea) {
                [$n, $nac] = array_pad(array_map('trim', explode(';', $linea, 2)), 2, '');
                if ($n !== '') {
                    $jid = $svc->crearJugador($id, $equipo, $n, preg_match('/^\d{4}-\d{2}-\d{2}$/', $nac) ? $nac : null);
                    $svc->vincularHijo($pid, $jid);
                }
            }
        }
        $this->ctx->redirect($this->ctx->adminUrl('clubes/' . $id), 'Persona agregada.');
    }

    public function ajustes(): void
    {
        $a = new Ajustes($this->pdo());
        $s = $a->smtp();
        $s['tiene_clave'] = $s['clave'] !== '';
        unset($s['clave']);
        $this->ver('ajustes.latte', ['s' => $s]);
    }

    public function guardarAjustes(): void
    {
        $a = new Ajustes($this->pdo());
        foreach (['smtp_host', 'smtp_usuario', 'smtp_remitente', 'smtp_nombre'] as $k) {
            $a->set($k, trim((string) ($_POST[$k] ?? '')));
        }
        $a->set('smtp_puerto', (string) (int) ($_POST['smtp_puerto'] ?? 587));
        $seg = (string) ($_POST['smtp_seguridad'] ?? 'tls');
        $a->set('smtp_seguridad', in_array($seg, ['ssl', 'tls', 'ninguna'], true) ? $seg : 'tls');
        if (($_POST['smtp_clave'] ?? '') !== '') {
            $a->guardarSecreto('smtp_clave', (string) $_POST['smtp_clave']);
        }
        if (!empty($_POST['probar'])) {
            $s = $a->smtp();
            try {
                (new SmtpCliente($s['host'], $s['puerto'], $s['seguridad'], $s['usuario'], $s['clave']))->probar();
                $this->ctx->redirect($this->ctx->adminUrl('ajustes'), 'Conexión correcta con el servidor de correo.');
            } catch (\RuntimeException $e) {
                if ($e instanceof \TypeDock\Core\RedirectException) {
                    throw $e;
                }
                $this->ctx->redirect($this->ctx->adminUrl('ajustes'), 'No se pudo conectar: ' . $e->getMessage(), 'error');
            }
            return;
        }
        $this->ctx->redirect($this->ctx->adminUrl('ajustes'), 'Ajustes guardados.');
    }
}
