<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Carpeta de archivos propios, fuera del web-root: typedock/storage/sportiva_uploads/ (en dev: dev/storage). */
final class Almacen
{
    public static function dir(string $sub = ''): string
    {
        $base = defined('SPORTIVA_STORAGE_DIR') ? (string) constant('SPORTIVA_STORAGE_DIR') : dirname(__DIR__, 3) . '/storage/sportiva_uploads';
        $d = $base . ($sub !== '' ? '/' . trim($sub, '/') : '');
        return $d;
    }

    public static function asegurar(string $sub = ''): string
    {
        $d = self::dir($sub);
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }
}
