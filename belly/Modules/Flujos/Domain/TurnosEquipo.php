<?php

namespace Belly\Flujos\Domain;

/** Puerto de salida: los turnos de atención que la empresa define en sus grupos tipo turno. */
interface TurnosEquipo
{
    /** @return list<array{dias:list<int>, desde:string, hasta:string}> */
    public function listar(): array;
}
