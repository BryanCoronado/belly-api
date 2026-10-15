<?php

namespace Belly\Flujos\Domain;

/** Puerto de salida: lo que el bot puede hacer en el CRM además de enviar mensajes. */
interface AccionesConversacion
{
    /**
     * Pasa la conversación al asesor que corresponda según el pedido (ver Asignacion::pedido).
     *
     * @param array{modo:string, asesor_id:int, equipo:list<int>} $pedido
     * @return string|null Nombre con el que se le presentará al cliente (null si no se pudo saber).
     */
    public function asignar(int $idConversacion, array $pedido): ?string;

    /** Marca qué tan interesado está el contacto: frio, tibio o caliente. Nunca baja ni pisa una venta. */
    public function calificar(int $idConversacion, string $temperatura): void;
}
