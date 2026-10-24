<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Foto adjunta a un mensaje de consulta, a una publicación o a un comentario (comprimida, sin EXIF). */
final class AdjuntoService extends Repo
{
    public const MAX_BYTES = 12 * 1024 * 1024;
    public const POR_HORA = 20;
    public const CONTEXTOS = ['mensaje', 'post', 'comentario'];

    /** ¿Subieron un archivo en este campo? */
    public static function hayArchivo(?array $f): bool
    {
        return is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Guarda la foto subida y la ata a un mensaje, publicación o comentario.
     * @param array<string, mixed>|null $f entrada de $_FILES @return array{id: ?string, error: ?string}
     */
    public function guardar(string $club, string $contexto, string $ref, string $persona, ?array $f): array
    {
        if (!self::hayArchivo($f) || !in_array($contexto, self::CONTEXTOS, true)) {
            return ['id' => null, 'error' => null];
        }
        $tmp = (string) ($f['tmp_name'] ?? '');
        $tam = (int) ($f['size'] ?? 0);
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || $tam <= 0 || $tam > self::MAX_BYTES || !(defined('SPORTIVA_PRUEBAS') || is_uploaded_file($tmp))) {
            return ['id' => null, 'error' => ($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || $tam > self::MAX_BYTES ? 'adjunto.error.tamano' : 'adjunto.error.archivo'];
        }
        $hora = Tiempo::ahora()->modify('-1 hour')->format('Y-m-d H:i:s');
        if ((int) ($this->fila('SELECT COUNT(*) AS n FROM sportiva_adjuntos WHERE subido_por = ? AND created_at >= ?', [$persona, $hora])['n'] ?? 0) >= self::POR_HORA) {
            return ['id' => null, 'error' => 'adjunto.error.muchas'];
        }
        $id = typedock_uuid7();
        $info = MedioArchivos::procesarFoto($tmp, (string) MedioArchivos::ruta($id, 'f'), (string) MedioArchivos::ruta($id, 'm'));
        if ($info === null) {
            return ['id' => null, 'error' => 'adjunto.error.tipo'];
        }
        $this->ejecutar('INSERT INTO sportiva_adjuntos (id, club_id, contexto, ref_id, subido_por, ancho, alto, bytes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$id, $club, $contexto, $ref, $persona, $info['ancho'], $info['alto'], $info['bytes'], Tiempo::sello()]);
        return ['id' => $id, 'error' => null];
    }

    public function buscar(string $id): ?array
    {
        return $this->fila('SELECT * FROM sportiva_adjuntos WHERE id = ?', [$id]);
    }

    /** Foto de cada referencia: [ref_id => id del adjunto]. @param array<int, string> $refs @return array<string, string> */
    public function de(string $contexto, array $refs): array
    {
        if ($refs === []) {
            return [];
        }
        $r = [];
        foreach ($this->filas('SELECT id, ref_id FROM sportiva_adjuntos WHERE contexto = ? AND ref_id IN (' . implode(',', array_fill(0, count($refs), '?')) . ')', array_merge([$contexto], array_values($refs))) as $a) {
            $r[$a['ref_id']] = $a['id'];
        }
        return $r;
    }

    /** Borra la foto (archivos y fila) de esas referencias. @param array<int, string> $refs */
    public function borrarDe(string $contexto, array $refs): void
    {
        foreach ($this->de($contexto, $refs) as $ref => $id) {
            foreach (['f', 'm'] as $v) {
                if (($ruta = MedioArchivos::ruta($id, $v)) !== null) {
                    @unlink($ruta);
                }
            }
            $this->ejecutar('DELETE FROM sportiva_adjuntos WHERE id = ?', [$id]);
        }
    }
}
