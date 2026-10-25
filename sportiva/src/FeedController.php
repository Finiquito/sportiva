<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Feed: la hoja de ruta del equipo. Publican entrenadores, encargados y directiva; las familias comentan. */
final class FeedController extends AppBase
{
    private function svc(): FeedService
    {
        return new FeedService($this->pdo);
    }

    /** @return array<int, string>|null */
    private function equipos(): ?array
    {
        return $this->svc()->equiposDe((string) $this->rol, $this->persona['id'], $this->club['id'], $this->gestionables());
    }

    private function puedePublicar(): bool
    {
        return self::esCoach($this->rol) || $this->rol === 'directiva';
    }

    /** Destinos posibles al publicar: equipos y, para encargado y directiva, todo el club. @return array<int, array<string, mixed>> */
    private function destinos(): array
    {
        return $this->rol === 'entrenador' ? $this->gestionables() : (new ClubService($this->pdo))->equipos($this->club['id']);
    }

    private function puedeClub(): bool
    {
        return $this->rol === 'encargado' || $this->rol === 'directiva';
    }

    private function puedeModerar(array $post): bool
    {
        return $post['autor_id'] === $this->persona['id'] || $this->puedeClub() || ($this->rol === 'entrenador' && $post['equipo_id'] !== null && in_array($post['equipo_id'], array_column($this->gestionables(), 'id'), true));
    }

    private function entrar(): bool
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return false;
        }
        return true;
    }

    public function inicio(): void
    {
        if (!$this->entrar()) {
            return;
        }
        $svc = $this->svc();
        $eq = $this->equipos();
        $vista = (string) ($_GET['v'] ?? '');
        if (!in_array($vista, ['equipo', 'club'], true)) {
            $vista = $eq === [] ? 'club' : ($this->rol === 'directiva' ? 'club' : 'equipo');
        }
        $posts = $svc->posts($this->club['id'], $eq, $vista);
        $items = array_map(fn($p) => ['k' => 'post', 'f' => (int) $p['fijado'], 't' => $p['created_at'], 'p' => $p], $posts);
        if ($vista === 'equipo') {
            $desde = Tiempo::ahora((string) $this->club['zona'])->modify('-14 days')->format('Y-m-d H:i:s');
            $hoy = Tiempo::ahora((string) $this->club['zona'])->format('Y-m-d') . ' 00:00:00';
            foreach ($svc->convocatorias($this->club['id'], $eq, $desde) as $c) {
                $c['inicio'] >= $hoy && $items[] = ['k' => 'evento', 'f' => 0, 't' => $c['created_at'], 'p' => $c];
            }
        }
        if ($vista === 'equipo') {
            $desdeR = Tiempo::ahora((string) $this->club['zona'])->modify('-7 days')->format('Y-m-d H:i:s');
            foreach ($svc->resultados($this->club['id'], $eq, $desdeR) as $r) {
                $items[] = ['k' => 'resultado', 'f' => 0, 't' => $r['updated_at'], 'p' => $r];
            }
        }
        if ($vista === 'equipo') {
            $desdeM = Tiempo::ahora((string) $this->club['zona'])->modify('-7 days')->format('Y-m-d H:i:s');
            foreach ((new MedioService($this->pdo))->novedades($this->club['id'], $eq, $desdeM) as $n) {
                $items[] = ['k' => 'medios', 'f' => 0, 't' => $n['ultimo'], 'p' => $n];
            }
        }
        // Filtro por tipo: todo | publicaciones | convocatorias | fotos (con su cantidad)
        $tipos = ['post' => 'posts', 'evento' => 'citaciones', 'resultado' => 'citaciones', 'medios' => 'fotos'];
        $conteo = ['todo' => count($items), 'posts' => 0, 'citaciones' => 0, 'fotos' => 0];
        foreach ($items as $i) {
            $conteo[$tipos[$i['k']] ?? 'posts']++;
        }
        $sig = null;
        foreach ($items as $i) {
            if ($i['k'] === 'evento' && !Tiempo::sinFecha($i['p']['inicio']) && ($sig === null || $i['p']['inicio'] < $sig['inicio'])) {
                $sig = $i['p'];
            }
        }
        $filtro = in_array($_GET['t'] ?? '', ['posts', 'citaciones', 'fotos'], true) ? (string) $_GET['t'] : 'todo';
        if ($filtro !== 'todo') {
            $items = array_values(array_filter($items, fn($i) => ($tipos[$i['k']] ?? 'posts') === $filtro));
        }
        usort($items, fn($a, $b) => [$b['f'], $b['t']] <=> [$a['f'], $a['t']]);
        echo $this->vista()->pagina('feed.latte', [
            'conteo' => $conteo, 'filtro' => $filtro, 'sig' => $sig,
            'adj' => (new AdjuntoService($this->pdo))->de('post', array_map(fn($i) => $i['p']['id'], array_filter($items, fn($i) => $i['k'] === 'post'))),
            'activa' => 'feed', 'items' => $items, 'vista' => $vista, 'puedePublicar' => $this->puedePublicar(), 'tieneEquipos' => $eq !== [],
        ]);
    }

    private function formulario(array $d, array $err): void
    {
        echo $this->vista()->pagina('feed-form.latte', ['activa' => 'feed', 'd' => $d, 'errores' => $err, 'destinos' => $this->destinos(), 'puedeClub' => $this->puedeClub()]);
    }

    public function nuevo(): void
    {
        if ($this->entrar()) {
            if (!$this->puedePublicar()) {
                $this->ir('/app/feed');
                return;
            }
            $dest = $this->destinos();
            $this->formulario(['destino' => $this->puedeClub() && $this->rol === 'directiva' ? 'club' : ($dest[0]['id'] ?? 'club')], []);
        }
    }

    public function crear(): void
    {
        if (!$this->entrar()) {
            return;
        }
        if (!$this->puedePublicar() || !$this->csrfOk()) {
            $this->ir('/app/feed');
            return;
        }
        $svc = $this->svc();
        [$d, $err] = $svc->normalizar($_POST);
        $equipo = null;
        if ($d['destino'] === 'club') {
            if (!$this->puedeClub()) {
                $err['destino'] = 'feed.error.destino';
            }
        } elseif (in_array($d['destino'], array_column($this->destinos(), 'id'), true)) {
            $equipo = $d['destino'];
        } else {
            $err['destino'] = 'feed.error.destino';
        }
        if ($err) {
            $this->formulario($_POST, $err);
            return;
        }
        $id = $svc->crear($this->club['id'], $equipo, $this->persona['id'], $d);
        $foto = (new AdjuntoService($this->pdo))->guardar($this->club['id'], 'post', $id, $this->persona['id'], $_FILES['foto'] ?? null);
        $av = new AvisoService($this->pdo);
        $destino = $equipo === null ? null : (string) ((new ClubService($this->pdo))->nombreEquipo($equipo));
        $autor = (string) $this->persona['nombre'];
        $resumen = $d['titulo'] !== '' ? $d['titulo'] : mb_substr($d['texto'], 0, 120);
        $this->notificar($equipo === null ? $av->miembrosDelClub($this->club['id']) : $av->adultosDelEquipo($equipo), 'feed', fn(I18n $i) => [$i->t('aviso.feed.nuevo', ['autor' => $autor, 'destino' => $destino ?? $i->t('feed.todo_club')]), $resumen], '/app/feed/' . $id);
        $url = '/app/feed?v=' . ($equipo === null ? 'club' : 'equipo');
        $foto['error'] === null ? $this->ctx->redirect($url, $this->i18n->t('feed.publicado')) : $this->ctx->redirect($url, $this->i18n->t($foto['error']) . ' ' . $this->i18n->t('adjunto.sin_foto'), 'error');
    }

    /** Post visible para esta persona o null. */
    private function postVisible(string $id): ?array
    {
        $p = $this->svc()->post($id);
        return $p !== null && $this->svc()->visible($p, $this->club['id'], $this->equipos()) ? $p : null;
    }

    public function ver(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $p = $this->postVisible($id);
        if ($p === null) {
            \Flight::notFound();
            return;
        }
        $this->pantallaPost($p, [], '');
    }

    /** @param array<string, string> $errores */
    private function pantallaPost(array $p, array $errores, string $texto): void
    {
        $coms = $this->svc()->comentarios($p['id']);
        $a = new AdjuntoService($this->pdo);
        echo $this->vista()->pagina('feed-post.latte', [
            'activa' => 'feed', 'p' => $p, 'comentarios' => $coms, 'puedeComentar' => $this->rol !== 'jugador', 'moderar' => $this->puedeModerar($p),
            'errores' => $errores, 'texto' => $texto, 'adjPost' => $a->de('post', [$p['id']])[$p['id']] ?? null, 'adjCom' => $a->de('comentario', array_column($coms, 'id')),
        ]);
    }

    public function comentar(string $id): void
    {
        if (!$this->entrar()) {
            return;
        }
        $p = $this->postVisible($id);
        if ($p === null || $this->rol === 'jugador' || !$this->csrfOk()) {
            $this->ir('/app/feed');
            return;
        }
        if (trim((string) ($_POST['texto'] ?? '')) === '' && AdjuntoService::hayArchivo($_FILES['foto'] ?? null)) {
            $_POST['texto'] = $this->i18n->t('adjunto.solo_foto');
        }
        $cid = $this->svc()->comentar($id, $this->persona['id'], (string) ($_POST['texto'] ?? ''));
        if ($cid === null) {
            $this->pantallaPost($p, ['texto' => 'feed.error.comentario'], (string) ($_POST['texto'] ?? ''));
            return;
        }
        $foto = (new AdjuntoService($this->pdo))->guardar($this->club['id'], 'comentario', $cid, $this->persona['id'], $_FILES['foto'] ?? null);
        $url = '/app/feed/' . $id . '#comentarios';
        $foto['error'] === null ? $this->ir($url) : $this->ctx->redirect($url, $this->i18n->t($foto['error']) . ' ' . $this->i18n->t('adjunto.sin_foto'), 'error');
    }

    public function fijar(string $id): void
    {
        if ($this->entrar()) {
            $p = $this->postVisible($id);
            if ($p !== null && $this->puedeModerar($p) && $this->csrfOk()) {
                $this->svc()->fijar($id, !(int) $p['fijado']);
            }
            $this->ir('/app/feed/' . $id);
        }
    }

    public function borrar(string $id): void
    {
        if ($this->entrar()) {
            $p = $this->postVisible($id);
            if ($p !== null && $this->puedeModerar($p) && $this->csrfOk()) {
                $a = new AdjuntoService($this->pdo);
                $a->borrarDe('comentario', array_column($this->svc()->comentarios($id), 'id'));
                $a->borrarDe('post', [$id]);
                $this->svc()->borrar($id);
                $this->ctx->redirect('/app/feed', $this->i18n->t('feed.borrado'));
                return;
            }
            $this->ir('/app/feed');
        }
    }

    public function borrarComentario(string $id, string $cid): void
    {
        if ($this->entrar()) {
            $p = $this->postVisible($id);
            $c = $this->svc()->comentario($cid);
            if ($p !== null && $c !== null && $c['post_id'] === $id && ($c['persona_id'] === $this->persona['id'] || $this->puedeModerar($p)) && $this->csrfOk()) {
                (new AdjuntoService($this->pdo))->borrarDe('comentario', [$cid]);
                $this->svc()->borrarComentario($cid);
            }
            $this->ir('/app/feed/' . $id . '#comentarios');
        }
    }
}
