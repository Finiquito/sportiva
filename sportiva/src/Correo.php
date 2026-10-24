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
            . '<p style="font-size:32px;letter-spacing:6px;font-weight:700;color:#215E5E">' . $codigo . '</p>'
            . '<p style="color:#4B5E5C;font-size:13px">' . $i->t('correo.codigo.pie') . '</p></div>';
        $texto = $i->t('correo.hola', ['nombre' => $nombre]) . "\n\n" . $i->t('correo.codigo.cuerpo') . "\n\n" . $codigo . "\n\n" . $i->t('correo.codigo.pie');
        return $this->enviar($para, $asunto, $html, $texto);
    }
}
