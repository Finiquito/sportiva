<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

use TypeDock\Core\PluginContext;

/**
 * Base de los controladores del front: resuelve quién está dentro (persona, club, rol),
 * el idioma y la vista. Las pantallas que exigen sesión llaman a exigir().
 */
abstract class AppBase
{
    protected \PDO $pdo;
    protected ?array $persona = null;
    protected ?array $club = null;
    protected ?string $rol = null;
    protected I18n $i18n;

    public function __construct(protected readonly PluginContext $ctx)
    {
        $this->pdo = $ctx->db()->pdo();
        $this->i18n = new I18n(Sesion::idioma() ?: 'fr');
    }

    /** Sesión lista (persona + club + rol) o null si hay que ir a entrar/elegir. Devuelve la URL de redirección. */
    protected function exigir(): ?string
    {
        $pid = Sesion::persona();
        $persona = $pid ? (new PersonaService($this->pdo))->buscar($pid) : null;
        if ($persona === null) {
            return '/app/entrar';
        }
        $this->persona = $persona;
        $mem = (new PersonaService($this->pdo))->membresias($persona['id']);
        if ($mem === []) {
            Sesion::salir();
            return '/app/entrar?sin=1';
        }
        $club = Sesion::club();
        $rol = Sesion::rol();
        $ok = array_values(array_filter($mem, fn($m) => $m['club_id'] === $club && $m['rol'] === $rol));
        if ($ok === []) {
            if (count($mem) === 1) {
                Sesion::elegir($mem[0]['club_id'], $mem[0]['rol']);
                $ok = [$mem[0]];
            } else {
                return '/app/elegir';
            }
        }
        $this->club = (new ClubService($this->pdo))->buscar($ok[0]['club_id']);
        $this->rol = $ok[0]['rol'];
        $this->i18n = new I18n($this->idiomaElegido());
        return null;
    }

    protected function idiomaElegido(): string
    {
        foreach ([Sesion::idioma(), $this->persona['idioma'] ?? null, $this->club['idioma'] ?? null] as $c) {
            if ($c && I18n::esValido($c)) {
                return $c;
            }
        }
        return 'fr';
    }

    protected function vista(): Vista
    {
        return new Vista($this->ctx, $this->i18n, $this->club, $this->rol, $this->persona);
    }

    protected function csrfOk(): bool
    {
        return Sesion::csrfValido($_POST['csrf'] ?? null);
    }

    protected function ir(string $url): void
    {
        $this->ctx->redirect($url);
    }

    /** Equipos que esta persona puede gestionar: entrenador = los suyos; encargado = todos los del club. @return array<int, array<string, mixed>> */
    protected function gestionables(): array
    {
        if ($this->rol === 'encargado') {
            return (new ClubService($this->pdo))->equipos($this->club['id']);
        }
        return (new PersonaService($this->pdo))->equiposDeStaff($this->persona['id'], $this->club['id']);
    }

    protected function exigirCoach(): ?string
    {
        if (($r = $this->exigir()) !== null) {
            return $r;
        }
        return self::esCoach($this->rol) ? null : '/app/agenda';
    }

    public static function esCoach(?string $rol): bool
    {
        return $rol === 'entrenador' || $rol === 'encargado';
    }
}
