<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Login sin contraseña: código de 6 dígitos por correo, válido 10 minutos, de un solo uso.
 * Un código nuevo invalida los anteriores; máximo 5 códigos pedidos y 5 intentos fallidos
 * cada 10 minutos por persona (si no, un código de 6 dígitos se adivinaría por fuerza bruta).
 */
final class CodigoAcceso extends Repo
{
    private const TTL_MINUTOS = 10;
    private const MAX_CODIGOS = 5;
    private const MAX_FALLOS  = 5;

    private function hace(int $min): string
    {
        return (Tiempo::$ahora ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->modify("-{$min} minutes")->format('Y-m-d H:i:s');
    }

    public function demasiadosCodigos(string $persona): bool
    {
        $f = $this->fila('SELECT COUNT(*) AS n FROM sportiva_codigos WHERE persona_id = ? AND created_at > ?', [$persona, $this->hace(self::TTL_MINUTOS)]);
        return (int) ($f['n'] ?? 0) >= self::MAX_CODIGOS;
    }

    public function bloqueado(string $persona): bool
    {
        $f = $this->fila('SELECT COUNT(*) AS n FROM sportiva_intentos WHERE persona_id = ? AND created_at > ?', [$persona, $this->hace(self::TTL_MINUTOS)]);
        return (int) ($f['n'] ?? 0) >= self::MAX_FALLOS;
    }

    public function generar(string $persona): string
    {
        $codigo = (string) random_int(100000, 999999);
        $ahora = Tiempo::utc();
        $this->ejecutar('UPDATE sportiva_codigos SET usado_en = ? WHERE persona_id = ? AND usado_en IS NULL', [$ahora, $persona]);
        $expira = (new \DateTimeImmutable($ahora, new \DateTimeZone('UTC')))->modify('+' . self::TTL_MINUTOS . ' minutes')->format('Y-m-d H:i:s');
        $this->ejecutar('INSERT INTO sportiva_codigos (id, persona_id, codigo, expira_en, created_at) VALUES (?, ?, ?, ?, ?)', [$this->id(), $persona, $codigo, $expira, $ahora]);
        return $codigo;
    }

    public function verificar(string $persona, string $codigo): bool
    {
        if ($this->bloqueado($persona)) {
            return false;
        }
        $f = $this->fila('SELECT id, expira_en FROM sportiva_codigos WHERE persona_id = ? AND codigo = ? AND usado_en IS NULL ORDER BY created_at DESC LIMIT 1', [$persona, trim($codigo)]);
        if ($f === null || $f['expira_en'] < Tiempo::utc()) {
            $this->ejecutar('INSERT INTO sportiva_intentos (id, persona_id, created_at) VALUES (?, ?, ?)', [$this->id(), $persona, Tiempo::utc()]);
            return false;
        }
        $this->ejecutar('UPDATE sportiva_codigos SET usado_en = ? WHERE id = ?', [Tiempo::utc(), $f['id']]);
        $this->ejecutar('DELETE FROM sportiva_intentos WHERE persona_id = ?', [$persona]);
        return true;
    }
}
