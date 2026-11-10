<?php

namespace Belly\Reparto\UseCases;

use Belly\Reparto\Domain\NodosReparto;
use Belly\Reparto\Domain\ValidadorReparto;
use Belly\Reparto\Services\RepartoRepositorio;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/** Lienzo de llegada: qué pasa cuando un cliente inicia una conversación por WhatsApp. */
class RepartoUseCase
{
    private RepartoRepositorio $repositorio;
    private AsignadorChatUseCase $asignador;

    public function __construct(?RepartoRepositorio $repositorio = null, ?AsignadorChatUseCase $asignador = null)
    {
        $this->repositorio = $repositorio ?? new RepartoRepositorio();
        $this->asignador = $asignador ?? new AsignadorChatUseCase($this->repositorio);
    }

    /** Todo lo que necesita el editor: el lienzo y con qué se puede armar. @return array<string, mixed> */
    public function obtener(): array
    {
        $idEmpresa = $this->idEmpresa();
        $definicion = $this->repositorio->definicion($idEmpresa);

        return [
            'definicion' => $definicion,
            'configurado' => $this->repositorio->tieneDefinicionGuardada($idEmpresa),
            'revision' => ValidadorReparto::revisar(ValidadorReparto::normalizar($definicion), $this->catalogo($idEmpresa)),
            'grupos' => $this->repositorio->grupos($idEmpresa),
            'asesores' => array_map(
                static fn (array $a): array => ['id' => $a['id'], 'nombre' => $a['nombre'], 'activo' => $a['activo']],
                asesores()->listar()
            ),
            'personas' => $this->repositorio->personas($idEmpresa),
            'agentes' => agentes_ia()->resumenes($idEmpresa),
            'flujos' => $this->resumenFlujos($idEmpresa),
        ];
    }

    /** @return list<array{id: int, nombre: string, activo: bool}> */
    private function resumenFlujos(int $idEmpresa): array
    {
        try {
            $filas = (new \Belly\Flujos\Models\FlujoModel())
                ->select('id, nombre, activo')
                ->where('id_empresa', $idEmpresa)
                ->orderBy('id', 'ASC')
                ->findAll();

            return array_map(static fn ($f): array => [
                'id' => (int) $f->id,
                'nombre' => (string) $f->nombre,
                'activo' => (bool) $f->activo,
            ], $filas);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{definicion: array, revision: array} */
    public function guardar(mixed $definicion): array
    {
        $idEmpresa = $this->idEmpresa();
        $limpia = ValidadorReparto::normalizar($this->conTurnosEnGrupos($definicion, $idEmpresa));
        $revision = ValidadorReparto::revisar($limpia, $this->catalogo($idEmpresa));
        if ($revision['errores'] !== []) {
            throw new DomainException('No se guardó: ' . $revision['errores'][0]['mensaje']);
        }

        $this->repositorio->guardarDefinicion($idEmpresa, $limpia);

        return ['definicion' => $limpia, 'revision' => $revision];
    }

    /** @return array{errores: list<array>, avisos: list<array>} */
    public function revisar(mixed $definicion): array
    {
        $idEmpresa = $this->idEmpresa();

        return ValidadorReparto::revisar(ValidadorReparto::normalizar($this->conTurnosEnGrupos($definicion, $idEmpresa)), $this->catalogo($idEmpresa));
    }

    /**
     * Simula la llegada de un cliente y dice a quién le tocaría, sin asignar nada.
     *
     * @param array<string, mixed> $datos cliente (nuevo|recurrente), origen (anuncio|directo), texto, cuando y,
     *                                    opcional, la definicion sin guardar del editor
     * @return array<string, mixed>
     */
    public function probar(array $datos): array
    {
        $idEmpresa = $this->idEmpresa();
        $zona = new DateTimeZone(config('App')->appTimezone);
        $ahora = new DateTimeImmutable('now', $zona);
        $cuando = is_string($datos['cuando'] ?? null) ? trim($datos['cuando']) : '';
        if ($cuando !== '') {
            try {
                $ahora = new DateTimeImmutable($cuando, $zona);
            } catch (\Exception) {
                throw new DomainException('La fecha y hora de la prueba no es válida.');
            }
        }

        $recurrente = ($datos['cliente'] ?? 'nuevo') === 'recurrente';
        $decision = $this->asignador->decidir($idEmpresa, [
            'recurrente' => $recurrente,
            // En la prueba, el «asesor de antes» es el primero disponible, para ver ese camino.
            'asesor_previo' => $recurrente ? ($this->repositorio->candidatos($idEmpresa)[0]['id'] ?? null) : null,
            'anuncio' => ($datos['origen'] ?? 'directo') === 'anuncio',
            'texto' => mb_substr(is_scalar($datos['texto'] ?? null) ? (string) $datos['texto'] : '', 0, 1000),
            'ahora' => $ahora,
        ], null, null, isset($datos['definicion']) ? ValidadorReparto::normalizar($this->conTurnosEnGrupos($datos['definicion'], $idEmpresa)) : null);

        $nombre = null;
        foreach ($this->repositorio->personas($idEmpresa) as $persona) {
            if ($persona['id'] === $decision['id_usuario']) {
                $nombre = $persona['nombre'];
            }
        }
        if ($decision['tipo'] === 'agente') {
            foreach (agentes_ia()->resumenes($idEmpresa) as $agente) {
                if ($agente['id'] === $decision['id_agente']) {
                    $nombre = $agente['nombre'];
                }
            }
        }
        if ($decision['tipo'] === 'flujo' && $nombre === null) {
            foreach ($this->repositorio->personas($idEmpresa) as $persona) {
                if ($persona['id'] === $decision['id_usuario']) {
                    $nombre = $persona['nombre'];
                }
            }
        }
        $grupoReparto = null;
        if (isset($decision['id_grupo_reparto']) && (int) $decision['id_grupo_reparto'] > 0) {
            foreach ($this->repositorio->grupos($idEmpresa) as $grupo) {
                if ($grupo['id'] === (int) $decision['id_grupo_reparto']) {
                    $grupoReparto = $grupo['nombre'];
                }
            }
        }

        return $decision + ['nombre' => $nombre, 'grupo_reparto' => $grupoReparto];
    }

    /** @return array{grupos: array<int, int>, personas: list<int>, agentes: array<int, array{activo: bool, listo: bool}>} */
    private function catalogo(int $idEmpresa): array
    {
        $grupos = [];
        $gruposNombres = [];
        foreach ($this->repositorio->grupos($idEmpresa) as $grupo) {
            $grupos[$grupo['id']] = count($grupo['miembros']);
            $gruposNombres[$grupo['id']] = $grupo['nombre'];
        }
        $agentes = [];
        foreach (agentes_ia()->resumenes($idEmpresa) as $agente) {
            $agentes[$agente['id']] = ['activo' => $agente['activo'], 'listo' => $agente['listo']];
        }
        $flujos = [];
        $flujosActivos = 0;
        try {
            helper('vip2system');
            $filas = (new \Belly\Flujos\Models\FlujoModel())
                ->select('id, nombre, activo')
                ->where('id_empresa', $idEmpresa)
                ->orderBy('id', 'ASC')
                ->findAll();
            foreach ($filas as $flujo) {
                $activo = (bool) ($flujo->activo ?? false);
                $flujos[(int) $flujo->id] = ['nombre' => (string) $flujo->nombre, 'activo' => $activo];
                if ($activo) {
                    $flujosActivos++;
                }
            }
        } catch (\Throwable) {
            $flujos = [];
        }

        return [
            'grupos' => $grupos,
            'grupos_nombres' => $gruposNombres,
            'personas' => array_column($this->repositorio->personas($idEmpresa), 'id'),
            'agentes' => $agentes,
            'grupos_turno' => $this->repositorio->gruposTurno($idEmpresa),
            'flujos' => $flujos,
            'flujos_activos' => $flujosActivos,
        ];
    }

    /** @param mixed $definicion @return mixed */
    private function conTurnosEnGrupos(mixed $definicion, int $idEmpresa): mixed
    {
        if (! is_array($definicion) || ! is_array($definicion['nodos'] ?? null)) {
            return $definicion;
        }
        $turnos = array_map(static fn (array $g): array => ['id' => $g['id']], $this->repositorio->gruposTurno($idEmpresa));
        foreach ($definicion['nodos'] as &$nodo) {
            if (($nodo['tipo'] ?? '') === NodosReparto::TURNOS_GRUPOS) {
                $nodo['datos'] = is_array($nodo['datos'] ?? null) ? $nodo['datos'] : [];
                $nodo['datos']['grupos_turno'] = $turnos;
            }
        }

        return $definicion;
    }

    private function idEmpresa(): int
    {
        helper('vip2system');
        $id = id_empresa_sesion();
        if ($id === null) {
            throw new DomainException('No se pudo identificar tu empresa.');
        }

        return $id;
    }
}
