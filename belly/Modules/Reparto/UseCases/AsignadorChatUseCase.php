<?php

namespace Belly\Reparto\UseCases;

use Belly\Reparto\Domain\MotorReparto;
use Belly\Reparto\Domain\NodosReparto;
use Belly\Reparto\Services\DirectorioEmpresa;
use Belly\Reparto\Services\RepartoRepositorio;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Decide quién atiende un chat nuevo siguiendo el lienzo de llegada que armó la empresa:
 * un asesor, un agente de IA o, si nadie puede, la persona de respaldo.
 */
class AsignadorChatUseCase
{
    private RepartoRepositorio $repositorio;

    public function __construct(?RepartoRepositorio $repositorio = null)
    {
        $this->repositorio = $repositorio ?? new RepartoRepositorio();
    }

    /**
     * @param array{recurrente?: bool, asesor_previo?: ?int, anuncio?: bool, texto?: string, ahora?: DateTimeImmutable, azar?: int} $contexto
     * @param string|null $desdeNodo seguir el lienzo desde este paso (un agente de IA que pasa el chat a una persona)
     * @param array<string, mixed>|null $definicion lienzo a usar en lugar del guardado (pruebas desde el editor)
     * @return array{tipo: string, id_usuario: ?int, id_agente: ?int, nodo: ?string, motivo: string, ruta: list<string>, pasos: list<string>}
     */
    public function decidir(int $idEmpresa, array $contexto, ?string $desdeNodo = null, ?string $salida = null, ?array $definicion = null): array
    {
        $situacion = [
            'ahora' => $contexto['ahora'] ?? new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone)),
            'recurrente' => (bool) ($contexto['recurrente'] ?? false),
            'asesor_previo' => isset($contexto['asesor_previo']) ? (int) $contexto['asesor_previo'] : null,
            'anuncio' => (bool) ($contexto['anuncio'] ?? false),
            'texto' => (string) ($contexto['texto'] ?? ''),
        ];
        if (isset($contexto['azar'])) {
            $situacion['azar'] = (int) $contexto['azar'];
        }

        return MotorReparto::decidir(
            $definicion ?? $this->repositorio->definicion($idEmpresa),
            $situacion,
            new DirectorioEmpresa($idEmpresa, $this->repositorio),
            $desdeNodo,
            $salida
        );
    }

    /** La persona de respaldo del lienzo (o el primer administrador) para cuando el camino no termina en nadie. */
    public function respaldo(int $idEmpresa): ?int
    {
        $preferido = null;
        foreach ($this->repositorio->definicion($idEmpresa)['nodos'] ?? [] as $nodo) {
            if (($nodo['tipo'] ?? '') === NodosReparto::INICIO && isset($nodo['datos']['id_usuario_respaldo'])) {
                $preferido = (int) $nodo['datos']['id_usuario_respaldo'];
            }
        }

        return (new DirectorioEmpresa($idEmpresa, $this->repositorio))->respaldo($preferido);
    }
}
