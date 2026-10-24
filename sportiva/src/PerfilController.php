<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** «Más»: perfil, datos que se comparten, privacidad (exportar, quitar fotos, eliminar), ayuda y familias del equipo. */
final class PerfilController extends AppBase
{
    private function dentro(): bool
    {
        if (($r = $this->exigir()) !== null) {
            $this->ir($r);
            return false;
        }
        return true;
    }

    private function esApoderado(): bool
    {
        return $this->rol === 'apoderado';
    }

    public function inicio(): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('perfil.latte', ['activa' => 'mas', 'idiomas' => I18n::disponibles()]);
        }
    }

    public function datos(array $err = [], array $d = []): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('perfil-datos.latte', ['activa' => 'mas', 'errores' => $err, 'd' => $d + ['nombre' => $this->persona['nombre'], 'telefono' => $this->persona['telefono']]]);
        }
    }

    public function guardarDatos(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/perfil/datos');
            return;
        }
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 160) {
            $this->datos(['nombre' => 'club.error.nombre'], $_POST);
            return;
        }
        (new PersonaService($this->pdo))->actualizarDatos($this->persona['id'], $nombre, mb_substr((string) ($_POST['telefono'] ?? ''), 0, 40));
        $this->ctx->redirect('/app/perfil/datos', $this->i18n->t('evento.guardado'));
    }

    public function compartir(): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('perfil-compartir.latte', ['activa' => 'mas', 'c' => (new PrivacidadService($this->pdo))->get($this->persona['id'])]);
        }
    }

    public function guardarCompartir(): void
    {
        if ($this->dentro()) {
            if ($this->csrfOk()) {
                $dir = !empty($_POST['directorio']);
                (new PrivacidadService($this->pdo))->guardar($this->persona['id'], $dir && !empty($_POST['telefono']), $dir && !empty($_POST['correo']), $dir);
            }
            $this->ctx->redirect('/app/perfil/compartir', $this->i18n->t('evento.guardado'));
        }
    }

    public function privacidad(): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('perfil-privacidad.latte', [
                'activa' => 'mas', 'hijos' => $this->esApoderado() ? (new PersonaService($this->pdo))->hijos($this->persona['id'], $this->club['id']) : [],
                'impedimento' => (new PrivacidadService($this->pdo))->impedimento($this->persona['id']),
            ]);
        }
    }

    public function exportar(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if (!$this->csrfOk()) {
            $this->ir('/app/perfil/privacidad');
            return;
        }
        $json = json_encode((new PrivacidadService($this->pdo))->exportar($this->persona['id']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="u330-mis-datos.json"');
            header('Cache-Control: no-store');
        }
        echo $json;
    }

    /** Pide al club que quite las fotos de sus hijos: avisa al cuerpo técnico y a la directiva. */
    public function pedirQuitarFotos(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if ($this->csrfOk() && $this->esApoderado()) {
            $hijos = (new PersonaService($this->pdo))->hijos($this->persona['id'], $this->club['id']);
            $destino = array_column($this->filaPersonas("SELECT DISTINCT persona_id AS id FROM sportiva_membresias WHERE club_id = ? AND estado = 'activo' AND rol IN ('directiva', 'encargado')", [$this->club['id']]), 'id');
            foreach ($hijos as $h) {
                $destino = array_merge($destino, array_column($this->filaPersonas('SELECT persona_id AS id FROM sportiva_equipo_staff WHERE equipo_id = ?', [$h['equipo_id']]), 'id'));
            }
            $nombres = implode(', ', array_column($hijos, 'nombre'));
            $quien = (string) $this->persona['nombre'];
            $this->notificar(array_values(array_unique($destino)), 'plantel', fn(I18n $i) => [$i->t('perfil.fotos.aviso_titulo', ['quien' => $quien]), $i->t('perfil.fotos.aviso_cuerpo', ['hijos' => $nombres])], '/app/plantel');
            $this->ctx->redirect('/app/perfil/privacidad', $this->i18n->t('perfil.fotos.enviado'));
            return;
        }
        $this->ir('/app/perfil/privacidad');
    }

    /** @return array<int, array<string, mixed>> */
    private function filaPersonas(string $sql, array $p): array
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function eliminar(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if (!$this->csrfOk() || mb_strtolower(trim((string) ($_POST['confirmar'] ?? ''))) !== mb_strtolower($this->i18n->t('perfil.eliminar.palabra'))) {
            $this->ctx->redirect('/app/perfil/privacidad', $this->i18n->t('perfil.error.confirmar'), 'error');
            return;
        }
        $svc = new PrivacidadService($this->pdo);
        if (($m = $svc->impedimento($this->persona['id'])) !== null) {
            $this->ctx->redirect('/app/perfil/privacidad', $this->i18n->t($m), 'error');
            return;
        }
        $svc->eliminar($this->persona['id']);
        Sesion::salir();
        $this->ctx->redirect('/app/entrar?eliminada=1');
    }

    public function ayuda(): void
    {
        if ($this->dentro()) {
            echo $this->vista()->pagina('ayuda.latte', ['activa' => 'mas']);
        }
    }

    public function familias(): void
    {
        if (!$this->dentro()) {
            return;
        }
        if (!$this->esApoderado()) {
            $this->ir('/app/mas');
            return;
        }
        $pr = new PrivacidadService($this->pdo);
        echo $this->vista()->pagina('familias-equipo.latte', ['activa' => 'mas', 'familias' => $pr->familiasDelEquipo($this->persona['id'], $this->club['id']), 'yo' => $pr->get($this->persona['id'])]);
    }
}
