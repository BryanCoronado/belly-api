<?php

namespace Belly\AgenteIa\Domain;

/** Un proveedor de IA que contesta una conversación. Los errores se lanzan como DomainException con un texto entendible. */
interface ModeloLenguaje
{
    /**
     * @param list<array{role: string, content: string}> $mensajes
     * @return array{texto: ?string, traspaso: ?string} traspaso = motivo si la IA decidió pasar el chat a una persona
     */
    public function responder(string $apiKey, string $modelo, array $mensajes, float $temperatura): array;

    /** Comprueba que la llave funcione. */
    public function verificar(string $apiKey): void;
}
