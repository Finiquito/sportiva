<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Sirve la hoja de estilos y los íconos de la app por PHP, sin pasar por la copia pública de assets del núcleo
 * (esa copia puede quedar vieja al actualizar el plugin). La URL lleva el hash del archivo, así que se cachea para siempre.
 */
final class RecursosController
{
    /** Archivos que se pueden pedir (sin extensión en la URL: algunos hostings atienden `.css` y `.png` por su cuenta): nombre → ruta en assets/. */
    private const ARCHIVOS = [
        'grano' => 'grano.png', 'favicon' => 'marca/favicon.svg', 'apple-touch-icon' => 'marca/apple-touch-icon.png',
        'icon-192' => 'marca/icon-192.png', 'icon-512' => 'marca/icon-512.png',
    ];
    private const TIPOS = ['png' => 'image/png', 'svg' => 'image/svg+xml'];

    public static function assets(): string
    {
        return dirname(__DIR__) . '/assets';
    }

    /** Hash corto de la hoja de estilos (cambia con cada versión). */
    public static function hashCss(): string
    {
        return substr((string) @md5_file(self::assets() . '/sportiva.css'), 0, 10);
    }

    public static function versionPlugin(): string
    {
        return (string) (json_decode((string) @file_get_contents(dirname(__DIR__) . '/plugin.json'), true)['version'] ?? '?');
    }

    /** Contenido de la CSS con las rutas de imágenes apuntando a /app/recurso. */
    public static function contenidoCss(): ?string
    {
        $c = @file_get_contents(self::assets() . '/sportiva.css');
        return $c === false ? null : str_replace('url("grano.png")', 'url("/app/recurso/grano")', $c);
    }

    /** @return array{ruta: string, mime: string}|null */
    public static function archivo(string $nombre): ?array
    {
        $rel = self::ARCHIVOS[$nombre] ?? null;
        $ruta = $rel !== null ? self::assets() . '/' . $rel : null;
        return $ruta !== null && is_file($ruta) ? ['ruta' => $ruta, 'mime' => self::TIPOS[pathinfo($ruta, PATHINFO_EXTENSION)] ?? 'application/octet-stream'] : null;
    }

    private function cabeceras(string $tipo, int $largo): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: ' . $tipo);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Content-Length: ' . $largo);
        }
    }

    public function css(): void
    {
        $c = self::contenidoCss();
        if ($c === null) {
            try {
                \Flight::response()->status(404);
            } catch (\Throwable) {
                http_response_code(404);
            }
            return;
        }
        $this->cabeceras('text/css; charset=utf-8', strlen($c));
        echo $c;
    }

    public function recurso(string $nombre): void
    {
        $a = self::archivo($nombre);
        if ($a === null) {
            try {
                \Flight::response()->status(404);
            } catch (\Throwable) {
                http_response_code(404);
            }
            return;
        }
        $this->cabeceras($a['mime'], (int) filesize($a['ruta']));
        readfile($a['ruta']);
    }
}
