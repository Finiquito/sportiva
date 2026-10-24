<?php
declare(strict_types=1);

/**
 * Datos de ejemplo en la base de desarrollo: el club FCJ Orée de l’Autize con equipos, entrenadores,
 * familias y algunos eventos.   php dev/seed.php [--reset]
 */
require_once __DIR__ . '/bootstrap.php';

use TypeDock\Plugin\Sportiva as S;

function sportiva_sembrar(PDO $pdo): array
{
    $club = new S\ClubService($pdo);
    $pers = new S\PersonaService($pdo);
    $cid = $club->crear([
        'nombre' => 'FCJ Orée de l’Autize', 'slug' => 'fcj-oree-de-l-autize', 'ciudad' => 'Coulonges-sur-l’Autize', 'deporte' => 'futbol', 'idioma' => 'fr',
        'telefono' => '06 23 60 52 30', 'direccion' => '6 Place du Château, 79160 Coulonges-sur-l’Autize', 'lat' => '46.480236', 'lon' => '-0.599575',
        'descripcion' => 'Bienvenue au Football Club de Jeunes de l’Orée de l’Autize, un club dynamique situé à Coulonges-sur-l’Autize dans les Deux-Sèvres. Passionné de football, venez rejoindre notre association pour pratiquer votre sport favori dans une ambiance conviviale et sportive.',
    ]);
    $u12 = $club->crearEquipo($cid, 'U12', '2026-2027');
    $u10 = $club->crearEquipo($cid, 'U10', '2026-2027');
    $lug = new S\LugarService($pdo);
    $estadio = $lug->crear($cid, ['nombre' => 'Estadio del club', 'direccion' => 'Coulonges-sur-l’Autize', 'lat' => '46.480236', 'lon' => '-0.599575', 'predeterminado' => 1]);

    $camila = $pers->crear('camila@ejemplo.fr', 'Camila', 'es');
    $pers->dar($camila, $cid, 'encargado');
    $pers->asignarStaff($u12, $camila, 'encargado');
    $pedro = $pers->crear('pedro@ejemplo.fr', 'Pedro', 'es');
    $pers->dar($pedro, $cid, 'entrenador');
    $pers->asignarStaff($u12, $pedro);
    $sylvie = $pers->crear('sylvie@ejemplo.fr', 'Sylvie', 'fr');
    $pers->dar($sylvie, $cid, 'directiva');

    $daniela = $pers->crear('daniela@ejemplo.fr', 'Daniela Pérez', 'es');
    $pers->dar($daniela, $cid, 'apoderado');
    $lucas = $pers->crearJugador($cid, $u12, 'Lucas', '2014-03-12');
    $emma = $pers->crearJugador($cid, $u10, 'Emma', '2016-06-02');
    $pers->vincularHijo($daniela, $lucas);
    $pers->vincularHijo($daniela, $emma);
    $lucasCuenta = $pers->crear('lucas@ejemplo.fr', 'Lucas', 'es');
    $pers->dar($lucasCuenta, $cid, 'jugador');
    $pdo->prepare('UPDATE sportiva_jugadores SET persona_id = ? WHERE id = ?')->execute([$lucasCuenta, $lucas]);

    foreach (['Tomás' => 'Marta Garcia', 'Mateo' => 'Paul Fournier', 'Gabriel' => 'Inés Garcia', 'Louis' => 'Karim Dubois', 'Adam' => 'Luis Girard'] as $hijo => $padre) {
        $j = $pers->crearJugador($cid, $u12, $hijo, '2014-05-01');
        $pe = $pers->crear(strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $padre))) . '@ejemplo.fr', $padre, 'fr');
        $pers->dar($pe, $cid, 'apoderado');
        $pers->vincularHijo($pe, $j);
    }

    $ev = new S\EventoService($pdo);
    $hoy = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
    $sab = $hoy->modify('next saturday')->format('Y-m-d');
    $mar = $hoy->modify('next tuesday')->format('Y-m-d');
    $sab2 = $hoy->modify('next saturday +7 days')->format('Y-m-d');
    [$d] = $ev->normalizar(['tipo' => 'partido', 'titulo' => 'U12 vs Niort', 'rival' => 'Niort', 'fecha' => $sab, 'hora_partido' => '14:00', 'cancha_antes' => '45', 'hay_encuentro' => '1', 'hora_encuentro' => '12:30', 'encuentro_texto' => 'Estacionamiento del club', 'lugar_id' => $estadio]);
    $e1 = $ev->crear($cid, $u12, $d, $camila);
    [$d] = $ev->normalizar(['tipo' => 'entreno', 'titulo' => 'Entrenamiento U12', 'fecha' => $mar, 'hora_partido' => '18:00', 'hora_fin' => '19:30', 'lugar_id' => $estadio]);
    $ev->crear($cid, $u12, $d, $camila);
    [$d] = $ev->normalizar(['tipo' => 'partido', 'titulo' => 'U12 vs Chauray', 'rival' => 'Chauray', 'fecha' => $sab2, 'hora_partido' => '15:00', 'cancha_antes' => '45', 'lugar_id' => $estadio]);
    $ev->crear($cid, $u12, $d, $camila);
    $ev->responder($e1, $lucas, 'voy', $daniela);
    return ['club' => $cid, 'u12' => $u12, 'u10' => $u10, 'lucas' => $lucas, 'emma' => $emma, 'daniela' => $daniela, 'camila' => $camila, 'evento' => $e1];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    $pdo = sportiva_dev_pdo();
    $ctx = sportiva_dev_contexto($pdo);
    if (in_array('--reset', $argv ?? [], true)) {
        foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'sportiva_%'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $pdo->exec("DELETE FROM {$t}");
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM sportiva_clubes')->fetchColumn() === 0) {
        sportiva_sembrar($pdo);
        echo "Datos de ejemplo listos. Entra con /dev/entrar?email=daniela@ejemplo.fr (apoderada), camila@ejemplo.fr (encargada), pedro@ejemplo.fr, sylvie@ejemplo.fr, lucas@ejemplo.fr\n";
    } else {
        echo "Ya hay datos (usa --reset para borrarlos).\n";
    }
}
