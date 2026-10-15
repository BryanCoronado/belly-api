<?php

namespace Belly\Flujos\Domain;

use DateTimeImmutable;

/** Reglas de horario de atención: días de la semana (1 = lunes … 7 = domingo) y rango de horas. */
final class Horario
{
    /** @param array{dias?:mixed, desde?:mixed, hasta?:mixed} $datos */
    public static function dentro(array $datos, DateTimeImmutable $ahora): bool
    {
        $dias = array_map('intval', (array) ($datos['dias'] ?? []));
        if (! in_array((int) $ahora->format('N'), $dias, true)) {
            return false;
        }

        $desde = self::minutos((string) ($datos['desde'] ?? '00:00'));
        $hasta = self::minutos((string) ($datos['hasta'] ?? '23:59'));
        $actual = (int) $ahora->format('G') * 60 + (int) $ahora->format('i');

        return $desde <= $hasta
            ? ($actual >= $desde && $actual < $hasta)
            : ($actual >= $desde || $actual < $hasta);
    }

    public static function valido(array $datos): bool
    {
        if ((array) ($datos['dias'] ?? []) === []) {
            return false;
        }
        foreach (['desde', 'hasta'] as $campo) {
            if (! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', (string) ($datos[$campo] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    private static function minutos(string $hora): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m)) {
            return 0;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
