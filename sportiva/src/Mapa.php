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
    /** Si las teselas principales no cargan (bloqueo, caída), se prueba con estas. */
    public const RESPALDO = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const ATRIBUCION = 'Esri · © OpenStreetMap';

    public static function valido(?string $lat, ?string $lon): bool
    {
        return $lat !== null && $lon !== null && is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180;
    }

    public static function html(string $lat, string $lon, int $zoom = 16, string $etiqueta = '', ?I18n $i = null): string
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
                $rep = [(string) $zoom, (string) ($tx0 + $c), (string) ($ty0 + $r)];
                $u = str_replace(['{z}', '{x}', '{y}'], $rep, self::TESELAS);
                $f = str_replace(['{z}', '{x}', '{y}'], $rep, self::RESPALDO);
                $imgs .= '<img src="' . htmlspecialchars($u, ENT_QUOTES) . '" data-f="' . htmlspecialchars($f, ENT_QUOTES) . '" onerror="this.onerror=null;this.src=this.dataset.f" alt="" width="256" height="256" loading="lazy">';
            }
        }
        $ox = $px - $tx0 * 256;
        $oy = $py - $ty0 * 256;
        $en = self::enlaces($lat, $lon);
        $boton = '<button type="button" class="mapa-amp" data-mapa-full data-lat="' . htmlspecialchars($lat, ENT_QUOTES) . '" data-lon="' . htmlspecialchars($lon, ENT_QUOTES) . '" data-nombre="' . htmlspecialchars($etiqueta, ENT_QUOTES)
            . '" data-google="' . htmlspecialchars($en['google'], ENT_QUOTES) . '" data-waze="' . htmlspecialchars($en['waze'], ENT_QUOTES)
            . '" data-t1="' . htmlspecialchars(self::TESELAS, ENT_QUOTES) . '" data-t2="' . htmlspecialchars(self::RESPALDO, ENT_QUOTES)
            . '" data-pin="' . htmlspecialchars(Iconos::svg('map-pin', 'fill'), ENT_QUOTES)
            . '" data-mi="' . htmlspecialchars($i ? $i->t('mapa.mi_ubicacion') : 'Mi ubicación', ENT_QUOTES) . '" data-mi-error="' . htmlspecialchars($i ? $i->t('mapa.sin_ubicacion') : 'No pude obtener tu ubicación', ENT_QUOTES)
            . '" data-yo-ico="' . htmlspecialchars(Iconos::svg('users-three', 'fill'), ENT_QUOTES)
            . '" data-est="' . htmlspecialchars($i ? $i->t('mapa.estimado') : 'En auto ≈ {min} min · {km} km · llegas ~{hora}', ENT_QUOTES)
            . '" data-est-aprox="' . htmlspecialchars($i ? $i->t('mapa.estimado_aprox') : 'En auto ≈ {min} min (aprox.) · {km} km', ENT_QUOTES)
            . '" data-cerrar="' . htmlspecialchars($i ? $i->t('mapa.cerrar') : 'Cerrar', ENT_QUOTES) . '" aria-label="' . htmlspecialchars($i ? $i->t('mapa.ampliar') : 'Ampliar', ENT_QUOTES) . '">'
            . '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . '<span>' . htmlspecialchars($i ? $i->t('mapa.ampliar') : 'Ampliar', ENT_QUOTES) . '</span></button>';
        return sprintf(
            '<div class="mapa" role="img" aria-label="%s"><div class="tiles" style="width:%dpx;height:%dpx;transform:translate(-%.1fpx,-%.1fpx)">%s</div><span class="pin">%s</span><span class="osm">%s</span>%s</div>',
            htmlspecialchars($etiqueta, ENT_QUOTES), $cols * 256, $rows * 256, $ox, $oy, $imgs, Iconos::svg('map-pin', 'fill'), self::ATRIBUCION, $boton
        );
    }

    /** @return array{google:string,waze:string} */
    public static function enlaces(string $lat, string $lon): array
    {
        $q = rawurlencode($lat) . ',' . rawurlencode($lon);
        return ['google' => 'https://www.google.com/maps/search/?api=1&query=' . $q, 'waze' => 'https://waze.com/ul?ll=' . $q . '&navigate=yes'];
    }

    /** Enlaces para llegar a un lugar: con coordenadas si las tiene; si no, buscando por nombre y dirección. @param array<string, mixed> $l @return array{google: string, waze: string}|null */
    public static function enlacesLugar(array $l): ?array
    {
        $lat = isset($l['lugar_lat']) ? $l['lugar_lat'] : ($l['lat'] ?? null);
        $lon = isset($l['lugar_lon']) ? $l['lugar_lon'] : ($l['lon'] ?? null);
        if (self::valido($lat !== null ? (string) $lat : null, $lon !== null ? (string) $lon : null)) {
            return self::enlaces((string) $lat, (string) $lon);
        }
        $q = trim(((string) ($l['lugar'] ?? $l['nombre'] ?? '')) . ' ' . ((string) ($l['lugar_direccion'] ?? $l['direccion'] ?? '')));
        if ($q === '') {
            return null;
        }
        return ['google' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q), 'waze' => 'https://waze.com/ul?q=' . rawurlencode($q) . '&navigate=yes'];
    }

    /** Saca latitud y longitud de un enlace largo de Google Maps u OpenStreetMap (o de «lat, lon» pegado). @return array{0: string, 1: string}|null */
    public static function coordenadasDeEnlace(string $t): ?array
    {
        $t = urldecode(trim($t));
        foreach (['/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/', '/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', '/[?&](?:q|ll|query|destination)=(-?\d{1,2}\.\d+)[, ](-?\d{1,3}\.\d+)/', '/mlat=(-?\d{1,2}\.\d+)&mlon=(-?\d{1,3}\.\d+)/', '/^\s*(-?\d{1,2}\.\d+)\s*[,;]\s*(-?\d{1,3}\.\d+)\s*$/'] as $re) {
            if (preg_match($re, $t, $m) && self::valido($m[1], $m[2])) {
                return [$m[1], $m[2]];
            }
        }
        return null;
    }

    /** Texto para compartir un evento por WhatsApp u otra app. @param array<string, mixed> $e */
    public static function textoCompartir(array $e, I18n $i, string $urlEvento): string
    {
        $l = [$i->t('compartir.' . ($e['tipo'] === 'partido' ? 'partido' : 'evento'), ['titulo' => $e['titulo'], 'equipo' => $e['equipo']])];
        $l[] = Tiempo::diaLargo($e['inicio'], $i);
        if ($e['tipo'] === 'partido') {
            $h = [];
            if (!empty($e['hora_encuentro'])) {
                $h[] = $i->t('hora.encuentro') . ' ' . Tiempo::hora($e['hora_encuentro']);
            }
            $h[] = $i->t('hora.cancha') . ' ' . Tiempo::hora($e['hora_cancha'] ?: $e['inicio']);
            $h[] = $i->t('hora.partido') . ' ' . Tiempo::hora($e['inicio']);
            $l[] = implode(' · ', $h);
        } else {
            $l[] = Tiempo::hora($e['inicio']) . (!empty($e['fin']) ? ' – ' . Tiempo::hora($e['fin']) : '');
        }
        if (!empty($e['lugar'])) {
            $l[] = '📍 ' . $e['lugar'] . (!empty($e['lugar_direccion']) ? ', ' . $e['lugar_direccion'] : '');
            if (($en = self::enlacesLugar($e)) !== null) {
                $l[] = $en['google'];
            }
        }
        $l[] = $urlEvento;
        return implode("\n", $l);
    }
}
