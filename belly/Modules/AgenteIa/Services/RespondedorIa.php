<?php

namespace Belly\AgenteIa\Services;

use Belly\AgenteIa\Domain\Indicaciones;
use Belly\AgenteIa\Domain\ModeloLenguaje;
use DomainException;

/** Junta lo que sabe el agente con la conversación y le pide la respuesta al modelo. */
class RespondedorIa
{
    public function __construct(
        private readonly AgenteRepositorio $repositorio,
        private readonly ModeloLenguaje $modelo
    ) {
    }

    /**
     * @param array<string, mixed> $agente
     * @param list<array{rol: string, texto: string}> $charla rol = cliente | asistente
     * @return array{texto: ?string, traspaso: ?string}
     */
    public function responder(int $idEmpresa, array $agente, array $charla, ?string $nombreCliente = null): array
    {
        $conexion = $this->repositorio->conexion($idEmpresa);
        if ($conexion === null) {
            throw new DomainException('Primero conecta tu cuenta de OpenAI.');
        }

        $sistema = Indicaciones::sistema(
            $agente,
            $this->repositorio->nombreEmpresa($idEmpresa),
            $this->repositorio->conocimiento((int) $agente['id']),
            $agente['usar_catalogo'] ? $this->repositorio->catalogoTexto($idEmpresa) : '',
            $nombreCliente
        );

        $mensajes = [['role' => 'system', 'content' => $sistema]];
        foreach (array_slice($charla, -Indicaciones::MAX_HISTORIAL) as $linea) {
            $texto = trim($linea['texto']);
            if ($texto !== '') {
                $mensajes[] = ['role' => $linea['rol'] === 'cliente' ? 'user' : 'assistant', 'content' => mb_substr($texto, 0, 2000)];
            }
        }

        return $this->modelo->responder($conexion['api_key'], $agente['modelo'], $mensajes, (float) $agente['temperatura']);
    }
}
