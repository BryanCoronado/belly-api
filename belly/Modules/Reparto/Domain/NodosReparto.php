<?php

namespace Belly\Reparto\Domain;

/**
 * Pasos del lienzo de llegada y sus salidas (flechas).
 * Un nodo: ['id' => string, 'tipo' => string, 'datos' => array, 'x' => number, 'y' => number].
 * Conexión: ['id' => string, 'desde' => id, 'salida' => string, 'hacia' => id].
 */
final class NodosReparto
{
    /** El cliente inicia la conversación (único, no se borra). Guarda la persona de respaldo. */
    public const INICIO = 'inicio';
    /** Si el cliente ya habló con un asesor que sigue activo, vuelve con él. */
    public const ASESOR_PREVIO = 'asesor_previo';
    /** Divide por tramos de horario (mañana, tarde, fuera de horario…). */
    public const HORARIO = 'horario';
    /** Cliente nuevo o que ya había escrito. */
    public const CLIENTE = 'cliente';
    /** Llegó desde un anuncio o escribió directo. */
    public const ORIGEN = 'origen';
    /** Su primer mensaje menciona alguna palabra. */
    public const PALABRAS = 'palabras';
    /** Reparte al azar entre dos caminos (por porcentaje). */
    public const DIVIDIR = 'dividir';
    /** Reparte entre los asesores de un grupo (o todos). */
    public const REPARTIR = 'repartir';
    /** Elige el turno que toca según los grupos tipo «turno» (horario en Grupos, no aquí). */
    public const TURNOS_GRUPOS = 'turnos_grupos';
    /** Lo asigna a una persona concreta. */
    public const PERSONA = 'persona';
    /** Lo atiende un agente de IA hasta que pase a una persona. */
    public const AGENTE_IA = 'agente_ia';

    public const TODOS = [
        self::INICIO, self::ASESOR_PREVIO, self::HORARIO, self::CLIENTE, self::ORIGEN,
        self::PALABRAS, self::DIVIDIR, self::TURNOS_GRUPOS, self::REPARTIR, self::PERSONA, self::AGENTE_IA,
    ];

    public const SALIDA_SIGUIENTE = 'siguiente';
    public const SALIDA_NO = 'no';
    public const SALIDA_FUERA = 'fuera';
    public const SALIDA_NADIE = 'nadie';
    public const SALIDA_HUMANO = 'humano';

    public const MAX_NODOS = 80;
    public const MAX_TRAMOS = 8;
    public const MAX_PALABRAS = 30;

    /** @return list<string> */
    public static function salidas(array $nodo): array
    {
        $datos = (array) ($nodo['datos'] ?? []);

        return match ($nodo['tipo'] ?? '') {
            self::INICIO => [self::SALIDA_SIGUIENTE],
            self::ASESOR_PREVIO => [self::SALIDA_NO],
            self::HORARIO => [...self::idsTramos($datos['tramos'] ?? []), self::SALIDA_FUERA],
            self::TURNOS_GRUPOS => [...self::idsGruposTurno($datos['grupos_turno'] ?? []), self::SALIDA_FUERA],
            self::CLIENTE => ['nuevo', 'recurrente'],
            self::ORIGEN => ['anuncio', 'directo'],
            self::PALABRAS => ['si', 'no'],
            self::DIVIDIR => ['a', 'b'],
            self::REPARTIR, self::PERSONA => [self::SALIDA_NADIE],
            self::AGENTE_IA => [self::SALIDA_HUMANO],
            default => [],
        };
    }

    /** Lienzo con el que arranca toda empresa: vuelve con su asesor y, si no, reparto parejo entre todos. */
    public static function porDefecto(): array
    {
        return [
            'nodos' => [
                ['id' => 'inicio', 'tipo' => self::INICIO, 'x' => 0, 'y' => 0, 'datos' => ['id_usuario_respaldo' => null]],
                ['id' => 'previo', 'tipo' => self::ASESOR_PREVIO, 'x' => 0, 'y' => 170, 'datos' => []],
                ['id' => 'todos', 'tipo' => self::REPARTIR, 'x' => 0, 'y' => 340, 'datos' => ['id_grupo' => null, 'estrategia' => Estrategia::EQUITATIVO, 'limite_chats' => null]],
            ],
            'conexiones' => [
                ['id' => 'c1', 'desde' => 'inicio', 'salida' => self::SALIDA_SIGUIENTE, 'hacia' => 'previo'],
                ['id' => 'c2', 'desde' => 'previo', 'salida' => self::SALIDA_NO, 'hacia' => 'todos'],
            ],
        ];
    }

    /** @return list<string> */
    /** @return list<string> salidas g_{id} */
    private static function idsGruposTurno(mixed $grupos): array
    {
        $ids = [];
        foreach (is_array($grupos) ? $grupos : [] as $g) {
            if (is_array($g) && isset($g['id']) && (int) $g['id'] > 0) {
                $ids[] = 'g_' . (int) $g['id'];
            }
        }

        return $ids;
    }

    /** @return list<string> */
    private static function idsTramos(mixed $tramos): array
    {
        $ids = [];
        foreach (is_array($tramos) ? $tramos : [] as $tramo) {
            if (is_array($tramo) && isset($tramo['id']) && is_string($tramo['id']) && $tramo['id'] !== '') {
                $ids[] = $tramo['id'];
            }
        }

        return $ids;
    }
}
