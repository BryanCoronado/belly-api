<?php

namespace Belly\Reparto\Domain;

/** Formas de repartir un chat nuevo entre los asesores disponibles. */
final class Estrategia
{
    /** Parejo: gana quien recibió menos chats hoy. */
    public const EQUITATIVO = 'equitativo';
    /** Por turnos: gana quien lleva más tiempo sin recibir un chat. */
    public const ROTATIVO = 'rotativo';
    /** Gana quien tiene menos chats abiertos ahora mismo. */
    public const MENOS_CARGA = 'menos_carga';
    /** Siempre a la primera persona de la lista que esté disponible. */
    public const PRIORIDAD = 'prioridad';

    public const TODAS = [self::EQUITATIVO, self::ROTATIVO, self::MENOS_CARGA, self::PRIORIDAD];

    public static function valida(mixed $valor): bool
    {
        return is_string($valor) && in_array($valor, self::TODAS, true);
    }
}
