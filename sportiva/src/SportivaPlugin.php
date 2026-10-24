<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

use TypeDock\Contract\PluginInterface;
use TypeDock\Core\PluginContext;

/**
 * Sportiva — plataforma multi-club para coordinar clubes, equipos, entrenadores, jugadores y apoderados.
 *
 * 0.1  Base: clubes, equipos y personas con rol por club; entrada con código por correo; es/fr; Más.
 * 0.2  Agenda y asistencia: convocatorias (horas encuentro / en cancha / partido), lugares con mapa,
 *      Voy / Duda / No voy por hijo, vista del entrenador y del jugador (solo lectura).
 * Siguiente: turnos de traslado, feed y consultas, notificaciones, fotos, chat de niños, panel de la directiva.
 */
class SportivaPlugin implements PluginInterface
{
    public function register(PluginContext $ctx): void
    {
        $ctx->migrate(__DIR__ . '/../migrations');

        // Admin técnico: /admin/sportiva
        $admin = new AdminController($ctx);
        $ctx->registerAdminRoute('GET',  '',                       [$admin, 'index']);
        $ctx->registerAdminRoute('POST', 'clubes',                 [$admin, 'crear']);
        $ctx->registerAdminRoute('GET',  'clubes/@id',             fn(string $id) => $admin->editar($id));
        $ctx->registerAdminRoute('POST', 'clubes/@id',             fn(string $id) => $admin->actualizar($id));
        $ctx->registerAdminRoute('POST', 'clubes/@id/equipos',     fn(string $id) => $admin->equipo($id));
        $ctx->registerAdminRoute('POST', 'clubes/@id/personas',    fn(string $id) => $admin->persona($id));
        $ctx->registerAdminRoute('GET',  'ajustes',                [$admin, 'ajustes']);
        $ctx->registerAdminRoute('POST', 'ajustes',                [$admin, 'guardarAjustes']);
        $ctx->addAdminMenuItem('Clubes', '');
        $ctx->addAdminMenuItem('Correo (SMTP)', 'ajustes');

        // Front: /app
        $auth = new AuthController($ctx);
        $agenda = new AgendaController($ctx);
        \Flight::route('GET /app',                   [$auth, 'landing']);
        \Flight::route('GET /app/club/@slug',        fn(string $s) => $auth->clubPublico($s));
        \Flight::route('GET /app/entrar',            [$auth, 'entrar']);
        \Flight::route('POST /app/entrar',           [$auth, 'pedirCodigo']);
        \Flight::route('GET /app/codigo',            [$auth, 'codigo']);
        \Flight::route('POST /app/codigo',           [$auth, 'verificar']);
        \Flight::route('GET /app/elegir',            [$auth, 'elegir']);
        \Flight::route('POST /app/elegir',           [$auth, 'elegir']);
        \Flight::route('POST /app/salir',            [$auth, 'salir']);
        \Flight::route('GET /app/mas',               [$auth, 'mas']);
        \Flight::route('POST /app/idioma',           [$auth, 'cambiarIdioma']);
        \Flight::route('GET /app/agenda',            [$agenda, 'agenda']);
        \Flight::route('GET /app/evento/nuevo',      [$agenda, 'nuevo']);
        \Flight::route('POST /app/evento',           [$agenda, 'crear']);
        \Flight::route('GET /app/evento/@id',        fn(string $id) => $agenda->evento($id));
        \Flight::route('POST /app/evento/@id',       fn(string $id) => $agenda->actualizar($id));
        \Flight::route('GET /app/evento/@id/editar', fn(string $id) => $agenda->editar($id));
        \Flight::route('POST /app/evento/@id/asistencia', fn(string $id) => $agenda->responder($id));
        \Flight::route('POST /app/evento/@id/cancelar',   fn(string $id) => $agenda->cancelar($id));
        \Flight::route('GET /app/@seccion',          fn(string $s) => $auth->pronto($s));
    }

    public function getName(): string
    {
        return 'Sportiva';
    }

    public function getVersion(): string
    {
        return '0.2.0';
    }

    public function provides(): array
    {
        return [];
    }
}
