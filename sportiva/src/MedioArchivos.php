<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Archivos de fotos y videos: rutas, procesado de fotos con GD y lectura de la duración de un MP4/MOV (sin ffmpeg). */
final class MedioArchivos
{
    public const FOTO_LADO = 1600;
    public const MINIATURA_LADO = 480;

    /** @param string $variante 'f' foto reducida, 'm' miniatura (también el póster de un video), 'v' video */
    public static function ruta(string $id, string $variante): ?string
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $id) !== 1 || !in_array($variante, ['f', 'm', 'v'], true)) {
            return null;
        }
        return Almacen::asegurar('medios') . '/' . $id . '.' . $variante;
    }

    public static function dirTemporal(string $persona, string $subida): ?string
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $persona) !== 1 || preg_match('/^[0-9a-f-]{36}$/', $subida) !== 1) {
            return null;
        }
        return Almacen::asegurar('medios-tmp/' . $persona . '_' . $subida);
    }

    /** Carga una imagen con GD aplicando la orientación EXIF. @return \GdImage|null */
    private static function cargar(string $tmp): ?\GdImage
    {
        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) {
            return null;
        }
        $img = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($img === false) {
            return null;
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $giro = [3 => 180, 6 => -90, 8 => 90][(int) (@exif_read_data($tmp)['Orientation'] ?? 1)] ?? 0;
            if ($giro !== 0 && function_exists('imagerotate') && ($r = imagerotate($img, $giro, 0)) !== false) {
                $img = $r;
            }
        }
        return $img;
    }

    private static function reducir(\GdImage $img, int $lado): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $k = min(1, $lado / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
        imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        return $out;
    }

    /**
     * Foto: vuelve a codificar (sin EXIF) a 1600 px y crea la miniatura. @return array{ancho:int,alto:int,bytes:int}|null
     */
    public static function procesarFoto(string $tmp, string $destino, string $miniatura): ?array
    {
        if (!FotoService::disponible() || ($img = self::cargar($tmp)) === null) {
            return null;
        }
        $grande = self::reducir($img, self::FOTO_LADO);
        if (!imagejpeg($grande, $destino, 82)) {
            return null;
        }
        imagejpeg(self::reducir($img, self::MINIATURA_LADO), $miniatura, 78);
        return ['ancho' => imagesx($grande), 'alto' => imagesy($grande), 'bytes' => (int) filesize($destino) + (int) @filesize($miniatura)];
    }

    /** Póster (miniatura) de un video enviado por el navegador. */
    public static function procesarPoster(string $tmp, string $miniatura): bool
    {
        if (!FotoService::disponible() || ($img = self::cargar($tmp)) === null) {
            return false;
        }
        return imagejpeg(self::reducir($img, self::MINIATURA_LADO), $miniatura, 76);
    }

    /** Tipo real de un video por su cabecera: video/mp4, video/quicktime, video/webm o null. */
    public static function tipoVideo(string $ruta): ?string
    {
        $f = @fopen($ruta, 'rb');
        if ($f === false) {
            return null;
        }
        $c = (string) fread($f, 12);
        fclose($f);
        if (strlen($c) >= 12 && substr($c, 4, 4) === 'ftyp') {
            return str_starts_with(substr($c, 8, 4), 'qt') ? 'video/quicktime' : 'video/mp4';
        }
        return str_starts_with($c, "\x1A\x45\xDF\xA3") ? 'video/webm' : null;
    }

    /** Duración en segundos de un MP4/MOV (átomo mvhd dentro de moov); null si no se puede leer. */
    public static function duracionMp4(string $ruta): ?float
    {
        $f = @fopen($ruta, 'rb');
        if ($f === false) {
            return null;
        }
        $tam = (int) filesize($ruta);
        $pos = 0;
        $dur = null;
        while ($pos + 8 <= $tam) {
            fseek($f, $pos);
            $h = (string) fread($f, 8);
            $s = unpack('N', substr($h, 0, 4))[1];
            $tipo = substr($h, 4, 4);
            $cab = 8;
            if ($s === 1) {
                $s = unpack('J', (string) fread($f, 8))[1];
                $cab = 16;
            } elseif ($s === 0) {
                $s = $tam - $pos;
            }
            if ($s < $cab) {
                break;
            }
            if ($tipo === 'moov' && $s - $cab <= 16 * 1024 * 1024) {
                $dur = self::mvhd((string) fread($f, $s - $cab));
                break;
            }
            $pos += $s;
        }
        fclose($f);
        return $dur;
    }

    private static function mvhd(string $moov): ?float
    {
        $pos = 0;
        $n = strlen($moov);
        while ($pos + 8 <= $n) {
            $s = unpack('N', substr($moov, $pos, 4))[1];
            $tipo = substr($moov, $pos + 4, 4);
            if ($s < 8) {
                break;
            }
            if ($tipo === 'mvhd') {
                $v = ord($moov[$pos + 8]);
                if ($v === 1) {
                    $esc = unpack('N', substr($moov, $pos + 28, 4))[1];
                    $d = unpack('J', substr($moov, $pos + 32, 8))[1];
                } else {
                    $esc = unpack('N', substr($moov, $pos + 20, 4))[1];
                    $d = unpack('N', substr($moov, $pos + 24, 4))[1];
                }
                return $esc > 0 ? $d / $esc : null;
            }
            $pos += $s;
        }
        return null;
    }

    /** Rango HTTP «bytes=a-b» → [inicio, fin] o null si no es válido. @return array{0:int,1:int}|null */
    public static function rango(string $cabecera, int $tam): ?array
    {
        if ($tam < 1 || preg_match('/^bytes=(\d*)-(\d*)$/', trim($cabecera), $m) !== 1 || ($m[1] === '' && $m[2] === '')) {
            return null;
        }
        if ($m[1] === '') {
            $ini = max(0, $tam - (int) $m[2]);
            $fin = $tam - 1;
        } else {
            $ini = (int) $m[1];
            $fin = $m[2] === '' ? $tam - 1 : min((int) $m[2], $tam - 1);
        }
        return $ini > $fin || $ini >= $tam ? null : [$ini, $fin];
    }

    public static function borrarCarpeta(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
