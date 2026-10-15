<?php

namespace Belly\Flujos\Domain;

/** Puerto de salida: personas de la empresa que pueden atender chats. */
interface CatalogoAsesores
{
    /**
     * @return list<array{id:int, nombre:string, chats_abiertos:int}>
     */
    public function listar(): array;
}
