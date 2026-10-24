<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Sesión del front (/app): quién es, en qué club y con qué rol está actuando. */
final class Sesion
{
    public static function iniciar(): void
    {
        typedock_session_start();
    }

    public static function entrar(string $persona): void
    {
        self::iniciar();
        $_SESSION['sportiva_persona'] = $persona;
        unset($_SESSION['sportiva_club'], $_SESSION['sportiva_rol'], $_SESSION['sportiva_pendiente']);
    }

    public static function salir(): void
    {
        self::iniciar();
        foreach (['sportiva_persona', 'sportiva_club', 'sportiva_rol', 'sportiva_pendiente', 'sportiva_csrf'] as $k) {
            unset($_SESSION[$k]);
        }
    }

    public static function persona(): ?string
    {
        self::iniciar();
        return $_SESSION['sportiva_persona'] ?? null;
    }

    public static function elegir(string $club, string $rol): void
    {
        self::iniciar();
        $_SESSION['sportiva_club'] = $club;
        $_SESSION['sportiva_rol'] = $rol;
    }

    public static function club(): ?string
    {
        self::iniciar();
        return $_SESSION['sportiva_club'] ?? null;
    }

    public static function rol(): ?string
    {
        self::iniciar();
        return $_SESSION['sportiva_rol'] ?? null;
    }

    /** Correo que está pidiendo código (entre el paso 1 y el 2). */
    public static function pendiente(?string $v = null): ?string
    {
        self::iniciar();
        if ($v !== null) {
            $_SESSION['sportiva_pendiente'] = $v;
        }
        return $_SESSION['sportiva_pendiente'] ?? null;
    }

    public static function csrf(): string
    {
        self::iniciar();
        return $_SESSION['sportiva_csrf'] ??= bin2hex(random_bytes(16));
    }

    public static function csrfValido(?string $t): bool
    {
        return is_string($t) && $t !== '' && hash_equals(self::csrf(), $t);
    }

    public static function idioma(?string $v = null): ?string
    {
        self::iniciar();
        if ($v !== null) {
            $_SESSION['sportiva_idioma'] = $v;
        }
        return $_SESSION['sportiva_idioma'] ?? null;
    }
}
