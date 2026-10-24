<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Mapa estático de teselas centrado en un punto (sin JavaScript) + enlaces a Google Maps y Waze.
 * Las teselas salen de un proveedor raster configurable; en producción se cambiará por MapLibre +
 * OpenFreeMap o PMTiles propio (ver docs/PLAN.md §28). Atribución siempre visible.
 */
final class Mapa
{
    public const TESELAS = 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}';
    public const ATRIBUCION = 'Esri · © OpenStreetMap';

    public static function valido(?string $lat, ?string $lon): bool
    {
        return $lat !== null && $lon !== null && is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180;
    }

    public static function html(string $lat, string $lon, int $zoom = 16, string $etiqueta = ''): string
    {
        $la = (float) $lat;
        $lo = (float) $lon;
        $n = 2 ** $zoom;
        $px = ($lo + 180) / 360 * $n * 256;
        $py = (1 - log(tan(deg2rad($la)) + 1 / cos(deg2rad($la))) / M_PI) / 2 * $n * 256;
        $cols = 4;
        $rows = 3;
        $tx0 = (int) floor($px / 256) - intdiv($cols, 2);
        $ty0 = (int) floor($py / 256) - intdiv($rows, 2);
        $imgs = '';
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $u = str_replace(['{z}', '{x}', '{y}'], [(string) $zoom, (string) ($tx0 + $c), (string) ($ty0 + $r)], self::TESELAS);
                $imgs .= '<img src="' . htmlspecialchars($u, ENT_QUOTES) . '" alt="" width="256" height="256" loading="lazy">';
            }
        }
        $ox = $px - $tx0 * 256;
        $oy = $py - $ty0 * 256;
        return sprintf(
            '<div class="mapa" role="img" aria-label="%s"><div class="tiles" style="width:%dpx;height:%dpx;transform:translate(-%.1fpx,-%.1fpx)">%s</div><span class="pin">%s</span><span class="osm">%s</span></div>',
            htmlspecialchars($etiqueta, ENT_QUOTES), $cols * 256, $rows * 256, $ox, $oy, $imgs, Iconos::svg('map-pin', 'fill'), self::ATRIBUCION
        );
    }

    /** @return array{google:string,waze:string} */
    public static function enlaces(string $lat, string $lon): array
    {
        $q = rawurlencode($lat) . ',' . rawurlencode($lon);
        return ['google' => 'https://www.google.com/maps/search/?api=1&query=' . $q, 'waze' => 'https://waze.com/ul?ll=' . $q . '&navigate=yes'];
    }
}
