<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

use TypeDock\Core\PluginContext;

/**
 * Correos de Sportiva, a prueba de fallos (una request nunca se rompe por un mail).
 * Si hay SMTP propio configurado en el admin se usa (HTML + texto); si no, el correo del núcleo.
 */
final class Correo
{
    public function __construct(private readonly PluginContext $ctx, private readonly \PDO $pdo) {}

    public function enviar(string $para, string $asunto, string $html, string $texto): bool
    {
        $s = (new Ajustes($this->pdo))->smtp();
        try {
            if ($s['host'] !== '' && $s['remitente'] !== '') {
                (new SmtpCliente($s['host'], $s['puerto'], $s['seguridad'], $s['usuario'], $s['clave']))
                    ->enviar($s['remitente'], $s['nombre'], $para, $asunto, $html, $texto);
                return true;
            }
            return (bool) $this->ctx->mail()->send($para, $asunto, $html);
        } catch (\Throwable $e) {
            error_log('[sportiva] correo no enviado: ' . $e->getMessage());
            return false;
        }
    }

    public function codigoAcceso(string $para, string $nombre, string $codigo, I18n $i): bool
    {
        $nombre = htmlspecialchars($nombre, ENT_QUOTES);
        $asunto = $i->t('correo.codigo.asunto', ['codigo' => $codigo]);
        $html = '<div style="font-family:Arial,sans-serif;color:#10201F;max-width:480px">'
            . '<p>' . $i->t('correo.hola', ['nombre' => $nombre]) . '</p>'
            . '<p>' . $i->t('correo.codigo.cuerpo') . '</p>'
            . '<p style="font-size:32px;letter-spacing:6px;font-weight:700;color:#0F4F4E">' . $codigo . '</p>'
            . '<p style="color:#4B5E5C;font-size:13px">' . $i->t('correo.codigo.pie') . '</p></div>';
        $texto = $i->t('correo.hola', ['nombre' => $nombre]) . "\n\n" . $i->t('correo.codigo.cuerpo') . "\n\n" . $codigo . "\n\n" . $i->t('correo.codigo.pie');
        return $this->enviar($para, $asunto, $html, $texto);
    }

    /** Aviso a quien fue agregado a una familia del club (puede entrar con su correo, sin contraseña). */
    public function invitacion(string $para, string $nombre, string $club, string $quien, I18n $i): bool
    {
        $nombre = htmlspecialchars($nombre, ENT_QUOTES);
        $v = ['club' => htmlspecialchars($club, ENT_QUOTES), 'quien' => htmlspecialchars($quien, ENT_QUOTES)];
        $asunto = $i->t('correo.invitacion.asunto', ['club' => $club]);
        $html = '<div style="font-family:Arial,sans-serif;color:#10201F;max-width:480px">'
            . '<p>' . $i->t('correo.hola', ['nombre' => $nombre]) . '</p>'
            . '<p>' . $i->t('correo.invitacion.cuerpo', $v) . '</p>'
            . '<p><a href="' . htmlspecialchars($this->base() . '/app/entrar', ENT_QUOTES) . '" style="color:#0F4F4E;font-weight:700">' . $i->t('correo.invitacion.boton') . '</a></p>'
            . '<p style="color:#4B5E5C;font-size:13px">' . $i->t('correo.invitacion.pie') . '</p></div>';
        $texto = $i->t('correo.hola', ['nombre' => $nombre]) . "\n\n" . $i->t('correo.invitacion.cuerpo', $v) . "\n\n" . $this->base() . '/app/entrar' . "\n\n" . $i->t('correo.invitacion.pie');
        return $this->enviar($para, $asunto, $html, $texto);
    }

    /** Aviso a quien aprueba: hay una solicitud nueva de una familia. */
    public function solicitudNueva(string $para, string $nombre, string $familia, string $club, I18n $i): bool
    {
        $nombre = htmlspecialchars($nombre, ENT_QUOTES);
        $v = ['familia' => htmlspecialchars($familia, ENT_QUOTES), 'club' => htmlspecialchars($club, ENT_QUOTES)];
        $asunto = $i->t('correo.solicitud.asunto', $v);
        $url = $this->base() . '/app/plantel/solicitudes';
        $html = '<div style="font-family:Arial,sans-serif;color:#10201F;max-width:480px"><p>' . $i->t('correo.hola', ['nombre' => $nombre]) . '</p>'
            . '<p>' . $i->t('correo.solicitud.cuerpo', $v) . '</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '" style="color:#0F4F4E;font-weight:700">' . $i->t('correo.solicitud.boton') . '</a></p></div>';
        $texto = $i->t('correo.hola', ['nombre' => $nombre]) . "\n\n" . $i->t('correo.solicitud.cuerpo', $v) . "\n\n" . $url;
        return $this->enviar($para, $asunto, $html, $texto);
    }

    /** Aviso de consulta nueva o respuesta (sin el texto: el contenido es privado y se lee dentro de la app). */
    public function consulta(string $para, string $nombre, string $asunto, string $quien, string $ruta, I18n $i): bool
    {
        $nombre = htmlspecialchars($nombre, ENT_QUOTES);
        $v = ['quien' => htmlspecialchars($quien, ENT_QUOTES), 'asunto' => htmlspecialchars($asunto, ENT_QUOTES)];
        $url = $this->base() . $ruta;
        $html = '<div style="font-family:Arial,sans-serif;color:#10201F;max-width:480px"><p>' . $i->t('correo.hola', ['nombre' => $nombre]) . '</p>'
            . '<p>' . $i->t('correo.consulta.cuerpo', $v) . '</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '" style="color:#0F4F4E;font-weight:700">' . $i->t('correo.consulta.boton') . '</a></p></div>';
        $texto = $i->t('correo.hola', ['nombre' => $nombre]) . "\n\n" . $i->t('correo.consulta.cuerpo', $v) . "\n\n" . $url;
        return $this->enviar($para, $i->t('correo.consulta.asunto', $v), $html, $texto);
    }

    private function base(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        return $host === '' ? '' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $host);
    }
}
