<?php

namespace Belly\Services;

/**
 * Tope simple por IP (cache). Evita fuerza bruta en login y registro.
 */
class Ritmo
{
    public static function exigir(string $accion, int $maximo, int $segundos): void
    {
        $ip = trim((string) (service('request')->getIPAddress() ?? ''));
        if ($ip === '' || $ip === '0.0.0.0') {
            $ip = 'desconocida';
        }

        $clave = 'ritmo-' . $accion . '-' . md5($ip);
        $usos = (int) cache($clave);
        if ($usos >= $maximo) {
            throw new \RuntimeException('Demasiados intentos. Espera un momento.');
        }

        cache()->save($clave, $usos + 1, $segundos);
    }
}
