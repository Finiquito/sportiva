<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Íconos en línea: Phosphor (MIT; bold/fill/duotone) y los propios del club (SVG con currentColor). */
final class Iconos
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $ph = null;
    /** @var array<string, string>|null */
    private static ?array $bb = null;

    public static function svg(string $nombre, string $peso = 'bold'): string
    {
        self::$ph ??= json_decode((string) file_get_contents(dirname(__DIR__) . '/assets/iconos.json'), true) ?: [];
        $inner = self::$ph[$nombre][$peso] ?? self::$ph[$nombre]['bold'] ?? '';
        return '<svg class="pi" viewBox="0 0 256 256" aria-hidden="true">' . $inner . '</svg>';
    }

    public static function propio(string $nombre): string
    {
        self::$bb ??= json_decode((string) file_get_contents(dirname(__DIR__) . '/assets/propios/bbox.json'), true) ?: [];
        $f = dirname(__DIR__) . '/assets/propios/' . preg_replace('/[^a-z-]/', '', $nombre) . '.svg';
        if (!is_file($f) || !isset(self::$bb[$nombre])) {
            return '';
        }
        return (string) preg_replace('/<svg[^>]*>/', '<svg class="pi pw" viewBox="' . self::$bb[$nombre] . '" fill="currentColor" aria-hidden="true">', (string) file_get_contents($f), 1);
    }

    /** «S» del logo (color con CSS: fill). */
    public static function logo(string $relleno = 'var(--limon)'): string
    {
        return '<svg viewBox="205 453 326 404" aria-hidden="true" style="fill:' . $relleno . '"><path d="M305 458 L322 459 C328 459 330 465 331 476 C340 560 400 620 480 684 C508 702 527 722 527 745 C527 800 480 850 430 850 C421 850 417 844 415 832 C405 760 385 700 325 652 C285 626 212 590 210 548 C215 500 260 458 305 458 Z"/><path d="M385 458 L415 457 C490 458 527 510 527 560 L527 636 C527 644 520 647 510 646 C440 622 372 555 369 488 C369 465 375 458 385 458 Z"/><path d="M226 657 C218 657 209 660 209 678 L209 712 C209 790 255 851 315 851 L352 852 C366 852 371 846 370 826 C362 750 320 690 245 660 C238 658 232 657 226 657 Z"/></svg>';
    }
}
