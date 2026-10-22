<?php

namespace Belly\Reparto\UseCases;

use Belly\Flujos\Domain\Horario;
use Belly\Reparto\Domain\GrupoAtencion;
use Belly\Reparto\Services\RepartoRepositorio;
use DomainException;

/** Grupos de asesores para repartir los chats (por turno, por área, por idioma…). */
class GrupoAtencionUseCase
{
    private const MAX_GRUPOS = 40;
    private const MAX_MIEMBROS = 100;
    private const COLOR_POR_DEFECTO = '#0d9488';

    private RepartoRepositorio $repositorio;

    public function __construct(?RepartoRepositorio $repositorio = null)
    {
        $this->repositorio = $repositorio ?? new RepartoRepositorio();
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->repositorio->grupos($this->idEmpresa());
    }

    /** @param array<string, mixed> $datos nombre, descripcion, color, miembros @return array<string, mixed> */
    public function crear(array $datos): array
    {
        $idEmpresa = $this->idEmpresa();
        if (count($this->repositorio->grupos($idEmpresa)) >= self::MAX_GRUPOS) {
            throw new DomainException('Llegaste al máximo de ' . self::MAX_GRUPOS . ' grupos.');
        }

        $campos = $this->campos($datos);
        $this->nombreLibre($idEmpresa, $campos['nombre'], null);
        $miembros = $this->miembros($datos['miembros'] ?? []);
        $this->revisarConflictoTurno($idEmpresa, null, $campos, $miembros);
        $id = $this->repositorio->insertarGrupo($idEmpresa, $campos);
        $this->repositorio->fijarMiembros($id, $miembros);

        return $this->buscar($idEmpresa, $id);
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function actualizar(int $id, array $datos): array
    {
        $idEmpresa = $this->idEmpresa();
        $actual = $this->buscar($idEmpresa, $id);
        $campos = $this->campos($datos + $actual);
        $this->nombreLibre($idEmpresa, $campos['nombre'], $id);
        $miembros = array_key_exists('miembros', $datos) ? $this->miembros($datos['miembros']) : null;
        $this->revisarConflictoTurno($idEmpresa, $id, $campos, $miembros ?? $actual['miembros']);
        $this->repositorio->actualizarGrupo($idEmpresa, $id, $campos);
        if ($miembros !== null) {
            $this->repositorio->fijarMiembros($id, $miembros);
        }

        return $this->buscar($idEmpresa, $id);
    }

    /** Si el grupo se usa en el reparto, esos pasos seguirán por «Nadie disponible» hasta que elijas otro. */
    public function eliminar(int $id): void
    {
        $idEmpresa = $this->idEmpresa();
        $this->buscar($idEmpresa, $id);
        $this->repositorio->eliminarGrupo($idEmpresa, $id);
    }

    /** @return array<string, mixed> */
    private function campos(array $datos): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            throw new DomainException('Ponle un nombre al grupo (entre 2 y 60 letras).');
        }
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $color = (string) ($datos['color'] ?? self::COLOR_POR_DEFECTO);
        $tipo = GrupoAtencion::tipoValido($datos['tipo'] ?? null) ? (string) $datos['tipo'] : GrupoAtencion::GENERAL;

        $fila = [
            'nombre' => $nombre,
            'descripcion' => $descripcion === '' ? null : mb_substr($descripcion, 0, 200),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : self::COLOR_POR_DEFECTO,
            'tipo' => $tipo,
            'turno_dias' => null,
            'turno_desde' => null,
            'turno_hasta' => null,
        ];

        if ($tipo === GrupoAtencion::TURNO) {
            $dias = array_values(array_unique(array_filter(array_map('intval', is_array($datos['turno_dias'] ?? null) ? $datos['turno_dias'] : []), static fn (int $d): bool => $d >= 1 && $d <= 7)));
            sort($dias);
            $desde = preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) ($datos['turno_desde'] ?? '')), $mDesde) ? sprintf('%02d:%02d', (int) $mDesde[1], (int) $mDesde[2]) : '';
            $hasta = preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) ($datos['turno_hasta'] ?? '')), $mHasta) ? sprintf('%02d:%02d', (int) $mHasta[1], (int) $mHasta[2]) : '';
            if ($dias === [] || ! Horario::valido(['dias' => $dias, 'desde' => $desde, 'hasta' => $hasta])) {
                throw new DomainException('En un grupo turno elige los días y las horas de inicio y fin.');
            }
            if ($desde === $hasta) {
                throw new DomainException('La hora de inicio y de fin del turno no pueden ser iguales.');
            }
            $fila['turno_dias'] = json_encode($dias, JSON_UNESCAPED_UNICODE);
            $fila['turno_desde'] = $desde;
            $fila['turno_hasta'] = $hasta;
        }

        return $fila;
    }

    /** @param list<int> $miembros */
    private function revisarConflictoTurno(int $idEmpresa, ?int $idPropio, array $fila, array $miembros): void
    {
        if ($fila['tipo'] !== GrupoAtencion::TURNO || $fila['turno_desde'] === null || $fila['turno_hasta'] === null) {
            return;
        }
        $dias = json_decode((string) $fila['turno_dias'], true);
        $dias = is_array($dias) ? array_values(array_map('intval', $dias)) : [];
        $propuesto = ['dias' => $dias, 'desde' => $fila['turno_desde'], 'hasta' => $fila['turno_hasta']];

        foreach ($this->repositorio->gruposTurno($idEmpresa) as $otro) {
            if ($idPropio !== null && $otro['id'] === $idPropio) {
                continue;
            }
            $otroHor = ['dias' => $otro['dias'], 'desde' => $otro['desde'], 'hasta' => $otro['hasta']];
            if (! Horario::seSolapan($propuesto, $otroHor)) {
                continue;
            }
            throw new DomainException('El horario se cruza con el turno «' . $otro['nombre'] . '». Separa las horas para que no coincidan.');
        }

        foreach ($this->repositorio->gruposTurno($idEmpresa) as $otro) {
            if ($idPropio !== null && $otro['id'] === $idPropio) {
                continue;
            }
            $otroHor = ['dias' => $otro['dias'], 'desde' => $otro['desde'], 'hasta' => $otro['hasta']];
            if (! Horario::seSolapan($propuesto, $otroHor)) {
                continue;
            }
            if (array_intersect($miembros, $otro['miembros']) !== []) {
                throw new DomainException('Un asesor no puede estar en «' . $otro['nombre'] . '» y en este turno si los horarios se cruzan. Quítalo de uno o ajusta las horas.');
            }
        }
    }

    /** @return list<int> asesores de la empresa, sin repetir y en el orden recibido */
    private function miembros(mixed $ids): array
    {
        $conocidos = array_column(asesores()->listar(), 'id');
        $lista = [];
        foreach (is_array($ids) ? $ids : [] as $id) {
            $id = (int) $id;
            if (! in_array($id, $conocidos, true)) {
                throw new DomainException('Una de las personas elegidas no es asesor de tu empresa.');
            }
            $lista[$id] = $id;
        }
        if (count($lista) > self::MAX_MIEMBROS) {
            throw new DomainException('Un grupo puede tener hasta ' . self::MAX_MIEMBROS . ' asesores.');
        }

        return array_values($lista);
    }

    private function nombreLibre(int $idEmpresa, string $nombre, ?int $idPropio): void
    {
        foreach ($this->repositorio->grupos($idEmpresa) as $grupo) {
            if ($grupo['id'] !== $idPropio && mb_strtolower($grupo['nombre']) === mb_strtolower($nombre)) {
                throw new DomainException('Ya tienes un grupo llamado «' . $grupo['nombre'] . '». Usa otro nombre.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function buscar(int $idEmpresa, int $id): array
    {
        $grupo = $this->repositorio->buscarGrupo($idEmpresa, $id);
        if ($grupo === null) {
            throw new DomainException('No encontramos ese grupo.');
        }

        return $grupo;
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
