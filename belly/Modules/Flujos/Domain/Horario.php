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

    /** @param array{dias: list<int>, desde: string, hasta: string} $a @param array{dias: list<int>, desde: string, hasta: string} $b */
    public static function seSolapan(array $a, array $b): bool
    {
        return self::cruces([
            ['nombre' => 'a', 'dias' => $a['dias'], 'desde' => $a['desde'], 'hasta' => $a['hasta']],
            ['nombre' => 'b', 'dias' => $b['dias'], 'desde' => $b['desde'], 'hasta' => $b['hasta']],
        ]) !== [];
    }

    /**
     * Pares de horarios que comparten algún minuto de la semana (mismo criterio que dentro()).
     *
     * @param list<array{nombre: string, dias: list<int>, desde: string, hasta: string}> $tramos
     * @return list<array{0: string, 1: string}>
     */
    public static function cruces(array $tramos): array
    {
        $rangos = [];
        foreach ($tramos as $i => $t) {
            if (! self::valido($t) || $t['desde'] === $t['hasta']) {
                continue;
            }
            [$hd, $md] = array_map('intval', explode(':', $t['desde']));
            [$hh, $mh] = array_map('intval', explode(':', $t['hasta']));
            $desde = $hd * 60 + $md;
            $hasta = $hh * 60 + $mh;
            foreach ($t['dias'] as $dia) {
                $base = ($dia - 1) * 1440;
                if ($desde < $hasta) {
                    $rangos[$i][] = [$base + $desde, $base + $hasta];
                } else {
                    $rangos[$i][] = [$base, $base + $hasta];
                    $rangos[$i][] = [$base + $desde, $base + 1440];
                }
            }
        }

        $cruces = [];
        $indices = array_keys($rangos);
        foreach ($indices as $x => $a) {
            foreach (array_slice($indices, $x + 1) as $b) {
                foreach ($rangos[$a] as [$a1, $a2]) {
                    foreach ($rangos[$b] as [$b1, $b2]) {
                        if ($a1 < $b2 && $b1 < $a2) {
                            $cruces[] = [$tramos[$a]['nombre'] ?: 'sin nombre', $tramos[$b]['nombre'] ?: 'sin nombre'];
                            continue 3;
                        }
                    }
                }
            }
        }

        return $cruces;
    }

    private static function minutos(string $hora): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m)) {
            return 0;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
