<?php

namespace Belly\Flujos\Domain;

/**
 * Catálogo de pasos que puede tener un flujo y las "salidas" (flechas) de cada uno.
 * Un nodo tiene la forma: ['id' => string, 'tipo' => string, 'datos' => array, 'x' => number, 'y' => number].
 */
final class TiposNodo
{
    public const INICIO = 'inicio';
    public const MENSAJE = 'mensaje';
    public const BOTONES = 'botones';
    public const LISTA = 'lista';
    public const PREGUNTA = 'pregunta';
    /** Varios datos en un solo mensaje (el cliente responde todo junto). */
    public const FORMULARIO = 'formulario';
    public const CONDICION = 'condicion';
    public const HORARIO = 'horario';
    public const VARIABLE = 'variable';
    public const ASESOR = 'asesor';
    public const FIN = 'fin';
    public const ESPERA = 'espera';
    public const UBICACION = 'ubicacion';
    /** Marca el interés del contacto (frío, tibio, caliente) sin detener el flujo. */
    public const TEMPERATURA = 'temperatura';
    /** Reparte a los clientes en dos caminos (prueba A/B). */
    public const DIVIDIR = 'dividir';

    public const TODOS = [
        self::INICIO, self::MENSAJE, self::BOTONES, self::LISTA, self::PREGUNTA, self::FORMULARIO,
        self::CONDICION, self::HORARIO, self::VARIABLE, self::ASESOR, self::FIN,
        self::ESPERA, self::UBICACION, self::TEMPERATURA, self::DIVIDIR,
    ];

    /** Niveles que el bot puede marcar (la venta la registra una persona). */
    public const TEMPERATURAS = ['frio', 'tibio', 'caliente'];
    public const MAX_INTENTOS_PERMITIDOS = 5;

    public const MAX_BOTONES = 3;
    public const MAX_OPCIONES = 10;
    public const MAX_CAMPOS_FORMULARIO = 8;
    public const SALIDA_SIGUIENTE = 'siguiente';
    public const SALIDA_OTRO = 'otro';
    public const SALIDA_SIN_RESPUESTA = 'sin_respuesta';

    /** Espera máxima de un paso «Esperar»: 30 días. */
    public const MAX_ESPERA_SEGUNDOS = 2592000;
    /** Hasta este tiempo se espera dentro de la misma petición; más allá lo retoma el proceso en segundo plano. */
    public const ESPERA_EN_LINEA = 15;
    /** Pausa máxima (segundos) antes de enviar un mensaje. */
    public const MAX_PAUSA = 10;
    /** Tiempo máximo (minutos) para el aviso «sin respuesta»: 30 días. */
    public const MAX_SIN_RESPUESTA_MIN = 43200;

    /** @return list<string> */
    public static function salidas(array $nodo): array
    {
        $datos = $nodo['datos'] ?? [];

        return match ($nodo['tipo'] ?? '') {
            self::INICIO, self::MENSAJE, self::VARIABLE, self::ESPERA, self::UBICACION, self::TEMPERATURA => [self::SALIDA_SIGUIENTE],
            self::DIVIDIR => ['a', 'b'],
            self::PREGUNTA, self::FORMULARIO => [self::SALIDA_SIGUIENTE, ...self::salidaTiempo($datos)],
            self::BOTONES => [...self::idsOpciones($datos['botones'] ?? []), self::SALIDA_OTRO, ...self::salidaTiempo($datos)],
            self::LISTA => [...self::idsOpciones($datos['opciones'] ?? []), self::SALIDA_OTRO, ...self::salidaTiempo($datos)],
            self::CONDICION => ['si', 'no'],
            self::HORARIO => ['abierto', 'cerrado'],
            default => [],
        };
    }

    /** Pasos que mandan un mensaje de WhatsApp. */
    public const QUE_MANDAN = [
        self::MENSAJE, self::BOTONES, self::LISTA, self::PREGUNTA,
        self::FORMULARIO, self::UBICACION, self::ASESOR, self::FIN,
    ];

    /** En modo ahorro, más que esto pide confirmación para activar. */
    public const TOPE_AHORRO = 2;

    /** @param array<string, mixed> $definicion */
    public static function cuentaMensajes(array $definicion): int
    {
        $cuenta = 0;
        foreach ((array) ($definicion['nodos'] ?? []) as $nodo) {
            if (is_array($nodo) && in_array($nodo['tipo'] ?? '', self::QUE_MANDAN, true)) {
                $cuenta++;
            }
        }

        return $cuenta;
    }

    /** El paso se detiene a esperar lo que responda el cliente. */
    public static function esperaRespuesta(string $tipo): bool
    {
        return in_array($tipo, [self::BOTONES, self::LISTA, self::PREGUNTA, self::FORMULARIO], true);
    }

    /** Segundos que se espera antes de seguir por «sin respuesta» (0 = no se usa). */
    public static function segundosSinRespuesta(array $datos): int
    {
        $minutos = (int) ($datos['timeout_min'] ?? 0);

        return $minutos > 0 ? min($minutos, self::MAX_SIN_RESPUESTA_MIN) * 60 : 0;
    }

    /** Segundos de un paso «Esperar» (cantidad + unidad). */
    public static function segundosDeEspera(array $datos): int
    {
        $cantidad = max(0, (int) ($datos['cantidad'] ?? 0));
        $factor = match ($datos['unidad'] ?? 'segundos') {
            'minutos' => 60,
            'horas' => 3600,
            'dias' => 86400,
            default => 1,
        };

        return min($cantidad * $factor, self::MAX_ESPERA_SEGUNDOS);
    }

    /** @return list<string> */
    private static function salidaTiempo(array $datos): array
    {
        return self::segundosSinRespuesta($datos) > 0 ? [self::SALIDA_SIN_RESPUESTA] : [];
    }

    /** @return list<string> */
    private static function idsOpciones(mixed $opciones): array
    {
        $ids = [];
        foreach (is_array($opciones) ? $opciones : [] as $opcion) {
            if (is_array($opcion) && isset($opcion['id']) && is_string($opcion['id'])) {
                $ids[] = $opcion['id'];
            }
        }

        return $ids;
    }
}
