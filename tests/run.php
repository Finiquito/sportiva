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

echo "\n\n";
if ($GLOBALS['fallas']) {
    echo count($GLOBALS['fallas']) . " fallas:\n - " . implode("\n - ", $GLOBALS['fallas']) . "\n";
    exit(1);
}
echo $GLOBALS['ok'] . " pruebas OK\n";
