<?php

namespace Belly\Flujos\Domain;

/** Puerto de salida: cómo se entrega al cliente un mensaje armado por el motor. */
interface EnviadorMensajes
{
    /**
     * @param array $mensaje Mensaje del motor (ver MotorFlujo).
     * @return bool true si se envió.
     */
    public function enviar(int $idConversacion, array $mensaje): bool;
}
