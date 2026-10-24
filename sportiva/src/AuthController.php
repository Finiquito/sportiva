<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Entrar con correo + código, elegir club/rol, cuenta (Más) e idioma. */
final class AuthController extends AppBase
{
    /** Idioma de las pantallas sin sesión: ?lang=es lo cambia y queda en la sesión. */
    private function idiomaPublico(): void
    {
        $l = (string) ($_GET['lang'] ?? '');
        if ($l !== '' && I18n::esValido($l)) {
            Sesion::idioma($l);
        }
        $this->i18n = new I18n(Sesion::idioma() ?: 'fr');
    }

    public function landing(): void
    {
        $this->idiomaPublico();
        if (Sesion::persona()) {
            $this->ir('/app/agenda');
            return;
        }
        echo $this->vista()->pagina('clubes.latte', ['clubes' => (new ClubService($this->pdo))->publicos()]);
    }

    public function clubPublico(string $slug): void
    {
        $this->idiomaPublico();
        $club = (new ClubService($this->pdo))->porSlug($slug);
        if ($club === null || !(int) $club['publico']) {
            \Flight::notFound();
            return;
        }
        echo $this->vista()->pagina('club-publico.latte', ['c' => $club, 'equipos' => (new ClubService($this->pdo))->equipos($club['id'])]);
    }

    public function entrar(): void
    {
        $this->idiomaPublico();
        echo $this->vista()->pagina('entrar.latte', ['error' => null, 'correo' => '', 'sin' => isset($_GET['sin'])]);
    }

    public function pedirCodigo(): void
    {
        $this->idiomaPublico();
        $correo = PersonaService::correo((string) ($_POST['correo'] ?? ''));
        $v = $this->vista();
        if (!$this->csrfOk() || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo $v->pagina('entrar.latte', ['error' => 'entrar.error.correo', 'correo' => $correo, 'sin' => false]);
            return;
        }
        $personas = new PersonaService($this->pdo);
        $p = $personas->porCorreo($correo);
        // Misma respuesta exista o no la cuenta (no revela quién está en el club).
        if ($p !== null && $personas->membresias($p['id']) !== []) {
            $codigos = new CodigoAcceso($this->pdo);
            if (!$codigos->demasiadosCodigos($p['id'])) {
                $codigo = $codigos->generar($p['id']);
                $i = new I18n($p['idioma'] ?: ($this->i18n->idioma()));
                (new Correo($this->ctx, $this->pdo))->codigoAcceso($correo, $p['nombre'], $codigo, $i);
            }
        }
        Sesion::pendiente($correo);
        $this->ir('/app/codigo');
    }

    public function codigo(): void
    {
        $this->idiomaPublico();
        $c = Sesion::pendiente();
        if ($c === null) {
            $this->ir('/app/entrar');
            return;
        }
        echo $this->vista()->pagina('codigo.latte', ['correo' => $c, 'error' => null, 'oculto' => self::ocultar($c)]);
    }

    public function verificar(): void
    {
        $this->idiomaPublico();
        $correo = Sesion::pendiente();
        if ($correo === null) {
            $this->ir('/app/entrar');
            return;
        }
        $personas = new PersonaService($this->pdo);
        $p = $personas->porCorreo($correo);
        $ok = $this->csrfOk() && $p !== null && (new CodigoAcceso($this->pdo))->verificar($p['id'], (string) ($_POST['codigo'] ?? ''));
        if (!$ok) {
            echo $this->vista()->pagina('codigo.latte', ['correo' => $correo, 'error' => 'codigo.error', 'oculto' => self::ocultar($correo)]);
            return;
        }
        Sesion::entrar($p['id']);
        $this->ir('/app/agenda');
    }

    public static function ocultar(string $correo): string
    {
        [$u, $d] = array_pad(explode('@', $correo, 2), 2, '');
        return mb_substr($u, 0, 2) . '•••@' . $d;
    }

    public function elegir(): void
    {
        $r = $this->exigirSoloPersona();
        if ($r !== null) {
            $this->ir($r);
            return;
        }
        $mem = (new PersonaService($this->pdo))->membresias($this->persona['id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (string) ($_POST['membresia'] ?? '');
            foreach ($mem as $m) {
                if ($m['id'] === $id && $this->csrfOk()) {
                    Sesion::elegir($m['club_id'], $m['rol']);
                    $this->ir('/app/agenda');
                    return;
                }
            }
        }
        $this->i18n = new I18n($this->persona['idioma'] ?: 'fr');
        echo $this->vista()->pagina('elegir.latte', ['mem' => $mem]);
    }

    private function exigirSoloPersona(): ?string
    {
        $pid = Sesion::persona();
        $this->persona = $pid ? (new PersonaService($this->pdo))->buscar($pid) : null;
        return $this->persona === null ? '/app/entrar' : null;
    }

    public function salir(): void
    {
        Sesion::salir();
        $this->ir('/app/entrar');
    }

    /** Más: solo cuenta (perfil, idioma, clubes, salir). */
    public function mas(): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $mem = (new PersonaService($this->pdo))->membresias($this->persona['id']);
        echo $this->vista()->pagina('mas.latte', ['activa' => 'mas', 'mem' => $mem, 'idiomas' => I18n::disponibles()]);
    }

    public function cambiarIdioma(): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $l = (string) ($_POST['idioma'] ?? '');
        if ($this->csrfOk() && I18n::esValido($l)) {
            (new PersonaService($this->pdo))->cambiarIdioma($this->persona['id'], $l);
            Sesion::idioma($l);
        }
        $this->ir('/app/mas');
    }

    /** Secciones que llegan en versiones siguientes. */
    public function pronto(string $seccion): void
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return;
        }
        $validas = array_column(Vista::TABS[$this->rol] ?? [], 0);
        if (!in_array($seccion, $validas, true)) {
            \Flight::notFound();
            return;
        }
        echo $this->vista()->pagina('pronto.latte', ['activa' => $seccion, 'seccion' => $seccion]);
    }
}
