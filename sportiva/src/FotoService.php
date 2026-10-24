<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Foto de cada jugador. Se valida el tipo real, se corrige la orientación, se recorta cuadrada y se vuelve a
 * codificar en JPEG de 320 px: así se pierden los datos EXIF (ubicación, cámara) y pesa poco.
 * El archivo vive en storage/sportiva_uploads/fotos/ (fuera del web-root) y se sirve solo a quien está en el club.
 */
final class FotoService extends Repo
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const LADO = 320;

    public static function ruta(string $jugador): ?string
    {
        return preg_match('/^[0-9a-f-]{36}$/', $jugador) === 1 ? Almacen::asegurar('fotos') . '/' . $jugador . '.jpg' : null;
    }

    public static function disponible(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /** Guarda la foto subida. Devuelve null si salió bien o la clave del error. */
    public function guardar(string $jugador, string $tmp, int $tam): ?string
    {
        $destino = self::ruta($jugador);
        if ($destino === null || $tmp === '' || !is_file($tmp)) {
            return 'plantel.foto.error.archivo';
        }
        if ($tam <= 0 || $tam > self::MAX_BYTES) {
            return 'plantel.foto.error.tamano';
        }
        if (!self::disponible()) {
            return 'plantel.foto.error.servidor';
        }
        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) {
            return 'plantel.foto.error.tipo';
        }
        $img = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($img === false) {
            return 'plantel.foto.error.tipo';
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
            $giro = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($giro !== 0 && function_exists('imagerotate') && ($r = imagerotate($img, $giro, 0)) !== false) {
                $img = $r;
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $lado = min($w, $h);
        $out = imagecreatetruecolor(self::LADO, self::LADO);
        imagecopyresampled($out, $img, 0, 0, intdiv($w - $lado, 2), intdiv($h - $lado, 2), self::LADO, self::LADO, $lado, $lado);
        $tmpOut = $destino . '.tmp';
        $ok = imagejpeg($out, $tmpOut, 84) && @rename($tmpOut, $destino);
        if (!$ok) {
            @unlink($tmpOut);
            return 'plantel.foto.error.archivo';
        }
        @chmod($destino, 0640);
        $this->ejecutar('DELETE FROM sportiva_fotos WHERE jugador_id = ?', [$jugador]);
        $this->ejecutar('INSERT INTO sportiva_fotos (jugador_id, version) VALUES (?, ?)', [$jugador, Tiempo::sello()]);
        return null;
    }

    public function quitar(string $jugador): void
    {
        if (($r = self::ruta($jugador)) !== null) {
            @unlink($r);
        }
        $this->ejecutar('DELETE FROM sportiva_fotos WHERE jugador_id = ?', [$jugador]);
    }
}
