<?php

namespace Belly\Flujos\Domain;

use DateTimeImmutable;

/**
 * Motor del bot sin IA: recorre el grafo de un flujo y devuelve qué mensajes enviar
 * y en qué paso queda la conversación. No hace nada de red ni de base de datos,
 * por eso lo usan igual el bot real y el simulador del editor.
 *
 * Estado de una conversación:
 *   estado        'activa' | 'esperando' | 'terminada' | 'derivada'
 *   nodo          paso en el que se detuvo (null si ya terminó)
 *   variables     datos guardados
 *   intentos      respuestas inválidas seguidas en el paso actual
 *   reanudar_en   segundos para retomar el flujo solo (espera o «sin respuesta»), o null
 *
 * Mensajes que devuelve (todos con 'tipo'):
 *   texto      {texto}
 *   imagen     {url, texto}   video {url, texto}   audio {url}   documento {url, nombre}
 *   ubicacion  {latitud, longitud, nombre, direccion}
 *   botones    {texto, botones:[{id,titulo}]}
 *   lista      {texto, boton, opciones:[{id,titulo,descripcion}]}
 *   pausa      {segundos}   pausa corta antes del siguiente mensaje
 *   espera     {segundos}   espera larga; el flujo se retoma después (solo informativo)
 *
 * Acciones (no se envían al cliente; las ejecuta quien corre el flujo):
 *   asignar      {modo, asesor_id}            pasar el chat a un asesor (ver Asignacion::pedido)
 *   temperatura  {valor}                      marcar frío / tibio / caliente
 *
 * En el texto de «Pasar a un asesor» se puede usar {{asesor}}: llega como MARCA_ASESOR
 * y quien ejecuta la asignación la cambia por el nombre real.
 */
final class MotorFlujo
{
    public const MAX_PASOS = 50;
    public const MAX_INTENTOS = 3;
    public const MARCA_ASESOR = '[[asesor]]';
    /** Variable interna con las opciones que fue eligiendo el cliente («Ventas › Precios»). */
    public const VARIABLE_RUTA = '_ruta';
    public const SEPARADOR_RUTA = ' › ';
    private const MAX_RUTA = 20;

    public const PALABRAS_ASESOR = ['asesor', 'agente', 'humano', 'persona', 'operador'];
    public const MENSAJE_ASESOR = 'Perfecto, un asesor te atenderá en breve. 🙌';

    public function __construct(private readonly ?TurnosEquipo $turnos = null)
    {
    }

    /** @return array{mensajes:list<array>, estado:array} */
    public function iniciar(array $definicion, array $variables, DateTimeImmutable $ahora): array
    {
        $grafo = new Grafo($definicion);
        $estado = $this->estadoInicial($variables, $ahora);
        $inicio = $grafo->inicio();

        if ($inicio === null) {
            return $this->terminar($estado, []);
        }

        return $this->recorrer($grafo, $inicio['id'], $estado, [], $ahora);
    }

    /**
     * @param array{texto?:string, respuesta_id?:?string} $entrada
     * @return array{mensajes:list<array>, estado:array}
     */
    public function continuar(array $definicion, array $estado, array $entrada, DateTimeImmutable $ahora): array
    {
        $grafo = new Grafo($definicion);
        $nodo = $grafo->nodo($estado['nodo'] ?? null);
        $actual = $estado['estado'] ?? '';

        if ($actual === 'esperando') {
            return ['mensajes' => [], 'estado' => $estado]; // mientras espera, el bot no responde
        }
        if ($actual !== 'activa' || $nodo === null) {
            $estado['estado'] = $actual === 'derivada' ? 'derivada' : 'terminada';
            $estado['nodo'] = null;
            $estado['reanudar_en'] = null;

            return ['mensajes' => [], 'estado' => $estado];
        }

        $texto = trim((string) ($entrada['texto'] ?? ''));
        $respuestaId = isset($entrada['respuesta_id']) ? (string) $entrada['respuesta_id'] : null;

        $estado['variables'] = array_merge($estado['variables'] ?? [], $this->variablesDeReloj($ahora), [
            'ultimo_mensaje' => $texto,
        ]);
        $estado['intentos'] = (int) ($estado['intentos'] ?? 0);
        $estado['reanudar_en'] = null;

        if ($this->pideAsesor($grafo, $texto)) {
            return $this->derivar($grafo, $estado, []);
        }

        return match ($nodo['tipo']) {
            TiposNodo::BOTONES => $this->responderOpciones($grafo, $nodo, 'botones', $estado, $texto, $respuestaId, $ahora),
            TiposNodo::LISTA => $this->responderOpciones($grafo, $nodo, 'opciones', $estado, $texto, $respuestaId, $ahora),
            TiposNodo::PREGUNTA => $this->responderPregunta($grafo, $nodo, $estado, $texto, $ahora),
            TiposNodo::FORMULARIO => $this->responderFormulario($grafo, $nodo, $estado, $texto, $ahora),
            default => $this->recorrer($grafo, $nodo['id'], $estado, [], $ahora),
        };
    }

    /**
     * Retoma un flujo detenido por una espera o porque el cliente no respondió a tiempo.
     *
     * @return array{mensajes:list<array>, estado:array}
     */
    public function reanudar(array $definicion, array $estado, DateTimeImmutable $ahora): array
    {
        $grafo = new Grafo($definicion);
        $nodo = $grafo->nodo($estado['nodo'] ?? null);

        if ($nodo === null || ! in_array($estado['estado'] ?? '', ['activa', 'esperando'], true)) {
            return $this->terminar($estado, []);
        }

        $estado['estado'] = 'activa';
        $estado['reanudar_en'] = null;
        $estado['variables'] = array_merge($estado['variables'] ?? [], $this->variablesDeReloj($ahora));

        $salida = $nodo['tipo'] === TiposNodo::ESPERA ? TiposNodo::SALIDA_SIGUIENTE : TiposNodo::SALIDA_SIN_RESPUESTA;

        return $this->recorrer($grafo, $grafo->destino($nodo['id'], $salida), $estado, [], $ahora);
    }

    // ───────────────────────── recorrido ─────────────────────────

    /** @return array{mensajes:list<array>, estado:array} */
    private function recorrer(Grafo $grafo, ?string $id, array $estado, array $mensajes, DateTimeImmutable $ahora): array
    {
        $pasos = 0;

        while ($id !== null) {
            if (++$pasos > self::MAX_PASOS) {
                return $this->terminar($estado, $mensajes);
            }

            $nodo = $grafo->nodo($id);
            if ($nodo === null) {
                return $this->terminar($estado, $mensajes);
            }

            $datos = (array) ($nodo['datos'] ?? []);
            $vars = $estado['variables'];
            $siguiente = null;

            switch ($nodo['tipo']) {
                case TiposNodo::INICIO:
                    $siguiente = $grafo->destino($id, TiposNodo::SALIDA_SIGUIENTE);
                    break;

                case TiposNodo::MENSAJE:
                    array_push($mensajes, ...$this->pausa($datos), ...$this->mensajesDeTexto($datos, $vars));
                    $siguiente = $grafo->destino($id, TiposNodo::SALIDA_SIGUIENTE);
                    break;

                case TiposNodo::UBICACION:
                    array_push($mensajes, ...$this->pausa($datos));
                    $mensajes[] = [
                        'tipo' => 'ubicacion',
                        'latitud' => (float) str_replace(',', '.', (string) ($datos['latitud'] ?? 0)),
                        'longitud' => (float) str_replace(',', '.', (string) ($datos['longitud'] ?? 0)),
                        'nombre' => Texto::plantilla((string) ($datos['nombre'] ?? ''), $vars),
                        'direccion' => Texto::plantilla((string) ($datos['direccion'] ?? ''), $vars),
                    ];
                    $siguiente = $grafo->destino($id, TiposNodo::SALIDA_SIGUIENTE);
                    break;

                case TiposNodo::VARIABLE:
                    $nombre = Texto::nombreVariable((string) ($datos['variable'] ?? ''));
                    if ($nombre !== '') {
                        $estado['variables'][$nombre] = Texto::plantilla((string) ($datos['valor'] ?? ''), $vars);
                    }
                    $siguiente = $grafo->destino($id, TiposNodo::SALIDA_SIGUIENTE);
                    break;

                case TiposNodo::CONDICION:
                    $siguiente = $grafo->destino($id, $this->evaluarCondicion($datos, $vars) ? 'si' : 'no');
                    break;

                case TiposNodo::HORARIO:
                    $siguiente = $grafo->destino($id, $this->hayTurno($ahora) ? 'abierto' : 'cerrado');
                    break;

                case TiposNodo::ESPERA:
                    $segundos = TiposNodo::segundosDeEspera($datos);
                    if ($segundos > TiposNodo::ESPERA_EN_LINEA) {
                        $mensajes[] = ['tipo' => 'espera', 'segundos' => $segundos];
                        $estado['nodo'] = $id;
                        $estado['estado'] = 'esperando';
                        $estado['reanudar_en'] = $segundos;

                        return ['mensajes' => $mensajes, 'estado' => $estado];
                    }
                    if ($segundos > 0) {
                        $mensajes[] = ['tipo' => 'pausa', 'segundos' => $segundos];
                    }
                    $siguiente = $grafo->destino($id, TiposNodo::SALIDA_SIGUIENTE);
                    break;

                case TiposNodo::BOTONES:
                case TiposNodo::LISTA:
                case TiposNodo::PREGUNTA:
                case TiposNodo::FORMULARIO:
                    array_push($mensajes, ...$this->pausa($datos));
                    $mensajes[] = $this->mensajeDeEspera($nodo, $vars);
                    $estado['nodo'] = $id;
                    $estado['intentos'] = 0;
                    $estado['estado'] = 'activa';
                    $limite = TiposNodo::segundosSinRespuesta($datos);
                    $estado['reanudar_en'] = $limite > 0 ? $limite : null;

                    return ['mensajes' => $mensajes, 'estado' => $estado];

                case TiposNodo::TEMPERATURA:
                    $nivel = (string) ($datos['valor'] ?? '');
                    if (in_array($nivel, TiposNodo::TEMPERATURAS, true)) {
                        $mensajes[] = ['tipo' => 'temperatura', 'valor' => $nivel];
                    }
                    $siguiente = $grafo->destino($id, TiposNodo::SALIDA_SIGUIENTE);
                    break;

                case TiposNodo::DIVIDIR:
                    $porcentaje = min(99, max(1, (int) ($datos['porcentaje_a'] ?? 50)));
                    $siguiente = $grafo->destino($id, random_int(1, 100) <= $porcentaje ? 'a' : 'b');
                    break;

                case TiposNodo::ASESOR:
                    $mensajes[] = Asignacion::pedido($datos);
                    array_push($mensajes, ...$this->mensajeFinal($datos, $vars + ['asesor' => self::MARCA_ASESOR]));

                    return $this->cerrar($estado, $mensajes, 'derivada');

                case TiposNodo::FIN:
                    array_push($mensajes, ...$this->mensajeFinal($datos, $vars));

                    return $this->terminar($estado, $mensajes);

                default:
                    return $this->terminar($estado, $mensajes);
            }

            $id = $siguiente;
        }

        return $this->terminar($estado, $mensajes);
    }

    // ───────────────────────── respuestas del cliente ─────────────────────────

    private function responderOpciones(
        Grafo $grafo,
        array $nodo,
        string $clave,
        array $estado,
        string $texto,
        ?string $respuestaId,
        DateTimeImmutable $ahora
    ): array {
        $opciones = array_values(array_filter((array) ($nodo['datos'][$clave] ?? []), 'is_array'));
        $elegida = $this->opcionElegida($nodo['id'], $opciones, $texto, $respuestaId);

        if ($elegida !== null) {
            $titulo = (string) ($elegida['titulo'] ?? '');
            $estado['variables']['ultima_opcion'] = $titulo;
            $estado['variables'][self::VARIABLE_RUTA] = $this->agregarARuta((string) ($estado['variables'][self::VARIABLE_RUTA] ?? ''), $titulo);
            $estado['intentos'] = 0;

            return $this->recorrer($grafo, $grafo->destino($nodo['id'], (string) $elegida['id']), $estado, [], $ahora);
        }

        // No eligió ninguna opción de la lista.
        $otro = $grafo->destino($nodo['id'], TiposNodo::SALIDA_OTRO);
        if ($otro !== null) {
            $estado['intentos'] = 0;

            return $this->recorrer($grafo, $otro, $estado, [], $ahora);
        }

        return $this->reintentar($grafo, $nodo, $estado, 'Por favor elige una de las opciones 👇');
    }

    private function responderPregunta(Grafo $grafo, array $nodo, array $estado, string $texto, DateTimeImmutable $ahora): array
    {
        $datos = (array) ($nodo['datos'] ?? []);

        if (! $this->respuestaValida((string) ($datos['validacion'] ?? 'texto'), $texto)) {
            $error = trim(Texto::plantilla((string) ($datos['error'] ?? ''), $estado['variables']));

            return $this->reintentar($grafo, $nodo, $estado, $error !== '' ? $error : 'No entendí tu respuesta. ¿Puedes intentarlo de nuevo? 🙏', false);
        }

        $variable = Texto::nombreVariable((string) ($datos['variable'] ?? ''));
        if ($variable !== '') {
            $estado['variables'][$variable] = $texto;
        }
        $estado['intentos'] = 0;

        return $this->recorrer($grafo, $grafo->destino($nodo['id'], TiposNodo::SALIDA_SIGUIENTE), $estado, [], $ahora);
    }

    private function responderFormulario(Grafo $grafo, array $nodo, array $estado, string $texto, DateTimeImmutable $ahora): array
    {
        if (trim($texto) === '') {
            return $this->reintentar($grafo, $nodo, $estado, 'Necesito esos datos para seguir. ¿Los puedes escribir en un solo mensaje?', false);
        }

        $datos = (array) ($nodo['datos'] ?? []);
        $variable = Texto::nombreVariable((string) ($datos['variable'] ?? 'formulario'));
        if ($variable !== '') {
            $estado['variables'][$variable] = $texto;
        }
        foreach ($this->camposFormulario($datos) as $i => $campo) {
            $nombre = Texto::nombreVariable((string) ($campo['variable'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $estado['variables'][$nombre] = $this->valorCampoFormulario($campo, $texto, $i);
        }
        $estado['intentos'] = 0;

        return $this->recorrer($grafo, $grafo->destino($nodo['id'], TiposNodo::SALIDA_SIGUIENTE), $estado, [], $ahora);
    }

    /** @return list<array{etiqueta?:string, variable?:string}> */
    private function camposFormulario(array $datos): array
    {
        $campos = [];
        foreach ((array) ($datos['campos'] ?? []) as $campo) {
            if (is_array($campo)) {
                $campos[] = $campo;
            }
        }

        return array_slice($campos, 0, TiposNodo::MAX_CAMPOS_FORMULARIO);
    }

    /** @param array{etiqueta?:string, variable?:string} $campo */
    private function valorCampoFormulario(array $campo, string $texto, int $indice): string
    {
        $etiqueta = trim((string) ($campo['etiqueta'] ?? ''));
        if ($etiqueta !== '' && preg_match('/^' . preg_quote($etiqueta, '/') . '\s*[:\-]\s*(.+)$/im', $texto, $m)) {
            return trim($m[1]);
        }

        $lineas = preg_split('/\R+/', trim($texto)) ?: [];
        $lineas = array_values(array_filter(array_map('trim', $lineas)));

        return $lineas[$indice] ?? $texto;
    }

    /** Repite el paso; tras varios intentos fallidos pasa la conversación a un asesor. */
    private function reintentar(Grafo $grafo, array $nodo, array $estado, string $aviso, bool $repetirPaso = true): array
    {
        $estado['intentos']++;
        $mensajes = [['tipo' => 'texto', 'texto' => $aviso]];

        if ($estado['intentos'] >= $this->maxIntentos($grafo)) {
            return $this->derivar($grafo, $estado, $mensajes);
        }
        if ($repetirPaso) {
            $mensajes[] = $this->mensajeDeEspera($nodo, $estado['variables']);
        }
        $limite = TiposNodo::segundosSinRespuesta((array) ($nodo['datos'] ?? []));
        $estado['reanudar_en'] = $limite > 0 ? $limite : null;

        return ['mensajes' => $mensajes, 'estado' => $estado];
    }

    private function derivar(Grafo $grafo, array $estado, array $mensajes): array
    {
        $datos = (array) ($grafo->inicio()['datos'] ?? []);
        $texto = trim(Texto::plantilla((string) ($datos['mensaje_asesor'] ?? ''), $estado['variables'] + ['asesor' => self::MARCA_ASESOR]));
        $mensajes[] = Asignacion::pedido([]);
        $mensajes[] = ['tipo' => 'texto', 'texto' => $texto !== '' ? $texto : self::MENSAJE_ASESOR];

        return $this->cerrar($estado, $mensajes, 'derivada');
    }

    /** Intentos fallidos seguidos antes de pasar al cliente con una persona (configurable en el inicio, 1 a 5). */
    private function maxIntentos(Grafo $grafo): int
    {
        $datos = (array) ($grafo->inicio()['datos'] ?? []);
        $valor = (int) ($datos['max_intentos'] ?? self::MAX_INTENTOS);

        return $valor >= 1 ? min($valor, TiposNodo::MAX_INTENTOS_PERMITIDOS) : self::MAX_INTENTOS;
    }

    private function agregarARuta(string $ruta, string $titulo): string
    {
        if ($titulo === '') {
            return $ruta;
        }
        $pasos = $ruta === '' ? [] : explode(self::SEPARADOR_RUTA, $ruta);
        $pasos[] = $titulo;

        return implode(self::SEPARADOR_RUTA, array_slice($pasos, -self::MAX_RUTA));
    }

    private function pideAsesor(Grafo $grafo, string $texto): bool
    {
        $datos = (array) ($grafo->inicio()['datos'] ?? []);
        $palabras = $datos['palabras_asesor'] ?? self::PALABRAS_ASESOR;

        foreach (is_array($palabras) ? $palabras : [] as $palabra) {
            if (is_string($palabra) && Texto::normalizar($texto) === Texto::normalizar($palabra)) {
                return true;
            }
        }

        return false;
    }

    private function opcionElegida(string $idNodo, array $opciones, string $texto, ?string $respuestaId): ?array
    {
        if ($respuestaId !== null && str_contains($respuestaId, ':')) {
            [$nodoDelBoton, $idOpcion] = explode(':', $respuestaId, 2);
            if ($nodoDelBoton === $idNodo) {
                foreach ($opciones as $opcion) {
                    if (($opcion['id'] ?? null) === $idOpcion) {
                        return $opcion;
                    }
                }
            }
        }

        $normal = Texto::normalizar($texto);
        if ($normal === '') {
            return null;
        }
        foreach ($opciones as $i => $opcion) {
            if ($normal === Texto::normalizar((string) ($opcion['titulo'] ?? '')) || $normal === (string) ($i + 1)) {
                return $opcion;
            }
        }

        return null;
    }

    private function respuestaValida(string $tipo, string $texto): bool
    {
        if ($texto === '') {
            return false;
        }

        return match ($tipo) {
            'numero' => (bool) preg_match('/^-?\d+([.,]\d+)?$/', $texto),
            'email' => filter_var($texto, FILTER_VALIDATE_EMAIL) !== false,
            'telefono' => (bool) preg_match('/^\d{7,15}$/', preg_replace('/[\s\-\(\)\+]/', '', $texto) ?? ''),
            default => true,
        };
    }

    // ───────────────────────── mensajes ─────────────────────────

    /** @return list<array> */
    private function pausa(array $datos): array
    {
        $segundos = min(max(0, (int) ($datos['pausa'] ?? 0)), TiposNodo::MAX_PAUSA);

        return $segundos > 0 ? [['tipo' => 'pausa', 'segundos' => $segundos]] : [];
    }

    /** @return list<array> */
    private function mensajeFinal(array $datos, array $vars): array
    {
        $texto = trim(Texto::plantilla((string) ($datos['texto'] ?? ''), $vars));

        return $texto === '' ? [] : [...$this->pausa($datos), ['tipo' => 'texto', 'texto' => $texto]];
    }

    /** @return list<array> */
    private function mensajesDeTexto(array $datos, array $vars): array
    {
        $texto = trim(Texto::plantilla((string) ($datos['texto'] ?? ''), $vars));
        $media = (string) ($datos['media'] ?? 'ninguno');
        $url = trim((string) ($datos['url'] ?? ''));
        $mensajes = [];

        if ($url !== '' && $media === 'imagen') {
            $mensajes[] = ['tipo' => 'imagen', 'url' => $url, 'texto' => $texto];
        } elseif ($url !== '' && $media === 'video') {
            $mensajes[] = ['tipo' => 'video', 'url' => $url, 'texto' => $texto];
        } elseif ($url !== '' && in_array($media, ['documento', 'audio'], true)) {
            $mensajes[] = $media === 'audio'
                ? ['tipo' => 'audio', 'url' => $url]
                : ['tipo' => 'documento', 'url' => $url, 'nombre' => (string) ($datos['nombre_archivo'] ?? 'documento')];
            if ($texto !== '') {
                $mensajes[] = ['tipo' => 'texto', 'texto' => $texto];
            }
        } elseif ($texto !== '') {
            $mensajes[] = ['tipo' => 'texto', 'texto' => $texto];
        }

        return $mensajes;
    }

    private function mensajeDeEspera(array $nodo, array $vars): array
    {
        $datos = (array) ($nodo['datos'] ?? []);
        $texto = Texto::plantilla((string) ($datos['texto'] ?? ''), $vars);

        if ($nodo['tipo'] === TiposNodo::BOTONES) {
            $botones = [];
            foreach (array_slice((array) ($datos['botones'] ?? []), 0, TiposNodo::MAX_BOTONES) as $b) {
                $botones[] = ['id' => $nodo['id'] . ':' . $b['id'], 'titulo' => (string) ($b['titulo'] ?? '')];
            }

            return ['tipo' => 'botones', 'texto' => $texto, 'botones' => $botones];
        }

        if ($nodo['tipo'] === TiposNodo::LISTA) {
            $opciones = [];
            foreach (array_slice((array) ($datos['opciones'] ?? []), 0, TiposNodo::MAX_OPCIONES) as $o) {
                $opciones[] = [
                    'id' => $nodo['id'] . ':' . $o['id'],
                    'titulo' => (string) ($o['titulo'] ?? ''),
                    'descripcion' => (string) ($o['descripcion'] ?? ''),
                ];
            }

            return ['tipo' => 'lista', 'texto' => $texto, 'boton' => (string) ($datos['boton'] ?? 'Ver opciones'), 'opciones' => $opciones];
        }

        if ($nodo['tipo'] === TiposNodo::FORMULARIO) {
            return ['tipo' => 'texto', 'texto' => $this->textoFormulario($datos, $vars)];
        }

        return ['tipo' => 'texto', 'texto' => $texto];
    }

    private function textoFormulario(array $datos, array $vars): string
    {
        $intro = trim(Texto::plantilla((string) ($datos['texto'] ?? ''), $vars));
        if ($intro === '') {
            $intro = 'Para atenderte, respondé todo en un solo mensaje:';
        }
        $lineas = [$intro, ''];
        foreach ($this->camposFormulario($datos) as $campo) {
            $etiqueta = trim((string) ($campo['etiqueta'] ?? ''));
            if ($etiqueta !== '') {
                $lineas[] = '• ' . $etiqueta;
            }
        }

        return trim(implode("\n", $lineas));
    }

    // ───────────────────────── condiciones ─────────────────────────

    private function evaluarCondicion(array $datos, array $vars): bool
    {
        $variable = Texto::nombreVariable((string) ($datos['variable'] ?? ''));
        $actual = trim((string) ($vars[$variable] ?? ''));
        $esperado = trim(Texto::plantilla((string) ($datos['valor'] ?? ''), $vars));
        $a = Texto::normalizar($actual);
        $e = Texto::normalizar($esperado);
        $numero = static fn (string $v): ?float => is_numeric(str_replace(',', '.', $v)) ? (float) str_replace(',', '.', $v) : null;

        return match ($datos['operador'] ?? 'igual') {
            'igual' => $a === $e,
            'distinto' => $a !== $e,
            'contiene' => $e !== '' && Texto::contienePalabra($actual, $esperado),
            'empieza' => $e !== '' && str_starts_with($a, $e),
            'vacio' => $actual === '',
            'no_vacio' => $actual !== '',
            'mayor' => $numero($actual) !== null && $numero($esperado) !== null && $numero($actual) > $numero($esperado),
            'menor' => $numero($actual) !== null && $numero($esperado) !== null && $numero($actual) < $numero($esperado),
            default => false,
        };
    }

    // ───────────────────────── estado ─────────────────────────

    private function estadoInicial(array $variables, DateTimeImmutable $ahora): array
    {
        $normalizadas = [];
        foreach ($variables as $clave => $valor) {
            if (is_scalar($valor)) {
                $normalizadas[strtolower((string) $clave)] = (string) $valor;
            }
        }

        return [
            'estado' => 'activa',
            'nodo' => null,
            'variables' => array_merge($normalizadas, $this->variablesDeReloj($ahora)),
            'intentos' => 0,
            'reanudar_en' => null,
        ];
    }

    private function variablesDeReloj(DateTimeImmutable $ahora): array
    {
        return ['fecha' => $ahora->format('d/m/Y'), 'hora' => $ahora->format('H:i')];
    }

    /** Sin grupos tipo turno se considera que siempre hay atención, para no responder «estamos cerrados» por error. */
    private function hayTurno(DateTimeImmutable $ahora): bool
    {
        $turnos = $this->turnos?->listar() ?? [];
        if ($turnos === []) {
            return true;
        }
        foreach ($turnos as $turno) {
            if (Horario::dentro($turno, $ahora)) {
                return true;
            }
        }

        return false;
    }

    private function terminar(array $estado, array $mensajes): array
    {
        return $this->cerrar($estado, $mensajes, 'terminada');
    }

    private function cerrar(array $estado, array $mensajes, string $final): array
    {
        $estado['estado'] = $final;
        $estado['nodo'] = null;
        $estado['reanudar_en'] = null;

        return ['mensajes' => $mensajes, 'estado' => $estado];
    }
}
