<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Fotos y videos de un evento: galería, visor, subida (fotos y videos por trozos), borrado y «pedir que lo quiten». */
final class MedioController extends AppBase
{
    private const MAX_FOTO_BYTES = 12 * 1024 * 1024;
    private const MAX_TROZO_BYTES = 4 * 1024 * 1024;

    private function svc(): MedioService
    {
        return new MedioService($this->pdo);
    }

    private function entrar(): bool
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return false;
        }
        return true;
    }

    private function existe(string $sql, array $p): bool
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        return $s->fetchColumn() !== false;
    }

    /** Evento y permisos de esta persona sobre su álbum, o null si no lo ve. @return array{e: array<string, mixed>, ver: bool, subir: bool, coach: bool}|null */
    private function acceso(string $evento): ?array
    {
        $e = (new EventoService($this->pdo))->buscar($evento);
        if ($e === null || $e['club_id'] !== $this->club['id']) {
            return null;
        }
        $coach = self::esCoach($this->rol) && in_array($e['equipo_id'], array_column($this->gestionables(), 'id'), true);
        $familia = $this->rol === 'apoderado' && $this->existe('SELECT 1 FROM sportiva_convocados c JOIN sportiva_apoderados_jugadores aj ON aj.jugador_id = c.jugador_id WHERE c.evento_id = ? AND aj.persona_id = ?', [$evento, $this->persona['id']]);
        $jugador = $this->rol === 'jugador' && $this->existe('SELECT 1 FROM sportiva_convocados c JOIN sportiva_jugadores j ON j.id = c.jugador_id WHERE c.evento_id = ? AND j.persona_id = ?', [$evento, $this->persona['id']]);
        $ver = $coach || $familia || $jugador || $this->rol === 'directiva';
        return $ver ? ['e' => $e, 'ver' => true, 'subir' => $coach || $familia, 'coach' => $coach] : null;
    }

    public function galeria(string $evento): void
    {
        if (!$this->entrar()) {
            return;
        }
        $a = $this->acceso($evento);
        if ($a === null) {
            \Flight::notFound();
            return;
        }
        $svc = $this->svc();
        $lim = $svc->limites();
        $uso = $svc->usoEquipoBytes($a['e']['equipo_id']);
        echo $this->vista()->pagina('medios.latte', [
            'activa' => 'agenda', 'e' => $a['e'], 'medios' => $svc->delEvento($evento), 'puedeSubir' => $a['subir'], 'lim' => $lim,
            'lleno' => $uso >= $lim['cuota_mb'] * 1048576, 'casiLleno' => $uso >= 0.8 * $lim['cuota_mb'] * 1048576,
        ]);
    }

    public function ver(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $svc = $this->svc();
        $m = $svc->buscar($id);
        $a = $m !== null ? $this->acceso($m['evento_id']) : null;
        if ($a === null) {
            \Flight::notFound();
            return;
        }
        [$ant, $sig] = $svc->vecinos($m);
        echo $this->vista()->pagina('medio.latte', [
            'activa' => 'agenda', 'm' => $m, 'anterior' => $ant, 'siguiente' => $sig,
            'puedeBorrar' => $a['coach'] || $m['subido_por'] === $this->persona['id'], 'esMio' => $m['subido_por'] === $this->persona['id'],
        ]);
    }

    /**
     * Qué archivo enviar y cómo, o null si no corresponde. Pura (sin salida) para poder probarla.
     * @return array{ruta: string, mime: string, tam: int, inicio: int, fin: int, parcial: bool}|null
     */
    public function datosServir(string $id, string $variante, string $rango = ''): ?array
    {
        if (($r = $this->exigir()) !== null) {
            return null;
        }
        $m = $this->svc()->buscar($id);
        if ($m === null || $this->acceso($m['evento_id']) === null) {
            return null;
        }
        $ok = match ($variante) {
            'f' => $m['tipo'] === 'foto',
            'm' => $m['tipo'] === 'foto' || (int) $m['tiene_poster'] === 1,
            'v' => $m['tipo'] === 'video',
            default => false,
        };
        $ruta = $ok ? MedioArchivos::ruta($id, $variante) : null;
        if ($ruta === null || !is_file($ruta)) {
            return null;
        }
        $tam = (int) filesize($ruta);
        $r = $variante === 'v' && $rango !== '' ? MedioArchivos::rango($rango, $tam) : null;
        return ['ruta' => $ruta, 'mime' => $variante === 'v' ? (string) $m['mime'] : 'image/jpeg', 'tam' => $tam, 'inicio' => $r[0] ?? 0, 'fin' => $r[1] ?? $tam - 1, 'parcial' => $r !== null];
    }

    /** Código de estado compatible con Flight (que fija el suyo al terminar la ruta). */
    private function codigoHttp(int $codigo): void
    {
        try {
            \Flight::response()->status($codigo);
        } catch (\Throwable) {
            http_response_code($codigo);
        }
    }

    public function servir(string $id, string $variante): void
    {
        $d = $this->datosServir($id, $variante, (string) ($_SERVER['HTTP_RANGE'] ?? ''));
        if ($d === null) {
            $this->codigoHttp(404);
            return;
        }
        // Se envía directo, sin buffers de salida (el núcleo los usa y pisaría el código 206 o gastaría memoria con un video).
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($d['parcial'] ? 206 : 200);
        header('Content-Type: ' . $d['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . ($d['fin'] - $d['inicio'] + 1));
        if ($d['parcial']) {
            header('Content-Range: bytes ' . $d['inicio'] . '-' . $d['fin'] . '/' . $d['tam']);
        }
        $f = fopen($d['ruta'], 'rb');
        fseek($f, $d['inicio']);
        $falta = $d['fin'] - $d['inicio'] + 1;
        while ($falta > 0 && !feof($f)) {
            $b = fread($f, min(8192, $falta));
            echo $b;
            $falta -= strlen((string) $b);
            if (connection_aborted()) {
                break;
            }
        }
        fclose($f);
    }

    private function aviso(string $evento, array $e, bool $video, bool $hayReciente): void
    {
        if ($hayReciente) {
            return;
        }
        $autor = (string) $this->persona['nombre'];
        $titulo = (string) $e['titulo'];
        $clave = $video ? 'aviso.medios.video' : 'aviso.medios.fotos';
        $this->notificar((new AvisoService($this->pdo))->adultosDelEvento($evento), 'feed', fn(I18n $i) => [$i->t($clave, ['titulo' => $titulo]), $autor], '/app/evento/' . $evento . '/fotos');
    }

    /** Varias fotos a la vez (el navegador ya las redujo; aquí se validan y se vuelven a codificar). */
    public function subirFotos(string $evento): void
    {
        if (!$this->entrar()) {
            return;
        }
        $a = $this->acceso($evento);
        if ($a === null || !$a['subir'] || !$this->csrfOk()) {
            $this->ir($a === null ? '/app/agenda' : '/app/evento/' . $evento . '/fotos');
            return;
        }
        $svc = $this->svc();
        $reciente = $svc->subidaReciente($evento, Tiempo::ahora()->modify('-30 minutes')->format('Y-m-d H:i:s'));
        $f = $_FILES['fotos'] ?? null;
        $ok = 0;
        $mal = 0;
        $lleno = false;
        if (is_array($f) && is_array($f['tmp_name'] ?? null)) {
            foreach (array_slice(array_keys($f['tmp_name']), 0, 20) as $k) {
                $tmp = (string) $f['tmp_name'][$k];
                $tam = (int) ($f['size'][$k] ?? 0);
                $valido = ($f['error'][$k] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && $tam > 0 && $tam <= self::MAX_FOTO_BYTES && (defined('SPORTIVA_PRUEBAS') || is_uploaded_file($tmp));
                if (!$valido) {
                    $mal++;
                    continue;
                }
                if (!$svc->hayEspacio($a['e']['equipo_id'], $tam)) {
                    $lleno = true;
                    break;
                }
                $id = typedock_uuid7();
                $info = MedioArchivos::procesarFoto($tmp, (string) MedioArchivos::ruta($id, 'f'), (string) MedioArchivos::ruta($id, 'm'));
                if ($info === null) {
                    $mal++;
                    continue;
                }
                $svc->crear($id, ['club_id' => $this->club['id'], 'equipo_id' => $a['e']['equipo_id'], 'evento_id' => $evento, 'tipo' => 'foto', 'mime' => 'image/jpeg', 'bytes' => $info['bytes'], 'ancho' => $info['ancho'], 'alto' => $info['alto'], 'subido_por' => $this->persona['id']]);
                $ok++;
            }
        }
        if ($ok > 0) {
            $this->aviso($evento, $a['e'], false, $reciente);
        }
        $msg = $ok > 0 ? $this->i18n->n('medios.subidas', $ok) : $this->i18n->t($lleno ? 'medios.error.lleno' : 'medios.error.fotos');
        if ($ok > 0 && ($mal > 0 || $lleno)) {
            $msg .= ' ' . $this->i18n->t($lleno ? 'medios.error.lleno' : 'medios.error.algunas');
        }
        $this->ctx->redirect('/app/evento/' . $evento . '/fotos', $msg, $ok > 0 ? 'success' : 'error');
    }

    // ---- videos por trozos (JSON) ------------------------------------------------------------

    private function json(string $evento, callable $f): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $a = ($r = $this->exigir()) === null ? $this->acceso($evento) : null;
        if ($a === null || !$a['subir'] || !Sesion::csrfValido($_SERVER['HTTP_X_CSRF'] ?? null)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            echo json_encode(['ok' => false, 'error' => $this->i18n->t('medios.error.permiso')]);
            return;
        }
        echo json_encode($f($a));
    }

    private function tmp(string $subida): ?string
    {
        return MedioArchivos::dirTemporal($this->persona['id'], $subida);
    }

    /** Trozos que ya llegaron de una subida (para reanudar si se cortó). */
    public function estado(string $evento): void
    {
        $this->json($evento, function (array $a): array {
            $dir = $this->tmp((string) ($_GET['subida'] ?? ''));
            $hay = [];
            foreach ($dir ? glob($dir . '/*') ?: [] : [] as $f) {
                if (ctype_digit(basename($f))) {
                    $hay[] = (int) basename($f);
                }
            }
            sort($hay);
            return ['ok' => true, 'trozos' => $hay];
        });
    }

    public function trozo(string $evento): void
    {
        $this->json($evento, function (array $a): array {
            $lim = $this->svc()->limites();
            $dir = $this->tmp((string) ($_POST['subida'] ?? ''));
            $n = (int) ($_POST['n'] ?? -1);
            $total = (int) ($_POST['total'] ?? 0);
            $f = $_FILES['trozo'] ?? null;
            $maxTrozos = (int) ceil($lim['video_mb'] * 1048576 / 1048576) + 4;
            if ($dir === null || $n < 0 || $total < 1 || $n >= $total || $total > $maxTrozos * 4 || !is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (int) $f['size'] > self::MAX_TROZO_BYTES || !(defined('SPORTIVA_PRUEBAS') || is_uploaded_file((string) $f['tmp_name']))) {
                return ['ok' => false, 'error' => $this->i18n->t('medios.error.trozo')];
            }
            if (!$this->svc()->hayEspacio($a['e']['equipo_id'], $total * 1024)) {
                return ['ok' => false, 'error' => $this->i18n->t('medios.error.lleno')];
            }
            $ok = defined('SPORTIVA_PRUEBAS') ? copy((string) $f['tmp_name'], $dir . '/' . $n) : move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $n);
            return $ok ? ['ok' => true] : ['ok' => false, 'error' => $this->i18n->t('medios.error.trozo')];
        });
    }

    /** Une los trozos, valida el video y lo deja listo. */
    public function fin(string $evento): void
    {
        $this->json($evento, function (array $a) use ($evento): array {
            $svc = $this->svc();
            $lim = $svc->limites();
            $dir = $this->tmp((string) ($_POST['subida'] ?? ''));
            $total = (int) ($_POST['total'] ?? 0);
            $falla = fn(string $k) => ['ok' => false, 'error' => $this->i18n->t($k, ['mb' => $lim['video_mb'], 'seg' => $lim['video_seg']])];
            if ($dir === null || $total < 1) {
                return $falla('medios.error.trozo');
            }
            $todo = $dir . '/todo';
            $out = fopen($todo, 'wb');
            $bytes = 0;
            for ($i = 0; $i < $total; $i++) {
                $p = $dir . '/' . $i;
                if (!is_file($p)) {
                    fclose($out);
                    return $falla('medios.error.faltan');
                }
                $bytes += (int) filesize($p);
                fwrite($out, (string) file_get_contents($p));
                @unlink($p);
                if ($bytes > $lim['video_mb'] * 1048576) {
                    fclose($out);
                    MedioArchivos::borrarCarpeta($dir);
                    return $falla('medios.error.video_pesado');
                }
            }
            fclose($out);
            $mime = MedioArchivos::tipoVideo($todo);
            $dur = $mime === 'video/webm' ? (float) ($_POST['duracion'] ?? 0) : MedioArchivos::duracionMp4($todo);
            $dur ??= (float) ($_POST['duracion'] ?? 0);
            if ($mime === null) {
                MedioArchivos::borrarCarpeta($dir);
                return $falla('medios.error.video_tipo');
            }
            if ($dur <= 0 || $dur > $lim['video_seg'] + 1) {
                MedioArchivos::borrarCarpeta($dir);
                return $falla('medios.error.video_largo');
            }
            if (!$svc->hayEspacio($a['e']['equipo_id'], $bytes)) {
                MedioArchivos::borrarCarpeta($dir);
                return $falla('medios.error.lleno');
            }
            $id = typedock_uuid7();
            $reciente = $svc->subidaReciente($evento, Tiempo::ahora()->modify('-30 minutes')->format('Y-m-d H:i:s'));
            rename($todo, (string) MedioArchivos::ruta($id, 'v'));
            $poster = $_FILES['poster'] ?? null;
            $tiene = is_array($poster) && ($poster['error'] ?? 1) === UPLOAD_ERR_OK && (int) $poster['size'] < 2 * 1048576 && (defined('SPORTIVA_PRUEBAS') || is_uploaded_file((string) $poster['tmp_name']))
                && MedioArchivos::procesarPoster((string) $poster['tmp_name'], (string) MedioArchivos::ruta($id, 'm'));
            MedioArchivos::borrarCarpeta($dir);
            $svc->crear($id, ['club_id' => $this->club['id'], 'equipo_id' => $a['e']['equipo_id'], 'evento_id' => $evento, 'tipo' => 'video', 'mime' => $mime, 'bytes' => $bytes + ($tiene ? (int) @filesize((string) MedioArchivos::ruta($id, 'm')) : 0), 'ancho' => (int) ($_POST['ancho'] ?? 0) ?: null, 'alto' => (int) ($_POST['alto'] ?? 0) ?: null, 'duracion' => (int) round($dur), 'tiene_poster' => $tiene ? 1 : 0, 'subido_por' => $this->persona['id']]);
            $this->aviso($evento, $a['e'], true, $reciente);
            return ['ok' => true, 'url' => '/app/medio/' . $id];
        });
    }

    public function borrar(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $svc = $this->svc();
        $m = $svc->buscar($id);
        $a = $m !== null ? $this->acceso($m['evento_id']) : null;
        if ($m !== null && $a !== null && ($a['coach'] || $m['subido_por'] === $this->persona['id']) && $this->csrfOk()) {
            $svc->borrar($id);
            $this->ctx->redirect('/app/evento/' . $m['evento_id'] . '/fotos', $this->i18n->t('medios.borrado'));
            return;
        }
        $this->ir('/app/agenda');
    }

    /** «Pedir que lo quiten»: avisa al cuerpo técnico (privacidad de los niños). */
    public function reportar(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $m = $this->svc()->buscar($id);
        $a = $m !== null ? $this->acceso($m['evento_id']) : null;
        if ($m !== null && $a !== null && $this->csrfOk()) {
            $quien = (string) $this->persona['nombre'];
            $evento = (string) $m['evento'];
            $clave = $m['tipo'] === 'video' ? 'aviso.medios.reporte_video' : 'aviso.medios.reporte_foto';
            $this->notificar((new AvisoService($this->pdo))->staffDelEquipo($this->club['id'], $m['equipo_id']), 'consultas', fn(I18n $i) => [$i->t($clave, ['quien' => $quien]), $evento], '/app/medio/' . $id);
            $this->ctx->redirect('/app/medio/' . $id, $this->i18n->t('medios.reportado'));
            return;
        }
        $this->ir('/app/agenda');
    }
}
