<?php
declare(strict_types=1);

// Servidor de desarrollo:  php -S 127.0.0.1:8080 -t dev/public dev/public/index.php

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($uri, '/plugins/sportiva/assets/')) {
    $base = realpath(dirname(__DIR__, 2) . '/sportiva/assets');
    $f = realpath(dirname(__DIR__, 2) . '/sportiva/assets/' . substr($uri, strlen('/plugins/sportiva/assets/')));
    if ($f && $base && str_starts_with($f, $base) && is_file($f)) {
        $tipos = ['css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'json' => 'application/json'];
        header('Content-Type: ' . ($tipos[pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        readfile($f);
        return true;
    }
}

require dirname(__DIR__) . '/bootstrap.php';

$pdo = sportiva_dev_pdo();
$ctx = sportiva_dev_contexto($pdo);
Flight::route('GET /', fn() => Flight::redirect('/app'));
Flight::route('GET /admin', fn() => Flight::redirect('/admin/sportiva'));
// Atajo solo de desarrollo: entrar sin código.  /dev/entrar?email=...
Flight::route('GET /dev/entrar', function () use ($ctx) {
    $p = (new TypeDock\Plugin\Sportiva\PersonaService($ctx->db()->pdo()))->porCorreo((string) ($_GET['email'] ?? ''));
    if ($p === null) {
        echo 'No existe esa persona. Corre dev/seed.php';
        return;
    }
    TypeDock\Plugin\Sportiva\Sesion::entrar($p['id']);
    Flight::redirect('/app/agenda');
});
Flight::map('error', function (Throwable $e) {
    @mkdir(dirname(__DIR__) . '/storage', 0777, true);
    file_put_contents(dirname(__DIR__) . '/storage/errores.log', date('c') . ' ' . $e . "\n\n", FILE_APPEND);
    http_response_code(500);
    echo '<h1>500</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
});
try {
    Flight::start();
} catch (TypeDock\Core\RedirectException) {
}
