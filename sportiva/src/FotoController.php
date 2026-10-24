<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Subir, quitar y ver la foto de un jugador. La suben el entrenador (fotos oficiales) o el apoderado titular. */
final class FotoController extends AppBase
{
    private function jugadores(): PlantelService
    {
        return new PlantelService($this->pdo);
    }

    /** Foto: solo la ve quien tiene algún rol en el club del jugador. */
    public function ver(string $id): void
    {
        $pid = Sesion::persona();
        $j = $pid ? $this->jugadores()->jugador($id) : null;
        $ruta = FotoService::ruta($id);
        $dentro = $j !== null && in_array($j['club_id'], array_column((new PersonaService($this->pdo))->membresias($pid), 'club_id'), true);
        if (!$dentro || $ruta === null || !is_file($ruta)) {
            \Flight::halt(404, '');
            return;
        }
        $mod = (int) filemtime($ruta);
        header('Content-Type: image/jpeg');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=604800');
        header('Content-Length: ' . filesize($ruta));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mod) . ' GMT');
        readfile($ruta);
    }

    /** Quién puede cambiar la foto: entrenador del equipo / encargado, o el apoderado titular. Devuelve el jugador o null. */
    private function permitido(string $id, string $modo): ?array
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return null;
        }
        $j = $this->jugadores()->jugador($id);
        if ($j === null || $j['club_id'] !== $this->club['id']) {
            return null;
        }
        if ($modo === 'coach') {
            return self::esCoach($this->rol) && in_array($j['equipo_id'], array_column($this->gestionables(), 'id'), true) ? $j : null;
        }
        return $this->rol === 'apoderado' && $this->jugadores()->esTitular($this->persona['id'], $id) ? $j : null;
    }

    private function volver(string $id, string $modo): string
    {
        return $modo === 'coach' ? '/app/plantel/jugador/' . $id : '/app/familia/hijo/' . $id;
    }

    public function subir(string $id, string $modo): void
    {
        $j = $this->permitido($id, $modo);
        if ($j === null) {
            $this->ir($modo === 'coach' ? '/app/plantel' : '/app/familia');
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir($this->volver($id, $modo));
            return;
        }
        $f = $_FILES['foto'] ?? null;
        $tmp = is_array($f) ? (string) ($f['tmp_name'] ?? '') : '';
        $valido = is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (defined('SPORTIVA_PRUEBAS') || is_uploaded_file($tmp));
        $error = $valido ? (new FotoService($this->pdo))->guardar($id, $tmp, (int) ($f['size'] ?? 0)) : (is_array($f) && in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'plantel.foto.error.tamano' : 'plantel.foto.error.archivo');
        if ($error !== null) {
            $this->ctx->redirect($this->volver($id, $modo), $this->i18n->t($error), 'error');
            return;
        }
        $this->ctx->redirect($this->volver($id, $modo), $this->i18n->t('plantel.foto.ok'));
    }

    public function quitar(string $id, string $modo): void
    {
        $j = $this->permitido($id, $modo);
        if ($j === null) {
            $this->ir($modo === 'coach' ? '/app/plantel' : '/app/familia');
            return;
        }
        if ($this->csrfOk()) {
            (new FotoService($this->pdo))->quitar($id);
        }
        $this->ctx->redirect($this->volver($id, $modo), $this->i18n->t('plantel.foto.quitada'));
    }
}
