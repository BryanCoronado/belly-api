<?php

namespace Belly\Reparto\Domain;

use Belly\Flujos\Domain\Horario;
use DomainException;

/** Limpia y revisa el lienzo de llegada antes de guardarlo. Los mensajes son para personas, no técnicos. */
final class ValidadorReparto
{
    private const MAX_COORDENADA = 100000;
    private const PATRON_ID = '/^[A-Za-z0-9_\-]{1,40}$/';

    /** Pasos que solo eligen camino: cada salida sin flecha es un cliente que termina en el respaldo. */
    private const PASOS_DE_DECISION = [NodosReparto::HORARIO, NodosReparto::TURNOS_GRUPOS, NodosReparto::CLIENTE, NodosReparto::ORIGEN, NodosReparto::PALABRAS, NodosReparto::DIVIDIR];

    private const NOMBRES_SALIDA = [
        'fuera' => 'Fuera de horario',
        'nuevo' => 'Cliente nuevo',
        'recurrente' => 'Ya había escrito antes',
        'anuncio' => 'Viene de un anuncio',
        'directo' => 'Escribió directo',
        'si' => 'Menciona alguna',
        'no' => 'No menciona ninguna',
        'a' => 'Camino A',
        'b' => 'Camino B',
    ];

    /**
     * Deja solo lo que el motor entiende. Lanza un error si la forma básica no es válida.
     *
     * @return array{nodos: list<array>, conexiones: list<array>}
     */
    public static function normalizar(mixed $definicion): array
    {
        if (! is_array($definicion) || ! is_array($definicion['nodos'] ?? null) || ! is_array($definicion['conexiones'] ?? null)) {
            throw new DomainException('El lienzo llegó incompleto. Recarga la página e inténtalo de nuevo.');
        }
        if (count($definicion['nodos']) > NodosReparto::MAX_NODOS) {
            throw new DomainException('El lienzo puede tener hasta ' . NodosReparto::MAX_NODOS . ' pasos.');
        }

        $nodos = [];
        foreach ($definicion['nodos'] as $nodo) {
            $id = is_array($nodo) ? (string) ($nodo['id'] ?? '') : '';
            $tipo = (string) ($nodo['tipo'] ?? '');
            if (! preg_match(self::PATRON_ID, $id) || ! in_array($tipo, NodosReparto::TODOS, true) || isset($nodos[$id])) {
                throw new DomainException('Hay un paso que no se reconoce. Bórralo y vuelve a crearlo.');
            }
            $nodos[$id] = [
                'id' => $id,
                'tipo' => $tipo,
                'x' => self::coordenada($nodo['x'] ?? 0),
                'y' => self::coordenada($nodo['y'] ?? 0),
                'datos' => self::datos($tipo, is_array($nodo['datos'] ?? null) ? $nodo['datos'] : []),
            ];
        }

        $conexiones = [];
        $usadas = [];
        foreach ($definicion['conexiones'] as $c) {
            if (! is_array($c)) {
                continue;
            }
            $desde = (string) ($c['desde'] ?? '');
            $hacia = (string) ($c['hacia'] ?? '');
            $salida = (string) ($c['salida'] ?? '');
            if (! isset($nodos[$desde], $nodos[$hacia]) || $desde === $hacia || $nodos[$hacia]['tipo'] === NodosReparto::INICIO) {
                continue;
            }
            if (! in_array($salida, NodosReparto::salidas($nodos[$desde]), true) || isset($usadas[$desde . ':' . $salida])) {
                continue;
            }
            $usadas[$desde . ':' . $salida] = true;
            $idConexion = (string) ($c['id'] ?? '');
            $conexiones[] = [
                'id' => preg_match(self::PATRON_ID, $idConexion) ? $idConexion : 'c_' . substr(md5($desde . ':' . $salida), 0, 10),
                'desde' => $desde,
                'salida' => $salida,
                'hacia' => $hacia,
            ];
        }

        return ['nodos' => array_values($nodos), 'conexiones' => $conexiones];
    }

    /**
     * @param array{grupos: array<int, int>, personas: list<int>, agentes: array<int, array{activo: bool, listo: bool}>, grupos_turno: list<array{id: int}>} $catalogo
     *        grupos: id => cantidad de miembros; agentes: id => estado
     * @return array{errores: list<array{nodo: ?string, mensaje: string}>, avisos: list<array{nodo: ?string, mensaje: string}>}
     */
    public static function revisar(array $definicion, array $catalogo): array
    {
        $errores = [];
        $avisos = [];
        $inicios = array_values(array_filter($definicion['nodos'], static fn (array $n): bool => $n['tipo'] === NodosReparto::INICIO));
        if (count($inicios) !== 1) {
            $errores[] = ['nodo' => null, 'mensaje' => 'Debe haber un solo paso «El cliente escribe».'];
        }

        $conectadas = [];
        $siguientes = [];
        foreach ($definicion['conexiones'] as $c) {
            $conectadas[$c['desde'] . ':' . $c['salida']] = true;
            $siguientes[$c['desde']][] = $c['hacia'];
        }
        $alcanzables = $inicios === [] ? [] : self::alcanzables($inicios[0]['id'], $siguientes);
        $enCirculo = self::nodoEnCirculo($definicion['nodos'], $siguientes);
        if ($enCirculo !== null) {
            $errores[] = ['nodo' => $enCirculo, 'mensaje' => 'Las flechas forman un círculo y vuelven a un paso anterior. Quita alguna para que el camino termine.'];
        }

        $entregan = false;
        foreach ($definicion['nodos'] as $nodo) {
            $id = $nodo['id'];
            $d = $nodo['datos'];
            $alcanzable = isset($alcanzables[$id]);
            $mal = static function (string $mensaje) use (&$errores, $id): void {
                $errores[] = ['nodo' => $id, 'mensaje' => $mensaje];
            };
            $ojo = static function (string $mensaje) use (&$avisos, $id): void {
                $avisos[] = ['nodo' => $id, 'mensaje' => $mensaje];
            };
            if ($alcanzable && in_array($nodo['tipo'], [NodosReparto::REPARTIR, NodosReparto::PERSONA, NodosReparto::AGENTE_IA], true)) {
                $entregan = true;
            }

            switch ($nodo['tipo']) {
                case NodosReparto::INICIO:
                    if ($d['id_usuario_respaldo'] !== null && ! in_array($d['id_usuario_respaldo'], $catalogo['personas'], true)) {
                        $mal('La persona de respaldo ya no está activa. Elige otra.');
                    }
                    break;
                case NodosReparto::TURNOS_GRUPOS:
                    if (($catalogo['grupos_turno'] ?? []) === []) {
                        $mal('Crea al menos un grupo tipo «Turno» con horario en la pestaña Grupos.');
                    } else {
                        self::avisosTurnosGrupos($catalogo['grupos_turno'], $ojo);
                    }
                    break;
                case NodosReparto::HORARIO:
                    $ojo('Este paso tiene horas escritas a mano. Cámbialo por «Según turnos del equipo» para que el horario se lea de Grupos y no tengas dos horarios distintos.');
                    if ($d['tramos'] === []) {
                        $mal('Agrega al menos un horario.');
                    }
                    $nombres = [];
                    foreach ($d['tramos'] as $tramo) {
                        $nombre = $tramo['nombre'] ?: 'sin nombre';
                        if ($tramo['nombre'] === '' || ! Horario::valido($tramo)) {
                            $mal('Revisa el horario «' . $nombre . '»: necesita nombre, al menos un día y las horas.');
                        } elseif ($tramo['desde'] === $tramo['hasta']) {
                            $mal('En el horario «' . $nombre . '» la hora de inicio y de fin son iguales: nunca se cumpliría.');
                        }
                        $clave = mb_strtolower($tramo['nombre']);
                        if ($clave !== '' && isset($nombres[$clave])) {
                            $mal('Hay dos horarios llamados «' . $nombre . '». Ponles nombres distintos para reconocer sus flechas.');
                        }
                        $nombres[$clave] = true;
                    }
                    foreach (Horario::cruces($d['tramos']) as [$a, $b]) {
                        $ojo('Los horarios «' . $a . '» y «' . $b . '» se cruzan: en esas horas gana «' . $a . '», el que está primero.');
                    }
                    break;
                case NodosReparto::PALABRAS:
                    if ($d['palabras'] === []) {
                        $mal('Escribe al menos una palabra clave.');
                    }
                    break;
                case NodosReparto::REPARTIR:
                    if ($d['id_grupo'] !== null && ! isset($catalogo['grupos'][$d['id_grupo']])) {
                        $mal('El grupo elegido ya no existe. Elige otro.');
                    } elseif ($d['id_grupo'] !== null && $catalogo['grupos'][$d['id_grupo']] === 0) {
                        $ojo('El grupo elegido no tiene asesores: los chats seguirán por «Si nadie está disponible».');
                    }
                    if ($d['estrategia'] === Estrategia::PRIORIDAD && $d['id_grupo'] === null) {
                        $mal('Para repartir por prioridad elige un grupo: su orden define quién va primero.');
                    }
                    break;
                case NodosReparto::PERSONA:
                    if (! in_array($d['id_usuario'], $catalogo['personas'], true)) {
                        $mal('Elige a qué persona le llega el chat.');
                    }
                    break;
                case NodosReparto::AGENTE_IA:
                    $agente = $d['id_agente'] === null ? null : ($catalogo['agentes'][$d['id_agente']] ?? null);
                    if ($agente === null) {
                        $mal('Elige qué asistente atiende.');
                    } elseif (! $agente['listo']) {
                        $ojo(($agente['activo'] ? 'Falta conectar tu cuenta de OpenAI' : 'Este asistente está apagado')
                            . ': mientras tanto los chats seguirán por «Cuando pase a una persona».');
                    }
                    if ($alcanzable && ! isset($conectadas[$id . ':' . NodosReparto::SALIDA_HUMANO])) {
                        $ojo('«Cuando pase a una persona» no va a ningún paso: esos chats le llegarán a la persona de respaldo.');
                    }
                    break;
            }

            if ($nodo['tipo'] === NodosReparto::INICIO) {
                if (! isset($conectadas[$id . ':' . NodosReparto::SALIDA_SIGUIENTE])) {
                    $ojo('El inicio no está unido a nada: todos los chats irán a la persona de respaldo.');
                }
                continue;
            }
            if (! $alcanzable) {
                $ojo('Ningún camino llega a este paso: nunca se usará.');
                continue;
            }
            if (in_array($nodo['tipo'], self::PASOS_DE_DECISION, true)) {
                foreach (NodosReparto::salidas($nodo) as $salida) {
                    if ($nodo['tipo'] === NodosReparto::TURNOS_GRUPOS && str_starts_with($salida, 'g_')) {
                        continue;
                    }
                    if (! isset($conectadas[$id . ':' . $salida])) {
                        $ojo('La salida «' . self::nombreSalida($nodo, $salida) . '» no va a ningún paso: esos chats le llegarán a la persona de respaldo.');
                    }
                }
            }
        }

        if ($inicios !== [] && isset($conectadas[$inicios[0]['id'] . ':' . NodosReparto::SALIDA_SIGUIENTE]) && ! $entregan) {
            $avisos[] = ['nodo' => null, 'mensaje' => 'Ningún camino termina en «Repartir», «A una persona» o «Asistente IA»: los chats nuevos irán a la persona de respaldo.'];
        }

        self::revisarEnlacesTurnosRepartir($definicion, $catalogo, $alcanzables, $errores, $avisos);

        return ['errores' => $errores, 'avisos' => $avisos];
    }

    /**
     * @param array<string, true> $alcanzables
     * @param list<array{nodo: ?string, mensaje: string}> $errores
     * @param list<array{nodo: ?string, mensaje: string}> $avisos
     */
    private static function revisarEnlacesTurnosRepartir(array $definicion, array $catalogo, array $alcanzables, array &$errores, array &$avisos): void
    {
        $porId = [];
        foreach ($definicion['nodos'] as $nodo) {
            $porId[$nodo['id']] = $nodo;
        }
        $nombres = $catalogo['grupos_nombres'] ?? [];
        $miembros = $catalogo['grupos'] ?? [];
        $turnosPorRepartir = [];

        foreach ($definicion['conexiones'] as $c) {
            $origen = $porId[$c['desde']] ?? null;
            $destino = $porId[$c['hacia']] ?? null;
            if ($origen === null || ($origen['tipo'] ?? '') !== NodosReparto::TURNOS_GRUPOS) {
                continue;
            }
            if (! str_starts_with((string) $c['salida'], 'g_')) {
                continue;
            }
            if (! isset($alcanzables[$c['desde']])) {
                continue;
            }
            $idTurno = (int) substr((string) $c['salida'], 2);
            $nombreTurno = $nombres[$idTurno] ?? 'turno';
            if ($destino === null) {
                continue;
            }
            if (($destino['tipo'] ?? '') !== NodosReparto::REPARTIR) {
                $avisos[] = ['nodo' => $c['desde'], 'mensaje' => 'La salida «' . $nombreTurno . '» debería llegar a un paso «Repartir entre asesores».'];
                continue;
            }
            $turnosPorRepartir[$c['hacia']][] = $idTurno;
            $explicito = isset($destino['datos']['id_grupo']) && (int) $destino['datos']['id_grupo'] > 0
                ? (int) $destino['datos']['id_grupo']
                : null;
            if ($explicito !== null && $explicito !== $idTurno) {
                $otro = $nombres[$explicito] ?? 'otro grupo';
                $errores[] = ['nodo' => $c['hacia'], 'mensaje' => 'Este reparto apunta a «' . $otro . '», pero la flecha viene del turno «' . $nombreTurno . '». Usa el mismo grupo o un paso Repartir aparte.'];
            }
            $cantidad = $miembros[$idTurno] ?? null;
            if ($cantidad === null) {
                $errores[] = ['nodo' => $c['hacia'], 'mensaje' => 'El turno «' . $nombreTurno . '» ya no existe. Actualiza Grupos o quita esa flecha.'];
            } elseif ($cantidad === 0) {
                $errores[] = ['nodo' => $c['hacia'], 'mensaje' => 'En el horario de «' . $nombreTurno . '» no hay asesores en el grupo.'];
            } elseif ($explicito === null) {
                $avisos[] = ['nodo' => $c['hacia'], 'mensaje' => 'Por la flecha de «' . $nombreTurno . '» se repartirá solo entre los asesores de ese grupo.'];
            } else {
                $avisos[] = ['nodo' => $c['hacia'], 'mensaje' => '«' . $nombreTurno . '» listo para repartir chats en su horario.'];
            }
        }

        foreach ($turnosPorRepartir as $repartirId => $ids) {
            $unicos = array_values(array_unique($ids));
            if (count($unicos) > 1) {
                $lista = implode(', ', array_map(static fn (int $id): string => '«' . ($nombres[$id] ?? 'turno') . '»', $unicos));
                $errores[] = ['nodo' => $repartirId, 'mensaje' => 'Varios turnos distintos (' . $lista . ') llegan al mismo paso Repartir. Crea un Repartir separado para cada turno.'];
            }
        }

        foreach ($definicion['nodos'] as $nodo) {
            if (($nodo['tipo'] ?? '') !== NodosReparto::TURNOS_GRUPOS || ! isset($alcanzables[$nodo['id']])) {
                continue;
            }
            foreach ($catalogo['grupos_turno'] ?? [] as $turno) {
                $salida = 'g_' . $turno['id'];
                $conectada = false;
                foreach ($definicion['conexiones'] as $c) {
                    if ($c['desde'] === $nodo['id'] && $c['salida'] === $salida) {
                        $conectada = true;
                        break;
                    }
                }
                if (! $conectada) {
                    $avisos[] = ['nodo' => $nodo['id'], 'mensaje' => 'Conecta la salida «' . ($turno['nombre'] ?? 'turno') . '» a un paso Repartir con ese mismo grupo.'];
                }
            }
        }
    }

    private static function nombreSalida(array $nodo, string $salida): string
    {
        foreach ($nodo['datos']['tramos'] ?? [] as $tramo) {
            if ($tramo['id'] === $salida) {
                return $tramo['nombre'] ?: 'sin nombre';
            }
        }

        return self::NOMBRES_SALIDA[$salida] ?? $salida;
    }

    /** @param array<string, list<string>> $siguientes @return array<string, true> */
    private static function alcanzables(string $inicio, array $siguientes): array
    {
        $vistos = [$inicio => true];
        $pendientes = [$inicio];
        while ($pendientes !== []) {
            foreach ($siguientes[array_pop($pendientes)] ?? [] as $hacia) {
                if (! isset($vistos[$hacia])) {
                    $vistos[$hacia] = true;
                    $pendientes[] = $hacia;
                }
            }
        }

        return $vistos;
    }

    /** Un nodo que forma parte de un círculo de flechas, o null. @param array<string, list<string>> $siguientes */
    private static function nodoEnCirculo(array $nodos, array $siguientes): ?string
    {
        $estado = [];
        foreach ($nodos as $nodo) {
            if (isset($estado[$nodo['id']])) {
                continue;
            }
            // Recorrido en profundidad sin recursión: 1 = en el camino actual, 2 = terminado.
            $pila = [[$nodo['id'], 0]];
            $estado[$nodo['id']] = 1;
            while ($pila !== []) {
                [$actual, $i] = $pila[count($pila) - 1];
                $hijos = $siguientes[$actual] ?? [];
                if ($i >= count($hijos)) {
                    $estado[$actual] = 2;
                    array_pop($pila);
                    continue;
                }
                $pila[count($pila) - 1][1]++;
                $hijo = $hijos[$i];
                if (($estado[$hijo] ?? 0) === 1) {
                    return $hijo;
                }
                if (! isset($estado[$hijo])) {
                    $estado[$hijo] = 1;
                    $pila[] = [$hijo, 0];
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int, nombre: string, dias: list<int>, desde: string, hasta: string, miembros?: list<int>}> $grupos
     * @param callable(string): void $ojo
     */
    private static function avisosTurnosGrupos(array $grupos, callable $ojo): void
    {
        $tramos = array_map(static fn (array $g): array => [
            'nombre' => $g['nombre'],
            'dias' => $g['dias'],
            'desde' => $g['desde'],
            'hasta' => $g['hasta'],
        ], $grupos);
        foreach (Horario::cruces($tramos) as [$a, $b]) {
            $ojo('Los turnos «' . $a . '» y «' . $b . '» se cruzan: en esas horas gana «' . $a . '», el que aparece primero en la lista de Grupos.');
        }
        $n = count($grupos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $gi = $grupos[$i];
                $gj = $grupos[$j];
                if (! Horario::seSolapan(['dias' => $gi['dias'], 'desde' => $gi['desde'], 'hasta' => $gi['hasta']], ['dias' => $gj['dias'], 'desde' => $gj['desde'], 'hasta' => $gj['hasta']])) {
                    continue;
                }
                $compartidos = array_intersect($gi['miembros'] ?? [], $gj['miembros'] ?? []);
                if ($compartidos === []) {
                    continue;
                }
                $ojo('Hay asesores en «' . $gi['nombre'] . '» y «' . $gj['nombre'] . '» con horarios que se cruzan: en esas horas solo cuenta el turno que gana.');
            }
        }
    }

    private static function coordenada(mixed $valor): int
    {
        $numero = is_numeric($valor) ? (float) $valor : 0.0;

        return (int) round(max(-self::MAX_COORDENADA, min(self::MAX_COORDENADA, $numero)));
    }

    /** @return array<string, mixed> */
    private static function datos(string $tipo, array $d): array
    {
        $entero = static fn (mixed $v): ?int => ($v === null || $v === '' || (int) $v <= 0) ? null : (int) $v;

        return match ($tipo) {
            NodosReparto::INICIO => ['id_usuario_respaldo' => $entero($d['id_usuario_respaldo'] ?? null)],
            NodosReparto::HORARIO => ['tramos' => self::tramos($d['tramos'] ?? [])],
            NodosReparto::PALABRAS => ['palabras' => self::palabras($d['palabras'] ?? [])],
            NodosReparto::DIVIDIR => ['porcentaje_a' => max(1, min(99, (int) ($d['porcentaje_a'] ?? 50)))],
            NodosReparto::TURNOS_GRUPOS => [
                'grupos_turno' => self::idsGruposTurno($d['grupos_turno'] ?? []),
            ],
            NodosReparto::REPARTIR => [
                'id_grupo' => $entero($d['id_grupo'] ?? null),
                'estrategia' => Estrategia::valida($d['estrategia'] ?? null) ? $d['estrategia'] : Estrategia::EQUITATIVO,
                'limite_chats' => ($l = $entero($d['limite_chats'] ?? null)) === null ? null : min($l, 500),
            ],
            NodosReparto::PERSONA => ['id_usuario' => $entero($d['id_usuario'] ?? null)],
            NodosReparto::AGENTE_IA => ['id_agente' => $entero($d['id_agente'] ?? null)],
            default => [],
        };
    }

    /** @return list<array{id: string, nombre: string, dias: list<int>, desde: string, hasta: string}> */
    private static function tramos(mixed $tramos): array
    {
        $lista = [];
        foreach (array_slice(is_array($tramos) ? $tramos : [], 0, NodosReparto::MAX_TRAMOS) as $t) {
            $id = is_array($t) ? ($t['id'] ?? null) : null;
            if (! is_string($id) || ! preg_match(self::PATRON_ID, $id) || $id === NodosReparto::SALIDA_FUERA || isset($lista[$id])) {
                continue;
            }
            $dias = array_values(array_unique(array_filter(array_map('intval', (array) ($t['dias'] ?? [])), static fn (int $x): bool => $x >= 1 && $x <= 7)));
            sort($dias);
            $lista[$id] = [
                'id' => $id,
                'nombre' => mb_substr(trim((string) ($t['nombre'] ?? '')), 0, 40),
                'dias' => $dias,
                'desde' => self::hora($t['desde'] ?? ''),
                'hasta' => self::hora($t['hasta'] ?? ''),
            ];
        }

        return array_values($lista);
    }

    /** «9:5» o «09:05:00» quedan como «09:05»; lo que no sea una hora queda vacío y la revisión lo marca. */
    private static function hora(mixed $valor): string
    {
        if (! is_string($valor) || ! preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', trim($valor), $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
            return '';
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    /** @return list<array{id: int}> */
    private static function idsGruposTurno(mixed $lista): array
    {
        $salida = [];
        foreach (is_array($lista) ? $lista : [] as $g) {
            if (is_array($g) && (int) ($g['id'] ?? 0) > 0) {
                $salida[(int) $g['id']] = ['id' => (int) $g['id']];
            }
        }

        return array_values($salida);
    }

    /** @return list<string> */
    private static function palabras(mixed $palabras): array
    {
        $lista = [];
        foreach (is_array($palabras) ? $palabras : [] as $p) {
            $p = mb_strtolower(trim((string) $p));
            if ($p !== '' && mb_strlen($p) <= 40) {
                $lista[$p] = $p;
            }
        }

        return array_values(array_slice($lista, 0, NodosReparto::MAX_PALABRAS));
    }
}
