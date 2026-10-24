<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Campana, preferencias de avisos, suscripción push, service worker, manifest y cron. */
final class AvisosController extends AppBase
{
    private function cabecera(string $h): void
    {
        if (!headers_sent()) {
            header($h);
        }
    }

    private function estado(int $c): void
    {
        if (!headers_sent()) {
            http_response_code($c);
        }
    }

    private function svc(): AvisoService
    {
        return new AvisoService($this->pdo);
    }

    private function entrar(): bool
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return false;
        }
        return true;
    }

    public function lista(): void
    {
        if ($this->entrar()) {
            echo $this->vista()->pagina('avisos.latte', ['activa' => 'mas', 'avisos' => $this->svc()->lista($this->persona['id'], $this->club['id'])]);
        }
    }

    public function abrir(string $id): void
    {
        if ($this->entrar()) {
            $url = $this->svc()->abrir($id, $this->persona['id']);
            $this->ir($url !== null && str_starts_with($url, '/app') ? $url : '/app/avisos');
        }
    }

    public function leerTodo(): void
    {
        if ($this->entrar()) {
            if ($this->csrfOk()) {
                $this->svc()->leerTodo($this->persona['id'], $this->club['id']);
            }
            $this->ir('/app/avisos');
        }
    }

    private function pantallaAjustes(): void
    {
        $nt = new Notificador($this->ctx, $this->pdo);
        echo $this->vista()->pagina('avisos-ajustes.latte', [
            'activa' => 'mas', 'prefs' => $this->svc()->prefs($this->persona['id']), 'categorias' => AvisoService::CATEGORIAS,
            'push' => WebPush::disponible(), 'vapid' => WebPush::disponible() ? $nt->claves()[1] : '', 'suscrito' => $this->svc()->tieneSuscripcion($this->persona['id']),
        ]);
    }

    public function ajustes(): void
    {
        if ($this->entrar()) {
            $this->pantallaAjustes();
        }
    }

    public function guardarAjustes(): void
    {
        if ($this->entrar()) {
            if ($this->csrfOk()) {
                $this->svc()->guardarPrefs($this->persona['id'], $_POST);
            }
            $this->ctx->redirect('/app/avisos/ajustes', $this->i18n->t('evento.guardado'));
        }
    }

    /** JSON de la suscripción push del navegador (cabecera X-CSRF). */
    public function suscribir(): void
    {
        $this->json(function (array $in): array {
            return ['ok' => $this->svc()->suscribir($this->persona['id'], (string) ($in['endpoint'] ?? ''), (string) ($in['p256dh'] ?? ''), (string) ($in['auth'] ?? ''))];
        });
    }

    public function baja(): void
    {
        $this->json(function (array $in): array {
            $this->svc()->baja($this->persona['id'], (string) ($in['endpoint'] ?? ''));
            return ['ok' => true];
        });
    }

    private function json(callable $f): void
    {
        $this->cabecera('Content-Type: application/json');
        if (($r = $this->exigir()) !== null || !Sesion::csrfValido($_SERVER['HTTP_X_CSRF'] ?? null)) {
            $this->estado(403);
            echo '{"ok":false}';
            return;
        }
        $in = json_decode((string) file_get_contents('php://input'), true);
        echo json_encode($f(is_array($in) ? $in : []));
    }

    /** Service worker: muestra el aviso y abre la app al tocarlo. */
    public function sw(): void
    {
        $this->cabecera('Content-Type: application/javascript; charset=utf-8');
        $this->cabecera('Service-Worker-Allowed: /app/');
        $this->cabecera('Cache-Control: no-cache');
        echo <<<'JS'
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) {}
  var b = (typeof d.n === 'number' && self.navigator && self.navigator.setAppBadge) ? self.navigator.setAppBadge(d.n).catch(function () {}) : null;
  e.waitUntil(Promise.all([b, self.registration.showNotification(d.t || 'u330', {
    body: d.b || '', data: { u: d.u || '/app/avisos' },
    icon: '/app/recurso/icon-192', badge: '/app/recurso/icon-192'
  })]));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var u = (e.notification.data && e.notification.data.u) || '/app/avisos';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (l) {
    for (var i = 0; i < l.length; i++) { if (l[i].url.indexOf('/app') !== -1 && 'focus' in l[i]) { l[i].navigate(u); return l[i].focus(); } }
    return self.clients.openWindow(u);
  }));
});
JS;
    }

    public function manifest(): void
    {
        $this->cabecera('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name' => 'u330', 'short_name' => 'u330', 'start_url' => '/app', 'scope' => '/app/', 'display' => 'standalone',
            'background_color' => '#F7F1E8', 'theme_color' => '#0F4F4E',
            'icons' => [
                ['src' => '/app/recurso/icon-192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/app/recurso/icon-512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    /** Lo llama el cron del hosting cada 15 minutos: recordatorios y envío de lo pendiente. */
    public function cron(string $token): void
    {
        $nt = new Notificador($this->ctx, $this->pdo);
        $this->cabecera('Content-Type: text/plain; charset=utf-8');
        if (!hash_equals($nt->tokenCron(), $token)) {
            $this->estado(404);
            echo 'no';
            return;
        }
        $r = $nt->recordatorios();
        $e = $nt->entregar(100);
        echo 'ok recordatorios=' . $r . ' push=' . $e['push'] . ' correo=' . $e['correo'];
    }
}
