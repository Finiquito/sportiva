<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Fotos de un evento: galería, visor, subida (comprimidas), borrado y «pedir que lo quiten». */
final class MedioController extends AppBase
{
    private const MAX_FOTO_BYTES = 12 * 1024 * 1024;

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
     * Qué archivo enviar, o null si no corresponde. Pura (sin salida) para poder probarla.
     * @return array{ruta: string, mime: string, tam: int}|null
     */
    public function datosServir(string $id, string $variante): ?array
    {
        if (($r = $this->exigir()) !== null) {
            return null;
        }
        $m = $this->svc()->buscar($id);
        if ($m === null || $this->acceso($m['evento_id']) === null || !in_array($variante, ['f', 'm'], true)) {
            return null;
        }
        $ruta = MedioArchivos::ruta($id, $variante);
        return $ruta !== null && is_file($ruta) ? ['ruta' => $ruta, 'mime' => 'image/jpeg', 'tam' => (int) filesize($ruta)] : null;
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
        $d = $this->datosServir($id, $variante);
        if ($d === null) {
            $this->codigoHttp(404);
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: ' . $d['mime']);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=604800');
            header('Content-Length: ' . $d['tam']);
        }
        readfile($d['ruta']);
    }

    private function aviso(string $evento, array $e, bool $hayReciente): void
    {
        if ($hayReciente) {
            return;
        }
        $autor = (string) $this->persona['nombre'];
        $titulo = (string) $e['titulo'];
        $this->notificar((new AvisoService($this->pdo))->adultosDelEvento($evento), 'feed', fn(I18n $i) => [$i->t('aviso.medios.fotos', ['titulo' => $titulo]), $autor], '/app/evento/' . $evento . '/fotos');
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
                $svc->crear($id, ['club_id' => $this->club['id'], 'equipo_id' => $a['e']['equipo_id'], 'evento_id' => $evento, 'bytes' => $info['bytes'], 'ancho' => $info['ancho'], 'alto' => $info['alto'], 'subido_por' => $this->persona['id']]);
                $ok++;
            }
        }
        if ($ok > 0) {
            $this->aviso($evento, $a['e'], $reciente);
        }
        $msg = $ok > 0 ? $this->i18n->n('medios.subidas', $ok) : $this->i18n->t($lleno ? 'medios.error.lleno' : 'medios.error.fotos');
        if ($ok > 0 && ($mal > 0 || $lleno)) {
            $msg .= ' ' . $this->i18n->t($lleno ? 'medios.error.lleno' : 'medios.error.algunas');
        }
        $this->ctx->redirect('/app/evento/' . $evento . '/fotos', $msg, $ok > 0 ? 'success' : 'error');
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
            $this->notificar((new AvisoService($this->pdo))->staffDelEquipo($this->club['id'], $m['equipo_id']), 'consultas', fn(I18n $i) => [$i->t('aviso.medios.reporte_foto', ['quien' => $quien]), $evento], '/app/medio/' . $id);
            $this->ctx->redirect('/app/medio/' . $id, $this->i18n->t('medios.reportado'));
            return;
        }
        $this->ir('/app/agenda');
    }
}
