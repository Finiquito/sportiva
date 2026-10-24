<?php
declare(strict_types=1);

/**
 * Pruebas del plugin sportiva.
 *   php tests/run.php                        # SQLite en memoria
 *   SPORTIVA_DB_DSN="mysql:host=127.0.0.1;dbname=sportiva_test;charset=utf8mb4" SPORTIVA_DB_USER=root SPORTIVA_DB_PASS=root php tests/run.php   # MariaDB (borra las tablas sportiva_*)
 */

use TypeDock\Plugin\Sportiva as S;

require_once dirname(__DIR__) . '/dev/bootstrap.php';
require_once dirname(__DIR__) . '/dev/seed.php';

define('SPORTIVA_PRUEBAS', true);
$GLOBALS['ok'] = 0;
$GLOBALS['fallas'] = [];
$GLOBALS['seccion'] = '';
function seccion(string $s): void { $GLOBALS['seccion'] = $s; echo "\n{$s}\n"; }
function check(bool $c, string $m): void
{
    if ($c) { $GLOBALS['ok']++; echo '.'; } else { $GLOBALS['fallas'][] = $GLOBALS['seccion'] . ' → ' . $m; echo 'F'; }
}

function baseLimpia(): array
{
    $dsn = getenv('SPORTIVA_DB_DSN') ?: 'sqlite::memory:';
    $pdo = sportiva_dev_pdo($dsn);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (str_starts_with((string) $t, 'sportiva_')) { $pdo->exec("DROP TABLE `{$t}`"); }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
    $ctx = new TypeDock\Core\PluginContext($pdo, dirname(__DIR__) . '/sportiva', sys_get_temp_dir() . '/sportiva-latte-' . getmypid(), 'sportiva');
    $ctx->migrate(dirname(__DIR__) . '/sportiva/migrations');
    return [$pdo, $ctx];
}

/** Ejecuta un controlador sin servidor: devuelve [html, url de redirección|null]. */
function llamar(callable $f, array $post = [], array $get = []): array
{
    $_POST = $post;
    $_GET = $get;
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    ob_start();
    $redir = null;
    try { $f(); } catch (TypeDock\Core\RedirectException $e) { $redir = $e->getMessage(); }
    $html = (string) ob_get_clean();
    return [$html, $redir];
}
function entrarComo(PDO $pdo, string $email): void
{
    S\Sesion::salir();
    $p = (new S\PersonaService($pdo))->porCorreo($email);
    S\Sesion::entrar($p['id']);
}
function conCsrf(array $d = []): array { return $d + ['csrf' => S\Sesion::csrf()]; }

// ---------------------------------------------------------------------------------------------
seccion('Migraciones y datos de ejemplo');
[$pdo, $ctx] = baseLimpia();
$ctx->migrate(dirname(__DIR__) . '/sportiva/migrations'); // idempotente
foreach (['clubes', 'equipos', 'personas', 'membresias', 'jugadores', 'apoderados_jugadores', 'equipo_staff', 'codigos', 'intentos', 'ajustes', 'lugares', 'eventos', 'convocados', 'asistencias'] as $t) {
    check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_{$t}")->fetchColumn() >= 0, "tabla {$t}");
}
$d = sportiva_sembrar($pdo);
check($d['club'] !== '', 'sembrado');
$S = ['club' => new S\ClubService($pdo), 'pers' => new S\PersonaService($pdo), 'ev' => new S\EventoService($pdo)];
check($S['club']->porSlug('fcj-oree-de-l-autize') !== null, 'club por slug');
check(S\ClubService::slugDe('Orée de l’Autize') === 'oree-de-l-autize', 'slug sin tildes');

seccion('Idiomas');
check(S\I18n::disponibles() === ['es' => 'Español', 'fr' => 'Français'], 'idiomas disponibles');
$fr = json_decode((string) file_get_contents(dirname(__DIR__) . '/sportiva/lang/fr.json'), true);
$es = json_decode((string) file_get_contents(dirname(__DIR__) . '/sportiva/lang/es.json'), true);
check(array_keys($fr) === array_keys($es) || !array_diff_key($fr, $es) && !array_diff_key($es, $fr), 'fr y es tienen las mismas claves');
// Toda clave usada en plantillas y PHP existe.
$usadas = [];
$fuentes = array_merge(glob(dirname(__DIR__) . '/sportiva/templates/app/*.latte'), glob(dirname(__DIR__) . '/sportiva/src/*.php'));
foreach ($fuentes as $f) {
    $src = (string) file_get_contents($f);
    preg_match_all("/(?:\\\$t|->t|->n)\\('([a-z_]+(?:\\.[a-z_]+)+)'/", $src, $m);
    foreach ($m[1] as $k) { $usadas[$k] = $f; }
    preg_match_all("/'((?:evento|entrar|codigo)\\.error(?:\\.[a-z_]+)?)'/", $src, $m2);
    foreach ($m2[1] as $k) { $usadas[$k] = $f; }
}
$faltan = array_filter(array_keys($usadas), fn($k) => !isset($fr[$k]) && !isset($fr[$k . '.uno']));
check($faltan === [], 'claves de idioma sin traducir: ' . implode(', ', $faltan));
foreach (['nav' => ['agenda', 'feed', 'actividad', 'consultas', 'plantel', 'club', 'directorio', 'chat', 'mas'], 'tipo' => ['partido', 'entreno', 'especial', 'stage'], 'rol' => S\PersonaService::ROLES, 'deporte' => ['futbol', 'voley', 'basquet']] as $pre => $ks) {
    foreach ($ks as $k) { check(isset($fr["{$pre}.{$k}"]) && isset($es["{$pre}.{$k}"]), "clave dinámica {$pre}.{$k}"); }
}
$i = new S\I18n('es');
check($i->t('hola', ['nombre' => 'Ana']) === 'Hola, Ana', 'variables');
check(S\Tiempo::diaLargo('2026-10-24 14:00:00', $i) === 'Sábado 24 de octubre', 'fecha larga es: ' . S\Tiempo::diaLargo('2026-10-24 14:00:00', $i));
check(S\Tiempo::diaLargo('2026-10-24 14:00:00', new S\I18n('fr')) === 'Samedi 24 octobre', 'fecha larga fr');
check((new S\I18n('xx'))->t('nav.agenda') === 'Agenda', 'respaldo a fr');

seccion('Entrar con código');
$correos = &$ctx->correos;
S\Sesion::salir();
[$h, $r] = llamar(fn() => (new S\AuthController($ctx))->pedirCodigo(), conCsrf(['correo' => 'daniela@ejemplo.fr']));
check($r === '/app/codigo', 'redirige al código');
check(count($ctx->correos) === 1, 'sale un correo');
preg_match('/(\d{6})/', $ctx->correos[0]['subject'], $m);
check(str_contains($ctx->correos[0]['body'], ($m[1] ?? 'x')) && $ctx->correos[0]['to'] === 'daniela@ejemplo.fr', 'el correo trae el código y va a la persona');
$codigo = $m[1] ?? '';
check(strlen($codigo) === 6, 'el asunto trae el código');
[$h, $r] = llamar(fn() => (new S\AuthController($ctx))->verificar(), conCsrf(['codigo' => '000000']));
check($r === null && str_contains($h, 'codigo.error') === false && str_contains($h, 'no es válido') !== false || str_contains($h, 'pas valide') || str_contains($h, 'n’est pas'), 'código malo no entra');
check(S\Sesion::persona() === null, 'sin sesión tras código malo');
[$h, $r] = llamar(fn() => (new S\AuthController($ctx))->verificar(), conCsrf(['codigo' => $codigo]));
check($r === '/app/agenda' && S\Sesion::persona() !== null, 'código bueno entra');
[$h, $r] = llamar(fn() => (new S\AuthController($ctx))->verificar(), conCsrf(['codigo' => $codigo]));
check(S\Sesion::persona() !== null, '(la sesión sigue)');
S\Sesion::salir();
S\Sesion::pendiente('daniela@ejemplo.fr');
$cod = new S\CodigoAcceso($pdo);
$pid = $S['pers']->porCorreo('daniela@ejemplo.fr')['id'];
$c1 = $cod->generar($pid);
check($cod->verificar($pid, $c1) === true && $cod->verificar($pid, $c1) === false, 'el código es de un solo uso');
$c2 = $cod->generar($pid);
$c3 = $cod->generar($pid);
check($cod->verificar($pid, $c2) === false, 'un código nuevo invalida el anterior');
for ($k = 0; $k < 5; $k++) { $cod->verificar($pid, '111111'); }
check($cod->bloqueado($pid) && $cod->verificar($pid, $c3) === false, 'tras 5 fallos queda bloqueado');
$antes = count($ctx->correos);
llamar(fn() => (new S\AuthController($ctx))->pedirCodigo(), conCsrf(['correo' => 'nadie@ejemplo.fr']));
check(count($ctx->correos) === $antes, 'correo desconocido: no se envía nada (y no se revela)');
llamar(fn() => (new S\AuthController($ctx))->pedirCodigo(), ['correo' => 'camila@ejemplo.fr']);
check(count($ctx->correos) === $antes, 'sin CSRF no se envía código');

seccion('Pantallas por rol');
foreach ([['daniela@ejemplo.fr', 'apoderado'], ['camila@ejemplo.fr', 'encargado'], ['pedro@ejemplo.fr', 'entrenador'], ['lucas@ejemplo.fr', 'jugador']] as [$mail, $rol]) {
    entrarComo($pdo, $mail);
    [$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
    check($r === null && str_contains($h, 'U12 vs Niort'), "{$rol}: agenda con el partido");
    check(str_contains($h, 'class="bar"'), "{$rol}: menú inferior");
    [$h2] = llamar(fn() => (new S\AuthController($ctx))->mas());
    check(str_contains($h2, 'Cerrar sesión') || str_contains($h2, 'Se déconnecter'), "{$rol}: Más");
}
entrarComo($pdo, 'sylvie@ejemplo.fr');
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check($r === '/app/feed', 'directiva no tiene agenda: va al feed');
[$h] = llamar(fn() => (new S\AuthController($ctx))->pronto('feed'));
check(str_contains($h, 'prochaine version') || str_contains($h, 'próxima versión'), 'feed: pronto');
[$h] = llamar(fn() => (new S\AuthController($ctx))->pronto('chat'));
check(str_contains($h, '404'), 'sección que el rol no tiene → 404');
S\Sesion::salir();
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check($r === '/app/entrar', 'sin sesión → entrar');
[$h] = llamar(fn() => (new S\AuthController($ctx))->landing());
check(str_contains($h, 'FCJ Orée de l’Autize'), 'landing lista el club público');
[$h] = llamar(fn() => (new S\AuthController($ctx))->clubPublico('fcj-oree-de-l-autize'));
check(str_contains($h, '06 23 60 52 30') && str_contains($h, 'Bienvenue au Football Club'), 'club público: teléfono y texto del club');

seccion('Asistencia');
$ev = $d['evento'];
entrarComo($pdo, 'daniela@ejemplo.fr');
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->responder($ev), conCsrf(['jugador' => $d['lucas'], 'respuesta' => 'duda']));
check($r === '/app/evento/' . $ev && $pdo->query("SELECT respuesta FROM sportiva_asistencias WHERE jugador_id = '{$d['lucas']}'")->fetchColumn() === 'duda', 'apoderada cambia la asistencia de su hijo');
$tomas = $pdo->query("SELECT id FROM sportiva_jugadores WHERE nombre = 'Tomás'")->fetchColumn();
llamar(fn() => (new S\AgendaController($ctx))->responder($ev), conCsrf(['jugador' => $tomas, 'respuesta' => 'voy']));
check($pdo->query("SELECT COUNT(*) FROM sportiva_asistencias WHERE jugador_id = '{$tomas}'")->fetchColumn() == 0, 'no puede responder por el hijo de otra familia');
llamar(fn() => (new S\AgendaController($ctx))->responder($ev), ['jugador' => $d['lucas'], 'respuesta' => 'no']);
check($pdo->query("SELECT respuesta FROM sportiva_asistencias WHERE jugador_id = '{$d['lucas']}'")->fetchColumn() === 'duda', 'sin CSRF no cambia');
llamar(fn() => (new S\AgendaController($ctx))->responder($ev), conCsrf(['jugador' => $d['lucas'], 'respuesta' => 'quizas']));
check($pdo->query("SELECT respuesta FROM sportiva_asistencias WHERE jugador_id = '{$d['lucas']}'")->fetchColumn() === 'duda', 'respuesta inválida se ignora');
$emma = $d['emma'];
llamar(fn() => (new S\AgendaController($ctx))->responder($ev), conCsrf(['jugador' => $emma, 'respuesta' => 'voy']));
check($pdo->query("SELECT COUNT(*) FROM sportiva_asistencias WHERE jugador_id = '{$emma}'")->fetchColumn() == 0, 'un hijo no convocado no responde');
entrarComo($pdo, 'lucas@ejemplo.fr');
llamar(fn() => (new S\AgendaController($ctx))->responder($ev), conCsrf(['jugador' => $d['lucas'], 'respuesta' => 'no']));
check($pdo->query("SELECT respuesta FROM sportiva_asistencias WHERE jugador_id = '{$d['lucas']}'")->fetchColumn() === 'duda', 'el jugador (hijo) es de solo lectura');
entrarComo($pdo, 'camila@ejemplo.fr');
llamar(fn() => (new S\AgendaController($ctx))->responder($ev), conCsrf(['jugador' => $tomas, 'respuesta' => 'no']));
check($pdo->query("SELECT respuesta FROM sportiva_asistencias WHERE jugador_id = '{$tomas}'")->fetchColumn() === 'no', 'la encargada puede marcar por un jugador');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($ev));
check(str_contains($h, 'Quién va') && str_contains($h, 'Editar'), 'la encargada ve asistencia y puede editar');
$r = $S['ev']->resumen($ev);
check($r['duda'] === 1 && $r['no'] === 1 && $r['sin'] === 4 && $r['total'] === 6, 'resumen: ' . json_encode($r));

seccion('Convocatorias: validación y permisos');
[$dd, $err] = $S['ev']->normalizar(['tipo' => 'partido', 'titulo' => 'X', 'fecha' => '2026-11-07', 'hora_partido' => '14:00', 'hora_cancha' => '15:00', 'lugar_id' => 'x']);
check(($err['hora_cancha'] ?? '') === 'evento.error.orden', 'cancha después del partido: error');
[$dd, $err] = $S['ev']->normalizar(['tipo' => 'partido', 'titulo' => 'X', 'fecha' => '2026-11-07', 'hora_partido' => '14:00', 'hay_encuentro' => '1', 'hora_encuentro' => '13:50', 'hora_cancha' => '13:15', 'lugar_id' => 'x']);
check(($err['hora_encuentro'] ?? '') === 'evento.error.orden', 'encuentro después de cancha: error');
[$dd, $err] = $S['ev']->normalizar(['tipo' => 'partido', 'titulo' => 'X', 'fecha' => '2026-11-07', 'hora_partido' => '14:00', 'cancha_antes' => '45', 'lugar_id' => 'x']);
check($err === [] && substr((string) $dd['hora_cancha'], 11, 5) === '13:15' && $dd['hora_encuentro'] === null, 'cancha se calcula (45 min antes); sin encuentro');
[, $err] = $S['ev']->normalizar(['tipo' => 'partido', 'titulo' => 'X', 'fecha' => '2026-11-07', 'hora_partido' => '14:00']);
check(isset($err['lugar_id']), 'sin lugar no se publica');
[, $err] = $S['ev']->normalizar(['tipo' => 'entreno', 'titulo' => '', 'fecha' => '', 'hora_partido' => '', 'lugar_id' => 'x']);
check(isset($err['titulo']) && isset($err['fecha']), 'título y fecha obligatorios');
// Crear desde el formulario (encargada), con lugar nuevo en línea y otra categoría convocada.
$u10 = $d['u10'];
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->crear(), conCsrf(['equipo_id' => $d['u12'], 'tipo' => 'partido', 'titulo' => 'U12 vs Aiffres', 'fecha' => '2026-11-14', 'hora_partido' => '15:00', 'cancha_antes' => '45', 'hay_encuentro' => '1', 'hora_encuentro' => '13:30', 'lugar_nombre' => 'Stade municipal (Aiffres)', 'lugar_lat' => '46.35', 'lugar_lon' => '-0.5', 'extra' => [$u10]]));
check($r !== null && str_starts_with($r, '/app/evento/'), 'se publica la convocatoria');
$nuevo = substr((string) $r, strlen('/app/evento/'));
$e2 = $S['ev']->buscar($nuevo);
check($e2 !== null && $e2['lugar'] === 'Stade municipal (Aiffres)' && substr((string) $e2['hora_cancha'], 11, 5) === '14:15' && substr((string) $e2['hora_encuentro'], 11, 5) === '13:30', 'lugar nuevo y horas guardadas');
check(count($S['ev']->convocados($nuevo)) === 7, 'convoca U12 + U10 (otras categorías): ' . count($S['ev']->convocados($nuevo)));
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->crear(), ['equipo_id' => $d['u12'], 'titulo' => 'Sin csrf']);
check($pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Sin csrf'")->fetchColumn() == 0, 'crear exige CSRF');
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->crear(), conCsrf(['equipo_id' => $d['u12'], 'tipo' => 'partido', 'titulo' => 'Sin lugar', 'fecha' => '2026-11-14', 'hora_partido' => '15:00']));
check($r === null && str_contains($h, 'Sin ubicación') && $pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Sin lugar'")->fetchColumn() == 0, 'sin lugar vuelve al formulario con el error');
entrarComo($pdo, 'pedro@ejemplo.fr');
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->crear(), conCsrf(['equipo_id' => $u10, 'tipo' => 'entreno', 'titulo' => 'Ajeno', 'fecha' => '2026-11-14', 'hora_partido' => '18:00', 'lugar_id' => $S['club']->equipos($d['club']) ? $pdo->query('SELECT id FROM sportiva_lugares LIMIT 1')->fetchColumn() : '']));
check($pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Ajeno'")->fetchColumn() == 0, 'un entrenador no crea eventos en un equipo que no entrena');
entrarComo($pdo, 'daniela@ejemplo.fr');
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->nuevo());
check($r === '/app/agenda', 'una familia no abre el formulario de convocatoria');
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->cancelar($nuevo), conCsrf());
check($S['ev']->buscar($nuevo)['estado'] === 'publicado', 'una familia no cancela');

seccion('Aislamiento entre clubes');
$otro = $S['club']->crear(['nombre' => 'Club Pampa', 'ciudad' => 'Poitiers', 'deporte' => 'voley', 'idioma' => 'fr']);
$otroEq = $S['club']->crearEquipo($otro, 'U14');
$ana = $S['pers']->crear('ana@ejemplo.fr', 'Ana', 'fr');
$S['pers']->dar($ana, $otro, 'encargado');
$S['pers']->asignarStaff($otroEq, $ana, 'encargado');
$S['pers']->dar($ana, $d['club'], 'apoderado');
entrarComo($pdo, 'ana@ejemplo.fr');
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check($r === '/app/elegir', 'con dos roles/clubes pide elegir');
[$h] = llamar(fn() => (new S\AuthController($ctx))->elegir());
check(str_contains($h, 'Club Pampa') && str_contains($h, 'FCJ'), 'lista las membresías');
$mem = (new S\PersonaService($pdo))->membresias($ana);
$mp = array_values(array_filter($mem, fn($m) => $m['club_id'] === $otro))[0];
[$h, $r] = llamar(fn() => (new S\AuthController($ctx))->elegir(), conCsrf(['membresia' => $mp['id']]));
check($r === '/app/agenda' && S\Sesion::club() === $otro, 'elige el club');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($ev));
check(str_contains($h, '404'), 'un evento de otro club no se ve (404)');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check(!str_contains($h, 'U12 vs Niort'), 'la agenda no mezcla clubes');
S\Sesion::elegir($otro, 'apoderado'); // rol que no tiene en ese club
[$h, $r] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check($r === '/app/elegir', 'un rol que no le corresponde en ese club se rechaza');

seccion('Idioma de la persona');
entrarComo($pdo, 'daniela@ejemplo.fr');
llamar(fn() => (new S\AuthController($ctx))->cambiarIdioma(), conCsrf(['idioma' => 'fr']));
[$h] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check(str_contains($h, 'Bonjour, Daniela') && str_contains($h, 'Samedi') || str_contains($h, 'SAM'), 'la agenda sale en francés');
llamar(fn() => (new S\AuthController($ctx))->cambiarIdioma(), conCsrf(['idioma' => 'zz']));
check($S['pers']->porCorreo('daniela@ejemplo.fr')['idioma'] === 'fr', 'idioma inexistente se ignora');
llamar(fn() => (new S\AuthController($ctx))->cambiarIdioma(), conCsrf(['idioma' => 'es']));

seccion('Cifrado y ajustes');
$tmp = sys_get_temp_dir() . '/sportiva-pruebas-' . getmypid();
$aj = new S\Ajustes($pdo);
$aj->guardarSecreto('smtp_clave', 'secreta-123');
check($aj->get('smtp_clave') !== 'secreta-123' && str_starts_with($aj->get('smtp_clave'), 'v1:'), 'la clave SMTP se guarda cifrada');
check($aj->secreto('smtp_clave') === 'secreta-123', 'se descifra');
$aj->set('smtp_host', 'smtp.ejemplo.fr');
check($aj->smtp()['host'] === 'smtp.ejemplo.fr' && $aj->smtp()['clave'] === 'secreta-123', 'ajustes SMTP');

seccion('Mapa');
check(S\Mapa::valido('46.480236', '-0.599575') && !S\Mapa::valido('91', '0') && !S\Mapa::valido('x', '1'), 'coordenadas válidas');
$html = S\Mapa::html('46.480236', '-0.599575', 16, 'Estadio');
check(substr_count($html, '<img') === 12 && str_contains($html, '/16/') && str_contains($html, 'OpenStreetMap'), 'mapa de 12 teselas con atribución');
check(str_contains(S\Mapa::enlaces('46.48', '-0.59')['waze'], 'll=46.48,-0.59'), 'enlace de Waze');

echo "\n\n";
if ($GLOBALS['fallas']) {
    echo count($GLOBALS['fallas']) . " fallas:\n - " . implode("\n - ", $GLOBALS['fallas']) . "\n";
    exit(1);
}
echo $GLOBALS['ok'] . " pruebas OK\n";
