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
check(str_contains($h, 'Citados') && str_contains($h, 'Editar'), 'la encargada ve asistencia y puede editar');
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

seccion('Plantel y familias');
$cid = $d['club'];
$comoRol = function (string $email, string $rol) use ($pdo, $cid): void { entrarComo($pdo, $email); S\Sesion::elegir($cid, $rol); };
$pl = new S\PlantelService($pdo);
$u12 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U12'")->fetchColumn();
$u10 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U10'")->fetchColumn();
// El entrenador carga un jugador con su titular.
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->inicio());
check(str_contains($h, 'Plantel') && str_contains($h, 'Lucas'), 'el entrenador ve el plantel de su equipo');
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->crear(), conCsrf(['nombre' => 'Nico', 'nacimiento' => '2014-01-10', 'equipo_id' => $u12, 'nombre_adulto' => 'Rosa Soto', 'email' => 'ROSA@ejemplo.fr', 'telefono' => '0600000009']));
check($r !== null && str_contains($r, '/app/plantel/jugador/'), 'carga jugador + titular');
$rosa = $S['pers']->porCorreo('rosa@ejemplo.fr');
$nico = $pdo->query("SELECT id FROM sportiva_jugadores WHERE nombre = 'Nico'")->fetchColumn();
check($rosa !== null && $pl->esTitular($rosa['id'], $nico), 'el apoderado queda como titular del jugador');
check(count($S['pers']->membresias($rosa['id'])) === 1 && $S['pers']->membresias($rosa['id'])[0]['rol'] === 'apoderado', 'recibe rol de apoderado en el club');
[$h] = llamar(fn() => (new S\PlantelController($ctx))->crear(), conCsrf(['nombre' => '', 'equipo_id' => $u12, 'nombre_adulto' => 'X', 'email' => 'no-es-correo']));
check(str_contains($h, 'Escribe un nombre') && str_contains($h, 'correo válido'), 'valida nombre y correo');
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->crear(), ['nombre' => 'Sin csrf', 'equipo_id' => $u12, 'nombre_adulto' => 'Y', 'email' => 'y@ejemplo.fr']);
check($r === '/app/plantel' && $pdo->query("SELECT COUNT(*) FROM sportiva_jugadores WHERE nombre = 'Sin csrf'")->fetchColumn() == 0, 'sin CSRF no crea');
// Otro hijo con el mismo correo: se reutiliza la persona.
llamar(fn() => (new S\PlantelController($ctx))->crear(), conCsrf(['nombre' => 'Bea', 'equipo_id' => $u12, 'nombre_adulto' => 'Rosa Soto', 'email' => 'rosa@ejemplo.fr']));
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_personas WHERE email = 'rosa@ejemplo.fr'")->fetchColumn() === 1 && count($pl->hijosDeTitular($rosa['id'], $cid)) === 2, 'mismo titular, dos hijos, una sola persona');
// Un entrenador no gestiona equipos de otros.
$ajeno = $S['club']->crearEquipo($cid, 'U16', '2026-2027');
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->crear(), conCsrf(['nombre' => 'Colado', 'equipo_id' => $ajeno, 'nombre_adulto' => 'Z', 'email' => 'z@ejemplo.fr']));
check(str_contains($h, 'Elige un equipo') && $pdo->query("SELECT COUNT(*) FROM sportiva_jugadores WHERE nombre = 'Colado'")->fetchColumn() == 0, 'no carga en un equipo que no entrena');

// El titular pide agregar un adulto: queda pendiente.
$comoRol('rosa@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\FamiliaController($ctx))->inicio());
check(str_contains($h, 'Mi familia') && str_contains($h, 'Nico') && str_contains($h, 'Bea') && str_contains($h, 'Agregar a otro adulto'), 'el titular ve su familia con los botones de agregar');
[$h, $r] = llamar(fn() => (new S\FamiliaController($ctx))->guardarAdulto(), conCsrf(['nombre' => 'Abuela Elena', 'email' => 'elena@ejemplo.fr']));
check($r === '/app/familia' && $S['pers']->porCorreo('elena@ejemplo.fr') === null, 'el adulto no existe hasta que el entrenador lo apruebe');
check($pl->pendientesDe($rosa['id'], $cid) === 1, 'queda una solicitud pendiente');
[$h] = llamar(fn() => (new S\FamiliaController($ctx))->guardarAdulto(), conCsrf(['nombre' => 'Otra vez', 'email' => 'elena@ejemplo.fr']));
check(str_contains($h, 'ya está en la familia'), 'no repite un correo pendiente');
[$h] = llamar(fn() => (new S\FamiliaController($ctx))->guardarHijo(), conCsrf(['nombre' => 'Leo', 'nacimiento' => '2016-02-02', 'equipo_id' => $u10]));
check($pl->pendientesDe($rosa['id'], $cid) === 2 && $pdo->query("SELECT COUNT(*) FROM sportiva_jugadores WHERE nombre = 'Leo'")->fetchColumn() == 0, 'el hijo también queda pendiente');
// Otra familia no puede tocar la de Rosa ni editar sus hijos.
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h, $r] = llamar(fn() => (new S\FamiliaController($ctx))->editarHijo($nico));
check($r === '/app/familia', 'otra familia no edita hijos ajenos');
[$h, $r] = llamar(fn() => (new S\FamiliaController($ctx))->actualizarHijo($nico), conCsrf(['nombre' => 'Hackeado']));
check($pdo->query("SELECT nombre FROM sportiva_jugadores WHERE id = '{$nico}'")->fetchColumn() === 'Nico', 'no cambia el nombre de un hijo ajeno');
// El entrenador aprueba.
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => (new S\PlantelController($ctx))->solicitudes());
check(str_contains($h, 'Abuela Elena') && str_contains($h, 'Leo'), 'el entrenador ve las solicitudes');
$sols = $pl->pendientes($cid, [$u12, $u10]);
$sAdulto = array_values(array_filter($sols, fn($x) => $x['tipo'] === 'adulto'))[0]['id'];
$sHijo = array_values(array_filter($sols, fn($x) => $x['tipo'] === 'hijo'))[0]['id'];
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->resolver($sAdulto, 'aprobar'), conCsrf());
$elena = $S['pers']->porCorreo('elena@ejemplo.fr');
check($elena !== null && count($pl->adultos($nico)) === 2 && count($pl->adultos($pdo->query("SELECT id FROM sportiva_jugadores WHERE nombre = 'Bea'")->fetchColumn())) === 2, 'al aprobar, el adulto queda vinculado a todos los hijos de la familia');
check(!$pl->esTitular($elena['id'], $nico), 'el adulto agregado no es titular');
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->resolver($sHijo, 'aprobar'), conCsrf());
$leo = $pdo->query("SELECT id FROM sportiva_jugadores WHERE nombre = 'Leo'")->fetchColumn();
check($leo !== false && $pl->esTitular($rosa['id'], $leo) && count($pl->adultos($leo)) === 2, 'al aprobar el hijo, el titular y los otros adultos lo ven');
check($pl->pendientesDe($rosa['id'], $cid) === 0, 'ya no hay pendientes');
// Un adulto no titular no puede agregar ni editar.
$comoRol('elena@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\FamiliaController($ctx))->inicio());
check(str_contains($h, 'Nico') && !str_contains($h, 'Agregar a otro adulto'), 'un adulto no titular ve la familia sin botones de agregar');
[$h, $r] = llamar(fn() => (new S\FamiliaController($ctx))->guardarAdulto(), conCsrf(['nombre' => 'Intruso', 'email' => 'intruso@ejemplo.fr']));
check($r === '/app/familia' && $pl->pendientesDe($elena['id'], $cid) === 0, 'un adulto no titular no puede pedir agregar adultos');
// El titular edita a su hijo y quita un adulto; el entrenador de otro club no ve nada.
$comoRol('rosa@ejemplo.fr', 'apoderado');
llamar(fn() => (new S\FamiliaController($ctx))->actualizarHijo($nico), conCsrf(['nombre' => 'Nicolás', 'nacimiento' => '2014-01-11']));
// Foto del niño: la sube el titular (o el entrenador), se recorta y solo la ve gente del club.
$png = sys_get_temp_dir() . '/sportiva-foto-' . getmypid() . '.png';
$gd = imagecreatetruecolor(640, 480); imagefill($gd, 0, 0, imagecolorallocate($gd, 200, 90, 40)); imagepng($gd, $png);
$_FILES = ['foto' => ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)]];
llamar(fn() => (new S\FotoController($ctx))->subir($nico, 'familia'), conCsrf());
$ruta = S\FotoService::ruta($nico);
check(is_file($ruta) && getimagesize($ruta)[0] === 320 && getimagesize($ruta)[2] === IMAGETYPE_JPEG, 'la foto se guarda recortada en JPEG de 320 px');
check($pl->jugador($nico)['foto'] !== null && str_contains(S\Iconos::avatar($pl->jugador($nico)), '/app/foto/' . $nico), 'el avatar usa la foto');
check(!str_contains(S\Iconos::avatar(['id' => 'x', 'nombre' => 'Zoe', 'foto' => null]), '<img') && str_contains(S\Iconos::avatar(['id' => 'x', 'nombre' => 'Zoe', 'foto' => null]), '>Z<'), 'sin foto usa la inicial');
file_put_contents($png, 'esto no es una imagen');
$_FILES['foto']['size'] = filesize($png);
llamar(fn() => (new S\FotoController($ctx))->subir($nico, 'familia'), conCsrf());
check(getimagesize($ruta)[0] === 320, 'un archivo que no es imagen no reemplaza la foto');
$_FILES = [];
// Otra familia no puede cambiar la foto.
$comoRol('daniela@ejemplo.fr', 'apoderado');
$_FILES = ['foto' => ['name' => 'a.png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => 10]];
llamar(fn() => (new S\FotoController($ctx))->quitar($nico, 'familia'), conCsrf());
check(is_file($ruta), 'otra familia no quita la foto de un niño ajeno');
$_FILES = [];
$comoRol('rosa@ejemplo.fr', 'apoderado');
llamar(fn() => (new S\FotoController($ctx))->quitar($nico, 'familia'), conCsrf());
check(!is_file($ruta) && $pl->jugador($nico)['foto'] === null, 'el titular quita la foto');
@unlink($png);
check($pdo->query("SELECT nombre FROM sportiva_jugadores WHERE id = '{$nico}'")->fetchColumn() === 'Nicolás', 'el titular edita a su hijo');
llamar(fn() => (new S\FamiliaController($ctx))->quitarAdulto($elena['id']), conCsrf());
check(count($pl->adultos($nico)) === 1 && !in_array('apoderado', array_column($S['pers']->membresias($elena['id']), 'rol'), true), 'el titular quita a un adulto y se va su acceso de apoderado');
// Elegir una familia que ya existe: sin escribir al titular otra vez.
$comoRol('pedro@ejemplo.fr', 'entrenador');
$antes = (int) $pdo->query('SELECT COUNT(*) FROM sportiva_personas')->fetchColumn();
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->crear(), conCsrf(['nombre' => 'Hermano', 'equipo_id' => $u12, 'familia' => $rosa['id']]));
$hermano = $pdo->query("SELECT id FROM sportiva_jugadores WHERE nombre = 'Hermano'")->fetchColumn();
check($hermano !== false && $pl->esTitular($rosa['id'], $hermano) && (int) $pdo->query('SELECT COUNT(*) FROM sportiva_personas')->fetchColumn() === $antes, 'un hermano se agrega a la familia existente sin crear personas');
[$h] = llamar(fn() => (new S\PlantelController($ctx))->crear(), conCsrf(['nombre' => 'Fantasma', 'equipo_id' => $u12, 'familia' => 'no-existe']));
check(str_contains($h, 'no existe en el club') && $pdo->query("SELECT COUNT(*) FROM sportiva_jugadores WHERE nombre = 'Fantasma'")->fetchColumn() == 0, 'una familia inexistente se rechaza');
[$h] = llamar(fn() => (new S\PlantelController($ctx))->nuevo(), [], ['familia' => $rosa['id']]);
check(str_contains($h, 'Rosa Soto') && str_contains($h, 'Familia nueva'), 'el formulario ofrece las familias del club');
check(in_array('Rosa Soto', array_column($pl->familias($cid), 'nombre'), true), 'la lista de familias incluye a los titulares');
// El entrenador no puede resolver solicitudes de equipos que no gestiona.
$sx = $pl->solicitar($cid, 'adulto', $rosa['id'], ['nombre' => 'Zeta', 'email' => 'zeta@ejemplo.fr']);
$S['pers']->dar($S['pers']->crear('otro.coach@ejemplo.fr', 'Otro Coach'), $cid, 'entrenador');
$oc = $S['pers']->porCorreo('otro.coach@ejemplo.fr');
$S['pers']->asignarStaff($ajeno, $oc['id']);
$comoRol('otro.coach@ejemplo.fr', 'entrenador');
llamar(fn() => (new S\PlantelController($ctx))->resolver($sx, 'aprobar'), conCsrf());
check($pl->solicitud($sx)['estado'] === 'pendiente', 'un entrenador de otro equipo no aprueba solicitudes ajenas');
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->ficha($nico));
check(str_contains($h, '404'), 'la ficha de un jugador ajeno da 404');
// Un apoderado no entra al plantel.
$comoRol('rosa@ejemplo.fr', 'apoderado');
[$h, $r] = llamar(fn() => (new S\PlantelController($ctx))->inicio());
check($r === '/app/agenda', 'un apoderado no ve el plantel del entrenador');

seccion('Turnos de traslado');
$tu = new S\TurnoService($pdo);
$evT = $d['evento'];
$dani = $S['pers']->porCorreo('daniela@ejemplo.fr')['id'];
$ines = $S['pers']->porCorreo('ines.garcia@ejemplo.fr')['id'];
$comoRol('daniela@ejemplo.fr', 'apoderado');
llamar(fn() => (new S\TurnoController($ctx))->agregar($evT), conCsrf(['familia' => $dani]));
check($tu->delEvento($evT) === [], 'un apoderado no puede asignar turnos');
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => (new S\TurnoController($ctx))->agregar($evT), conCsrf(['familia' => $dani]));
check(count($tu->delEvento($evT)) === 1 && $tu->delEvento($evT)[0]['estado'] === 'confirmado' && in_array('Lucas', $tu->delEvento($evT)[0]['hijos'], true), 'el entrenador anota una familia de turno');
llamar(fn() => (new S\TurnoController($ctx))->agregar($evT), conCsrf(['familia' => $rosa['id']]));
check(count($tu->delEvento($evT)) === 1, 'no se agrega una familia sin hijos convocados');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($evT));
check(str_contains($h, 'Te toca llevar') && str_contains($h, 'Los cupos se reparten'), 'la familia de turno ve «Te toca llevar» y que no hay cupos');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check(str_contains($h, 'Te toca llevar'), 'la agenda marca el turno');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($evT));
check(str_contains($h, 'Yo también llevo') && !str_contains($h, 'Te toca llevar'), 'otra familia puede pedir sumarse');
llamar(fn() => (new S\TurnoController($ctx))->pedir($evT), conCsrf());
check($tu->delEvento($evT)[1]['estado'] === 'pendiente' || ($tu->delEvento($evT)[0]['estado'] === 'pendiente'), '«Yo también llevo» queda pendiente');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($evT));
check(str_contains($h, 'Esperando al entrenador') && !str_contains($h, 'Yo también llevo'), 'mientras espera no puede pedir otra vez');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
llamar(fn() => (new S\TurnoController($ctx))->resolver($evT, $ines, 'aceptar'), conCsrf());
check((array_count_values(array_column($tu->delEvento($evT), 'estado'))['pendiente'] ?? 0) === 1, 'una familia no se acepta sola');
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => (new S\TurnoController($ctx))->resolver($evT, $ines, 'aceptar'), conCsrf());
check(array_column($tu->delEvento($evT), 'estado') === ['confirmado', 'confirmado'], 'el entrenador acepta la solicitud');
llamar(fn() => (new S\TurnoController($ctx))->resolver($evT, $ines, 'quitar'), conCsrf());
check(count($tu->delEvento($evT)) === 1, 'el entrenador quita una familia del turno');
// Repartir entre los próximos partidos, pareja por pareja, empezando por quien menos ha llevado.
$f1 = date('Y-m-d', strtotime('+20 days')); $f2 = date('Y-m-d', strtotime('+27 days'));
foreach ([$f1, $f2] as $f) {
    [$dd] = $S['ev']->normalizar(['tipo' => 'partido', 'titulo' => 'Partido ' . $f, 'fecha' => $f, 'hora_partido' => '15:00', 'cancha_antes' => '45']);
    $S['ev']->crear($cid, $u12, $dd, null);
}
$antes = (int) $pdo->query('SELECT COUNT(*) FROM sportiva_turnos')->fetchColumn();
$n = $tu->repartir($cid, $u12, 2, date('Y-m-d H:i:s'), null);
$nuevos = $pdo->query("SELECT ev.titulo, t.familia_id FROM sportiva_turnos t JOIN sportiva_eventos ev ON ev.id = t.evento_id WHERE ev.titulo LIKE 'Partido 20%' ORDER BY ev.inicio")->fetchAll();
check($n >= 2 && count(array_filter($nuevos, fn($x) => $x['titulo'] === 'Partido ' . $f1)) === 2, 'se reparten dos familias por partido');
$fam1 = array_column(array_filter($nuevos, fn($x) => $x['titulo'] === 'Partido ' . $f1), 'familia_id');
$fam2 = array_column(array_filter($nuevos, fn($x) => $x['titulo'] === 'Partido ' . $f2), 'familia_id');
check($fam1 !== [] && $fam2 !== [] && array_intersect($fam1, $fam2) === [], 'en el segundo partido llevan otras familias (reparto parejo)');
check($tu->repartir($cid, $u12, 2, date('Y-m-d H:i:s'), null) === 0, 'repartir otra vez no pisa lo ya asignado');
[$h] = llamar(fn() => (new S\TurnoController($ctx))->panel());
check(str_contains($h, 'Repartir turnos') && str_contains($h, 'Partido ' . $f1), 'el panel de turnos del entrenador');
// «Asignar» en un partido ya creado: completa con las familias que menos han llevado.
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$ddp] = $S['ev']->normalizar(['tipo' => 'partido', 'titulo' => 'Partido asignar', 'fecha' => date('Y-m-d', strtotime('+40 days')), 'hora_partido' => '16:00', 'cancha_antes' => '45']);
$evA = $S['ev']->crear($cid, $u12, $ddp, null);
[$h, $r] = llamar(fn() => (new S\TurnoController($ctx))->asignar($evA), conCsrf(['por_partido' => '2', 'volver' => 'panel']));
check($r === '/app/turnos' && count($tu->delEvento($evA)) === 2, 'Asignar completa el partido con dos familias');
llamar(fn() => (new S\TurnoController($ctx))->asignar($evA), conCsrf(['por_partido' => '2']));
check(count($tu->delEvento($evA)) === 2, 'Asignar otra vez no repite familias');
llamar(fn() => (new S\TurnoController($ctx))->asignar($evA), conCsrf(['por_partido' => '3']));
check(count($tu->delEvento($evA)) === 3, 'se puede subir a tres familias');
$comoRol('daniela@ejemplo.fr', 'apoderado');
llamar(fn() => (new S\TurnoController($ctx))->asignar($evA), conCsrf(['por_partido' => '4']));
check(count($tu->delEvento($evA)) === 3, 'una familia no puede asignar');
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => (new S\TurnoController($ctx))->panel());
check(substr_count($h, 'turnos/asignar') >= 2 && str_contains($h, 'Asignar'), 'el panel de turnos tiene el botón Asignar en cada partido');

seccion('Feed');
$fs = new S\FeedService($pdo);
$camila = 'camila@ejemplo.fr';
$ajenoU10 = $u10;
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h, $r] = llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => $u12, 'titulo' => 'Reglas', 'texto' => 'Llegar 15 minutos antes.', 'fijado' => '1']));
$post1 = $pdo->query("SELECT id FROM sportiva_posts WHERE titulo = 'Reglas'")->fetchColumn();
check($post1 !== false && $r !== null, 'el entrenador publica en su equipo');
[$h] = llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => 'club', 'texto' => 'Para todos']));
check(str_contains($h, 'Elige para quién') && $pdo->query("SELECT COUNT(*) FROM sportiva_posts WHERE texto = 'Para todos'")->fetchColumn() == 0, 'un entrenador no publica para todo el club');
[$h] = llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => $u10, 'texto' => 'Otro equipo']));
check($pdo->query("SELECT COUNT(*) FROM sportiva_posts WHERE texto = 'Otro equipo'")->fetchColumn() == 0, 'ni en un equipo que no entrena');
[$h] = llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => $u12, 'texto' => '   ']));
check(str_contains($h, 'Escribe el mensaje'), 'valida que haya mensaje');
$comoRol($camila, 'encargado');
llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => 'club', 'titulo' => 'Asamblea', 'texto' => 'Sábado 10:00 en el club.']));
llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => $u10, 'titulo' => 'Solo U10', 'texto' => 'Entrenamiento movido.']));
$postClub = $pdo->query("SELECT id FROM sportiva_posts WHERE titulo = 'Asamblea'")->fetchColumn();
$postU10 = $pdo->query("SELECT id FROM sportiva_posts WHERE titulo = 'Solo U10'")->fetchColumn();
check($postClub !== false && $postU10 !== false, 'el encargado publica para el club y para cualquier equipo');
$comoRol('sylvie@ejemplo.fr', 'directiva');
llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => 'club', 'texto' => 'Aviso de la directiva']));
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_posts WHERE texto = 'Aviso de la directiva'")->fetchColumn() === 1, 'la directiva publica para el club');
// Las familias leen según los equipos de sus hijos.
$comoRol('daniela@ejemplo.fr', 'apoderado'); // hijos en U12 y U10
[$h] = llamar(fn() => (new S\FeedController($ctx))->inicio());
check(str_contains($h, 'Reglas') && str_contains($h, 'Solo U10'), 'una familia con hijos en dos equipos ve los dos');
check(str_contains($h, 'Fijado'), 'lo fijado se marca');
[$h] = llamar(fn() => (new S\FeedController($ctx))->inicio(), [], ['v' => 'club']);
check(str_contains($h, 'Asamblea') && !str_contains($h, 'Solo U10'), 'la vista Club solo trae avisos del club');
[$h] = llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => $u12, 'texto' => 'Intento']));
check($pdo->query("SELECT COUNT(*) FROM sportiva_posts WHERE texto = 'Intento'")->fetchColumn() == 0, 'una familia no publica');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado'); // solo U12
[$h] = llamar(fn() => (new S\FeedController($ctx))->inicio());
check(str_contains($h, 'Reglas') && !str_contains($h, 'Solo U10'), 'no se ven los avisos de otros equipos');
[$h] = llamar(fn() => (new S\FeedController($ctx))->ver($postU10));
check(str_contains($h, '404'), 'un aviso de otro equipo da 404');
// Comentarios.
[$h] = llamar(fn() => (new S\FeedController($ctx))->comentar($post1), conCsrf(['texto' => '']));
check(str_contains($h, 'Escribe un comentario'), 'no acepta comentarios vacíos');
llamar(fn() => (new S\FeedController($ctx))->comentar($post1), conCsrf(['texto' => 'Gracias, ahí estaremos']));
check(count($fs->comentarios($post1)) === 1, 'una familia comenta');
$cid = $fs->comentarios($post1)[0]['id'];
$comoRol('daniela@ejemplo.fr', 'apoderado');
llamar(fn() => (new S\FeedController($ctx))->borrarComentario($post1, $cid), conCsrf());
check(count($fs->comentarios($post1)) === 1, 'otra familia no borra comentarios ajenos');
$comoRol('lucas@ejemplo.fr', 'jugador');
llamar(fn() => (new S\FeedController($ctx))->comentar($post1), conCsrf(['texto' => 'hola']));
check(count($fs->comentarios($post1)) === 1, 'el jugador (hijo) solo lee');
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => (new S\FeedController($ctx))->borrarComentario($post1, $cid), conCsrf());
check(count($fs->comentarios($post1)) === 0, 'el entrenador del equipo modera comentarios');
llamar(fn() => (new S\FeedController($ctx))->fijar($post1), conCsrf());
check((int) $fs->post($post1)['fijado'] === 0, 'el autor puede quitar lo fijado');
$comoRol('daniela@ejemplo.fr', 'apoderado');
llamar(fn() => (new S\FeedController($ctx))->borrar($post1), conCsrf());
check($fs->post($post1) !== null, 'una familia no borra publicaciones');
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => (new S\FeedController($ctx))->borrar($post1), conCsrf());
check($fs->post($post1) === null, 'el autor borra su publicación');

seccion('Consultas privadas');
$cs = new S\ConsultaService($pdo);
$lucas = $d['lucas'];
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->crear(), conCsrf(['jugador_id' => $nico, 'asunto' => 'Hola', 'texto' => 'Hola']));
check(str_contains($h, 'Elige a quién') && $pdo->query('SELECT COUNT(*) FROM sportiva_consultas')->fetchColumn() == 0, 'no se pregunta por hijos ajenos');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->crear(), conCsrf(['jugador_id' => $lucas, 'asunto' => '', 'texto' => '']));
check(str_contains($h, 'Escribe un asunto') && str_contains($h, 'Escribe el mensaje'), 'valida asunto y mensaje');
[$h, $r] = llamar(fn() => (new S\ConsultaController($ctx))->crear(), conCsrf(['jugador_id' => $lucas, 'evento_id' => $evT, 'asunto' => '¿Botella?', 'texto' => '¿Hay que llevar botella?']));
$con = $pdo->query('SELECT id FROM sportiva_consultas')->fetchColumn();
check($con !== false && $r === '/app/consulta/' . $con, 'la familia crea una consulta');
check($cs->buscar($con)['evento'] !== null, 'queda ligada al evento');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->ver($con));
check(str_contains($h, '404'), 'otra familia no ve la consulta');
$comoRol('otro.coach@ejemplo.fr', 'entrenador'); // entrena U16
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->ver($con));
check(str_contains($h, '404'), 'un entrenador de otro equipo no la ve');
$comoRol('sylvie@ejemplo.fr', 'directiva');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->ver($con));
check(str_contains($h, '404'), 'la directiva no ve consultas deportivas');
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->lista());
check(str_contains($h, '¿Botella?') && str_contains($h, 'Nueva'), 'el entrenador la ve como pendiente y nueva');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->ver($con));
check(str_contains($h, 'Hay que llevar botella') && (int) $cs->buscar($con)['nueva_staff'] === 0, 'al abrirla queda leída para el entrenador');
llamar(fn() => (new S\ConsultaController($ctx))->responder($con), conCsrf(['texto' => 'Sí, y camiseta blanca.']));
check($cs->buscar($con)['ultimo_de'] === 'staff' && (int) $cs->buscar($con)['nueva_familia'] === 1 && count($cs->mensajes($con)) === 2, 'el entrenador responde');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->lista());
check(str_contains($h, 'No hay consultas por responder'), 'respondida, sale de los pendientes');
$comoRol($camila, 'encargado');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->ver($con));
check(str_contains($h, 'camiseta blanca'), 'el encargado puede ver todas las consultas del club');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->actividad());
check(str_contains($h, 'Respondida') && str_contains($h, 'Nueva') && str_contains($h, 'U12 vs Niort'), 'Mi actividad muestra la consulta respondida y las asistencias');
llamar(fn() => (new S\ConsultaController($ctx))->ver($con));
check((int) $cs->buscar($con)['nueva_familia'] === 0, 'al abrirla queda leída para la familia');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->responder($con), conCsrf(['texto' => '']));
check(str_contains($h, 'Escribe el mensaje') && count($cs->mensajes($con)) === 2, 'no se envían mensajes vacíos');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($evT));
check(str_contains($h, 'Preguntar al entrenador'), 'el evento ofrece preguntar al entrenador');

seccion('Avisos, notificaciones y cron');
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id']; // (arriba se reutilizó $cid para un comentario)
use TypeDock\Plugin\Sportiva\WebPush as W;
// Cifrado Web Push: lo que cifra el servidor lo descifra el navegador (RFC 8291).
$navegador = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$uaPub = W::puntoPublico($navegador);
$authSec = random_bytes(16);
$cuerpo = W::cifrar('{"t":"Hola ñ"}', $uaPub, $authSec);
$sal = substr($cuerpo, 0, 16); $idl = ord($cuerpo[20]); $asPub = substr($cuerpo, 21, $idl); $cif = substr($cuerpo, 21 + $idl);
$pemAs = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $asPub), 64, "\n") . "-----END PUBLIC KEY-----\n";
$comp = openssl_pkey_derive($pemAs, $navegador, 32);
$ikm = hash_hkdf('sha256', $comp, 32, "WebPush: info\0" . $uaPub . $asPub, $authSec);
$cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sal);
$non = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sal);
$plano = openssl_decrypt(substr($cif, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $non, substr($cif, -16));
check($plano === '{"t":"Hola ñ"}' . "\x02" && unpack('N', substr($cuerpo, 16, 4))[1] === 4096 && $idl === 65, 'el mensaje push se cifra (aes128gcm) y el navegador lo descifra');
[$pemV, $pubV] = W::generarClaves();
$jwt = W::jwt('https://push.example.org/xyz', 'mailto:a@b.cl', $pemV);
[$jh, $jc, $jf] = explode('.', $jwt);
$raw = W::deb64($jf); $rr = ltrim(substr($raw, 0, 32), "\0"); $ss = ltrim(substr($raw, 32), "\0");
$rr = (ord($rr[0]) > 127 ? "\0" : '') . $rr; $ss = (ord($ss[0]) > 127 ? "\0" : '') . $ss;
$seq = "\x02" . chr(strlen($rr)) . $rr . "\x02" . chr(strlen($ss)) . $ss;
check(openssl_verify("$jh.$jc", "\x30" . chr(strlen($seq)) . $seq, openssl_pkey_get_details(openssl_pkey_get_private($pemV))['key'], OPENSSL_ALGO_SHA256) === 1 && json_decode(W::deb64($jc), true)['aud'] === 'https://push.example.org', 'la firma VAPID (ES256) es válida y apunta al servicio push');

$av = new S\AvisoService($pdo);
$nt = new S\Notificador($ctx, $pdo);
$estadio = $pdo->query("SELECT id FROM sportiva_lugares WHERE club_id = '{$cid}' LIMIT 1")->fetchColumn();
$ines = $S['pers']->porCorreo('ines.garcia@ejemplo.fr')['id'];
$comoRol('pedro@ejemplo.fr', 'entrenador');
$pdo->prepare("UPDATE sportiva_personas SET idioma = 'es' WHERE id = ?")->execute([$dani]);
$antes = $av->sinLeer($dani, $cid);
$pedroId = $S['pers']->porCorreo('pedro@ejemplo.fr')['id'];
$antesPedro = $av->sinLeer($pedroId, $cid);
llamar(fn() => (new S\AgendaController($ctx))->crear(), conCsrf(['tipo' => 'partido', 'equipo_id' => $u12, 'titulo' => 'Partido de aviso', 'fecha' => date('Y-m-d', strtotime('+50 days')), 'hora_partido' => '15:00', 'cancha_antes' => '45', 'lugar_id' => $estadio]));
$evN = $pdo->query("SELECT id FROM sportiva_eventos WHERE titulo = 'Partido de aviso'")->fetchColumn();
check($av->sinLeer($dani, $cid) === $antes + 1, 'una convocatoria nueva avisa a las familias de los convocados');
check($av->sinLeer($pedroId, $cid) === $antesPedro, 'quien la crea no recibe su propio aviso');
$tit = fn($p) => implode(' | ', $pdo->query("SELECT titulo FROM sportiva_avisos WHERE persona_id = '{$p}' ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_COLUMN));
check(str_contains($tit($dani), 'Nueva convocatoria: Partido de aviso') && str_contains($tit($ines), 'Nouvelle convocation : Partido de aviso'), 'cada persona recibe el aviso en su idioma (es / fr)');
// Cambios y cancelación.
$ev0 = $S['ev']->buscar($evN);
llamar(fn() => (new S\AgendaController($ctx))->actualizar($evN), conCsrf(['tipo' => 'partido', 'equipo_id' => $u12, 'titulo' => 'Partido de aviso', 'fecha' => date('Y-m-d', strtotime('+50 days')), 'hora_partido' => '16:30', 'cancha_antes' => '45', 'lugar_id' => $estadio]));
check(str_contains($tit($dani), 'Cambió la hora: Partido de aviso'), 'un cambio de hora avisa');
llamar(fn() => (new S\AgendaController($ctx))->cancelar($evN), conCsrf());
check(str_contains($tit($dani), 'Se canceló: Partido de aviso'), 'cancelar un evento avisa');
// Preferencias y cola de envío.
check($av->pref($dani, 'consultas')['correo'] === true && $av->pref($dani, 'agenda')['correo'] === false && $av->pref($dani, 'agenda')['push'] === true, 'preferencias por defecto: correo solo en consultas y plantel');
$av->guardarPrefs($dani, ['push' => ['agenda' => '1'], 'correo' => ['agenda' => '1']]);
check($av->pref($dani, 'agenda')['correo'] === true && $av->pref($dani, 'feed')['push'] === false, 'se guardan las preferencias');
$b64 = fn($x) => W::b64($x);
check(!$av->suscribir($dani, 'http://inseguro.example/x', $b64($uaPub), $b64($authSec)), 'solo acepta suscripciones https');
check($av->suscribir($dani, 'https://127.0.0.1:9/push/abc', $b64($uaPub), $b64($authSec)) && $av->tieneSuscripcion($dani), 'guarda la suscripción push del dispositivo');
$nt->avisar([$dani], $cid, 'agenda', fn(S\I18n $i) => [$i->t('aviso.recordatorio', ['titulo' => 'X']), 'cuerpo'], '/app/agenda');
$enCola = $av->enCola();
check(count($enCola) >= 1 && (int) $enCola[count($enCola) - 1]['push_pendiente'] === 1 && (int) $enCola[count($enCola) - 1]['correo_pendiente'] === 1, 'con push y correo activos el aviso queda en cola para los dos');
$r = $nt->entregar(100);
check($av->cantidadEnCola() === 0 && $r['correo'] >= 1, 'entregar vacía la cola (sin red el push no llega pero no rompe) y manda los correos');
// Campana y lectura.
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check(str_contains($h, 'class="bell"') && preg_match('/class="bell"[^>]*>.*?<b>\d+\+?<\/b>/s', $h) === 1, 'la campana muestra el contador de avisos sin leer');
[$h] = llamar(fn() => (new S\AvisosController($ctx))->lista());
check(str_contains($h, 'Nueva convocatoria') && str_contains($h, 'Se canceló'), 'la lista de avisos');
$id1 = $pdo->query("SELECT id FROM sportiva_avisos WHERE persona_id = '{$dani}' AND leido_en IS NULL LIMIT 1")->fetchColumn();
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
[$h, $r] = llamar(fn() => (new S\AvisosController($ctx))->abrir($id1));
check($r === '/app/avisos' && $pdo->query("SELECT leido_en FROM sportiva_avisos WHERE id = '{$id1}'")->fetchColumn() === null, 'no se puede abrir (ni marcar leído) el aviso de otra persona');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h, $r] = llamar(fn() => (new S\AvisosController($ctx))->abrir($id1));
check($r !== null && str_starts_with($r, '/app') && $pdo->query("SELECT leido_en FROM sportiva_avisos WHERE id = '{$id1}'")->fetchColumn() !== null, 'abrir un aviso lo marca leído y lleva a su pantalla');
llamar(fn() => (new S\AvisosController($ctx))->leerTodo(), conCsrf());
check($av->sinLeer($dani, $cid) === 0, 'marcar todo como leído');
[$h] = llamar(fn() => (new S\AvisosController($ctx))->ajustes());
check(str_contains($h, 'Qué quieres recibir') && str_contains($h, 'push-boton'), 'pantalla de ajustes de avisos con el botón de notificaciones');
// Otros disparadores.
$antes = $av->sinLeer($dani, $cid);
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => $u12, 'titulo' => 'Aviso feed', 'texto' => 'Hola equipo']));
check($av->sinLeer($dani, $cid) === $antes + 1, 'una publicación en el feed avisa al equipo');
llamar(fn() => (new S\ConsultaController($ctx))->responder($con), conCsrf(['texto' => 'Otra respuesta']));
check(str_contains($tit($dani), 'Pedro respondió tu consulta'), 'responder una consulta avisa a la familia');
// Recordatorios de asistencia (24 h antes), una sola vez.
$clubFila = $S['club']->buscar($cid);
$manana = S\Tiempo::ahora((string) $clubFila['zona'])->modify('+6 hours');
[$dr] = $S['ev']->normalizar(['tipo' => 'entreno', 'titulo' => 'Entreno pronto', 'fecha' => $manana->format('Y-m-d'), 'hora_partido' => $manana->format('H:i')]);
$evR = $S['ev']->crear($cid, $u12, $dr, null);
$n1 = $nt->recordatorios();
$n2 = $nt->recordatorios();
check($n1 >= 1 && $n2 === 0, 'el recordatorio de asistencia se manda una sola vez');
check(str_contains($tit($dani), 'Falta tu asistencia'), 'el recordatorio dice qué falta');
// Cron, service worker y manifest.
$tok = $nt->tokenCron();
[$h] = llamar(fn() => (new S\AvisosController($ctx))->cron('mal'));
check(trim($h) === 'no', 'el cron rechaza un token incorrecto');
[$h] = llamar(fn() => (new S\AvisosController($ctx))->cron($tok));
check(str_starts_with($h, 'ok recordatorios='), 'el cron con el token correcto procesa avisos');
[$h] = llamar(fn() => (new S\AvisosController($ctx))->sw());
check(str_contains($h, "addEventListener('push'") && str_contains($h, 'notificationclick'), 'service worker con push y clic');
[$h] = llamar(fn() => (new S\AvisosController($ctx))->manifest());
$man = json_decode($h, true);
check($man['display'] === 'standalone' && $man['start_url'] === '/app' && count($man['icons']) === 2, 'manifest de la app instalable');
check(is_file(dirname(__DIR__) . '/sportiva/assets/marca/icon-192.png') && is_file(dirname(__DIR__) . '/sportiva/assets/marca/icon-512.png'), 'íconos de la app instalable');

seccion('Fotos');
$md = new S\MedioService($pdo);
$mc = fn() => new S\MedioController($ctx);
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id'];
$ev0 = $pdo->query("SELECT id FROM sportiva_eventos WHERE titulo = 'U12 vs Niort'")->fetchColumn();
$dani = $S['pers']->porCorreo('daniela@ejemplo.fr')['id'];
$ines = $S['pers']->porCorreo('ines.garcia@ejemplo.fr')['id'];
$pedroId = $S['pers']->porCorreo('pedro@ejemplo.fr')['id'];
$tmpd = sys_get_temp_dir() . '/sportiva-med-' . getmypid(); @mkdir($tmpd);
$png = function (int $w, int $h, string $n) use ($tmpd): string { $g = imagecreatetruecolor($w, $h); imagefill($g, 0, 0, imagecolorallocate($g, 30, 120, 90)); $f = "$tmpd/$n.png"; imagepng($g, $f); return $f; };
$subida = function (array $archivos) { $_FILES = ['fotos' => ['name' => [], 'tmp_name' => [], 'error' => [], 'size' => []]]; foreach ($archivos as $a) { $_FILES['fotos']['name'][] = basename($a); $_FILES['fotos']['tmp_name'][] = $a; $_FILES['fotos']['error'][] = UPLOAD_ERR_OK; $_FILES['fotos']['size'][] = filesize($a); } };
$n0 = $pdo->query('SELECT COUNT(*) FROM sportiva_medios')->fetchColumn();
// Fotos: la familia de un convocado las sube; se reducen a 1600 px y se hace miniatura.
$comoRol('daniela@ejemplo.fr', 'apoderado');
$antesInes = $pdo->query("SELECT COUNT(*) FROM sportiva_avisos WHERE persona_id = '{$ines}'")->fetchColumn();
$subida([$png(2400, 1200, 'grande'), $png(300, 200, 'chica')]);
[$h, $r] = llamar(fn() => $mc()->subirFotos($ev0), conCsrf());
$fotos = $md->delEvento($ev0);
check(count($fotos) === 2 && $r === '/app/evento/' . $ev0 . '/fotos', 'la familia sube fotos');
$grande = array_values(array_filter($fotos, fn($m) => $m['ancho'] == 1600))[0] ?? null;
check($grande !== null && getimagesize(S\MedioArchivos::ruta($grande['id'], 'f'))[0] === 1600 && getimagesize(S\MedioArchivos::ruta($grande['id'], 'm'))[0] === 480, 'la foto grande se reduce a 1600 px y tiene miniatura de 480 px');
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_avisos WHERE persona_id = '{$ines}'")->fetchColumn() === (int) $antesInes + 1, 'las demás familias reciben un solo aviso por tanda de fotos');
$subida([$png(200, 200, 'otra')]);
llamar(fn() => $mc()->subirFotos($ev0), conCsrf());
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_avisos WHERE persona_id = '{$ines}'")->fetchColumn() === (int) $antesInes + 1, 'no se avisa otra vez si se sube de nuevo enseguida');
file_put_contents("$tmpd/falso.png", 'esto no es una imagen');
$cuenta = (int) $pdo->query('SELECT COUNT(*) FROM sportiva_medios')->fetchColumn();
$subida(["$tmpd/falso.png"]);
[$h, $r] = llamar(fn() => $mc()->subirFotos($ev0), conCsrf());
check((int) $pdo->query('SELECT COUNT(*) FROM sportiva_medios')->fetchColumn() === $cuenta, 'un archivo que no es imagen no se guarda');
[$h] = llamar(fn() => $mc()->galeria($ev0));
check(str_contains($h, 'Fotos') && str_contains($h, 'class="med"') && str_contains($h, 'fotos-form'), 'la galería muestra las fotos y el formulario de subida');
// Permisos de ver y subir.
$comoRol('rosa@ejemplo.fr', 'apoderado'); // sus hijos no están convocados a este evento
[$h] = llamar(fn() => $mc()->galeria($ev0));
check(str_contains($h, '404'), 'una familia que no está convocada no ve la galería');
$subida([$png(100, 100, 'intruso')]);
llamar(fn() => $mc()->subirFotos($ev0), conCsrf());
check((int) $pdo->query('SELECT COUNT(*) FROM sportiva_medios')->fetchColumn() === $cuenta, 'ni puede subir');
check($mc()->datosServir($grande['id'], 'f') === null, 'ni se le sirve el archivo');
$comoRol('lucas@ejemplo.fr', 'jugador');
[$h] = llamar(fn() => $mc()->galeria($ev0));
check(str_contains($h, 'class="med"') && !str_contains($h, 'fotos-form'), 'el jugador ve pero no sube');
$comoRol('sylvie@ejemplo.fr', 'directiva');
check($mc()->datosServir($grande['id'], 'm') !== null, 'la directiva puede ver');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
$d = $mc()->datosServir($grande['id'], 'f');
check($d !== null && $d['mime'] === 'image/jpeg' && $d['tam'] > 0, 'otra familia convocada recibe la foto');
check($mc()->datosServir($grande['id'], 'v') === null && $mc()->datosServir($grande['id'], 'x') === null, 'ya no hay videos: esas variantes dan null');
// Cuota del equipo.
$md->guardarLimites(['cuota_mb' => 100]);
$pdo->prepare("INSERT INTO sportiva_medios (id, club_id, equipo_id, evento_id, tipo, mime, bytes, subido_por, created_at) VALUES (?, ?, ?, ?, 'foto', 'image/jpeg', ?, ?, ?)")->execute(['00000000-0000-0000-0000-00000000cafe', $cid, $u12, $ev0, 100 * 1048576, $dani, '2026-01-01 00:00:00']);
$cuenta = (int) $pdo->query('SELECT COUNT(*) FROM sportiva_medios')->fetchColumn();
$subida([$png(120, 120, 'sinespacio')]);
[$h, $r] = llamar(fn() => $mc()->subirFotos($ev0), conCsrf());
check((int) $pdo->query('SELECT COUNT(*) FROM sportiva_medios')->fetchColumn() === $cuenta, 'con la cuota llena no se aceptan más fotos');
[$h] = llamar(fn() => $mc()->galeria($ev0));
check(str_contains($h, 'llegó a su límite') && !str_contains($h, 'fotos-form'), 'la galería avisa que no queda espacio');
$pdo->exec("DELETE FROM sportiva_medios WHERE id = '00000000-0000-0000-0000-00000000cafe'");
$md->guardarLimites(['cuota_mb' => 2048]);
// Borrar y pedir que quiten.
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
llamar(fn() => $mc()->borrar($grande['id']), conCsrf());
check($md->buscar($grande['id']) !== null, 'otra familia no borra la foto de alguien más');
$avPedro = (int) $pdo->query("SELECT COUNT(*) FROM sportiva_avisos WHERE persona_id = '{$pedroId}'")->fetchColumn();
llamar(fn() => $mc()->reportar($grande['id']), conCsrf());
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_avisos WHERE persona_id = '{$pedroId}'")->fetchColumn() === $avPedro + 1, '«Pedir que lo quiten» le avisa al entrenador');
[$h] = llamar(fn() => $mc()->ver($grande['id']));
check(str_contains($h, 'Pedir que lo quiten') && str_contains($h, '/f'), 'el visor de la foto');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($ev0));
check(str_contains($h, 'Fotos') && str_contains($h, '/fotos'), 'el evento muestra su álbum');
[$h] = llamar(fn() => (new S\FeedController($ctx))->inicio());
check(str_contains($h, 'Fotos nuevas'), 'el feed muestra que hay fotos nuevas');
llamar(fn() => $mc()->borrar($grande['id']), conCsrf());
check($md->buscar($grande['id']) === null && !is_file(S\MedioArchivos::ruta($grande['id'], 'f')), 'quien subió la foto puede borrarla (y se borran los archivos)');
$comoRol('pedro@ejemplo.fr', 'entrenador');
$otraId = $md->delEvento($ev0)[0]['id'];
llamar(fn() => $mc()->borrar($otraId), conCsrf());
check($md->buscar($otraId) === null && !is_file(S\MedioArchivos::ruta($otraId, 'f')), 'el entrenador puede borrar cualquier foto de su equipo');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\AvisosController($ctx))->lista());
check(str_contains($h, 'Nouvelles photos'), 'los avisos de fotos y videos aparecen en la campana');
foreach (glob("$tmpd/*") as $f) { @unlink($f); } @rmdir($tmpd);
$_FILES = [];

seccion('Fotos en mensajes, publicaciones y comentarios');
$ad = new S\AdjuntoService($pdo);
$tmpd2 = sys_get_temp_dir() . '/sportiva-adj-' . getmypid();
$img = function (int $w, int $h, string $n) use ($tmpd2) { @mkdir($tmpd2); $g = imagecreatetruecolor($w, $h); imagefill($g, 0, 0, imagecolorallocate($g, 200, 60, 40)); $f = "$tmpd2/$n.png"; imagepng($g, $f); return [$f, $tmpd2]; };
[$foto1, $dirA] = $img(2000, 1000, 'adj1');
$_FILES = ['foto' => ['name' => 'a.png', 'tmp_name' => $foto1, 'error' => UPLOAD_ERR_OK, 'size' => filesize($foto1)]];
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h, $r] = llamar(fn() => (new S\ConsultaController($ctx))->crear(), conCsrf(['jugador_id' => $lucas, 'asunto' => 'Mira esto', 'texto' => 'Adjunto una foto']));
$conF = $pdo->query("SELECT id FROM sportiva_consultas WHERE asunto = 'Mira esto'")->fetchColumn();
$m0 = $cs->mensajes($conF)[0]['id'];
$a0 = $ad->de('mensaje', [$m0])[$m0] ?? null;
check($a0 !== null && getimagesize(S\MedioArchivos::ruta($a0, 'f'))[0] === 1600 && getimagesize(S\MedioArchivos::ruta($a0, 'm'))[0] === 480, 'una consulta puede llevar una foto, comprimida a 1600 px y con miniatura');
$cA = new S\AdjuntoController($ctx);
check($cA->datosServir($a0, 'f') !== null && $cA->datosServir($a0, 'v') === null, 'la familia ve la foto de su consulta');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
check($cA->datosServir($a0, 'f') === null, 'otra familia no ve la foto de una consulta ajena');
$comoRol('otro.coach@ejemplo.fr', 'entrenador');
check($cA->datosServir($a0, 'm') === null, 'ni un entrenador de otro equipo');
$comoRol('pedro@ejemplo.fr', 'entrenador');
check($cA->datosServir($a0, 'm') !== null, 'el entrenador del equipo sí');
[$h] = llamar(fn() => (new S\ConsultaController($ctx))->ver($conF));
check(str_contains($h, 'class="adj"') && str_contains($h, '/adjunto/' . $a0 . '/m'), 'la foto se ve en el hilo de la consulta');
// Respuesta solo con foto.
[$foto2] = $img(300, 300, 'adj2');
$_FILES = ['foto' => ['name' => 'b.png', 'tmp_name' => $foto2, 'error' => UPLOAD_ERR_OK, 'size' => filesize($foto2)]];
llamar(fn() => (new S\ConsultaController($ctx))->responder($conF), conCsrf(['texto' => '']));
$msgs = $cs->mensajes($conF);
check(count($msgs) === 2 && $msgs[1]['texto'] === '(foto)' && isset($ad->de('mensaje', [$msgs[1]['id']])[$msgs[1]['id']]), 'se puede responder solo con una foto');
// Archivo que no es imagen: el mensaje sale, la foto no.
file_put_contents("$dirA/falso.png", 'no soy imagen');
$_FILES = ['foto' => ['name' => 'f.png', 'tmp_name' => "$dirA/falso.png", 'error' => UPLOAD_ERR_OK, 'size' => 13]];
[$h, $r] = llamar(fn() => (new S\ConsultaController($ctx))->responder($conF), conCsrf(['texto' => 'Con foto mala']));
check(count($cs->mensajes($conF)) === 3 && $ad->de('mensaje', [$cs->mensajes($conF)[2]['id']]) === [], 'si la foto falla, el mensaje igual se envía');
// Feed: publicación y comentario con foto; se borran juntos.
$_FILES = ['foto' => ['name' => 'c.png', 'tmp_name' => $foto1, 'error' => UPLOAD_ERR_OK, 'size' => filesize($foto1)]];
llamar(fn() => (new S\FeedController($ctx))->crear(), conCsrf(['destino' => $u12, 'titulo' => 'Con foto', 'texto' => 'Mira']));
$pf = $pdo->query("SELECT id FROM sportiva_posts WHERE titulo = 'Con foto'")->fetchColumn();
$ap = $ad->de('post', [$pf])[$pf] ?? null;
check($ap !== null, 'una publicación del feed puede llevar foto');
[$h] = llamar(fn() => (new S\FeedController($ctx))->inicio());
check(str_contains($h, '/adjunto/' . $ap . '/m'), 'la foto se ve en el feed');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
check($cA->datosServir($ap, 'f') !== null, 'las familias del equipo ven la foto de la publicación');
$_FILES = ['foto' => ['name' => 'd.png', 'tmp_name' => $foto2, 'error' => UPLOAD_ERR_OK, 'size' => filesize($foto2)]];
llamar(fn() => (new S\FeedController($ctx))->comentar($pf), conCsrf(['texto' => '']));
$cm = $fs->comentarios($pf);
$ac = $ad->de('comentario', [$cm[0]['id']])[$cm[0]['id']] ?? null;
check(count($cm) === 1 && $cm[0]['texto'] === '(foto)' && $ac !== null, 'un comentario puede ser solo una foto');
$comoRol('rosa@ejemplo.fr', 'apoderado'); // tiene hijos en U12 también: ve el equipo
check($cA->datosServir($ac, 'm') !== null, 'quien ve la publicación ve la foto del comentario');
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => (new S\FeedController($ctx))->borrar($pf), conCsrf());
check($fs->post($pf) === null && $ad->buscar($ap) === null && $ad->buscar($ac) === null && !is_file(S\MedioArchivos::ruta($ap, 'f')), 'al borrar la publicación se borran las fotos (publicación y comentarios)');
$_FILES = [];
foreach (glob("$dirA/*") as $f) { @unlink($f); } @rmdir($dirA);


seccion('Panel de la directiva');
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id'];
$u12 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U12'")->fetchColumn();
$cc = new S\ClubController($ctx);
$pc = new S\PersonasController($ctx);
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h, $r] = llamar(fn() => $cc->inicio());
check($r === '/app/mas', 'un entrenador no entra al panel del club');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h, $r] = llamar(fn() => $cc->crearEquipo(), conCsrf(['nombre' => 'U99']));
check($r === '/app/mas' && (int) $pdo->query("SELECT COUNT(*) FROM sportiva_equipos WHERE nombre = 'U99'")->fetchColumn() === 0, 'una familia no crea equipos');
$comoRol('sylvie@ejemplo.fr', 'directiva');
[$h] = llamar(fn() => $cc->inicio());
check(str_contains($h, '/app/club/equipos') && str_contains($h, '/app/club/personas') && str_contains($h, '/app/club/ajustes'), 'el panel muestra los accesos del club');
[$h] = llamar(fn() => $cc->equipos());
check(str_contains($h, 'U12'), 'lista de equipos');
[$h, $r] = llamar(fn() => $cc->crearEquipo(), conCsrf(['nombre' => 'U15']));
$u15 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U15'")->fetchColumn();
check($u15 && $r === '/app/club/equipos/' . $u15, 'la directiva crea un equipo');
[$h] = llamar(fn() => $cc->crearEquipo(), conCsrf(['nombre' => 'u15']));
check(str_contains($h, 'Ya existe un equipo') || str_contains($h, 'Une équipe porte déjà'), 'no se repite el nombre de un equipo');
[$h, $r] = llamar(fn() => $cc->crearEquipo(), ['nombre' => 'U17']);
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_equipos WHERE nombre = 'U17'")->fetchColumn() === 0, 'crear un equipo exige CSRF');
llamar(fn() => $cc->actualizarEquipo($u15), conCsrf(['nombre' => 'U15 A', 'temporada' => '2026 – 2027']));
check($S['club']->equipo($u15)['nombre'] === 'U15 A', 'se renombra un equipo');
$pedro = $S['pers']->porCorreo('pedro@ejemplo.fr')['id'];
llamar(fn() => $cc->agregarStaff($u15), conCsrf(['persona' => $pedro]));
check(in_array($pedro, array_column($S['club']->staffDe($u15), 'id'), true), 'se asigna un entrenador a un equipo');
$danielaId = $S['pers']->porCorreo('daniela@ejemplo.fr')['id'];
llamar(fn() => $cc->agregarStaff($u15), conCsrf(['persona' => $danielaId]));
check(!in_array($danielaId, array_column($S['club']->staffDe($u15), 'id'), true), 'una familia no puede quedar como cuerpo técnico');
llamar(fn() => $cc->quitarStaff($u15, $pedro), conCsrf());
check($S['club']->staffDe($u15) === [], 'se quita al entrenador del equipo');
[$h, $r] = llamar(fn() => $cc->archivarEquipo($u12), conCsrf());
check($S['club']->equipo($u12)['archivado'] == 0 && $r === '/app/club/equipos/' . $u12, 'no se archiva un equipo con jugadores');
llamar(fn() => $cc->archivarEquipo($u15), conCsrf());
check($S['club']->equipo($u15)['archivado'] == 1 && !in_array($u15, array_column($S['club']->equipos($cid), 'id'), true), 'se archiva un equipo vacío');
// Aislamiento: no se toca un equipo de otro club.
$otroClub = $pdo->query("SELECT id FROM sportiva_clubes WHERE id <> '{$cid}' LIMIT 1")->fetchColumn();
$eAjeno = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$otroClub}' LIMIT 1")->fetchColumn();
if ($eAjeno) {
    [$h] = llamar(fn() => $cc->equipo($eAjeno));
    check(str_contains($h, '404') || $h === '', 'no se abre un equipo de otro club');
    llamar(fn() => $cc->actualizarEquipo($eAjeno), conCsrf(['nombre' => 'Hackeado']));
    check($S['club']->equipo($eAjeno)['nombre'] !== 'Hackeado', 'no se renombra un equipo de otro club');
}
// Lugares
[$h, $r] = llamar(fn() => $cc->guardarLugar(), conCsrf(['nombre' => 'Stade Nord', 'direccion' => 'Rue 1', 'lat' => '46.6', 'lon' => '-0.3', 'predeterminado' => '1']));
$lg = $pdo->query("SELECT id FROM sportiva_lugares WHERE club_id = '{$cid}' AND nombre = 'Stade Nord'")->fetchColumn();
check($lg && $r === '/app/club/lugares', 'se crea un lugar');
check((int) $pdo->query("SELECT predeterminado FROM sportiva_lugares WHERE id = '{$lg}'")->fetchColumn() === 1 && (int) $pdo->query("SELECT COUNT(*) FROM sportiva_lugares WHERE club_id = '{$cid}' AND predeterminado = 1")->fetchColumn() === 1, 'solo un lugar habitual por club');
[$h] = llamar(fn() => $cc->guardarLugar(), conCsrf(['nombre' => 'Malo', 'lat' => '999', 'lon' => '0']));
check(str_contains($h, 'class="error"') && !$pdo->query("SELECT id FROM sportiva_lugares WHERE nombre = 'Malo'")->fetchColumn(), 'coordenadas inválidas se rechazan');
[$h] = llamar(fn() => $cc->lugarEditar($lg));
check(str_contains($h, 'GMaps') && str_contains($h, 'Waze'), 'el lugar con coordenadas muestra mapa, GMaps y Waze');
llamar(fn() => $cc->guardarLugar($lg), conCsrf(['nombre' => 'Stade Nord 2', 'lat' => '46.6', 'lon' => '-0.3']));
check($pdo->query("SELECT nombre FROM sportiva_lugares WHERE id = '{$lg}'")->fetchColumn() === 'Stade Nord 2', 'se edita un lugar');
$lgUso = $pdo->query("SELECT lugar_id FROM sportiva_eventos WHERE club_id = '{$cid}' AND lugar_id IS NOT NULL LIMIT 1")->fetchColumn();
if ($lgUso) {
    llamar(fn() => $cc->eliminarLugar($lgUso), conCsrf());
    check($pdo->query("SELECT id FROM sportiva_lugares WHERE id = '{$lgUso}'")->fetchColumn() !== false, 'un lugar con eventos no se elimina');
}
llamar(fn() => $cc->eliminarLugar($lg), conCsrf());
check($pdo->query("SELECT id FROM sportiva_lugares WHERE id = '{$lg}'")->fetchColumn() === false, 'un lugar sin eventos se elimina');
// Temporada y ajustes
llamar(fn() => $cc->guardarTemporada(), conCsrf(['temporada' => '2027 – 2028']));
check($S['club']->temporada($cid) === '2027 – 2028', 'se guarda la temporada del club');
llamar(fn() => $cc->guardarAjustes(), conCsrf(['nombre' => 'FCJ Renombrado', 'ciudad' => 'Niort', 'deporte' => 'voley', 'idioma' => 'es', 'zona' => 'America/Santiago', 'publico' => '1']));
$cl = $S['club']->buscar($cid);
check($cl['nombre'] === 'FCJ Renombrado' && $cl['deporte'] === 'voley' && $cl['zona'] === 'America/Santiago' && $cl['idioma'] === 'es' && (int) $cl['exigir_app'] === 0, 'se guardan los ajustes del club');
[$h] = llamar(fn() => $cc->guardarAjustes(), conCsrf(['nombre' => '', 'deporte' => 'futbol', 'idioma' => 'xx', 'zona' => 'Mars/Base']));
check(str_contains($h, 'class="error"') && $S['club']->buscar($cid)['nombre'] === 'FCJ Renombrado', 'un nombre vacío no se guarda');
check($S['club']->buscar($cid)['zona'] === 'America/Santiago', 'una zona inválida no se guarda');
llamar(fn() => $cc->guardarAjustes(), conCsrf(['nombre' => 'FC Jeunesse Orée de l’Autize', 'ciudad' => 'Niort', 'deporte' => 'futbol', 'idioma' => 'fr', 'zona' => 'Europe/Paris', 'publico' => '1', 'exigir_app' => '1']));
check($S['club']->crear(['nombre' => 'Invitar', 'ciudad' => 'X']) !== '' && $S['club']->porSlug('invitar') === null && $S['club']->porSlug('invitar-club') !== null, 'un slug reservado recibe el sufijo -club');

seccion('Personas, invitaciones y directorio');
$comoRol('sylvie@ejemplo.fr', 'directiva');
[$h] = llamar(fn() => $pc->lista());
check(str_contains($h, 'Pedro') && str_contains($h, 'Daniela'), 'lista de personas del club');
[$h] = llamar(fn() => $pc->lista(), [], ['g' => 'entrenadores']);
check(str_contains($h, 'Pedro') && !str_contains($h, 'Daniela'), 'se filtran las personas por grupo');
[$h] = llamar(fn() => $pc->lista(), [], ['q' => 'daniela']);
check(str_contains($h, 'Daniela') && !str_contains($h, 'Pedro'), 'se busca por nombre');
[$h, $r] = llamar(fn() => $pc->invitar(), conCsrf(['nombre' => 'Nuevo Coach', 'email' => 'nuevo.coach@ejemplo.fr', 'rol' => 'entrenador', 'equipos' => [$u12]]));
$nc = $S['pers']->porCorreo('nuevo.coach@ejemplo.fr');
check($nc && $r === '/app/club/personas/' . $nc['id'] && $S['pers']->rolesEn($nc['id'], $cid) === ['entrenador'] && in_array($u12, array_column($S['pers']->equiposStaff($nc['id'], $cid), 'id'), true), 'se invita a un entrenador con su equipo');
[$h] = llamar(fn() => $pc->invitar(), conCsrf(['nombre' => 'Mal', 'email' => 'no-es-correo', 'rol' => 'entrenador']));
check(str_contains($h, 'class="error"') && $S['pers']->porCorreo('no-es-correo') === null, 'un correo inválido no invita');
llamar(fn() => $pc->invitar(), conCsrf(['nombre' => 'Hacker', 'email' => 'hacker@ejemplo.fr', 'rol' => 'superadmin']));
check($S['pers']->porCorreo('hacker@ejemplo.fr') === null, 'no se puede invitar con un rol que no existe');
llamar(fn() => $pc->invitar(), ['nombre' => 'Sin csrf', 'email' => 'sin.csrf@ejemplo.fr', 'rol' => 'entrenador']);
check($S['pers']->porCorreo('sin.csrf@ejemplo.fr') === null, 'invitar exige CSRF');
[$h] = llamar(fn() => $pc->invitaciones());
check(str_contains($h, 'Nuevo Coach') && str_contains($h, '/reenviar'), 'las invitaciones muestran a quien no ha entrado');
[$h, $r] = llamar(fn() => $pc->reenviar($nc['id']), conCsrf());
check($r === '/app/club/invitaciones', 'se reenvía una invitación');
[$h, $r] = llamar(fn() => $pc->cargarLista(), conCsrf(['lista' => "ana.lista@ejemplo.fr;Ana Lista;entrenador;U12\nmalo;Sin correo;entrenador;U12\ndir.lista@ejemplo.fr;Dir Lista;directiva;"]));
$al = $S['pers']->porCorreo('ana.lista@ejemplo.fr');
check($al && $S['pers']->rolesEn($al['id'], $cid) === ['entrenador'] && $S['pers']->equiposStaff($al['id'], $cid)[0]['nombre'] === 'U12' && $S['pers']->porCorreo('malo') === null && $S['pers']->rolesEn($S['pers']->porCorreo('dir.lista@ejemplo.fr')['id'], $cid) === ['directiva'], 'se carga una lista de correos con rol y equipo');
// ficha: roles y equipos
[$h] = llamar(fn() => $pc->ficha($nc['id']));
check(str_contains($h, 'Nuevo Coach') && str_contains($h, 'U12'), 'ficha de una persona');
llamar(fn() => $pc->actualizar($nc['id'], 'rol'), conCsrf(['rol' => 'encargado']));
check(in_array('encargado', $S['pers']->rolesEn($nc['id'], $cid), true), 'se agrega un rol');
llamar(fn() => $pc->actualizar($nc['id'], 'quitar-rol', 'encargado'), conCsrf());
check(!in_array('encargado', $S['pers']->rolesEn($nc['id'], $cid), true), 'se quita un rol');
llamar(fn() => $pc->actualizar($nc['id'], 'quitar-rol', 'entrenador'), conCsrf());
check($S['pers']->equiposStaff($nc['id'], $cid) === [], 'al quitar el rol de entrenador se sueltan sus equipos');
$sylvie = $S['pers']->porCorreo('sylvie@ejemplo.fr')['id'];
$dirLista = $S['pers']->porCorreo('dir.lista@ejemplo.fr')['id'];
llamar(fn() => $pc->actualizar($dirLista, 'quitar-rol', 'directiva'), conCsrf());
llamar(fn() => $pc->actualizar($sylvie, 'quitar-rol', 'directiva'), conCsrf());
check(in_array('directiva', $S['pers']->rolesEn($sylvie, $cid), true), 'no se quita a la última persona de la directiva');
// Aislamiento: persona de otro club
$ajena = $pdo->query("SELECT persona_id FROM sportiva_membresias WHERE club_id = '{$otroClub}' AND persona_id NOT IN (SELECT persona_id FROM sportiva_membresias WHERE club_id = '{$cid}') LIMIT 1")->fetchColumn();
if ($ajena) {
    [$h] = llamar(fn() => $pc->ficha($ajena));
    check(str_contains($h, '404') || $h === '', 'no se abre la ficha de alguien de otro club');
    llamar(fn() => $pc->actualizar($ajena, 'rol'), conCsrf(['rol' => 'directiva']));
    check(!in_array('directiva', $S['pers']->rolesEn($ajena, $cid), true), 'no se da rol en este club a alguien que no es del club');
}
// Directorio
$S['pers']->actualizarDatos($danielaId, 'Daniela', '+56 9 1111 2222');
[$h] = llamar(fn() => $pc->directorio());
check(str_contains($h, 'tel:') && str_contains($h, 'wa.me/') && str_contains($h, 'mailto:') && (str_contains($h, 'exportar') || str_contains($h, 'export')), 'la directiva ve el directorio con llamar, WhatsApp y correo');
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => $pc->directorio());
check(str_contains($h, 'tel:') || str_contains($h, 'mailto:'), 'el entrenador ve el directorio de sus equipos');
$comoRol('otro.coach@ejemplo.fr', 'entrenador');
[$h2] = llamar(fn() => $pc->directorio(), [], ['equipo' => $u12]);
check(!str_contains($h2, 'daniela@ejemplo.fr'), 'el entrenador de otro equipo no ve los contactos de U12 aunque lo pida');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h, $r] = llamar(fn() => $pc->directorio());
check($r === '/app/mas', 'una familia no ve el directorio completo');

seccion('Perfil, compartir datos y privacidad');
$pf = new S\PerfilController($ctx);
$pv = new S\PrivacidadService($pdo);
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\AuthController($ctx))->mas());
check(str_contains($h, '/app/perfil') && str_contains($h, '/app/ayuda') && str_contains($h, '/app/familias') && str_contains($h, '/app/familia"'), 'Más lleva a perfil, familia, familias del equipo y ayuda');
[$h] = llamar(fn() => $pf->inicio());
check(str_contains($h, '/app/perfil/datos') && str_contains($h, '/app/perfil/compartir') && str_contains($h, '/app/perfil/privacidad') && str_contains($h, '/app/avisos/ajustes'), 'menú de Mi perfil');
llamar(fn() => $pf->guardarDatos(), conCsrf(['nombre' => 'Daniela Pérez', 'telefono' => '+56 9 1111 2222']));
$dp = $S['pers']->buscar($danielaId);
check($dp['nombre'] === 'Daniela Pérez' && $dp['telefono'] === '+56 9 1111 2222', 'se editan nombre y teléfono');
[$h] = llamar(fn() => $pf->guardarDatos(), conCsrf(['nombre' => '  ', 'telefono' => '1']));
check(str_contains($h, 'class="error"') && $S['pers']->buscar($danielaId)['nombre'] === 'Daniela Pérez', 'el nombre no puede quedar vacío');
llamar(fn() => $pf->guardarDatos(), conCsrf(['nombre' => 'Daniela Pérez', 'telefono' => '+56 9 1111 2222', 'email' => 'otro@x.cl']));
check($S['pers']->buscar($danielaId)['email'] === 'daniela@ejemplo.fr', 'el correo no se cambia desde el perfil');
check($pv->get($danielaId) === ['telefono' => 0, 'correo' => 0, 'directorio' => 0], 'por defecto no se comparte nada');
$ines = $S['pers']->porCorreo('ines.garcia@ejemplo.fr')['id'];
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $pf->familias());
check(!str_contains($h, 'Daniela') && str_contains($h, 'compartir'), 'quien no compartió no aparece en Familias del equipo');
$comoRol('daniela@ejemplo.fr', 'apoderado');
llamar(fn() => $pf->guardarCompartir(), conCsrf(['directorio' => '1', 'telefono' => '1']));
check($pv->get($danielaId) === ['telefono' => 1, 'correo' => 0, 'directorio' => 1], 'se guarda qué datos se comparten');
$comparteHijo = $pdo->query("SELECT 1 FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id JOIN sportiva_apoderados_jugadores b ON b.jugador_id <> aj.jugador_id JOIN sportiva_jugadores j2 ON j2.id = b.jugador_id AND j2.equipo_id = j.equipo_id WHERE aj.persona_id = '{$danielaId}' AND b.persona_id = '{$ines}' LIMIT 1")->fetchColumn();
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $pf->familias());
if ($comparteHijo) {
    check(str_contains($h, 'Daniela') && str_contains($h, '+56911112222') && !str_contains($h, 'daniela@ejemplo.fr'), 'Familias del equipo muestra solo lo que cada familia compartió');
} else {
    check(!str_contains($h, 'daniela@ejemplo.fr'), 'Familias del equipo no filtra el correo no compartido');
}
$comoRol('daniela@ejemplo.fr', 'apoderado');
llamar(fn() => $pf->guardarCompartir(), conCsrf(['telefono' => '1', 'correo' => '1']));
check($pv->get($danielaId) === ['telefono' => 0, 'correo' => 0, 'directorio' => 0], 'sin «aparecer», teléfono y correo quedan apagados');
// Exportar
[$h] = llamar(fn() => $pf->exportar(), conCsrf());
$j = json_decode($h, true);
check(is_array($j) && $j['persona']['correo'] === 'daniela@ejemplo.fr' && isset($j['hijos'], $j['clubes']), 'se descargan los datos propios en JSON');
[$h] = llamar(fn() => $pf->exportar(), []);
check(json_decode($h, true) === null, 'exportar exige CSRF');
// Pedir quitar fotos
$antes = (int) $pdo->query('SELECT COUNT(*) FROM sportiva_avisos')->fetchColumn();
[$h, $r] = llamar(fn() => $pf->pedirQuitarFotos(), conCsrf());
check($r === '/app/perfil/privacidad', 'la familia pide que quiten fotos de sus hijos');
// Eliminar cuenta: titular no puede
[$h, $r] = llamar(fn() => $pf->eliminar(), conCsrf(['confirmar' => 'eliminar']));
check($S['pers']->buscar($danielaId)['email'] === 'daniela@ejemplo.fr', 'una familia titular no puede eliminar su cuenta todavía');
[$h] = llamar(fn() => $pf->privacidad());
check(str_contains($h, 'titular') || str_contains($h, 'Eres titular'), 'la pantalla explica por qué no se puede eliminar');
// Una persona sin vínculos sí
$lib = $S['pers']->crear('adios@ejemplo.fr', 'Adiós Persona', 'es');
$S['pers']->dar($lib, $cid, 'entrenador');
$pdo->prepare("INSERT INTO sportiva_posts (id, club_id, equipo_id, autor_id, titulo, texto, created_at) VALUES ('p-adios', ?, NULL, ?, 'x', 'hola', ?)")->execute([$cid, $lib, S\Tiempo::sello()]);
$comoRol('adios@ejemplo.fr', 'entrenador');
[$h, $r] = llamar(fn() => $pf->eliminar(), conCsrf(['confirmar' => 'otra cosa']));
check($S['pers']->buscar($lib)['email'] === 'adios@ejemplo.fr', 'eliminar pide escribir la palabra de confirmación');
$S['pers']->dar($lib, $cid, 'entrenador');
[$h, $r] = llamar(fn() => $pf->eliminar(), conCsrf(['confirmar' => 'ELIMINAR']));
$ap = $S['pers']->buscar($lib);
check($r === '/app/entrar?eliminada=1' && $ap['email'] !== 'adios@ejemplo.fr' && $ap['nombre'] === '—' && $S['pers']->membresias($lib) === [], 'se elimina la cuenta: sin acceso, sin datos y sin roles');
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_posts WHERE id = 'p-adios'")->fetchColumn() === 1, 'lo que escribió se conserva sin su nombre');
check($S['pers']->porCorreo('adios@ejemplo.fr') === null && S\Sesion::persona() === null, 'el correo queda libre y la sesión se cierra');
// Única persona de la directiva
$unica = $S['pers']->crear('unica@ejemplo.fr', 'Única', 'es');
$cNuevo = $S['club']->crear(['nombre' => 'Club Solo', 'ciudad' => 'X']);
$S['pers']->dar($unica, $cNuevo, 'directiva');
check($pv->impedimento($unica) === 'perfil.error.unica_directiva', 'la única persona de la directiva no puede eliminarse');
// Ayuda
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $pf->ayuda());
check(str_contains($h, 'Ayuda') || str_contains($h, 'Aide'), 'pantalla de ayuda');
[$h, $r] = llamar(fn() => $pf->familias());
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h, $r] = llamar(fn() => $pf->familias());
check($r === '/app/mas', 'Familias del equipo es solo para apoderados');
// idioma vuelve al perfil
[$h, $r] = llamar(fn() => (new S\AuthController($ctx))->cambiarIdioma(), conCsrf(['idioma' => 'es', 'volver' => '/app/perfil']));
check($r === '/app/perfil', 'cambiar idioma vuelve a Mi perfil');
[$h, $r] = llamar(fn() => (new S\AuthController($ctx))->cambiarIdioma(), conCsrf(['idioma' => 'es', 'volver' => 'https://malo.com']));
check($r === '/app/mas', 'el idioma no redirige a sitios externos');


seccion('Planificación repetida, citados, mapas y compartir');
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id'];
$u12 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U12'")->fetchColumn();
$ag = new S\AgendaController($ctx);
$sc = new S\SerieController($ctx);
$sv = new S\SerieService($pdo);
$lugar0 = $pdo->query("SELECT id FROM sportiva_lugares WHERE club_id = '{$cid}' LIMIT 1")->fetchColumn();
// generar fechas
$f = S\SerieService::generar('2030-03-05', [4], 1); // martes 5 mar 2030 + jueves
check($f[0] === '2030-03-05' && in_array('2030-03-07', $f, true) && !in_array('2030-03-06', $f, true) && end($f) <= '2030-04-05', 'se generan las fechas de los días elegidos (el día de inicio siempre cuenta)');
check(count(S\SerieService::generar('2030-01-01', [1, 2, 3, 4, 5, 6, 7], 12)) <= 120, 'la repetición tiene un tope de fechas');
check(S\SerieService::generar('mala', [1], 1) === [], 'una fecha mala no genera nada');
// crear con vista previa
$comoRol('pedro@ejemplo.fr', 'entrenador');
$base = ['tipo' => 'entreno', 'equipo_id' => $u12, 'titulo' => 'Entreno martes', 'fecha' => '2030-03-05', 'hora_partido' => '18:00', 'hora_fin' => '19:30', 'lugar_id' => $lugar0, 'repetir' => '1', 'dias' => ['2', '4'], 'meses' => '1', 'jugadores_presente' => '1', 'jugadores' => array_column($S['ev']->jugadoresDe($u12), 'id')];
[$h, $r] = llamar(fn() => $ag->crear(), conCsrf($base + ['accion' => 'ver']));
check($r === null && str_contains($h, 'name="hacer[2030-03-05]"') && str_contains($h, 'name="hacer[2030-03-07]"') && (int) $pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Entreno martes'")->fetchColumn() === 0, '«Ver fechas» muestra las fechas sin crear nada');
[$h, $r] = llamar(fn() => $ag->crear(), conCsrf($base));
check($r === null && str_contains($h, 'name="hacer[') && (int) $pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Entreno martes'")->fetchColumn() === 0, 'al publicar sin haber visto las fechas, primero las muestra');
$todas = S\SerieService::generar('2030-03-05', [2, 4], 1);
$visto = $todas;
$hacer = array_fill_keys(array_diff($todas, ['2030-03-07']), '1'); // se salta el jueves 7
$antesAv = (int) $pdo->query('SELECT COUNT(*) FROM sportiva_avisos')->fetchColumn();
[$h, $r] = llamar(fn() => $ag->crear(), conCsrf($base + ['visto' => $visto, 'hacer' => $hacer, 'motivo' => ['2030-03-07' => 'Feriado']]));
$serieId = $pdo->query("SELECT id FROM sportiva_series ORDER BY created_at DESC, id DESC LIMIT 1")->fetchColumn();
check($serieId && $r === '/app/serie/' . $serieId, 'se publica la planificación y lleva a ella');
$nEv = (int) $pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Entreno martes'")->fetchColumn();
check($nEv === count($todas) - 1, 'se crea un evento por fecha, menos la saltada');
$om = $pdo->query("SELECT omitida, motivo, evento_id FROM sportiva_serie_fechas WHERE serie_id = '{$serieId}' AND fecha = '2030-03-07'")->fetch();
check($om && $om['omitida'] == 1 && $om['motivo'] === 'Feriado' && $om['evento_id'] === null, 'la fecha saltada queda con su motivo');
$tit = fn($pid) => $pdo->query("SELECT titulo FROM sportiva_avisos WHERE persona_id = '{$pid}' ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_COLUMN);
$dani = $S['pers']->porCorreo('daniela@ejemplo.fr')['id'];
check(count(array_filter($tit($dani), fn($t) => str_contains($t, 'Nueva planificación'))) === 1, 'las familias reciben un solo aviso por la planificación nueva');
// el evento 1 pertenece a la serie
$e1 = $pdo->query("SELECT evento_id FROM sportiva_serie_fechas WHERE serie_id = '{$serieId}' AND evento_id IS NOT NULL ORDER BY fecha LIMIT 1")->fetchColumn();
[$h] = llamar(fn() => $ag->evento($e1));
check(str_contains($h, '/app/serie/' . $serieId), 'el evento enlaza a su planificación');
// la planificación es de los entrenadores del equipo
[$h] = llamar(fn() => $sc->ver($serieId));
check(str_contains($h, 'name="hacer[2030-03-05]"') && str_contains($h, 'Feriado'), 'la planificación muestra las fechas y el motivo');
$comoRol('otro.coach@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => $sc->ver($serieId));
check(str_contains($h, '404') || $h === '', 'un entrenador de otro equipo no abre la planificación');
llamar(fn() => $sc->guardar($serieId), conCsrf(['visto' => ['2030-03-05'], 'motivo' => ['2030-03-05' => 'x']]));
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Entreno martes' AND estado = 'cancelado'")->fetchColumn() === 0, 'ni la modifica');
// quitar una fecha ya guardada → cancela y avisa
$comoRol('pedro@ejemplo.fr', 'entrenador');
$quitar = '2030-03-12';
$hacer2 = array_fill_keys(array_diff($todas, ['2030-03-07', $quitar]), '1');
$antes = count($tit($dani));
llamar(fn() => $sc->guardar($serieId), conCsrf(['visto' => $todas, 'hacer' => $hacer2, 'motivo' => ['2030-03-07' => 'Feriado', $quitar => 'Torneo']]));
$ef = $pdo->query("SELECT e.estado FROM sportiva_serie_fechas f JOIN sportiva_eventos e ON e.id = f.evento_id WHERE f.serie_id = '{$serieId}' AND f.fecha = '{$quitar}'")->fetchColumn();
check($ef === 'cancelado', 'al quitar una fecha guardada se cancela su evento');
$av = $tit($dani);
check(count($av) === $antes + 1 && str_contains($av[0], 'Cambios en la planificación'), 'quitar una fecha avisa a las familias (un solo aviso)');
$cuerpo = $pdo->query("SELECT cuerpo FROM sportiva_avisos WHERE persona_id = '{$dani}' ORDER BY created_at DESC, id DESC LIMIT 1")->fetchColumn();
check(str_contains((string) $cuerpo, 'Torneo'), 'el aviso dice por qué se suspende');
// no aparece en la agenda de la familia
$comoRol('daniela@ejemplo.fr', 'apoderado');
$evQ = $pdo->query("SELECT evento_id FROM sportiva_serie_fechas WHERE serie_id = '{$serieId}' AND fecha = '{$quitar}'")->fetchColumn();
[$h] = llamar(fn() => $ag->evento($evQ));
check(str_contains($h, '404') || $h === '' || !str_contains($h, 'Entreno martes'), 'una fecha cancelada ya no se muestra a la familia');
// volver a ponerla
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => $sc->guardar($serieId), conCsrf(['visto' => $todas, 'hacer' => array_fill_keys(array_diff($todas, ['2030-03-07']), '1')]));
check($pdo->query("SELECT estado FROM sportiva_eventos WHERE id = '{$evQ}'")->fetchColumn() === 'publicado', 'volver a marcar una fecha la recupera');
// la fecha saltada al crear se puede poner después
llamar(fn() => $sc->guardar($serieId), conCsrf(['visto' => $todas, 'hacer' => array_fill_keys($todas, '1')]));
check($pdo->query("SELECT evento_id FROM sportiva_serie_fechas WHERE serie_id = '{$serieId}' AND fecha = '2030-03-07'")->fetchColumn() !== null, 'una fecha saltada desde el comienzo se puede agregar después');
// extender
$antesN = (int) $pdo->query("SELECT COUNT(*) FROM sportiva_serie_fechas WHERE serie_id = '{$serieId}'")->fetchColumn();
llamar(fn() => $sc->guardar($serieId), conCsrf(['visto' => [], 'meses' => '1', 'dias' => ['2', '4']]));
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_serie_fechas WHERE serie_id = '{$serieId}'")->fetchColumn() > $antesN, 'se puede extender la planificación por más meses');
// Citados
$j12 = array_column($S['ev']->jugadoresDe($u12), 'id');
$conv = ['tipo' => 'partido', 'equipo_id' => $u12, 'titulo' => 'U12 vs Citados', 'fecha' => '2030-05-04', 'hora_partido' => '14:00', 'cancha_antes' => '45', 'lugar_id' => $lugar0, 'jugadores_presente' => '1', 'jugadores' => array_slice($j12, 0, 3)];
[$h, $r] = llamar(fn() => $ag->crear(), conCsrf($conv));
$pid = $pdo->query("SELECT id FROM sportiva_eventos WHERE titulo = 'U12 vs Citados'")->fetchColumn();
check($pid && count($S['ev']->convocadosIds($pid)) === 3, 'un partido cita solo a los jugadores elegidos');
[$h] = llamar(fn() => $ag->crear(), conCsrf(['titulo' => 'Sin nadie', 'jugadores' => []] + $conv));
check(str_contains($h, 'class="error"') && !$pdo->query("SELECT id FROM sportiva_eventos WHERE titulo = 'Sin nadie'")->fetchColumn(), 'no se publica sin citar a nadie');
$ajeno = $pdo->query("SELECT j.id FROM sportiva_jugadores j WHERE j.club_id <> '{$cid}' LIMIT 1")->fetchColumn();
[$h, $r] = llamar(fn() => $ag->crear(), conCsrf(['titulo' => 'Con ajeno', 'jugadores' => array_merge([$j12[0]], $ajeno ? [$ajeno] : [])] + $conv));
$pa = $pdo->query("SELECT id FROM sportiva_eventos WHERE titulo = 'Con ajeno'")->fetchColumn();
check($pa && count($S['ev']->convocadosIds($pa)) === 1, 'no se pueden citar jugadores de otro club');
// vistas de la familia: citados y confirmaron
$hijoCitado = $S['ev']->convocadosIds($pid)[0];
$ap = $pdo->query("SELECT persona_id FROM sportiva_apoderados_jugadores WHERE jugador_id = '{$hijoCitado}' AND titular = 1")->fetchColumn();
$apEmail = $pdo->query("SELECT email FROM sportiva_personas WHERE id = '{$ap}'")->fetchColumn();
$comoRol($apEmail, 'apoderado');
llamar(fn() => $ag->responder($pid), conCsrf(['jugador' => $hijoCitado, 'respuesta' => 'voy']));
[$h] = llamar(fn() => $ag->evento($pid));
check(str_contains($h, 'Citados') && str_contains($h, 'class="cit-voy"') && str_contains($h, '<b>1</b> de 3 van') && str_contains($h, 'Faltan por confirmar'), 'la familia ve cuántos van, el bloque de quienes confirmaron y quiénes faltan');
$noCit = array_values(array_diff($j12, $S['ev']->convocadosIds($pid)))[0] ?? null;
if ($noCit) {
    $nom = $pdo->query("SELECT nombre FROM sportiva_jugadores WHERE id = '{$noCit}'")->fetchColumn();
    check(!str_contains($h, '<b>' . $nom . '</b>'), 'un jugador no citado no aparece en las listas');
}
// editar citados: avisa a quienes entran y salen
$comoRol('pedro@ejemplo.fr', 'entrenador');
$nuevoJ = $noCit ?: $j12[0];
[$h] = llamar(fn() => $ag->editar($pid));
check(str_contains($h, 'name="jugadores[]"') && str_contains($h, 'checked'), 'el formulario de edición muestra a los citados');
$apNuevo = $pdo->query("SELECT persona_id FROM sportiva_apoderados_jugadores WHERE jugador_id = '{$nuevoJ}' LIMIT 1")->fetchColumn();
$antesN = count($tit($apNuevo));
llamar(fn() => $ag->actualizar($pid), conCsrf(['jugadores' => array_merge([$S['ev']->convocadosIds($pid)[0]], [$nuevoJ]), 'tipo' => 'partido', 'equipo_id' => $u12, 'titulo' => 'U12 vs Citados', 'fecha' => '2030-05-04', 'hora_partido' => '14:00', 'cancha_antes' => '45', 'lugar_id' => $lugar0, 'jugadores_presente' => '1']));
check(in_array($nuevoJ, $S['ev']->convocadosIds($pid), true) && count($S['ev']->convocadosIds($pid)) <= 3, 'se cambian los citados de un evento');
check(count($tit($apNuevo)) > $antesN && count(array_filter(array_slice($tit($apNuevo), 0, 2), fn($t) => str_contains($t, 'Citaron') || str_contains($t, 'Ton enfant est convoqué'))) === 1, 'quien entra a la lista recibe un aviso');
// Mapas y compartir
check(S\Mapa::coordenadasDeEnlace('https://www.google.com/maps/place/Stade/@46.480236,-0.599575,17z/data=!3m1') === ['46.480236', '-0.599575'], 'se sacan las coordenadas de un enlace de Google Maps');
check(S\Mapa::coordenadasDeEnlace('https://www.google.com/maps/place/Stade+Espinassou/@46.3289581,-0.465113,14z/') === ['46.3289581', '-0.465113'], 'enlace de lugar de Google Maps con solo el centro del mapa');
check(S\Mapa::coordenadasDeEnlace('https://www.google.com/maps/place/Stade+Espinassou/@46.3289581,-0.465113,14z/data=!4m10!1m2!2m1!1sniort+stade!3m6!1s0x4807303a2cc9a0c1:0x5c653953ebd87e43!8m2!3d46.3352282!4d-0.4576478!15sCg9u!16s%2Fg%2F11b77bwqd3?entry=ttu&g_ep=Eg%3D%3D') === ['46.3352282', '-0.4576478'], 'si el enlace trae la ubicación exacta del lugar (!3d!4d), se usa esa y no el centro del mapa');
check(S\Mapa::coordenadasDeEnlace('https://maps.google.com/?q=-33.4489,-70.6693') === ['-33.4489', '-70.6693'] && S\Mapa::coordenadasDeEnlace('-33.45, -70.66') === ['-33.45', '-70.66'] && S\Mapa::coordenadasDeEnlace('hola') === null, 'también de ?q=, de «lat, lon» y rechaza lo que no lo es');
$sin = S\Mapa::enlacesLugar(['lugar' => 'Cancha Norte', 'lugar_direccion' => 'Calle 1', 'lugar_lat' => null, 'lugar_lon' => null]);
check($sin !== null && str_contains($sin['google'], 'Cancha%20Norte') && str_contains($sin['waze'], 'q='), 'un lugar sin coordenadas igual ofrece Google Maps y Waze buscando por nombre y dirección');
$con = S\Mapa::enlacesLugar(['lugar' => 'X', 'lugar_lat' => '46.1', 'lugar_lon' => '-0.3']);
check(str_contains($con['google'], 'query=46.1,-0.3') && str_contains($con['waze'], 'll='), 'con coordenadas usa el punto exacto');
$comoRol($apEmail, 'apoderado');
[$h] = llamar(fn() => $ag->evento($pid));
check(str_contains($h, 'https://wa.me/?text=') && str_contains($h, 'GMaps') && str_contains($h, 'Waze') && str_contains($h, 'data-compartir'), 'la convocatoria tiene mapa, Google Maps, Waze y compartir por WhatsApp');
check(str_contains(urldecode($h), 'Estadio del club') || str_contains($h, 'Estadio'), 'el texto para compartir lleva el lugar');
[$h] = llamar(fn() => $ag->agenda());
check(str_contains($h, 'wa.me/?text=') && str_contains($h, 'Cómo llegar'), 'la tarjeta de la agenda tiene compartir y cómo llegar');
$lg2 = $S['ev'];
llamar(fn() => (new S\ClubController($ctx))->guardarLugar(), []);
$comoRol('sylvie@ejemplo.fr', 'directiva');
llamar(fn() => (new S\ClubController($ctx))->guardarLugar(), conCsrf(['nombre' => 'Con enlace', 'enlace' => 'https://www.google.com/maps/@46.5,-0.4,15z']));
check($pdo->query("SELECT lat FROM sportiva_lugares WHERE nombre = 'Con enlace'")->fetchColumn() === '46.5', 'el lugar toma sus coordenadas del enlace pegado');


seccion('Agenda compacta, avisos de citación y lugares locales');
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id'];
$u12 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U12'")->fetchColumn();
$ag = new S\AgendaController($ctx);
$ls = new S\LugarService($pdo);
$lugarX = $pdo->query("SELECT id FROM sportiva_lugares WHERE club_id = '{$cid}' LIMIT 1")->fetchColumn();
$manana = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$comoRol('pedro@ejemplo.fr', 'entrenador');
$todasF = S\SerieService::generar($manana, [1, 2, 3, 4, 5, 6, 7], 2);
$baseS = ['tipo' => 'entreno', 'equipo_id' => $u12, 'titulo' => 'Entreno largo', 'fecha' => $manana, 'hora_partido' => '18:00', 'hora_fin' => '19:00', 'lugar_id' => $lugarX, 'repetir' => '1', 'dias' => ['1', '2', '3', '4', '5', '6', '7'], 'meses' => '2', 'jugadores_presente' => '1', 'jugadores' => array_column($S['ev']->jugadoresDe($u12), 'id')];
llamar(fn() => $ag->crear(), conCsrf($baseS + ['visto' => $todasF, 'hacer' => array_fill_keys($todasF, '1')]));
check((int) $pdo->query("SELECT COUNT(*) FROM sportiva_eventos WHERE titulo = 'Entreno largo'")->fetchColumn() === count($todasF), 'una planificación larga crea muchos entrenamientos');
[$h] = llamar(fn() => $ag->agenda());
$cuenta = fn(string $x) => preg_match_all('/class="ev-t"><a [^>]*>Entreno largo</', $x);
check($cuenta($h) <= 3, 'la agenda no muestra todos los entrenamientos (máximo 2 y el próximo fijo)');
check(str_contains($h, 'Próximo') && str_contains($h, 'Ver más fechas'), 'el próximo evento queda fijo arriba y hay «Ver más fechas»');
$pos = strpos($h, 'Próximo');
$primera = strpos($h, 'class="card ev"');
check($pos !== false && $primera !== false && $pos < $primera, 'la etiqueta «Próximo» va antes de la primera tarjeta');
[$h2] = llamar(fn() => $ag->agenda(), [], ['dias' => '30']);
check($cuenta($h2) > $cuenta($h) && $cuenta($h2) <= 7, '«Ver más fechas» muestra otros 10 días, con más entrenamientos');
// partidos dentro de la ventana siempre salen
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h3] = llamar(fn() => $ag->agenda());
check(str_contains($h3, 'class="card ev"'), 'la tarjeta de la agenda usa el formato compacto');
// reglas de local: entrenamiento en un lugar local no lleva cómo llegar ni compartir
$comoRol('pedro@ejemplo.fr', 'entrenador');
$ls->marcarLocal($cid, $lugarX, true);
$entr = $pdo->query("SELECT id FROM sportiva_eventos WHERE titulo = 'Entreno largo' ORDER BY inicio LIMIT 1")->fetchColumn();
[$h] = llamar(fn() => $ag->evento($entr));
check(!str_contains($h, 'google.com/maps') && !str_contains($h, 'waze.com') && !str_contains($h, 'wa.me/?text=') && !str_contains($h, 'class="mapa"'), 'un entrenamiento en lugar local no muestra mapa, cómo llegar ni compartir');
$partL = $S['ev']->crear($cid, $u12, ['tipo' => 'partido', 'titulo' => 'Partido en casa', 'rival' => 'X', 'inicio' => '2030-06-01 15:00:00', 'fin' => null, 'hora_cancha' => '2030-06-01 14:15:00', 'hora_encuentro' => null, 'lugar_id' => $lugarX, 'encuentro_texto' => null, 'nota' => null, 'precio_info' => null], $S['pers']->porCorreo('pedro@ejemplo.fr')['id']);
[$h] = llamar(fn() => $ag->evento($partL));
check(str_contains($h, 'wa.me/?text=') && !preg_match('/href="https:\/\/(www\.google\.com\/maps|waze\.com)/', $h), 'un partido en casa se puede compartir pero no pide cómo llegar');
$ls->marcarLocal($cid, $lugarX, false);
[$h] = llamar(fn() => $ag->evento($entr));
check(str_contains($h, 'google.com/maps') && str_contains($h, 'waze.com') && str_contains($h, 'wa.me/?text='), 'de visita (lugar no local) sí hay mapa, cómo llegar y compartir');
$ls->marcarLocal($cid, $lugarX, true);
// formulario: campos según el tipo
[$h] = llamar(fn() => $ag->nuevo());
check(preg_match('/data-tipos="entreno especial stage"[^>]*hidden/', $h) === 1 && !preg_match('/name="rival"[^>]*>/', '') && preg_match('/data-tipos="partido"[^>]*>(?:(?!hidden).)*name="rival"/s', $h) === 1, 'para un partido el formulario muestra el rival y oculta lo del entrenamiento');
[$dn, $en] = $S['ev']->normalizar(['tipo' => 'entreno', 'fecha' => '2030-01-01', 'hora_partido' => '18:00', 'hora_fin' => '19:00', 'hora_cancha' => '17:00', 'hay_encuentro' => '1', 'hora_encuentro' => '16:00', 'rival' => 'Nadie', 'titulo' => 'E', 'lugar_id' => 'x']);
check($dn['hora_cancha'] === null && $dn['hora_encuentro'] === null && $dn['rival'] === null && $dn['fin'] === '2030-01-01 19:00:00', 'un entrenamiento no guarda rival, encuentro ni hora en cancha');
[$dp] = $S['ev']->normalizar(['tipo' => 'partido', 'fecha' => '2030-01-01', 'hora_partido' => '15:00', 'hora_fin' => '17:00', 'cancha_antes' => '45', 'rival' => 'Y', 'titulo' => 'P', 'lugar_id' => 'x']);
check($dp['fin'] === null && $dp['hora_cancha'] === '2030-01-01 14:15:00' && $dp['rival'] === 'Y', 'un partido no guarda hora de fin y calcula la hora en cancha');
[$h] = llamar(fn() => $ag->nuevo());
check(str_contains($h, 'id="cuenta-citados"') && str_contains($h, 'class="est cn"'), 'el formulario cuenta los citados (total y por categoría)');
// Plantel: lugares locales
$pl = new S\PlantelController($ctx);
[$h] = llamar(fn() => $pl->inicio());
check(str_contains($h, 'id="locales"') && str_contains($h, 'Dónde entrenamos'), 'el plantel lista los lugares donde somos local');
llamar(fn() => $pl->localAgregar(), conCsrf(['nombre' => 'Gimnasio Municipal', 'direccion' => 'Av. 1', 'enlace' => 'https://www.google.com/maps/@46.4,-0.5,17z']));
$gm = $pdo->query("SELECT id, lat FROM sportiva_lugares WHERE nombre = 'Gimnasio Municipal'")->fetch();
check($gm && $gm['lat'] === '46.4' && $ls->esLocal($gm['id']), 'se agrega un lugar local nuevo con su ubicación');
llamar(fn() => $pl->localQuitar($gm['id']), conCsrf());
check(!$ls->esLocal($gm['id']), 'se deja de ser local en un lugar');
$comoRol('daniela@ejemplo.fr', 'apoderado');
llamar(fn() => $pl->localAgregar(), conCsrf(['nombre' => 'Intruso']));
check($pdo->query("SELECT id FROM sportiva_lugares WHERE nombre = 'Intruso'")->fetchColumn() === false, 'una familia no agrega lugares locales');
// Avisos: citación con fecha y hora, filtros
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => $ag->crear(), conCsrf(['tipo' => 'partido', 'equipo_id' => $u12, 'titulo' => 'Partido avisos', 'fecha' => '2030-07-06', 'hora_partido' => '16:00', 'cancha_antes' => '45', 'lugar_id' => $lugarX, 'jugadores_presente' => '1', 'jugadores' => array_column($S['ev']->jugadoresDe($u12), 'id')]));
$comoRol('daniela@ejemplo.fr', 'apoderado');
$av = new S\AvisosController($ctx);
[$h] = llamar(fn() => $av->lista());
check(str_contains($h, 'class="av av-fecha') || str_contains($h, 'av-fecha'), 'el aviso de una citación muestra la fecha de la cita en el ícono');
check(str_contains($h, 'class="horas h-s') && str_contains($h, '16:00') && str_contains($h, 'class="hl"'), 'el aviso de una citación usa el mismo diseño de horarios, con la hora clave resaltada');
check(str_contains($h, 'Citaciones') && str_contains($h, 'Turnos') && str_contains($h, 'Otros'), 'los avisos tienen filtros Citaciones, Turnos y Otros');
[$h] = llamar(fn() => $av->lista(), [], ['f' => 'turnos']);
check(!str_contains($h, 'Partido avisos'), 'el filtro Turnos oculta las citaciones');
[$h] = llamar(fn() => $av->lista(), [], ['f' => 'citaciones']);
check(str_contains($h, 'Partido avisos'), 'el filtro Citaciones muestra las citaciones');
// una cancelación aparece como cancelada
$comoRol('pedro@ejemplo.fr', 'entrenador');
$pa = $pdo->query("SELECT id FROM sportiva_eventos WHERE titulo = 'Partido avisos'")->fetchColumn();
llamar(fn() => $ag->cancelar($pa), conCsrf());
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $av->lista(), [], ['f' => 'citaciones']);
check(str_contains($h, 'Cancelada'), 'una citación cancelada se marca como cancelada en los avisos');
check(S\Tiempo::hace(S\Tiempo::sello(), new S\I18n('es')) === 'ahora', 'la publicación se muestra como «ahora»');


seccion('Calendario del mes');
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id'];
$u12 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U12'")->fetchColumn();
$ag = new S\AgendaController($ctx);
$lugarC = $pdo->query("SELECT id FROM sportiva_lugares WHERE club_id = '{$cid}' LIMIT 1")->fetchColumn();
$mesQ = (new DateTimeImmutable('first day of next month'))->format('Y-m');
$diaQ = $mesQ . '-15';
$comoRol('pedro@ejemplo.fr', 'entrenador');
llamar(fn() => $ag->crear(), conCsrf(['tipo' => 'partido', 'equipo_id' => $u12, 'titulo' => 'Partido del calendario', 'fecha' => $diaQ, 'hora_partido' => '11:00', 'cancha_antes' => '45', 'lugar_id' => $lugarC, 'jugadores_presente' => '1', 'jugadores' => array_column($S['ev']->jugadoresDe($u12), 'id')]));
llamar(fn() => $ag->crear(), conCsrf(['tipo' => 'entreno', 'equipo_id' => $u12, 'titulo' => 'Entreno del calendario', 'fecha' => $diaQ, 'hora_partido' => '18:00', 'hora_fin' => '19:00', 'lugar_id' => $lugarC, 'jugadores_presente' => '1', 'jugadores' => array_column($S['ev']->jugadoresDe($u12), 'id')]));
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $ag->agenda(), [], ['vista' => 'mes', 'mes' => $mesQ]);
check(str_contains($h, 'class="card cal"') && str_contains($h, 'cal-g') && str_contains($h, 'mes=' . $mesQ . '&amp;dia=' . $diaQ), 'la vista de calendario muestra el mes con un enlace por día');
check(preg_match('/dia=' . $diaQ . '"[^>]*>\s*<b>15<\/b>\s*<span class="cal-p"><i class="p-partido"><\/i><i class="p-entreno"><\/i>/s', $h) === 1, 'el día con partido y entrenamiento lleva un punto de cada tipo');
check(str_contains($h, 'mes=' . (new DateTimeImmutable($mesQ . '-01'))->modify('+1 month')->format('Y-m')), 'el calendario enlaza al mes siguiente y al anterior');
[$h] = llamar(fn() => $ag->agenda(), [], ['vista' => 'mes', 'mes' => $mesQ, 'dia' => $diaQ]);
check(str_contains($h, 'Partido del calendario') && str_contains($h, 'Entreno del calendario') && str_contains($h, 'class="cal-c sel"') || str_contains($h, 'cal-c sel'), 'al tocar un día se ven sus eventos y el día queda marcado');
[$h] = llamar(fn() => $ag->agenda(), [], ['dia' => $diaQ]);
check(str_contains($h, 'Partido del calendario') && str_contains($h, 'Volver a los próximos'), 'en la lista, un día elegido muestra solo ese día con enlace para volver');
[$h] = llamar(fn() => $ag->agenda());
check(str_contains($h, 'class="ag-lateral"') && str_contains($h, 'class="card cal mini"'), 'la lista lleva el calendario en la columna lateral (escritorio)');
check(str_contains($h, 'ag-vista') && str_contains($h, 'vista=mes'), 'en el móvil hay opción Lista | Calendario');
[$h] = llamar(fn() => $ag->agenda(), [], ['vista' => 'mes', 'mes' => 'basura', 'dia' => 'x']);
check(str_contains($h, 'cal-g'), 'parámetros inválidos no rompen el calendario');
$comoRol('otro.coach@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => $ag->agenda(), [], ['vista' => 'mes', 'mes' => $mesQ, 'dia' => $diaQ]);
check(!str_contains($h, 'Partido del calendario'), 'un entrenador de otro equipo no ve en el calendario los eventos del U12');
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => $ag->nuevo());
check(str_contains($h, 'Lugar del partido') && str_contains($h, 'id="lugar-nuevo"') && str_contains($h, 'id="lugar-sel"') && strpos($h, 'id="lugar"') < strpos($h, 'id="cuenta-citados"'), 'el formulario titula el lugar según el tipo, oculta el lugar nuevo si hay uno guardado y pone el lugar antes de los citados');
$comoRol('ines.garcia@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $ag->agenda(), [], ['vista' => 'mes', 'mes' => $mesQ, 'dia' => $diaQ]);
check(str_contains($h, 'Partido del calendario') || str_contains($h, 'No hay eventos') || str_contains($h, 'Aucun'), 'cada familia ve en el calendario solo lo de sus hijos');


seccion('Laterales de escritorio');
$comoRol('daniela@ejemplo.fr', 'apoderado');
$evL = $pdo->query("SELECT e.id FROM sportiva_eventos e JOIN sportiva_convocados c ON c.evento_id = e.id JOIN sportiva_apoderados_jugadores aj ON aj.jugador_id = c.jugador_id JOIN sportiva_personas p ON p.id = aj.persona_id WHERE p.email = 'daniela@ejemplo.fr' AND e.estado = 'publicado' LIMIT 1")->fetchColumn();
[$h] = llamar(fn() => (new S\AgendaController($ctx))->evento($evL));
check(preg_match('/<aside class="dos-lat">.*?evento\.citados|<aside class="dos-lat">.*?Citados/s', $h) === 1 && str_contains($h, 'class="dos tres"'), 'en la convocatoria los citados van en el lateral derecho');
check(!str_contains($h, 'sheet angosta'), 'la convocatoria usa el ancho completo (con lateral), no la columna angosta');
[$h] = llamar(fn() => (new S\PerfilController($ctx))->inicio());
check(str_contains($h, 'sheet angosta'), 'las pantallas sin lateral quedan en columna angosta en escritorio');
[$h] = llamar(fn() => (new S\FeedController($ctx))->inicio());
check(str_contains($h, 'class="dos"') && str_contains($h, 'dos-lat solo-escritorio'), 'el feed tiene lateral con lo próximo');
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => (new S\PlantelController($ctx))->inicio());
check(str_contains($h, 'class="dos-lat"') && strpos($h, 'class="dos-lat"') < strpos($h, 'id="locales"'), 'el plantel lleva los lugares locales en el lateral');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->nuevo());
check(str_contains($h, 'class="dos tres"') && preg_match('/<aside class="dos-lat">\s*<section class="card">\s*<div class="ch"><h3>[^<]*<\/h3><span class="est" id="cuenta-citados"/s', $h) === 1, 'el formulario de la citación lleva los citados en el lateral');
$comoRol('sylvie@ejemplo.fr', 'directiva');
[$h] = llamar(fn() => (new S\ClubController($ctx))->inicio());
check(str_contains($h, 'dos-lat solo-escritorio'), 'el panel del club lleva su resumen en el lateral');


seccion('Feed: compositor, filtros y citaciones iguales a la agenda');
$fc = new S\FeedController($ctx);
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id'];
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => $fc->inicio());
check(str_contains($h, 'class="card compo"') && str_contains($h, 'href="/app/feed/nuevo"'), 'quien puede publicar ve el compositor «¿Qué quieres compartir…?» arriba del feed');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $fc->inicio());
check(!str_contains($h, 'class="card compo"') && str_contains($h, 'Aquí publican') || !str_contains($h, 'class="card compo"') && str_contains($h, 'publient'), 'las familias no ven el compositor y se les explica quién publica');
check(str_contains($h, '<article class="card ev ev-m">') && str_contains($h, 'class="horas h-m'), 'la citación del feed usa la misma tarjeta y los mismos horarios que la agenda');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check(str_contains($h, 'class="horas h-g'), 'la agenda usa los horarios en tamaño de agenda');
[$h] = llamar(fn() => $fc->inicio(), [], ['t' => 'posts']);
check(!str_contains($h, '<article class="card ev ev-m">'), 'el filtro Publicaciones oculta las citaciones');
[$h] = llamar(fn() => $fc->inicio(), [], ['t' => 'citaciones']);
check(str_contains($h, '<article class="card ev ev-m">') && !str_contains($h, '<article class="card post">'), 'el filtro Citaciones muestra solo citaciones');
check(str_contains($h, 'Todo') && str_contains($h, 'Publicaciones') && str_contains($h, 'Citaciones') && str_contains($h, 'Fotos'), 'el feed tiene filtros Todo / Publicaciones / Citaciones / Fotos');
[$h] = llamar(fn() => $fc->inicio(), [], ['t' => 'cualquiera']);
check(str_contains($h, '<article class="card ev ev-m">'), 'un filtro inválido muestra todo');


seccion('Mapa ampliable y ubicación faltante');
$cid = $S['club']->porSlug('fcj-oree-de-l-autize')['id'];
$u12 = $pdo->query("SELECT id FROM sportiva_equipos WHERE club_id = '{$cid}' AND nombre = 'U12'")->fetchColumn();
$ag = new S\AgendaController($ctx);
$ls = new S\LugarService($pdo);
$sinC = $ls->crear($cid, ['nombre' => 'Estadio sin coordenadas', 'direccion' => 'Calle 9']);
$evSC = $S['ev']->crear($cid, $u12, ['tipo' => 'partido', 'titulo' => 'Partido sin mapa', 'rival' => 'Z', 'inicio' => '2030-08-03 15:00:00', 'fin' => null, 'hora_cancha' => '2030-08-03 14:15:00', 'hora_encuentro' => null, 'lugar_id' => $sinC, 'encuentro_texto' => null, 'nota' => null, 'precio_info' => null], $S['pers']->porCorreo('pedro@ejemplo.fr')['id']);
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h] = llamar(fn() => $ag->evento($evSC));
check(!str_contains($h, 'class="mapa"') && str_contains($h, 'Falta la ubicación en el mapa') && str_contains($h, '/ubicacion') && preg_match('/class="ubic">\s*<input type="hidden" name="csrf"/', $h) === 1, 'si el lugar no tiene coordenadas, el entrenador ve cómo agregarlas');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => $ag->evento($evSC));
check(!str_contains($h, 'Falta la ubicación'), 'las familias no ven el aviso de ubicación faltante');
$comoRol('pedro@ejemplo.fr', 'entrenador');
[$h, $r] = llamar(fn() => $ag->guardarUbicacion($evSC), conCsrf(['enlace' => 'hola']));
check($ls->buscar($sinC)['lat'] === null, 'un enlace sin coordenadas no se guarda');
llamar(fn() => $ag->guardarUbicacion($evSC), ['enlace' => 'https://www.google.com/maps/@46.3,-0.4,15z']);
check($ls->buscar($sinC)['lat'] === null, 'guardar la ubicación exige CSRF');
llamar(fn() => $ag->guardarUbicacion($evSC), conCsrf(['enlace' => 'https://www.google.com/maps/@46.3,-0.4,15z']));
check($ls->buscar($sinC)['lat'] === '46.3' && $ls->buscar($sinC)['lon'] === '-0.4', 'el entrenador guarda la ubicación del lugar con un enlace de Google Maps');
[$h] = llamar(fn() => $ag->evento($evSC));
check(str_contains($h, 'class="mapa"') && str_contains($h, 'data-mapa-full') && str_contains($h, 'data-lat="46.3"') && str_contains($h, 'data-google=') && str_contains($h, 'data-waze=') && str_contains($h, 'Ampliar'), 'con coordenadas el mapa chico trae el botón Ampliar con todo lo que necesita la pantalla completa');
check(str_contains($h, 'onerror="this.onerror=null;this.src=this.dataset.f"') && str_contains($h, 'tile.openstreetmap.org'), 'si las teselas no cargan, el mapa chico prueba con las de respaldo');
check(str_contains($h, 'data-mi="Mi ubicación"') && str_contains($h, 'data-mi-error='), 'el mapa ampliado trae el texto del botón «Mi ubicación» y del error');
check(S\RecursosController::archivo('leaflet-js')['mime'] === 'application/javascript; charset=utf-8' && S\RecursosController::archivo('leaflet-css')['mime'] === 'text/css; charset=utf-8', 'Leaflet se sirve desde el propio plugin');
$comoRol('otro.coach@ejemplo.fr', 'entrenador');
llamar(fn() => $ag->guardarUbicacion($evSC), conCsrf(['enlace' => 'https://www.google.com/maps/@10.1,-0.9,15z']));
check($ls->buscar($sinC)['lat'] === '46.3', 'un entrenador de otro equipo no cambia la ubicación');

seccion('Hoja de estilos y recursos servidos por PHP');
$css = S\RecursosController::contenidoCss();
check($css !== null && str_contains($css, '--css-v:"' . S\RecursosController::versionPlugin() . '"'), 'la hoja de estilos lleva la versión del plugin dentro');
check($css !== null && !str_contains($css, 'url("grano.png")') && str_contains($css, '/app/recurso/grano'), 'las imágenes de la hoja apuntan a /app/recurso');
check(strlen(S\RecursosController::hashCss()) === 10, 'hash de la hoja para forzar la versión');
check(S\RecursosController::archivo('favicon')['mime'] === 'image/svg+xml' && S\RecursosController::archivo('icon-192')['mime'] === 'image/png', 'se sirven los íconos de la app');
check(S\RecursosController::archivo('../plugin.json') === null && S\RecursosController::archivo('sportiva.css') === null && S\RecursosController::archivo('') === null, 'solo se sirven los archivos de la lista');
$comoRol('daniela@ejemplo.fr', 'apoderado');
[$h] = llamar(fn() => (new S\AgendaController($ctx))->agenda());
check(str_contains($h, 'href="/app/estilos?v=' . S\RecursosController::hashCss() . '"') && str_contains($h, 'id="pie-version"') && str_contains($h, 'u330 v' . S\RecursosController::versionPlugin()), 'cada pantalla muestra la versión del plugin y de la hoja de estilos en el pie');

echo "\n\n";
if ($GLOBALS['fallas']) {
    echo count($GLOBALS['fallas']) . " fallas:\n - " . implode("\n - ", $GLOBALS['fallas']) . "\n";
    exit(1);
}
echo $GLOBALS['ok'] . " pruebas OK\n";
