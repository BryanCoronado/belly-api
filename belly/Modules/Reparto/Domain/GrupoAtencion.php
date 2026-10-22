<?php

namespace Belly\Reparto\Domain;

/** Tipos de grupo para repartir chats. */
final class GrupoAtencion
{
    /** Equipo, área, idioma… sin horario propio. */
    public const GENERAL = 'general';

    /** Turno con días y horas (mañana, tarde, noche…). El flujo los usa por nombre. */
    public const TURNO = 'turno';

    public const TIPOS = [self::GENERAL, self::TURNO];

    public static function tipoValido(mixed $valor): bool
    {
        return is_string($valor) && in_array($valor, self::TIPOS, true);
    }
}
