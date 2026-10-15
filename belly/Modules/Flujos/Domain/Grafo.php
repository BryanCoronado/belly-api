<?php

namespace Belly\Flujos\Domain;

/**
 * Vista de consulta de una definición de flujo:
 * ['nodos' => list<nodo>, 'conexiones' => list<['id','desde','salida','hacia']>].
 */
final class Grafo
{
    /** @var array<string, array> */
    private array $nodos = [];

    /** @var array<string, array<string, string>> */
    private array $destinos = [];

    public function __construct(array $definicion)
    {
        foreach ((array) ($definicion['nodos'] ?? []) as $nodo) {
            if (is_array($nodo) && isset($nodo['id']) && is_string($nodo['id'])) {
                $this->nodos[$nodo['id']] = $nodo;
            }
        }
        foreach ((array) ($definicion['conexiones'] ?? []) as $c) {
            if (is_array($c) && isset($c['desde'], $c['salida'], $c['hacia'])) {
                $this->destinos[(string) $c['desde']][(string) $c['salida']] = (string) $c['hacia'];
            }
        }
    }

    public function nodo(?string $id): ?array
    {
        return $id === null ? null : ($this->nodos[$id] ?? null);
    }

    /** Id del paso al que lleva la flecha de esa salida, o null si no tiene. */
    public function destino(string $idNodo, string $salida): ?string
    {
        $hacia = $this->destinos[$idNodo][$salida] ?? null;

        return ($hacia !== null && isset($this->nodos[$hacia])) ? $hacia : null;
    }

    public function inicio(): ?array
    {
        foreach ($this->nodos as $nodo) {
            if (($nodo['tipo'] ?? '') === TiposNodo::INICIO) {
                return $nodo;
            }
        }

        return null;
    }

    /** @return array<string, array> */
    public function nodos(): array
    {
        return $this->nodos;
    }
}
