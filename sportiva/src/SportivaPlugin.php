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
        $ctx->registerAdminRoute('GET',  'avisos',                 [$admin, 'avisos']);
        $ctx->registerAdminRoute('POST', 'avisos',                 [$admin, 'accionAvisos']);
        $ctx->registerAdminRoute('GET',  'medios',                 [$admin, 'medios']);
        $ctx->registerAdminRoute('POST', 'medios',                 [$admin, 'guardarMedios']);
        $ctx->registerAdminRoute('GET',  'diagnostico',            [$admin, 'diagnostico']);
        $ctx->registerAdminRoute('POST', 'diagnostico',            [$admin, 'vaciarDiagnostico']);
        $ctx->addAdminMenuItem('Clubes', '');
        $ctx->addAdminMenuItem('Correo (SMTP)', 'ajustes');
        $ctx->addAdminMenuItem('Fotos', 'medios');
        $ctx->addAdminMenuItem('Avisos y cron', 'avisos');
        $ctx->addAdminMenuItem('Diagnóstico', 'diagnostico');

        // Front: /app
        $auth = new AuthController($ctx);
        $agenda = new AgendaController($ctx);
        $plantel = new PlantelController($ctx);
        $familia = new FamiliaController($ctx);
        $foto = new FotoController($ctx);
        $turno = new TurnoController($ctx);
        $feed = new FeedController($ctx);
        $consulta = new ConsultaController($ctx);
        $avisos = new AvisosController($ctx);
        $medios = new MedioController($ctx);
        $adjuntos = new AdjuntoController($ctx);
        $recursos = new RecursosController();
        $club = new ClubController($ctx);
        $serie = new SerieController($ctx);
        $personas = new PersonasController($ctx);
        $perfil = new PerfilController($ctx);
        $p = static fn(callable $h): \Closure => static fn(mixed ...$a): mixed => Diagnostico::correr($h, $a);
        \Flight::route('GET /app',                   $p([$auth, 'landing']));
        \Flight::route('GET /app/club',                                 $p([$club, 'inicio']));
        \Flight::route('GET /app/club/equipos',                         $p([$club, 'equipos']));
        \Flight::route('POST /app/club/equipos',                        $p([$club, 'crearEquipo']));
        \Flight::route('GET /app/club/equipos/@id',                     $p(fn(string $id) => $club->equipo($id)));
        \Flight::route('POST /app/club/equipos/@id',                    $p(fn(string $id) => $club->actualizarEquipo($id)));
        \Flight::route('POST /app/club/equipos/@id/archivar',           $p(fn(string $id) => $club->archivarEquipo($id)));
        \Flight::route('POST /app/club/equipos/@id/staff',              $p(fn(string $id) => $club->agregarStaff($id)));
        \Flight::route('POST /app/club/equipos/@id/staff/@pid/quitar',  $p(fn(string $id, string $pid) => $club->quitarStaff($id, $pid)));
        \Flight::route('GET /app/club/lugares',                         $p([$club, 'lugares']));
        \Flight::route('GET /app/club/lugares/nuevo',                   $p([$club, 'lugarNuevo']));
        \Flight::route('POST /app/club/lugares',                        $p(fn() => $club->guardarLugar()));
        \Flight::route('GET /app/club/lugares/@id',                     $p(fn(string $id) => $club->lugarEditar($id)));
        \Flight::route('POST /app/club/lugares/@id',                    $p(fn(string $id) => $club->guardarLugar($id)));
        \Flight::route('POST /app/club/lugares/@id/eliminar',           $p(fn(string $id) => $club->eliminarLugar($id)));
        \Flight::route('GET /app/club/temporada',                       $p([$club, 'temporada']));
        \Flight::route('POST /app/club/temporada',                      $p([$club, 'guardarTemporada']));
        \Flight::route('GET /app/club/ajustes',                         $p([$club, 'ajustes']));
        \Flight::route('POST /app/club/ajustes',                        $p([$club, 'guardarAjustes']));
        \Flight::route('GET /app/club/personas',                        $p([$personas, 'lista']));
        \Flight::route('GET /app/club/personas/@id',                    $p(fn(string $id) => $personas->ficha($id)));
        \Flight::route('POST /app/club/personas/@id',                   $p(fn(string $id) => $personas->actualizar($id, 'datos')));
        \Flight::route('POST /app/club/personas/@id/rol',               $p(fn(string $id) => $personas->actualizar($id, 'rol')));
        \Flight::route('POST /app/club/personas/@id/rol/@rol/quitar',   $p(fn(string $id, string $r) => $personas->actualizar($id, 'quitar-rol', $r)));
        \Flight::route('POST /app/club/personas/@id/equipo',            $p(fn(string $id) => $personas->actualizar($id, 'equipo')));
        \Flight::route('POST /app/club/personas/@id/equipo/@eid/quitar', $p(fn(string $id, string $e) => $personas->actualizar($id, 'quitar-equipo', $e)));
        \Flight::route('GET /app/club/invitar',                         $p(fn() => $personas->formInvitar()));
        \Flight::route('POST /app/club/invitar',                        $p([$personas, 'invitar']));
        \Flight::route('GET /app/club/invitaciones',                    $p([$personas, 'invitaciones']));
        \Flight::route('POST /app/club/invitaciones/lista',             $p([$personas, 'cargarLista']));
        \Flight::route('POST /app/club/invitaciones/@id/reenviar',      $p(fn(string $id) => $personas->reenviar($id)));
        \Flight::route('GET /app/directorio',                           $p([$personas, 'directorio']));
        \Flight::route('GET /app/perfil',                               $p([$perfil, 'inicio']));
        \Flight::route('GET /app/perfil/datos',                         $p(fn() => $perfil->datos()));
        \Flight::route('POST /app/perfil/datos',                        $p([$perfil, 'guardarDatos']));
        \Flight::route('GET /app/perfil/compartir',                     $p([$perfil, 'compartir']));
        \Flight::route('POST /app/perfil/compartir',                    $p([$perfil, 'guardarCompartir']));
        \Flight::route('GET /app/perfil/privacidad',                    $p([$perfil, 'privacidad']));
        \Flight::route('POST /app/perfil/exportar',                     $p([$perfil, 'exportar']));
        \Flight::route('POST /app/perfil/fotos',                        $p([$perfil, 'pedirQuitarFotos']));
        \Flight::route('POST /app/perfil/eliminar',                     $p([$perfil, 'eliminar']));
        \Flight::route('GET /app/ayuda',                                $p([$perfil, 'ayuda']));
        \Flight::route('GET /app/familias',                             $p([$perfil, 'familias']));
        \Flight::route('GET /app/club/@slug',        $p(fn(string $s) => $auth->clubPublico($s)));
        \Flight::route('GET /app/entrar',            $p([$auth, 'entrar']));
        \Flight::route('POST /app/entrar',           $p([$auth, 'pedirCodigo']));
        \Flight::route('GET /app/codigo',            $p([$auth, 'codigo']));
        \Flight::route('POST /app/codigo',           $p([$auth, 'verificar']));
        \Flight::route('GET /app/elegir',            $p([$auth, 'elegir']));
        \Flight::route('POST /app/elegir',           $p([$auth, 'elegir']));
        \Flight::route('POST /app/salir',            $p([$auth, 'salir']));
        \Flight::route('GET /app/mas',               $p([$auth, 'mas']));
        \Flight::route('POST /app/idioma',           $p([$auth, 'cambiarIdioma']));
        \Flight::route('GET /app/agenda',            $p([$agenda, 'agenda']));
        \Flight::route('GET /app/turnos',                               $p([$turno, 'panel']));
        \Flight::route('POST /app/turnos/repartir',                     $p([$turno, 'repartir']));
        \Flight::route('POST /app/evento/@id/turnos',                   $p(fn(string $id) => $turno->agregar($id)));
        \Flight::route('POST /app/evento/@id/turnos/asignar',           $p(fn(string $id) => $turno->asignar($id)));
        \Flight::route('POST /app/evento/@id/turnos/yo',                $p(fn(string $id) => $turno->pedir($id)));
        \Flight::route('POST /app/evento/@id/turnos/@fid/@accion',      $p(fn(string $id, string $fid, string $a) => $turno->resolver($id, $fid, $a)));
        \Flight::route('GET /app/evento/@id/fotos',                     $p(fn(string $id) => $medios->galeria($id)));
        \Flight::route('POST /app/evento/@id/fotos',                    $p(fn(string $id) => $medios->subirFotos($id)));
        \Flight::route('GET /app/adjunto/@id/@variante',                $p(fn(string $id, string $v) => $adjuntos->servir($id, $v)));
        \Flight::route('GET /app/medio/@id',                            $p(fn(string $id) => $medios->ver($id)));
        \Flight::route('GET /app/medio/@id/@variante',                  $p(fn(string $id, string $v) => $medios->servir($id, $v)));
        \Flight::route('POST /app/medio/@id/borrar',                    $p(fn(string $id) => $medios->borrar($id)));
        \Flight::route('POST /app/medio/@id/reportar',                  $p(fn(string $id) => $medios->reportar($id)));
        \Flight::route('GET /app/serie/@id',                            $p(fn(string $id) => $serie->ver($id)));
        \Flight::route('POST /app/serie/@id',                           $p(fn(string $id) => $serie->guardar($id)));
        \Flight::route('GET /app/evento/nuevo',      $p([$agenda, 'nuevo']));
        \Flight::route('POST /app/evento',           $p([$agenda, 'crear']));
        \Flight::route('GET /app/evento/@id',        $p(fn(string $id) => $agenda->evento($id)));
        \Flight::route('POST /app/evento/@id',       $p(fn(string $id) => $agenda->actualizar($id)));
        \Flight::route('GET /app/evento/@id/editar', $p(fn(string $id) => $agenda->editar($id)));
        \Flight::route('POST /app/evento/@id/ubicacion', $p(fn(string $id) => $agenda->guardarUbicacion($id)));
        \Flight::route('POST /app/evento/@id/asistencia', $p(fn(string $id) => $agenda->responder($id)));
        \Flight::route('POST /app/evento/@id/cancelar',   $p(fn(string $id) => $agenda->cancelar($id)));
        \Flight::route('GET /app/plantel',                              $p([$plantel, 'inicio']));
        \Flight::route('POST /app/plantel/locales',                     $p([$plantel, 'localAgregar']));
        \Flight::route('POST /app/plantel/locales/@id/quitar',          $p(fn(string $id) => $plantel->localQuitar($id)));
        \Flight::route('GET /app/plantel/nuevo',                        $p([$plantel, 'nuevo']));
        \Flight::route('POST /app/plantel',                             $p([$plantel, 'crear']));
        \Flight::route('GET /app/plantel/solicitudes',                  $p([$plantel, 'solicitudes']));
        \Flight::route('POST /app/plantel/solicitudes/@id/@accion',     $p(fn(string $id, string $a) => $plantel->resolver($id, $a)));
        \Flight::route('GET /app/plantel/jugador/@id',                  $p(fn(string $id) => $plantel->ficha($id)));
        \Flight::route('POST /app/plantel/jugador/@id',                 $p(fn(string $id) => $plantel->actualizar($id)));
        \Flight::route('POST /app/plantel/jugador/@id/adulto',          $p(fn(string $id) => $plantel->agregarAdulto($id)));
        \Flight::route('POST /app/plantel/jugador/@id/adulto/@pid/quitar', $p(fn(string $id, string $pid) => $plantel->quitarAdulto($id, $pid)));
        \Flight::route('GET /app/familia',                              $p([$familia, 'inicio']));
        \Flight::route('GET /app/familia/adulto',                       $p([$familia, 'formAdulto']));
        \Flight::route('POST /app/familia/adulto',                      $p([$familia, 'guardarAdulto']));
        \Flight::route('GET /app/familia/hijo',                         $p([$familia, 'formHijo']));
        \Flight::route('POST /app/familia/hijo',                        $p([$familia, 'guardarHijo']));
        \Flight::route('GET /app/familia/hijo/@id',                     $p(fn(string $id) => $familia->editarHijo($id)));
        \Flight::route('POST /app/familia/hijo/@id',                    $p(fn(string $id) => $familia->actualizarHijo($id)));
        \Flight::route('POST /app/familia/solicitud/@id/cancelar',      $p(fn(string $id) => $familia->cancelar($id)));
        \Flight::route('POST /app/familia/adulto/@pid/quitar',          $p(fn(string $pid) => $familia->quitarAdulto($pid)));
        \Flight::route('GET /app/foto/@id',                             $p(fn(string $id) => $foto->ver($id)));
        \Flight::route('POST /app/plantel/jugador/@id/foto',            $p(fn(string $id) => $foto->subir($id, 'coach')));
        \Flight::route('POST /app/plantel/jugador/@id/foto/quitar',     $p(fn(string $id) => $foto->quitar($id, 'coach')));
        \Flight::route('POST /app/familia/hijo/@id/foto',               $p(fn(string $id) => $foto->subir($id, 'familia')));
        \Flight::route('POST /app/familia/hijo/@id/foto/quitar',        $p(fn(string $id) => $foto->quitar($id, 'familia')));
        \Flight::route('GET /app/feed',                                 $p([$feed, 'inicio']));
        \Flight::route('GET /app/feed/nuevo',                           $p([$feed, 'nuevo']));
        \Flight::route('POST /app/feed',                                $p([$feed, 'crear']));
        \Flight::route('GET /app/feed/@id',                             $p(fn(string $id) => $feed->ver($id)));
        \Flight::route('POST /app/feed/@id/comentario',                 $p(fn(string $id) => $feed->comentar($id)));
        \Flight::route('POST /app/feed/@id/fijar',                      $p(fn(string $id) => $feed->fijar($id)));
        \Flight::route('POST /app/feed/@id/borrar',                     $p(fn(string $id) => $feed->borrar($id)));
        \Flight::route('POST /app/feed/@id/comentario/@cid/borrar',     $p(fn(string $id, string $cid) => $feed->borrarComentario($id, $cid)));
        \Flight::route('GET /app/actividad',                            $p([$consulta, 'actividad']));
        \Flight::route('GET /app/consultas',                            $p([$consulta, 'lista']));
        \Flight::route('GET /app/consulta/nueva',                       $p([$consulta, 'nueva']));
        \Flight::route('POST /app/consulta',                            $p([$consulta, 'crear']));
        \Flight::route('GET /app/consulta/@id',                         $p(fn(string $id) => $consulta->ver($id)));
        \Flight::route('POST /app/consulta/@id',                        $p(fn(string $id) => $consulta->responder($id)));
        \Flight::route('GET /app/estilos',                               [$recursos, 'css']);
        \Flight::route('GET /app/recurso/@nombre',                       fn(string $n) => $recursos->recurso($n));
        \Flight::route('GET /app/sw.js',                                $p([$avisos, 'sw']));
        \Flight::route('GET /app/manifest.webmanifest',                 $p([$avisos, 'manifest']));
        \Flight::route('GET /app/cron/@token',                          $p(fn(string $t) => $avisos->cron($t)));
        \Flight::route('GET /app/avisos',                               $p([$avisos, 'lista']));
        \Flight::route('POST /app/avisos/leer',                         $p([$avisos, 'leerTodo']));
        \Flight::route('GET /app/avisos/ajustes',                       $p([$avisos, 'ajustes']));
        \Flight::route('POST /app/avisos/ajustes',                      $p([$avisos, 'guardarAjustes']));
        \Flight::route('POST /app/avisos/suscribir',                    $p([$avisos, 'suscribir']));
        \Flight::route('POST /app/avisos/baja',                         $p([$avisos, 'baja']));
        \Flight::route('GET /app/avisos/@id',                           $p(fn(string $id) => $avisos->abrir($id)));
        \Flight::route('GET /app/@seccion',          $p(fn(string $s) => $auth->pronto($s)));
    }

    public function getName(): string
    {
        return 'Sportiva';
    }

    public function getVersion(): string
    {
        return '0.16.3';
    }

    public function provides(): array
    {
        return [];
    }
}
