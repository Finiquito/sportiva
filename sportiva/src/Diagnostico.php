<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Registro de errores propio (el 500 del núcleo no dice nada): storage/sportiva_uploads/errores.log, visible en el admin. */
final class Diagnostico
{
    public static function archivo(): string
    {
        return Almacen::asegurar() . '/errores.log';
    }

    /** Ejecuta un handler de /app; si falla, anota el error y lo vuelve a lanzar. */
    public static function correr(callable $h, array $args): mixed
    {
        try {
            return $h(...$args);
        } catch (\Throwable $e) {
            if (!str_contains($e::class, 'Redirect')) {
                self::anotar($e);
            }
            throw $e;
        }
    }

    public static function anotar(\Throwable $e): void
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $linea = date('Y-m-d H:i:s') . ' ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . $uri . "\n"
            . $e::class . ': ' . $e->getMessage() . "\n  en " . $e->getFile() . ':' . $e->getLine() . "\n"
            . '  ' . str_replace("\n", "\n  ", substr($e->getTraceAsString(), 0, 1500)) . "\n\n";
        @file_put_contents(self::archivo(), $linea, FILE_APPEND | LOCK_EX);
        error_log('[sportiva] ' . $e::class . ': ' . $e->getMessage());
    }

    public static function leer(): string
    {
        $f = self::archivo();
        if (!is_file($f)) {
            return '';
        }
        $t = (string) file_get_contents($f);
        return strlen($t) > 12000 ? '…' . substr($t, -12000) : $t;
    }

    public static function vaciar(): void
    {
        @unlink(self::archivo());
    }
}
