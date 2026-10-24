<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Fechas: todo se guarda en hora local del club ('Y-m-d H:i:s'); aquí se calculan y se muestran. */
final class Tiempo
{
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

    /** «Sáb 24 oct» según el idioma (los nombres salen del archivo de idioma). */
    public static function diaCorto(string $fecha, I18n $i): string
    {
        $d = new \DateTimeImmutable($fecha);
        return $i->lista('fmt.dias_cortos')[(int) $d->format('w')] . ' ' . $d->format('j') . ' ' . $i->lista('fmt.meses_cortos')[(int) $d->format('n') - 1];
    }

    /** «Sábado 24 de octubre». */
    public static function diaLargo(string $fecha, I18n $i): string
    {
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
        return $fecha ? (new \DateTimeImmutable($fecha))->format('H:i') : '';
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
