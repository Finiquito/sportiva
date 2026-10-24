<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Acceso a datos con SQL portable (SQLite en pruebas, MariaDB en producción). */
class Repo
{
    public function __construct(protected readonly \PDO $pdo) {}

    /** @return array<int, array<string, mixed>> */
    protected function filas(string $sql, array $p = []): array
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    /** @return array<string, mixed>|null */
    protected function fila(string $sql, array $p = []): ?array
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        $r = $s->fetch();
        return $r === false ? null : $r;
    }

    protected function ejecutar(string $sql, array $p = []): void
    {
        $this->pdo->prepare($sql)->execute($p);
    }

    protected function id(): string
    {
        return typedock_uuid7();
    }
}
