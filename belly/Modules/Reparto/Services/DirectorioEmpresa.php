<?php

namespace Belly\Reparto\Services;

use Belly\Reparto\Domain\DirectorioReparto;

/** Directorio del motor de reparto para una empresa concreta. */
class DirectorioEmpresa implements DirectorioReparto
{
    public function __construct(private readonly int $idEmpresa, private readonly RepartoRepositorio $repositorio)
    {
    }

    public function asesorDisponible(int $idUsuario): bool
    {
        return $this->repositorio->asesorDisponible($this->idEmpresa, $idUsuario);
    }

    public function candidatos(?int $idGrupo): array
    {
        return $this->repositorio->candidatos($this->idEmpresa, $idGrupo);
    }

    public function personaActiva(int $idUsuario): bool
    {
        return $this->repositorio->usuarioActivo($this->idEmpresa, $idUsuario);
    }

    public function agenteDisponible(int $idAgente): bool
    {
        return agentes_ia()->disponible($this->idEmpresa, $idAgente);
    }

    public function respaldo(?int $preferido): ?int
    {
        if ($preferido !== null && $this->repositorio->usuarioActivo($this->idEmpresa, $preferido)) {
            return $preferido;
        }

        return $this->repositorio->primerAdministrador($this->idEmpresa);
    }

    public function gruposTurno(): array
    {
        return $this->repositorio->gruposTurno($this->idEmpresa);
    }
}
