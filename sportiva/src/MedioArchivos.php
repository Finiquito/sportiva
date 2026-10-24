<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Archivos de fotos: rutas y procesado con GD (se comprimen, se corrigen y se les quitan los datos EXIF). */
final class MedioArchivos
{
    public const FOTO_LADO = 1600;
    public const MINIATURA_LADO = 480;

    /** @param string $variante 'f' foto reducida, 'm' miniatura */
    public static function ruta(string $id, string $variante): ?string
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $id) !== 1 || !in_array($variante, ['f', 'm'], true)) {
            return null;
        }
        return Almacen::asegurar('medios') . '/' . $id . '.' . $variante;
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

    public static function borrarCarpeta(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
