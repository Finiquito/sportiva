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

    /** Ícono de balón del deporte del club (el de básquet es la cancha hasta tener el balón). */
    public static function balon(?string $deporte): string
    {
        return self::propio(['voley' => 'deporte-voley', 'basquet' => 'cancha-basquet', 'voley-playa' => 'deporte-voley-playa'][(string) $deporte] ?? 'deporte-futbol');
    }

    public static function cancha(?string $deporte): string
    {
        return self::propio(['voley' => 'cancha-voley', 'basquet' => 'cancha-basquet'][(string) $deporte] ?? 'cancha-futbol');
    }

    /** Avatar de un jugador: el elegido (1 a 5) o uno fijo según su id. */
    public static function avatar(array $j, string $clase = ''): string
    {
        $n = (int) ($j['avatar'] ?? 0);
        if ($n < 1 || $n > 5) {
            $n = (crc32((string) ($j['id'] ?? $j['nombre'] ?? '')) % 5) + 1;
        }
        return '<span class="avatar foto-av ' . $clase . '"><img src="/plugins/sportiva/assets/avatares/av' . $n . '.jpg" alt=""></span>';
    }

    /** Logo u330 en SVG en línea: `favicon-verde` (círculo del header), `principal-verde` (sobre fondo verde). */
    public static function logo(string $variante = 'favicon-verde'): string
    {
        $f = dirname(__DIR__) . '/assets/marca/logo-' . preg_replace('/[^a-z-]/', '', $variante) . '.svg';
        if (!is_file($f)) {
            return '';
        }
        $s = (string) file_get_contents($f);
        $s = (string) preg_replace(['/<\?xml.*?\?>\s*/s', '/<!--.*?-->\s*/s'], '', $s);
        $cols = [];
        if (preg_match_all('/\.(st\d)\s*\{\s*fill:\s*(#\w+)/', $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $c) {
                $cols[$c[1]] = $c[2];
            }
        }
        $s = (string) preg_replace('/<defs>.*?<\/defs>/s', '', $s);
        $s = (string) preg_replace_callback('/class="(st\d)"/', fn(array $x) => 'fill="' . ($cols[$x[1]] ?? '#000') . '"', $s);
        return (string) preg_replace('/<svg [^>]*viewBox="([^"]+)"[^>]*>/', '<svg viewBox="$1" role="img" aria-label="u330">', $s, 1);
    }
}
