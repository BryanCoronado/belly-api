<?php

namespace Belly\Flujos\Domain;

/**
 * Qué pasa con el asesor cuando el bot entrega el chat en «Pasar a un asesor».
 * Quién atiende cada chat lo decide Reparto de chats; el bot solo puede respetarlo o pasarlo a una persona concreta.
 */
final class Asignacion
{
    /** Sigue con el asesor que asignó Reparto de chats. */
    public const MANTENER = 'mantener';
    /** Pasa a un asesor elegido por la empresa. */
    public const ELEGIR = 'elegir';

    public const MODOS = [self::MANTENER, self::ELEGIR];

    /**
     * Pedido de asignación listo para el CRM.
     *
     * @param array<string, mixed> $datosPaso datos del paso «Pasar a un asesor» (vacío si el bot derivó por su cuenta)
     * @return array{tipo:string, modo:string, asesor_id:int}
     */
    public static function pedido(array $datosPaso): array
    {
        $asesor = (int) ($datosPaso['asesor_id'] ?? 0);
        $elegir = ($datosPaso['asignar'] ?? null) === self::ELEGIR && $asesor > 0;

        return [
            'tipo' => 'asignar',
            'modo' => $elegir ? self::ELEGIR : self::MANTENER,
            'asesor_id' => $elegir ? $asesor : 0,
        ];
    }

    /** Deja solo los campos que entiende el paso «Pasar a un asesor». */
    public static function limpiarPaso(array $datos): array
    {
        $datos['asignar'] = ($datos['asignar'] ?? null) === self::ELEGIR ? self::ELEGIR : self::MANTENER;
        if ($datos['asignar'] === self::MANTENER) {
            $datos['asesor_id'] = 0;
        }
        unset($datos['asesores']);

        return $datos;
    }
}
