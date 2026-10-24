<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Planificación repetida: ver todas las fechas, saltar o volver a poner una, y extenderla. Todo cambio avisa a las familias. */
final class SerieController extends AppBase
{
    private function propia(string $id): ?array
    {
        $s = (new SerieService($this->pdo))->buscar($id);
        return $s !== null && $s['club_id'] === $this->club['id'] && in_array($s['equipo_id'], array_column($this->gestionables(), 'id'), true) ? $s : null;
    }

    private function hoy(): string
    {
        return Tiempo::ahora((string) ($this->club['zona'] ?? 'Europe/Paris'))->format('Y-m-d');
    }

    public function ver(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $s = $this->propia($id);
        if ($s === null) {
            \Flight::notFound();
            return;
        }
        $hoy = $this->hoy();
        $futuras = [];
        $pasadas = 0;
        foreach ((new SerieService($this->pdo))->fechas($id) as $f) {
            if ($f['fecha'] < $hoy) {
                $pasadas++;
                continue;
            }
            $f['activa'] = $f['evento_id'] !== null && $f['estado'] === 'publicado';
            $futuras[] = $f;
        }
        echo $this->vista()->pagina('serie.latte', [
            'activa' => 'agenda', 's' => $s, 'p' => $s['plantilla'], 'futuras' => $futuras, 'pasadas' => $pasadas, 'equipo' => (new ClubService($this->pdo))->nombreEquipo($s['equipo_id']),
            'dias' => array_map('intval', array_filter(explode(',', (string) $s['dias']))),
        ]);
    }

    public function guardar(string $id): void
    {
        if (($r = $this->exigirCoach()) !== null) {
            $this->ir($r);
            return;
        }
        $s = $this->propia($id);
        if ($s === null || !$this->csrfOk()) {
            $this->ir('/app/agenda');
            return;
        }
        $svc = new SerieService($this->pdo);
        $hoy = $this->hoy();
        $hacer = (array) ($_POST['hacer'] ?? []);
        $quiero = [];
        $ultima = $hoy;
        foreach ((array) ($_POST['visto'] ?? []) as $f) {
            if (is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && $f >= $hoy) {
                $quiero[$f] = isset($hacer[$f]);
            }
        }
        foreach ($svc->fechas($id) as $f) {
            $ultima = max($ultima, $f['fecha']);
        }
        $nuevas = [];
        $meses = (int) ($_POST['meses'] ?? 0);
        if ($meses > 0) {
            $desde = (new \DateTimeImmutable($ultima))->modify('+1 day')->format('Y-m-d');
            $nuevas = SerieService::generar($desde, (array) ($_POST['dias'] ?? explode(',', (string) $s['dias'])), $meses, false);
            $nuevas = array_values(array_filter($nuevas, fn($f) => $f >= $hoy));
        }
        $ev = new EventoService($this->pdo);
        $res = $svc->aplicar($s, $quiero, (array) ($_POST['motivo'] ?? []), $nuevas, $this->persona['id'], $ev);
        if ($res['quitadas'] || $res['puestas']) {
            $titulo = (string) $s['plantilla']['titulo'];
            $adultos = [];
            foreach (array_unique($res['eventos']) as $eid) {
                $adultos = array_merge($adultos, (new AvisoService($this->pdo))->adultosDelEvento($eid));
            }
            $q = $res['quitadas'];
            $pu = $res['puestas'];
            $this->notificar(array_values(array_unique($adultos)), 'agenda', function (I18n $i) use ($titulo, $q, $pu) {
                $d = fn(string $f) => Tiempo::diaCorto($f . ' 00:00:00', $i);
                $partes = [];
                if ($q) {
                    $partes[] = $i->t('aviso.serie.suspende', ['lista' => implode(', ', array_map(fn($x) => $d($x['fecha']) . ($x['motivo'] !== '' ? ' (' . $x['motivo'] . ')' : ''), $q))]);
                }
                if ($pu) {
                    $partes[] = $i->t('aviso.serie.agrega', ['lista' => implode(', ', array_map($d, $pu))]);
                }
                return [$i->t('aviso.serie.cambios', ['titulo' => $titulo]), implode(' · ', $partes)];
            }, '/app/agenda');
        }
        $this->ctx->redirect('/app/serie/' . $id, $this->i18n->t($res['quitadas'] || $res['puestas'] ? 'serie.guardada' : 'evento.guardado'));
    }
}
