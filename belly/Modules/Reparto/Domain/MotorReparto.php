<?php

namespace Belly\Reparto\Domain;

use Belly\Flujos\Domain\Horario;
use DateTimeImmutable;

/**
 * Recorre el lienzo de llegada y decide quién atiende el chat. Sin dependencias del framework.
 *
 * Resultado:
 *  - tipo «asesor»: id_usuario elegido (motivo: asesor_previo | repartir | persona | respaldo)
 *  - tipo «agente»: id_agente y el nodo del agente (para seguir desde su salida «humano» cuando pase a una persona)
 *  - tipo «ninguno»: la empresa no tiene a nadie activo
 *  - ruta: ids de nodos recorridos; pasos: "nodo:salida" de cada flecha tomada (para iluminar el camino)
 */
final class MotorReparto
{
    private const MAX_PASOS = 100;

    /**
     * @param array{nodos?: list<array>, conexiones?: list<array>} $definicion
     * @param array{ahora: DateTimeImmutable, recurrente: bool, asesor_previo: ?int, anuncio: bool, texto: string, azar?: int} $situacion
     * @param string|null $desdeNodo seguir desde este nodo (por ejemplo, un agente que pasa a humano)
     * @return array{tipo: string, id_usuario: ?int, id_agente: ?int, nodo: ?string, motivo: string, ruta: list<string>, pasos: list<string>}
     */
    public static function decidir(array $definicion, array $situacion, DirectorioReparto $directorio, ?string $desdeNodo = null, ?string $salida = null): array
    {
        $nodos = [];
        foreach ($definicion['nodos'] ?? [] as $nodo) {
            $nodos[(string) $nodo['id']] = $nodo;
        }
        $flechas = [];
        foreach ($definicion['conexiones'] ?? [] as $c) {
            $flechas[$c['desde'] . ':' . $c['salida']] = (string) $c['hacia'];
        }

        $inicio = null;
        foreach ($nodos as $nodo) {
            if (($nodo['tipo'] ?? '') === NodosReparto::INICIO) {
                $inicio = $nodo;
            }
        }

        $ruta = [];
        $pasos = [];
        $actual = $desdeNodo ?? (string) ($inicio['id'] ?? '');
        $siguienteSalida = $salida ?? NodosReparto::SALIDA_SIGUIENTE;
        /** Grupo turno elegido en el paso anterior (salida g_{id}); el reparto lo usa si no hay grupo fijo en el paso. */
        $grupoTurnoActivo = null;
        if ($desdeNodo === null && $inicio !== null) {
            $ruta[] = $actual;
        }

        for ($i = 0; $i < self::MAX_PASOS; $i++) {
            $destino = $flechas[$actual . ':' . $siguienteSalida] ?? null;
            if ($destino === null || ! isset($nodos[$destino])) {
                break;
            }
            $pasos[] = $actual . ':' . $siguienteSalida;
            $actual = $destino;
            $ruta[] = $actual;
            $nodo = $nodos[$actual];
            $datos = (array) ($nodo['datos'] ?? []);

            $paso = self::evaluar((string) $nodo['tipo'], $datos, $situacion, $directorio, $grupoTurnoActivo);
            if (isset($paso['fin'])) {
                return $paso['fin'] + ['nodo' => $actual, 'ruta' => $ruta, 'pasos' => $pasos, 'id_usuario' => null, 'id_agente' => null];
            }
            $siguienteSalida = $paso['salida'];
            if (($nodo['tipo'] ?? '') === NodosReparto::TURNOS_GRUPOS) {
                $grupoTurnoActivo = str_starts_with($siguienteSalida, 'g_') ? (int) substr($siguienteSalida, 2) : null;
            }
        }

        $respaldo = $directorio->respaldo(isset($inicio['datos']['id_usuario_respaldo']) ? (int) $inicio['datos']['id_usuario_respaldo'] : null);

        return [
            'tipo' => $respaldo === null ? 'ninguno' : 'asesor',
            'id_usuario' => $respaldo,
            'id_agente' => null,
            'nodo' => null,
            'motivo' => $respaldo === null ? 'nadie' : 'respaldo',
            'ruta' => $ruta,
            'pasos' => $pasos,
        ];
    }

    /** @return array{salida: string}|array{fin: array<string, mixed>} */
    private static function evaluar(string $tipo, array $datos, array $situacion, DirectorioReparto $directorio, ?int $grupoTurnoActivo = null): array
    {
        switch ($tipo) {
            case NodosReparto::ASESOR_PREVIO:
                $previo = (int) ($situacion['asesor_previo'] ?? 0);
                if ($previo > 0 && $directorio->asesorDisponible($previo)) {
                    return ['fin' => ['tipo' => 'asesor', 'id_usuario' => $previo, 'motivo' => 'asesor_previo']];
                }

                return ['salida' => NodosReparto::SALIDA_NO];

            case NodosReparto::HORARIO:
                foreach ((array) ($datos['tramos'] ?? []) as $tramo) {
                    if (is_array($tramo) && Horario::dentro($tramo, $situacion['ahora'])) {
                        return ['salida' => (string) $tramo['id']];
                    }
                }

                return ['salida' => NodosReparto::SALIDA_FUERA];

            case NodosReparto::CLIENTE:
                return ['salida' => $situacion['recurrente'] ? 'recurrente' : 'nuevo'];

            case NodosReparto::ORIGEN:
                return ['salida' => $situacion['anuncio'] ? 'anuncio' : 'directo'];

            case NodosReparto::PALABRAS:
                $texto = mb_strtolower((string) $situacion['texto']);
                foreach ((array) ($datos['palabras'] ?? []) as $palabra) {
                    $palabra = mb_strtolower(trim((string) $palabra));
                    if ($palabra !== '' && str_contains($texto, $palabra)) {
                        return ['salida' => 'si'];
                    }
                }

                return ['salida' => 'no'];

            case NodosReparto::DIVIDIR:
                $azar = (int) ($situacion['azar'] ?? random_int(1, 100));

                return ['salida' => $azar <= (int) ($datos['porcentaje_a'] ?? 50) ? 'a' : 'b'];

            case NodosReparto::TURNOS_GRUPOS:
                foreach ($directorio->gruposTurno() as $grupo) {
                    if (Horario::dentro(['dias' => $grupo['dias'], 'desde' => $grupo['desde'], 'hasta' => $grupo['hasta']], $situacion['ahora'])) {
                        return ['salida' => 'g_' . $grupo['id']];
                    }
                }

                return ['salida' => NodosReparto::SALIDA_FUERA];

            case NodosReparto::REPARTIR:
                $grupoExplicito = isset($datos['id_grupo']) && (int) $datos['id_grupo'] > 0 ? (int) $datos['id_grupo'] : null;
                $grupo = $grupoTurnoActivo ?? $grupoExplicito;
                $estrategia = (string) ($datos['estrategia'] ?? Estrategia::EQUITATIVO);
                $limite = isset($datos['limite_chats']) && (int) $datos['limite_chats'] > 0 ? (int) $datos['limite_chats'] : null;
                $elegido = Elector::elegir($estrategia, $directorio->candidatos($grupo), $limite);
                if ($elegido !== null) {
                    return ['fin' => ['tipo' => 'asesor', 'id_usuario' => $elegido, 'motivo' => 'repartir', 'id_grupo_reparto' => $grupo]];
                }

                return ['salida' => NodosReparto::SALIDA_NADIE];

            case NodosReparto::PERSONA:
                $persona = (int) ($datos['id_usuario'] ?? 0);
                if ($persona > 0 && $directorio->personaActiva($persona)) {
                    return ['fin' => ['tipo' => 'asesor', 'id_usuario' => $persona, 'motivo' => 'persona']];
                }

                return ['salida' => NodosReparto::SALIDA_NADIE];

            case NodosReparto::AGENTE_IA:
                $agente = (int) ($datos['id_agente'] ?? 0);
                if ($agente > 0 && $directorio->agenteDisponible($agente)) {
                    return ['fin' => ['tipo' => 'agente', 'id_agente' => $agente, 'motivo' => 'agente']];
                }

                return ['salida' => NodosReparto::SALIDA_HUMANO];
        }

        return ['salida' => '__sin_salida__'];
    }
}
