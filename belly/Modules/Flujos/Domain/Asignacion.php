<?php

namespace Belly\Flujos\Domain;

/**
 * Reglas de a qué asesor pasa una conversación cuando el bot termina su parte.
 * Sin dependencias del framework: solo decide QUÉ se pide; quién ejecuta el cambio es el CRM.
 *
 * Reparto a nivel de flujo (paso de inicio):  reparto + equipo
 * Reparto a nivel de paso «Pasar a un asesor»: asignar + asesor_id + asesores
 */
final class Asignacion
{
    /** No se cambia de asesor: sigue con quien ya tiene el chat. */
    public const MANTENER = 'mantener';
    /** Asesor fijo elegido por la empresa. */
    public const ELEGIR = 'elegir';
    /** Reparto parejo: gana quien lleva menos chats asignados hoy. */
    public const EQUITATIVO = 'equitativo';
    /** Gana quien tiene menos chats abiertos ahora mismo. */
    public const MENOS_CARGA = 'menos_carga';
    /** Solo en un paso: usa lo que se configuró en el inicio del flujo. */
    public const DEL_FLUJO = 'flujo';

    /** Opciones del inicio del flujo. */
    public const REPARTOS = [self::MANTENER, self::EQUITATIVO, self::MENOS_CARGA];
    /** Opciones de un paso «Pasar a un asesor». */
    public const MODOS_PASO = [self::DEL_FLUJO, self::MANTENER, self::ELEGIR, self::EQUITATIVO, self::MENOS_CARGA];

    public const MAX_EQUIPO = 100;

    /**
     * Pedido de asignación listo para el CRM.
     *
     * @param array<string, mixed> $datosPaso   datos del paso «Pasar a un asesor» (vacío si el bot derivó por su cuenta)
     * @param array<string, mixed> $datosInicio datos del paso de inicio del flujo
     * @return array{tipo:string, modo:string, asesor_id:int, equipo:list<int>}
     */
    public static function pedido(array $datosPaso, array $datosInicio): array
    {
        $modo = (string) ($datosPaso['asignar'] ?? self::DEL_FLUJO);
        if (! in_array($modo, self::MODOS_PASO, true)) {
            $modo = self::DEL_FLUJO;
        }

        if ($modo === self::DEL_FLUJO) {
            $modo = (string) ($datosInicio['reparto'] ?? self::MANTENER);
            if (! in_array($modo, self::REPARTOS, true)) {
                $modo = self::MANTENER;
            }
            $equipo = self::ids($datosInicio['equipo'] ?? []);
        } else {
            $equipo = self::ids($datosPaso['asesores'] ?? []);
        }

        return [
            'tipo' => 'asignar',
            'modo' => $modo,
            'asesor_id' => $modo === self::ELEGIR ? max(0, (int) ($datosPaso['asesor_id'] ?? 0)) : 0,
            'equipo' => $modo === self::ELEGIR ? [] : $equipo,
        ];
    }

    /**
     * Ids de asesores únicos y positivos.
     *
     * @return list<int>
     */
    public static function ids(mixed $valor): array
    {
        $ids = [];
        foreach (is_array($valor) ? $valor : [] as $v) {
            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values(array_slice($ids, 0, self::MAX_EQUIPO));
    }

    /** ¿Todos los elementos son ids válidos? (para el validador) */
    public static function listaValida(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }
        if (! is_array($valor) || count($valor) > self::MAX_EQUIPO) {
            return false;
        }
        foreach ($valor as $v) {
            if (! (is_int($v) || (is_string($v) && ctype_digit($v))) || (int) $v <= 0) {
                return false;
            }
        }

        return true;
    }
}
