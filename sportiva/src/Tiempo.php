<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Fechas: todo se guarda en hora local del club ('Y-m-d H:i:s'); aquí se calculan y se muestran. */
final class Tiempo
{
    /** Marcadores de «por confirmar» (la columna `inicio` es obligatoria): fecha sin definir = día 2999-12-31; hora sin definir = 23:59:59. */
    public const SIN_FECHA = '2999-12-31';
    public const SIN_HORA = '23:59:59';

    public static function sinFecha(?string $fecha): bool
    {
        return $fecha !== null && str_starts_with($fecha, self::SIN_FECHA);
    }

    public static function sinHora(?string $fecha): bool
    {
        return $fecha !== null && str_ends_with($fecha, self::SIN_HORA);
    }

    /** «Sáb 24 oct · 15:00», o «Fecha por confirmar» / «Sáb 24 oct · Hora por confirmar». */
    public static function cuando(string $fecha, I18n $i): string
    {
        if (self::sinFecha($fecha)) {
            return $i->t('evento.conf.fecha');
        }
        return self::diaCorto($fecha, $i) . ' · ' . (self::sinHora($fecha) ? $i->t('evento.conf.hora') : self::hora($fecha));
    }

    /** Reloj inyectable para pruebas. */
    public static ?\DateTimeImmutable $ahora = null;

    public static function ahora(string $zona = 'Europe/Paris'): \DateTimeImmutable
    {
        $z = new \DateTimeZone($zona);
        return (self::$ahora ?? new \DateTimeImmutable('now', $z))->setTimezone($z);
    }

    public static function sello(string $zona = 'Europe/Paris'): string
    {
        return self::ahora($zona)->format('Y-m-d H:i:s');
    }

    /** Hora UTC para registros técnicos (códigos, cola). */
    public static function utc(): string
    {
        return (self::$ahora ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** «hace 2 h» para fechas de publicación (los registros se guardan con sello()). */
    public static function hace(string $sello, I18n $i): string
    {
        $seg = max(0, strtotime(self::sello() . ' UTC') - strtotime($sello . ' UTC'));
        if ($seg < 90) {
            return $i->t('tiempo.ahora');
        }
        if ($seg < 3600) {
            return $i->t('tiempo.min', ['n' => intdiv($seg, 60)]);
        }
        if ($seg < 86400) {
            return $i->t('tiempo.h', ['n' => intdiv($seg, 3600)]);
        }
        if ($seg < 7 * 86400) {
            return $i->t('tiempo.d', ['n' => intdiv($seg, 86400)]);
        }
        return self::diaCorto($sello, $i);
    }

    /** «Sáb 24 oct» según el idioma (los nombres salen del archivo de idioma). */
    public static function diaCorto(string $fecha, I18n $i): string
    {
        if (self::sinFecha($fecha)) {
            return $i->t('evento.conf.fecha');
        }
        $d = new \DateTimeImmutable($fecha);
        return $i->lista('fmt.dias_cortos')[(int) $d->format('w')] . ' ' . $d->format('j') . ' ' . $i->lista('fmt.meses_cortos')[(int) $d->format('n') - 1];
    }

    /** «Sábado 24 de octubre». */
    public static function diaLargo(string $fecha, I18n $i): string
    {
        if (self::sinFecha($fecha)) {
            return $i->t('evento.conf.fecha');
        }
        $d = new \DateTimeImmutable($fecha);
        $de = $i->t('fmt.de');
        return $i->lista('fmt.dias')[(int) $d->format('w')] . ' ' . $d->format('j') . ' ' . ($de !== '' ? $de . ' ' : '') . $i->lista('fmt.meses')[(int) $d->format('n') - 1];
    }

    /** «MAR 27 OCT» para los separadores de día. */
    public static function etiquetaDia(string $fecha, I18n $i): string
    {
        return mb_strtoupper(self::diaCorto($fecha, $i));
    }

    public static function hora(?string $fecha): string
    {
        return $fecha && !self::sinHora($fecha) ? (new \DateTimeImmutable($fecha))->format('H:i') : '';
    }

    /** Fecha sin hora (clave de agrupación). */
    public static function dia(string $fecha): string
    {
        return substr($fecha, 0, 10);
    }

    /** Edad en años cumplidos al día de hoy. */
    public static function edad(?string $nacimiento, string $zona = 'Europe/Paris'): ?int
    {
        if (!$nacimiento) {
            return null;
        }
        return (int) (new \DateTimeImmutable($nacimiento))->diff(self::ahora($zona))->y;
    }
}
