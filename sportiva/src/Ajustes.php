<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Ajustes clave/valor. club_id vacío = globales (SMTP). Los secretos se guardan cifrados. */
final class Ajustes
{
    public function __construct(private readonly \PDO $pdo) {}

    public function get(string $clave, string $defecto = '', string $club = ''): string
    {
        $s = $this->pdo->prepare('SELECT valor FROM sportiva_ajustes WHERE club_id = ? AND clave = ?');
        $s->execute([$club, $clave]);
        $v = $s->fetchColumn();
        return $v === false || $v === null ? $defecto : (string) $v;
    }

    public function set(string $clave, string $valor, string $club = ''): void
    {
        $this->pdo->prepare('DELETE FROM sportiva_ajustes WHERE club_id = ? AND clave = ?')->execute([$club, $clave]);
        $this->pdo->prepare('INSERT INTO sportiva_ajustes (club_id, clave, valor) VALUES (?, ?, ?)')->execute([$club, $clave, $valor]);
    }

    public function secreto(string $clave): string
    {
        $v = $this->get($clave);
        return $v === '' ? '' : (new Cifrado())->descifrar($v);
    }

    public function guardarSecreto(string $clave, string $claro): void
    {
        $this->set($clave, $claro === '' ? '' : (new Cifrado())->cifrar($claro));
    }

    /** @return array{host:string,puerto:int,seguridad:string,usuario:string,clave:string,remitente:string,nombre:string} */
    public function smtp(): array
    {
        return [
            'host'      => $this->get('smtp_host'),
            'puerto'    => (int) ($this->get('smtp_puerto') ?: 587),
            'seguridad' => $this->get('smtp_seguridad', 'tls'),
            'usuario'   => $this->get('smtp_usuario'),
            'clave'     => $this->secreto('smtp_clave'),
            'remitente' => $this->get('smtp_remitente'),
            'nombre'    => $this->get('smtp_nombre', 'Sportiva'),
        ];
    }
}
