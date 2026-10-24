<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

use TypeDock\Core\PluginContext;

/** Crea avisos (con el texto en el idioma de cada persona) y los entrega: push y correo. */
final class Notificador
{
    private static bool $flushRegistrado = false;
    private AvisoService $avisos;

    public function __construct(private readonly PluginContext $ctx, private readonly \PDO $pdo)
    {
        $this->avisos = new AvisoService($pdo);
    }

    /** Idioma de una persona: el suyo, el del club o francés. */
    private function idioma(string $persona, string $club): string
    {
        $p = (new PersonaService($this->pdo))->buscar($persona);
        $c = (new ClubService($this->pdo))->buscar($club);
        foreach ([$p['idioma'] ?? null, $c['idioma'] ?? null] as $i) {
            if ($i && I18n::esValido((string) $i)) {
                return (string) $i;
            }
        }
        return 'fr';
    }

    /**
     * Deja un aviso para cada persona. `$texto` recibe el I18n de cada una y devuelve [título, cuerpo].
     * @param array<int, string> $personas @param callable(I18n): array{0: string, 1: string} $texto
     */
    public function avisar(array $personas, string $club, string $cat, callable $texto, string $url, ?string $excluir = null, ?array $evento = null): int
    {
        $porIdioma = [];
        foreach (array_unique(array_filter($personas)) as $p) {
            if ($p !== $excluir) {
                $porIdioma[$this->idioma($p, $club)][] = $p;
            }
        }
        $n = 0;
        foreach ($porIdioma as $idioma => $ids) {
            [$titulo, $cuerpo] = $texto(new I18n($idioma));
            $n += $this->avisos->crear($ids, $club, $cat, $titulo, $cuerpo, $url, null, $evento);
        }
        if ($n > 0) {
            $this->entregarAlTerminar();
        }
        return $n;
    }

    /** Envía lo pendiente cuando la respuesta ya salió (sin hacer esperar a quien tocó el botón). */
    private function entregarAlTerminar(): void
    {
        if (self::$flushRegistrado || defined('SPORTIVA_PRUEBAS')) {
            return;
        }
        self::$flushRegistrado = true;
        register_shutdown_function(function (): void {
            foreach (['litespeed_finish_request', 'fastcgi_finish_request'] as $f) {
                if (function_exists($f)) {
                    $f();
                    break;
                }
            }
            @ignore_user_abort(true);
            @set_time_limit(25);
            try {
                $this->entregar(30);
            } catch (\Throwable $e) {
                Diagnostico::anotar($e);
            }
        });
    }

    /** @return array{0: string, 1: string} pem privada y pública (base64url) de VAPID; las crea la primera vez */
    public function claves(bool $regenerar = false): array
    {
        $aj = new Ajustes($this->pdo);
        $pem = $aj->secreto('vapid_privada');
        $pub = $aj->get('vapid_publica');
        if ($regenerar || $pem === '' || $pub === '') {
            [$pem, $pub] = WebPush::generarClaves();
            $aj->guardarSecreto('vapid_privada', $pem);
            $aj->set('vapid_publica', $pub);
            $this->pdo->exec('DELETE FROM sportiva_push_subs');
        }
        return [$pem, $pub];
    }

    public function tokenCron(): string
    {
        $aj = new Ajustes($this->pdo);
        $t = $aj->get('cron_token');
        if ($t === '') {
            $t = bin2hex(random_bytes(16));
            $aj->set('cron_token', $t);
        }
        return $t;
    }

    private function contacto(): string
    {
        $r = (new Ajustes($this->pdo))->get('smtp_remitente');
        return 'mailto:' . ($r !== '' ? $r : 'avisos@u330.app');
    }

    /** Entrega la cola. @return array{push: int, correo: int} */
    public function entregar(int $max = 40): array
    {
        $res = ['push' => 0, 'correo' => 0];
        $cola = $this->avisos->enCola($max);
        if ($cola === []) {
            return $res;
        }
        [$pem, $pub] = WebPush::disponible() ? $this->claves() : ['', ''];
        $correo = new Correo($this->ctx, $this->pdo);
        foreach ($cola as $a) {
            if ((int) $a['push_pendiente'] === 1 && $pem !== '') {
                $msg = json_encode(['t' => $a['titulo'], 'b' => $a['cuerpo'], 'u' => $a['url'], 'n' => $this->avisos->sinLeer($a['persona_id'], $a['club_id'])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                foreach ($this->avisos->suscripciones($a['persona_id']) as $s) {
                    $code = WebPush::enviar($s, (string) $msg, $pem, $pub, $this->contacto());
                    if ($code === 404 || $code === 410) {
                        $this->avisos->quitarSuscripcion($s['endpoint']);
                    } elseif ($code >= 200 && $code < 300) {
                        $res['push']++;
                    }
                }
            }
            if ((int) $a['correo_pendiente'] === 1 && $a['email']) {
                $i = new I18n($a['idioma'] && I18n::esValido((string) $a['idioma']) ? (string) $a['idioma'] : 'fr');
                if ($correo->aviso((string) $a['email'], (string) $a['persona_nombre'], (string) $a['titulo'], (string) $a['cuerpo'], (string) $a['url'], $i)) {
                    $res['correo']++;
                }
            }
            $this->avisos->entregado($a['id']);
        }
        return $res;
    }

    /** Recordatorio de asistencia para lo que empieza en las próximas 24 horas. @return int avisos creados */
    public function recordatorios(): int
    {
        $n = 0;
        foreach ((new ClubService($this->pdo))->todos() as $club) {
            $ahora = Tiempo::ahora((string) $club['zona']);
            $desde = $ahora->format('Y-m-d H:i:s');
            $hasta = $ahora->modify('+24 hours')->format('Y-m-d H:i:s');
            foreach ($this->avisos->recordatoriosPendientes($club['id'], $desde, $hasta) as $r) {
                $e = $r['evento'];
                $n += $this->avisar($r['personas'], $club['id'], 'agenda', fn(I18n $i) => [$i->t('aviso.recordatorio', ['titulo' => $e['titulo']]), $i->t('aviso.cuando', ['dia' => Tiempo::diaCorto($e['inicio'], $i), 'hora' => Tiempo::hora($e['inicio'])])], '/app/evento/' . $e['id']);
                foreach ($r['personas'] as $p) {
                    $this->avisos->recordado($e['id'], $p);
                }
            }
        }
        return $n;
    }
}
