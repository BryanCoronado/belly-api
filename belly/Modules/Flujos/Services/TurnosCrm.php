<?php

namespace Belly\Flujos\Services;

use Belly\Flujos\Domain\TurnosEquipo;
use Belly\Reparto\Services\RepartoRepositorio;

/** Lee los turnos desde Asesores → Grupos, el único lugar donde se escriben los horarios. */
class TurnosCrm implements TurnosEquipo
{
    public function listar(): array
    {
        helper('vip2system');
        $idEmpresa = (int) (id_empresa_sesion() ?? 0);
        if ($idEmpresa <= 0) {
            return [];
        }

        return array_map(
            static fn (array $g): array => ['dias' => $g['dias'], 'desde' => $g['desde'], 'hasta' => $g['hasta']],
            (new RepartoRepositorio())->gruposTurno($idEmpresa)
        );
    }
}
