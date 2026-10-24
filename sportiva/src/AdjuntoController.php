<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Sirve la foto adjunta de un mensaje, publicación o comentario, solo a quien puede leer ese mensaje. */
final class AdjuntoController extends AppBase
{
    /** ¿Esta persona puede ver el contenido al que está atada la foto? @param array<string, mixed> $a */
    private function puedeVer(array $a): bool
    {
        if ($a['club_id'] !== $this->club['id']) {
            return false;
        }
        if ($a['contexto'] === 'mensaje') {
            $m = $this->pdo->prepare('SELECT consulta_id FROM sportiva_mensajes WHERE id = ?');
            $m->execute([$a['ref_id']]);
            $cid = $m->fetchColumn();
            $c = $cid ? (new ConsultaService($this->pdo))->buscar((string) $cid) : null;
            if ($c === null) {
                return false;
            }
            $familia = $this->rol === 'apoderado' && (new EventoService($this->pdo))->esApoderadoDe($this->persona['id'], $c['jugador_id']);
            $staff = self::esCoach($this->rol) && ($this->rol === 'encargado' || in_array($c['equipo_id'], array_column($this->gestionables(), 'id'), true));
            return $familia || $staff;
        }
        $feed = new FeedService($this->pdo);
        $postId = $a['ref_id'];
        if ($a['contexto'] === 'comentario') {
            $c = $feed->comentario($a['ref_id']);
            $postId = $c['post_id'] ?? '';
        }
        $p = $postId !== '' ? $feed->post($postId) : null;
        return $p !== null && $feed->visible($p, $this->club['id'], $feed->equiposDe((string) $this->rol, $this->persona['id'], $this->club['id'], $this->gestionables()));
    }

    /** @return array{ruta: string, mime: string, tam: int}|null */
    public function datosServir(string $id, string $variante): ?array
    {
        if ($this->exigir() !== null || !in_array($variante, ['f', 'm'], true)) {
            return null;
        }
        $a = (new AdjuntoService($this->pdo))->buscar($id);
        $ruta = $a !== null && $this->puedeVer($a) ? MedioArchivos::ruta($id, $variante) : null;
        return $ruta !== null && is_file($ruta) ? ['ruta' => $ruta, 'mime' => 'image/jpeg', 'tam' => (int) filesize($ruta)] : null;
    }

    public function servir(string $id, string $variante): void
    {
        $d = $this->datosServir($id, $variante);
        if ($d === null) {
            try {
                \Flight::response()->status(404);
            } catch (\Throwable) {
                http_response_code(404);
            }
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: image/jpeg');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=604800');
            header('Content-Length: ' . $d['tam']);
        }
        readfile($d['ruta']);
    }
}
