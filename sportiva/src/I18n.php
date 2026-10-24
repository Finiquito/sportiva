<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Idiomas estilo WordPress: lang/fr.json es la base; cada idioma es un archivo JSON plano.
 * Los idiomas añadidos viven en storage/sportiva_lang/<código>.json (sobreviven a las actualizaciones).
 * Respaldo: idioma elegido → fr → la propia clave. Los textos admiten {nombre} como variables.
 */
final class I18n
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    public function __construct(private readonly string $idioma = 'fr') {}

    public function idioma(): string
    {
        return $this->idioma;
    }

    /** Idiomas disponibles: código => nombre en su propio idioma. */
    public static function disponibles(): array
    {
        $o = [];
        foreach (self::carpetas() as $dir) {
            foreach (glob($dir . '/*.json') ?: [] as $f) {
                $c = basename($f, '.json');
                $d = self::cargar($c);
                $o[$c] = (string) ($d['_nombre'] ?? $c);
            }
        }
        ksort($o);
        return $o;
    }

    public static function esValido(string $c): bool
    {
        return (bool) preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $c) && isset(self::disponibles()[$c]);
    }

    /** @return string[] */
    private static function carpetas(): array
    {
        $extra = Almacen::dir('lang');
        return array_filter([dirname(__DIR__) . '/lang', is_dir($extra) ? $extra : null]);
    }

    /** @return array<string, mixed> */
    private static function cargar(string $c): array
    {
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $c)) {
            return [];
        }
        if (!isset(self::$cache[$c])) {
            $datos = [];
            foreach (self::carpetas() as $dir) {
                $f = $dir . '/' . $c . '.json';
                if (is_file($f)) {
                    $j = json_decode((string) file_get_contents($f), true);
                    if (is_array($j)) {
                        $datos = $j + $datos;
                    }
                }
            }
            self::$cache[$c] = $datos;
        }
        return self::$cache[$c];
    }

    public static function reiniciar(): void
    {
        self::$cache = [];
    }

    private function buscar(string $clave): mixed
    {
        return self::cargar($this->idioma)[$clave] ?? self::cargar('fr')[$clave] ?? null;
    }

    /** @param array<string, scalar> $vars */
    public function t(string $clave, array $vars = []): string
    {
        $v = $this->buscar($clave);
        $s = is_string($v) ? $v : $clave;
        foreach ($vars as $k => $val) {
            $s = str_replace('{' . $k . '}', (string) $val, $s);
        }
        return $s;
    }

    /** Plural simple: claves «x.uno» y «x.otros» (con {n}). */
    public function n(string $clave, int $n): string
    {
        return $this->t($clave . ($n === 1 ? '.uno' : '.otros'), ['n' => $n]);
    }

    /** @return string[] */
    public function lista(string $clave): array
    {
        $v = $this->buscar($clave);
        return is_array($v) ? array_map('strval', $v) : [];
    }
}
