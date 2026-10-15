<?php

namespace Belly\Flujos\Domain;

/**
 * Revisa una definición de flujo y devuelve mensajes en lenguaje claro.
 * "errores" impiden activar el flujo; "avisos" solo advierten.
 */
final class ValidadorFlujo
{
    public const MAX_NODOS = 150;
    public const MAX_CONEXIONES = 400;

    private const PATRON_ID = '/^[A-Za-z0-9_-]{1,40}$/';
    private const OPERADORES = ['igual', 'distinto', 'contiene', 'empieza', 'vacio', 'no_vacio', 'mayor', 'menor'];
    private const VALIDACIONES = ['texto', 'numero', 'email', 'telefono'];
    private const MEDIAS = ['ninguno', 'imagen', 'video', 'documento', 'audio'];
    private const UNIDADES = ['segundos', 'minutos', 'horas', 'dias'];

    /** @var list<array{nodo:?string, mensaje:string}> */
    private array $errores = [];

    /** @var list<array{nodo:?string, mensaje:string}> */
    private array $avisos = [];

    /** @return array{errores:list<array{nodo:?string,mensaje:string}>, avisos:list<array{nodo:?string,mensaje:string}>} */
    public function validar(mixed $definicion): array
    {
        $this->errores = [];
        $this->avisos = [];

        if (! is_array($definicion) || ! is_array($definicion['nodos'] ?? null) || ! is_array($definicion['conexiones'] ?? null)) {
            $this->error(null, 'El flujo está vacío o tiene un formato que no se entiende.');

            return $this->resultado();
        }

        $nodos = $definicion['nodos'];
        $conexiones = $definicion['conexiones'];

        if (count($nodos) > self::MAX_NODOS || count($conexiones) > self::MAX_CONEXIONES) {
            $this->error(null, 'El flujo es demasiado grande. Divídelo en dos flujos más pequeños.');

            return $this->resultado();
        }

        $porId = $this->revisarNodos($nodos);
        $this->revisarConexiones($porId, $conexiones);

        $grafo = new Grafo($definicion);
        foreach ($porId as $id => $nodo) {
            $this->revisarContenido($nodo);
            $this->revisarSalidasSinConectar($grafo, $id, $nodo);
        }
        $this->revisarAlcanzables($grafo, $porId);

        return $this->resultado();
    }

    /** @return array<string, array> */
    private function revisarNodos(array $nodos): array
    {
        $porId = [];
        $inicios = 0;

        foreach ($nodos as $nodo) {
            $id = is_array($nodo) ? ($nodo['id'] ?? null) : null;
            if (! is_string($id) || ! preg_match(self::PATRON_ID, $id)) {
                $this->error(null, 'Hay un paso con identificador inválido.');
                continue;
            }
            if (isset($porId[$id])) {
                $this->error($id, 'Hay dos pasos repetidos.');
                continue;
            }
            if (! in_array($nodo['tipo'] ?? '', TiposNodo::TODOS, true)) {
                $this->error($id, 'Este paso es de un tipo que no existe.');
                continue;
            }
            if (isset($nodo['datos']) && ! is_array($nodo['datos'])) {
                $this->error($id, 'Los datos de este paso no son válidos.');
                continue;
            }
            $porId[$id] = $nodo;
            $inicios += $nodo['tipo'] === TiposNodo::INICIO ? 1 : 0;
        }

        if ($inicios === 0) {
            $this->error(null, 'Falta el paso de inicio.');
        } elseif ($inicios > 1) {
            $this->error(null, 'Solo puede haber un paso de inicio.');
        }

        return $porId;
    }

    private function revisarConexiones(array $porId, array $conexiones): void
    {
        $usadas = [];

        foreach ($conexiones as $c) {
            $desde = is_array($c) ? (string) ($c['desde'] ?? '') : '';
            $salida = is_array($c) ? (string) ($c['salida'] ?? '') : '';
            $hacia = is_array($c) ? (string) ($c['hacia'] ?? '') : '';

            if (! isset($porId[$desde]) || ! isset($porId[$hacia])) {
                $this->error(null, 'Hay una flecha que apunta a un paso que ya no existe.');
                continue;
            }
            if (! in_array($salida, TiposNodo::salidas($porId[$desde]), true)) {
                $this->error($desde, 'Hay una flecha que sale de una opción que ya no existe.');
                continue;
            }
            if (($porId[$hacia]['tipo'] ?? '') === TiposNodo::INICIO) {
                $this->error($desde, 'No se puede volver al paso de inicio.');
                continue;
            }
            if (isset($usadas[$desde . '|' . $salida])) {
                $this->error($desde, 'Una misma salida tiene más de una flecha.');
                continue;
            }
            $usadas[$desde . '|' . $salida] = true;
        }
    }

    private function revisarContenido(array $nodo): void
    {
        $id = $nodo['id'];
        $d = (array) ($nodo['datos'] ?? []);
        $texto = trim((string) ($d['texto'] ?? ''));

        if (isset($d['pausa']) && ((int) $d['pausa'] < 0 || (int) $d['pausa'] > TiposNodo::MAX_PAUSA)) {
            $this->error($id, 'La pausa antes de enviar debe estar entre 0 y ' . TiposNodo::MAX_PAUSA . ' segundos.');
        }
        if (isset($d['timeout_min']) && ((int) $d['timeout_min'] < 0 || (int) $d['timeout_min'] > TiposNodo::MAX_SIN_RESPUESTA_MIN)) {
            $this->error($id, 'El tiempo de espera de respuesta es demasiado largo (máximo 30 días).');
        }

        switch ($nodo['tipo']) {
            case TiposNodo::INICIO:
                if (! in_array($d['disparador'] ?? Disparador::PRIMER_MENSAJE, Disparador::TODOS, true)) {
                    $this->error($id, 'Elige cuándo debe empezar el flujo.');
                }
                if (($d['disparador'] ?? '') === Disparador::PALABRA_CLAVE && $this->palabras($d['palabras'] ?? []) === []) {
                    $this->error($id, 'Escribe al menos una palabra que active el flujo.');
                }
                if (isset($d['audiencia']) && ! in_array($d['audiencia'], Disparador::AUDIENCIAS, true)) {
                    $this->error($id, 'Elige a quién le responde este flujo.');
                }
                if (isset($d['repetir']) && ! in_array($d['repetir'], Disparador::REPETICIONES, true)) {
                    $this->error($id, 'Elige cada cuánto puede repetirse el flujo con el mismo cliente.');
                }
                if (($d['repetir'] ?? '') === 'cada_horas' && ((int) ($d['repetir_horas'] ?? 0) < 1 || (int) $d['repetir_horas'] > 8760)) {
                    $this->error($id, 'Indica cada cuántas horas puede repetirse (entre 1 y 8760).');
                }
                if (is_array($d['horario_activo'] ?? null) && ! empty($d['horario_activo']['activo']) && ! Horario::valido($d['horario_activo'])) {
                    $this->error($id, 'El horario en que funciona el flujo está incompleto.');
                }
                if (isset($d['reparto']) && ! in_array($d['reparto'], Asignacion::REPARTOS, true)) {
                    $this->error($id, 'Elige cómo se reparten los chats entre tus asesores.');
                }
                if (! Asignacion::listaValida($d['equipo'] ?? null)) {
                    $this->error($id, 'La lista de asesores del flujo no es válida.');
                }
                if (isset($d['max_intentos']) && ((int) $d['max_intentos'] < 1 || (int) $d['max_intentos'] > TiposNodo::MAX_INTENTOS_PERMITIDOS)) {
                    $this->error($id, 'Los intentos antes de pasar con un asesor deben estar entre 1 y ' . TiposNodo::MAX_INTENTOS_PERMITIDOS . '.');
                }
                break;

            case TiposNodo::MENSAJE:
                $media = (string) ($d['media'] ?? 'ninguno');
                $url = trim((string) ($d['url'] ?? ''));
                if (! in_array($media, self::MEDIAS, true)) {
                    $this->error($id, 'El tipo de archivo no es válido.');
                } elseif ($media === 'ninguno' && $texto === '') {
                    $this->error($id, 'Escribe el mensaje que enviará el bot.');
                } elseif ($media !== 'ninguno' && ! preg_match('#^https://\S+$#i', $url)) {
                    $this->error($id, 'El enlace del archivo debe empezar con https://');
                }
                if (mb_strlen($texto) > ($media === 'ninguno' ? 4000 : 1000)) {
                    $this->error($id, 'El mensaje es demasiado largo.');
                }
                break;

            case TiposNodo::BOTONES:
                $this->textoObligatorio($id, $texto, 'Escribe la pregunta que verá el cliente.', 1000);
                $this->revisarOpciones($id, $d['botones'] ?? [], TiposNodo::MAX_BOTONES, 20, 'botón', 'botones');
                break;

            case TiposNodo::LISTA:
                $this->textoObligatorio($id, $texto, 'Escribe el mensaje que verá el cliente.', 1000);
                if (trim((string) ($d['boton'] ?? '')) === '' || mb_strlen((string) $d['boton']) > 20) {
                    $this->error($id, 'El texto del botón de la lista es obligatorio (máximo 20 letras).');
                }
                $this->revisarOpciones($id, $d['opciones'] ?? [], TiposNodo::MAX_OPCIONES, 24, 'opción', 'opciones');
                foreach ((array) ($d['opciones'] ?? []) as $o) {
                    if (is_array($o) && mb_strlen((string) ($o['descripcion'] ?? '')) > 72) {
                        $this->error($id, 'La descripción de una opción pasa de 72 letras.');
                        break;
                    }
                }
                break;

            case TiposNodo::PREGUNTA:
                $this->textoObligatorio($id, $texto, 'Escribe lo que le preguntará el bot.', 1000);
                if (Texto::nombreVariable((string) ($d['variable'] ?? '')) === '') {
                    $this->error($id, 'Pon un nombre al dato que se va a guardar (por ejemplo: correo).');
                }
                if (! in_array($d['validacion'] ?? 'texto', self::VALIDACIONES, true)) {
                    $this->error($id, 'El tipo de respuesta esperada no es válido.');
                }
                break;

            case TiposNodo::CONDICION:
                if (Texto::nombreVariable((string) ($d['variable'] ?? '')) === '') {
                    $this->error($id, 'Elige el dato que se va a comparar.');
                }
                if (! in_array($d['operador'] ?? 'igual', self::OPERADORES, true)) {
                    $this->error($id, 'La comparación elegida no es válida.');
                }
                break;

            case TiposNodo::HORARIO:
                if ((array) ($d['dias'] ?? []) === []) {
                    $this->error($id, 'Marca al menos un día de atención.');
                } elseif (! Horario::valido($d)) {
                    $this->error($id, 'Las horas de atención no son válidas.');
                }
                break;

            case TiposNodo::ESPERA:
                $cantidad = (int) ($d['cantidad'] ?? 0);
                if (! in_array($d['unidad'] ?? 'segundos', self::UNIDADES, true) || $cantidad < 1) {
                    $this->error($id, 'Indica cuánto tiempo debe esperar el bot.');
                } elseif ($cantidad * $this->factorUnidad((string) ($d['unidad'] ?? 'segundos')) > TiposNodo::MAX_ESPERA_SEGUNDOS) {
                    $this->error($id, 'La espera máxima es de 30 días.');
                }
                break;

            case TiposNodo::UBICACION:
                $lat = str_replace(',', '.', trim((string) ($d['latitud'] ?? '')));
                $lng = str_replace(',', '.', trim((string) ($d['longitud'] ?? '')));
                if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
                    $this->error($id, 'La ubicación necesita latitud y longitud válidas.');
                }
                break;

            case TiposNodo::VARIABLE:
                if (Texto::nombreVariable((string) ($d['variable'] ?? '')) === '') {
                    $this->error($id, 'Pon un nombre al dato que se va a guardar.');
                }
                break;

            case TiposNodo::TEMPERATURA:
                if (! in_array($d['valor'] ?? '', TiposNodo::TEMPERATURAS, true)) {
                    $this->error($id, 'Elige qué tan interesado está el contacto.');
                }
                break;

            case TiposNodo::DIVIDIR:
                $porcentaje = $d['porcentaje_a'] ?? 50;
                if (! is_numeric($porcentaje) || (int) $porcentaje < 1 || (int) $porcentaje > 99) {
                    $this->error($id, 'El porcentaje del camino A debe estar entre 1 y 99.');
                }
                break;

            case TiposNodo::ASESOR:
                $modo = $d['asignar'] ?? Asignacion::DEL_FLUJO;
                if (! in_array($modo, Asignacion::MODOS_PASO, true)) {
                    $this->error($id, 'Elige a qué asesor pasa la conversación.');
                } elseif ($modo === Asignacion::ELEGIR && (int) ($d['asesor_id'] ?? 0) < 1) {
                    $this->error($id, 'Elige el asesor que atenderá esta conversación.');
                }
                if (! Asignacion::listaValida($d['asesores'] ?? null)) {
                    $this->error($id, 'La lista de asesores de este paso no es válida.');
                }
                if (mb_strlen($texto) > 4000) {
                    $this->error($id, 'El mensaje es demasiado largo.');
                }
                break;

            case TiposNodo::FIN:
                if (mb_strlen($texto) > 4000) {
                    $this->error($id, 'El mensaje es demasiado largo.');
                }
                break;
        }
    }

    private function revisarOpciones(string $id, mixed $opciones, int $max, int $maxTitulo, string $singular, string $plural): void
    {
        $opciones = is_array($opciones) ? $opciones : [];
        if ($opciones === []) {
            $this->error($id, "Agrega al menos un {$singular}.");

            return;
        }
        if (count($opciones) > $max) {
            $this->error($id, "Solo puede haber {$max} {$plural} (WhatsApp no permite más).");
        }

        $vistos = [];
        $ids = [];
        foreach ($opciones as $o) {
            $idOpcion = is_array($o) ? ($o['id'] ?? null) : null;
            $titulo = is_array($o) ? trim((string) ($o['titulo'] ?? '')) : '';

            if (! is_string($idOpcion) || ! preg_match('/^[A-Za-z0-9_-]{1,20}$/', $idOpcion) || isset($ids[$idOpcion]) || $idOpcion === TiposNodo::SALIDA_OTRO) {
                $this->error($id, "Hay un {$singular} con identificador inválido.");
                continue;
            }
            $ids[$idOpcion] = true;

            if ($titulo === '') {
                $this->error($id, "Hay un {$singular} sin texto.");
            } elseif (mb_strlen($titulo) > $maxTitulo) {
                $this->error($id, "El {$singular} «{$titulo}» pasa de {$maxTitulo} letras (WhatsApp no lo permite).");
            }
            $clave = Texto::normalizar($titulo);
            if ($clave !== '' && isset($vistos[$clave])) {
                $this->error($id, "Hay dos {$plural} con el mismo texto: «{$titulo}».");
            }
            $vistos[$clave] = true;
        }
    }

    private function revisarSalidasSinConectar(Grafo $grafo, string $id, array $nodo): void
    {
        $tipo = $nodo['tipo'];
        if ($tipo === TiposNodo::INICIO && $grafo->destino($id, TiposNodo::SALIDA_SIGUIENTE) === null) {
            $this->error($id, 'Conecta el inicio con el primer paso del flujo.');

            return;
        }

        foreach (TiposNodo::salidas($nodo) as $salida) {
            if (in_array($salida, [TiposNodo::SALIDA_OTRO, TiposNodo::SALIDA_SIN_RESPUESTA], true) || $tipo === TiposNodo::INICIO || $grafo->destino($id, $salida) !== null) {
                continue;
            }
            $this->aviso($id, 'Hay una salida sin flecha: ahí la conversación termina.');
            break;
        }
    }

    private function revisarAlcanzables(Grafo $grafo, array $porId): void
    {
        $inicio = $grafo->inicio();
        if ($inicio === null) {
            return;
        }

        $visitados = [$inicio['id'] => true];
        $pendientes = [$inicio['id']];
        while ($pendientes !== []) {
            $actual = array_pop($pendientes);
            foreach (TiposNodo::salidas($porId[$actual] ?? []) as $salida) {
                $destino = $grafo->destino($actual, $salida);
                if ($destino !== null && ! isset($visitados[$destino])) {
                    $visitados[$destino] = true;
                    $pendientes[] = $destino;
                }
            }
        }

        foreach ($porId as $id => $nodo) {
            if (! isset($visitados[$id])) {
                $this->aviso($id, 'Este paso no está conectado al inicio, así que nunca se usará.');
            }
        }
    }

    private function factorUnidad(string $unidad): int
    {
        return match ($unidad) {
            'minutos' => 60,
            'horas' => 3600,
            'dias' => 86400,
            default => 1,
        };
    }

    private function textoObligatorio(string $id, string $texto, string $mensaje, int $max): void
    {
        if ($texto === '') {
            $this->error($id, $mensaje);
        } elseif (mb_strlen($texto) > $max) {
            $this->error($id, "El texto pasa de {$max} letras (WhatsApp no lo permite).");
        }
    }

    /** @return list<string> */
    private function palabras(mixed $palabras): array
    {
        return array_values(array_filter(
            is_array($palabras) ? $palabras : [],
            static fn ($p) => is_string($p) && Texto::normalizar($p) !== ''
        ));
    }

    private function error(?string $nodo, string $mensaje): void
    {
        $this->errores[] = ['nodo' => $nodo, 'mensaje' => $mensaje];
    }

    private function aviso(?string $nodo, string $mensaje): void
    {
        $this->avisos[] = ['nodo' => $nodo, 'mensaje' => $mensaje];
    }

    private function resultado(): array
    {
        return ['errores' => $this->errores, 'avisos' => $this->avisos];
    }
}
