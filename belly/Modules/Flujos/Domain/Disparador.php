<?php

namespace Belly\Flujos\Domain;

use DateTimeImmutable;

/** Decide si un flujo debe arrancar con el mensaje que acaba de llegar. */
final class Disparador
{
    public const PALABRA_CLAVE = 'palabra_clave';
    public const PRIMER_MENSAJE = 'primer_mensaje';
    public const CUALQUIER_MENSAJE = 'cualquier_mensaje';

    public const TODOS = [self::PALABRA_CLAVE, self::PRIMER_MENSAJE, self::CUALQUIER_MENSAJE];

    public const AUDIENCIAS = ['nuevos', 'todos'];
    public const REPETICIONES = ['una_vez', 'cada_horas', 'siempre'];

    /**
     * Prioridad de arranque: 3 palabra clave, 2 primer mensaje, 1 cualquier mensaje, 0 no arranca.
     *
     * Contexto:
     *  - primer_mensaje:   la conversación acaba de crearse con este mensaje.
     *  - sin_bot:          no hubo ningún flujo antes en esta conversación.
     *  - contacto_nuevo:   el cliente nunca había conversado antes con la empresa.
     *  - veces:            cuántas veces corrió este flujo con este cliente.
     *  - horas_desde_ultimo: horas desde la última vez que corrió con este cliente (null si nunca).
     *
     * @param array{primer_mensaje?:bool, sin_bot?:bool, contacto_nuevo?:bool, veces?:int, horas_desde_ultimo?:?float} $contexto
     */
    public static function prioridad(array $definicion, string $texto, array $contexto, DateTimeImmutable $ahora): int
    {
        $datos = (array) ((new Grafo($definicion))->inicio()['datos'] ?? []);
        $tipo = (string) ($datos['disparador'] ?? self::PRIMER_MENSAJE);

        if (! self::dentroDeHorario($datos, $ahora) || ! self::audienciaPermitida($datos, $tipo, $contexto) || ! self::repeticionPermitida($datos, $tipo, $contexto)) {
            return 0;
        }

        $sinBot = (bool) ($contexto['sin_bot'] ?? false);

        return match ($tipo) {
            self::PALABRA_CLAVE => self::coincidePalabra($datos, $texto) ? 3 : 0,
            self::PRIMER_MENSAJE => (($contexto['primer_mensaje'] ?? false) && $sinBot) ? 2 : 0,
            self::CUALQUIER_MENSAJE => $sinBot ? 1 : 0,
            default => 0,
        };
    }

    public static function audienciaDe(array $datos, string $tipo): string
    {
        $valor = (string) ($datos['audiencia'] ?? '');

        return in_array($valor, self::AUDIENCIAS, true) ? $valor : ($tipo === self::PALABRA_CLAVE ? 'todos' : 'nuevos');
    }

    public static function repeticionDe(array $datos, string $tipo): string
    {
        $valor = (string) ($datos['repetir'] ?? '');

        return in_array($valor, self::REPETICIONES, true) ? $valor : ($tipo === self::PALABRA_CLAVE ? 'siempre' : 'una_vez');
    }

    private static function dentroDeHorario(array $datos, DateTimeImmutable $ahora): bool
    {
        $horario = $datos['horario_activo'] ?? null;
        if (! is_array($horario) || empty($horario['activo'])) {
            return true;
        }

        return Horario::dentro($horario, $ahora);
    }

    private static function audienciaPermitida(array $datos, string $tipo, array $contexto): bool
    {
        return self::audienciaDe($datos, $tipo) === 'todos' || (bool) ($contexto['contacto_nuevo'] ?? false);
    }

    private static function repeticionPermitida(array $datos, string $tipo, array $contexto): bool
    {
        $veces = (int) ($contexto['veces'] ?? 0);

        return match (self::repeticionDe($datos, $tipo)) {
            'una_vez' => $veces === 0,
            'cada_horas' => ($contexto['horas_desde_ultimo'] ?? null) === null
                || (float) $contexto['horas_desde_ultimo'] >= max(1, (int) ($datos['repetir_horas'] ?? 24)),
            default => true,
        };
    }

    private static function coincidePalabra(array $datos, string $texto): bool
    {
        $exacta = ! empty($datos['exacta']);

        foreach ((array) ($datos['palabras'] ?? []) as $palabra) {
            if (! is_string($palabra) || Texto::normalizar($palabra) === '') {
                continue;
            }
            $coincide = $exacta
                ? Texto::normalizar($texto) === Texto::normalizar($palabra)
                : Texto::contienePalabra($texto, $palabra);
            if ($coincide) {
                return true;
            }
        }

        return false;
    }
}
