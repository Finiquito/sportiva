<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Web Push sin librerías externas: VAPID (RFC 8292) y cifrado aes128gcm (RFC 8188 / 8291) con OpenSSL.
 * Funciona en hosting compartido sin Composer.
 */
final class WebPush
{
    public static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function deb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    public static function disponible(): bool
    {
        return function_exists('openssl_pkey_derive') && function_exists('hash_hkdf') && function_exists('openssl_sign');
    }

    /** Par de claves VAPID nuevo: [pem privada, pública sin comprimir en base64url]. @return array{0: string, 1: string} */
    public static function generarClaves(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($k === false) {
            throw new \RuntimeException('OpenSSL no pudo crear la clave.');
        }
        openssl_pkey_export($k, $pem);
        return [$pem, self::b64(self::puntoPublico($k))];
    }

    /** Punto público sin comprimir (65 bytes) de una clave EC P-256. */
    public static function puntoPublico(\OpenSSLAsymmetricKey $k): string
    {
        $d = openssl_pkey_get_details($k)['ec'];
        return "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** Clave pública (PEM) desde un punto sin comprimir. */
    private static function pemDePunto(string $punto): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $punto;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Firma ES256 en formato crudo r||s (64 bytes). */
    private static function firmaCruda(string $datos, string $pemPrivada): string
    {
        openssl_sign($datos, $der, $pemPrivada, OPENSSL_ALGO_SHA256);
        $o = 2;
        $len = ord($der[1]);
        if ($len & 0x80) {
            $o += $len & 0x7f;
        }
        $rl = ord($der[$o + 1]);
        $r = substr($der, $o + 2, $rl);
        $sl = ord($der[$o + 2 + $rl + 1]);
        $s = substr($der, $o + 2 + $rl + 2, $sl);
        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    /** JWT de VAPID para el origen del endpoint. */
    public static function jwt(string $endpoint, string $sub, string $pemPrivada, ?int $ahora = null): string
    {
        $p = parse_url($endpoint);
        $aud = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
        $cab = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $cuerpo = self::b64(json_encode(['aud' => $aud, 'exp' => ($ahora ?? time()) + 12 * 3600, 'sub' => $sub]));
        return $cab . '.' . $cuerpo . '.' . self::b64(self::firmaCruda($cab . '.' . $cuerpo, $pemPrivada));
    }

    /**
     * Cifra el mensaje para una suscripción (aes128gcm). @return string cuerpo de la petición
     * @param string $uaPublica punto público del navegador (65 bytes) @param string $auth secreto de autenticación (16 bytes)
     */
    public static function cifrar(string $mensaje, string $uaPublica, string $auth): string
    {
        $efimera = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asPublica = self::puntoPublico($efimera);
        $compartido = openssl_pkey_derive(self::pemDePunto($uaPublica), $efimera, 32);
        if ($compartido === false) {
            throw new \RuntimeException('No se pudo derivar la clave compartida.');
        }
        $ikm = hash_hkdf('sha256', $compartido, 32, "WebPush: info\0" . $uaPublica . $asPublica, $auth);
        $sal = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sal);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sal);
        $tag = '';
        $cifrado = openssl_encrypt($mensaje . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        return $sal . pack('N', 4096) . chr(65) . $asPublica . $cifrado . $tag;
    }

    /**
     * Envía un aviso. @param array{endpoint: string, p256dh: string, auth: string} $sub claves en base64url
     * @return int código HTTP (201 = entregado; 404/410 = suscripción caducada; 0 = sin conexión)
     */
    public static function enviar(array $sub, string $mensaje, string $pemPrivada, string $publicaVapid, string $contacto): int
    {
        $cuerpo = self::cifrar($mensaje, self::deb64($sub['p256dh']), self::deb64($sub['auth']));
        $cab = [
            'Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: normal',
            'Authorization: vapid t=' . self::jwt($sub['endpoint'], $contacto, $pemPrivada) . ', k=' . $publicaVapid,
        ];
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $cab), 'content' => $cuerpo, 'timeout' => 10, 'ignore_errors' => true]]);
            @file_get_contents($sub['endpoint'], false, $ctx);
            return (int) (preg_match('/ (\d{3}) /', (string) ($http_response_header[0] ?? ''), $m) ? $m[1] : 0);
        }
        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_HTTPHEADER => $cab, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $code;
    }
}
