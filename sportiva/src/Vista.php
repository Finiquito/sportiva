<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

use TypeDock\Core\PluginContext;

/** Dibuja plantillas Latte del front con lo común: textos, íconos, menú por rol y CSRF. */
final class Vista
{
    public const TABS = [
        'apoderado'  => [['agenda', 'calendar-blank'], ['feed', 'newspaper'], ['actividad', 'path'], ['mas', 'squares-four']],
        'entrenador' => [['agenda', 'calendar-blank'], ['feed', 'newspaper'], ['consultas', 'chat-circle-dots'], ['plantel', 'users-three'], ['mas', 'squares-four']],
        'encargado'  => [['agenda', 'calendar-blank'], ['feed', 'newspaper'], ['consultas', 'chat-circle-dots'], ['plantel', 'users-three'], ['mas', 'squares-four']],
        'directiva'  => [['feed', 'newspaper'], ['club', 'house'], ['directorio', 'user-circle'], ['mas', 'squares-four']],
        'jugador'    => [['agenda', 'calendar-blank'], ['feed', 'newspaper'], ['chat', 'chats-circle'], ['mas', 'squares-four']],
    ];

    public function __construct(private readonly PluginContext $ctx, public readonly I18n $i18n, private readonly ?array $club = null, private readonly ?string $rol = null, private readonly ?array $persona = null) {}

    /** @param array<string, mixed> $d */
    public function pagina(string $plantilla, array $d = []): string
    {
        $i = $this->i18n;
        $comunes = [
            't'       => fn(string $k, array $v = []) => $i->t($k, $v),
            'n'       => fn(string $k, int $n) => $i->n($k, $n),
            'lista'   => fn(string $k) => $i->lista($k),
            'ico'     => fn(string $n, string $p = 'bold') => Iconos::svg($n, $p),
            'propio'  => fn(string $n) => Iconos::propio($n),
            'logo'    => fn(string $v = 'favicon-verde') => Iconos::logo($v),
            'balon'   => fn() => Iconos::balon($this->club['deporte'] ?? null),
            'cancha'  => fn() => Iconos::cancha($this->club['deporte'] ?? null),
            'csrf'    => Sesion::csrf(),
            'lang'    => $i->idioma(),
            'club'    => $this->club,
            'rol'     => $this->rol,
            'persona' => $this->persona,
            'tabs'    => $this->rol ? self::TABS[$this->rol] ?? [] : [],
            'url'     => fn(string $p = '') => '/app' . ($p === '' ? '' : '/' . ltrim($p, '/')),
            'cssv'    => '3',
            'activa'  => null,
            'flash'   => $this->ctx->getFlash('success'),
            'dl'      => fn(string $f) => Tiempo::diaLargo($f, $i),
            'dc'      => fn(string $f) => Tiempo::diaCorto($f, $i),
            'ed'      => fn(string $f) => Tiempo::etiquetaDia($f, $i),
            'h'       => fn(?string $f) => Tiempo::hora($f),
            'edad'    => fn(?string $n) => Tiempo::edad($n),
        ];
        return $this->ctx->renderView('templates/app/' . $plantilla, $d + $comunes);
    }
}
