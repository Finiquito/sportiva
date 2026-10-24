<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Planificaciones repetidas: genera las fechas, crea los eventos y aplica cambios (saltar o volver a poner una fecha). */
final class SerieService extends Repo
{
    public const MAX_FECHAS = 120;

    /**
     * Fechas «Y-m-d» desde `$inicio` hasta `$meses` meses después, solo en los días de la semana pedidos (1 = lunes … 7 = domingo).
     * El día de la fecha inicial siempre cuenta. @param array<int, int|string> $dias @return array<int, string>
     */
    public static function generar(string $inicio, array $dias, int $meses, bool $incluirInicio = true): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
            return [];
        }
        $d0 = new \DateTimeImmutable($inicio);
        $dias = array_values(array_unique(array_filter(array_map('intval', $dias), fn($d) => $d >= 1 && $d <= 7)));
        if ($incluirInicio) {
            $dias[] = (int) $d0->format('N');
        }
        $dias = array_unique($dias);
        $fin = $d0->modify('+' . max(1, min(12, $meses)) . ' months');
        $r = [];
        for ($d = $d0; $d <= $fin && count($r) < self::MAX_FECHAS; $d = $d->modify('+1 day')) {
            if (in_array((int) $d->format('N'), $dias, true)) {
                $r[] = $d->format('Y-m-d');
            }
        }
        return $r;
    }

    /** Pasa un evento normalizado (con fecha completa) a plantilla: guarda solo la hora de cada campo. @param array<string, mixed> $d @param string[]|null $jugadores */
    public static function plantilla(array $d, ?array $jugadores, array $extra = []): array
    {
        $h = fn(?string $f) => $f ? substr($f, 11, 8) : null;
        $d['inicio'] = $h($d['inicio']);
        $d['fin'] = $h($d['fin']);
        $d['hora_cancha'] = $h($d['hora_cancha']);
        $d['hora_encuentro'] = $h($d['hora_encuentro']);
        return $d + ['jugadores' => $jugadores, 'extra' => $extra];
    }

    /** Datos listos para EventoService::crear() en una fecha dada. @param array<string, mixed> $p @return array<string, mixed> */
    public static function datosPara(array $p, string $fecha): array
    {
        foreach (['inicio', 'fin', 'hora_cancha', 'hora_encuentro'] as $k) {
            $p[$k] = !empty($p[$k]) ? $fecha . ' ' . $p[$k] : null;
        }
        return $p;
    }

    public function buscar(string $id): ?array
    {
        $s = $this->fila('SELECT * FROM sportiva_series WHERE id = ?', [$id]);
        if ($s !== null) {
            $s['plantilla'] = json_decode((string) $s['plantilla'], true) ?: [];
        }
        return $s;
    }

    /** Serie a la que pertenece un evento (o null). */
    public function deEvento(string $evento): ?array
    {
        $f = $this->fila('SELECT serie_id FROM sportiva_serie_fechas WHERE evento_id = ?', [$evento]);
        return $f ? $this->buscar($f['serie_id']) : null;
    }

    /** Fechas de la serie con el estado de su evento. @return array<int, array<string, mixed>> */
    public function fechas(string $serie): array
    {
        return $this->filas(
            'SELECT f.id, f.fecha, f.evento_id, f.omitida, f.motivo, ev.estado, ev.inicio FROM sportiva_serie_fechas f LEFT JOIN sportiva_eventos ev ON ev.id = f.evento_id WHERE f.serie_id = ? ORDER BY f.fecha',
            [$serie]
        );
    }

    /**
     * Crea la serie y sus eventos. `$fechas` = fecha => null (se hace) o motivo (se salta; '' si no hay motivo).
     * @param array<string, mixed> $plantilla @param array<string, string|null> $fechas @return array{0: string, 1: array<int, string>} [serie, eventos creados]
     */
    public function crear(string $club, string $equipo, array $plantilla, array $dias, array $fechas, ?string $por, EventoService $ev): array
    {
        $id = $this->id();
        $ahora = Tiempo::sello();
        $this->ejecutar('INSERT INTO sportiva_series (id, club_id, equipo_id, plantilla, dias, creado_por, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$id, $club, $equipo, json_encode($plantilla, JSON_UNESCAPED_UNICODE), implode(',', $dias), $por, $ahora, $ahora]);
        $creados = [];
        ksort($fechas);
        foreach ($fechas as $fecha => $motivo) {
            $eid = null;
            if ($motivo === null) {
                $eid = $this->crearEvento($id, $club, $equipo, $plantilla, $fecha, $por, $ev);
                $creados[] = $eid;
            }
            $this->ejecutar('INSERT INTO sportiva_serie_fechas (id, serie_id, fecha, evento_id, omitida, motivo) VALUES (?, ?, ?, ?, ?, ?)', [$this->id(), $id, $fecha, $eid, $motivo === null ? 0 : 1, $motivo !== null && $motivo !== '' ? mb_substr($motivo, 0, 160) : null]);
        }
        return [$id, $creados];
    }

    private function crearEvento(string $serie, string $club, string $equipo, array $plantilla, string $fecha, ?string $por, EventoService $ev): string
    {
        return $ev->crear($club, $equipo, self::datosPara($plantilla, $fecha), $por, (array) ($plantilla['extra'] ?? []), $plantilla['jugadores'] ?? null);
    }

    /**
     * Aplica la lista de fechas deseadas: salta las desmarcadas (cancela su evento), vuelve a poner las marcadas y suma fechas nuevas.
     * `$quiero` = fecha => true/false (solo fechas futuras), `$motivos` = fecha => texto, `$nuevas` = fechas nuevas a crear.
     * @return array{quitadas: array<int, array{fecha: string, motivo: string}>, puestas: array<int, string>, eventos: array<int, string>}
     */
    public function aplicar(array $serie, array $quiero, array $motivos, array $nuevas, ?string $por, EventoService $ev): array
    {
        $res = ['quitadas' => [], 'puestas' => [], 'eventos' => []];
        $filas = [];
        foreach ($this->fechas($serie['id']) as $f) {
            $filas[$f['fecha']] = $f;
        }
        foreach ($quiero as $fecha => $hacer) {
            $f = $filas[$fecha] ?? null;
            if ($f === null) {
                continue;
            }
            $activa = $f['evento_id'] !== null && $f['estado'] === 'publicado';
            $motivo = mb_substr(trim((string) ($motivos[$fecha] ?? '')), 0, 160);
            if ($activa && !$hacer) {
                $ev->cancelar($f['evento_id']);
                $this->ejecutar('UPDATE sportiva_serie_fechas SET omitida = 1, motivo = ? WHERE id = ?', [$motivo !== '' ? $motivo : null, $f['id']]);
                $res['quitadas'][] = ['fecha' => $fecha, 'motivo' => $motivo];
                $res['eventos'][] = $f['evento_id'];
            } elseif (!$activa && $hacer) {
                if ($f['evento_id'] !== null) {
                    $ev->reactivar($f['evento_id']);
                    $eid = $f['evento_id'];
                } else {
                    $eid = $this->crearEvento($serie['id'], $serie['club_id'], $serie['equipo_id'], $serie['plantilla'], $fecha, $por, $ev);
                }
                $this->ejecutar('UPDATE sportiva_serie_fechas SET evento_id = ?, omitida = 0, motivo = NULL WHERE id = ?', [$eid, $f['id']]);
                $res['puestas'][] = $fecha;
                $res['eventos'][] = $eid;
            } elseif (!$activa && !$hacer) {
                $this->ejecutar('UPDATE sportiva_serie_fechas SET motivo = ? WHERE id = ?', [$motivo !== '' ? $motivo : null, $f['id']]);
            }
        }
        foreach ($nuevas as $fecha) {
            if (isset($filas[$fecha])) {
                continue;
            }
            $eid = $this->crearEvento($serie['id'], $serie['club_id'], $serie['equipo_id'], $serie['plantilla'], $fecha, $por, $ev);
            $this->ejecutar('INSERT INTO sportiva_serie_fechas (id, serie_id, fecha, evento_id, omitida, motivo) VALUES (?, ?, ?, ?, 0, NULL)', [$this->id(), $serie['id'], $fecha, $eid]);
            $res['puestas'][] = $fecha;
            $res['eventos'][] = $eid;
        }
        $this->ejecutar('UPDATE sportiva_series SET updated_at = ? WHERE id = ?', [Tiempo::sello(), $serie['id']]);
        return $res;
    }
}
