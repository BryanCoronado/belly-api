<?php

namespace Belly\AgenteIa\UseCases;

use Belly\AgenteIa\Domain\Indicaciones;
use Belly\AgenteIa\Domain\ModeloLenguaje;
use Belly\AgenteIa\Services\AgenteRepositorio;
use Belly\AgenteIa\Services\ClienteOpenAI;
use Belly\AgenteIa\Services\RespondedorIa;
use DomainException;

/** Configuración de los asistentes de IA de la empresa: la conexión con OpenAI, cada agente y lo que sabe. */
class AgenteIaUseCase
{
    private const MAX_AGENTES = 10;
    private const MAX_CONOCIMIENTOS = 60;
    private const MAX_TEXTO_CONOCIMIENTO = 60000;
    private const MAX_PALABRAS = 30;
    private const MAX_INSTRUCCIONES = 8000;
    private const MAX_MENSAJE_PRUEBA = 1000;
    /** Intentos por minuto y empresa: cada prueba cuesta dinero en OpenAI y cada conexión llama a OpenAI. */
    private const PRUEBAS_POR_MINUTO = 20;
    private const CONEXIONES_POR_MINUTO = 5;

    private AgenteRepositorio $repositorio;
    private ModeloLenguaje $modelo;

    public function __construct(?AgenteRepositorio $repositorio = null, ?ModeloLenguaje $modelo = null)
    {
        $this->repositorio = $repositorio ?? new AgenteRepositorio();
        $this->modelo = $modelo ?? new ClienteOpenAI();
    }

    /** Todo lo que necesita la pantalla. Nunca devuelve la llave, solo una pista. @return array<string, mixed> */
    public function estado(): array
    {
        $idEmpresa = $this->idEmpresa();
        $hoy = $this->repositorio->atendidosHoy($idEmpresa);
        $agentes = array_map(fn (array $a): array => $a + [
            'conocimientos' => count($this->repositorio->conocimiento($a['id'])),
            'atendidos_hoy' => $hoy[$a['id']] ?? 0,
        ], $this->repositorio->agentes($idEmpresa));

        return [
            'conexion' => $this->conexionVisible($idEmpresa),
            'agentes' => $agentes,
            'modelos' => Indicaciones::MODELOS,
            'tonos' => array_keys(Indicaciones::TONOS),
        ];
    }

    /** @return array{conectada: bool, pista: ?string, verificada_at: ?string} */
    public function conectar(mixed $apiKey): array
    {
        $idEmpresa = $this->idEmpresa();
        $llave = is_string($apiKey) ? (preg_replace('/\s+/', '', $apiKey) ?? '') : '';
        if (! preg_match('/^sk-[A-Za-z0-9_\-]{20,300}$/', $llave)) {
            throw new DomainException('Esa no parece una llave de OpenAI. Debe empezar con «sk-» y no llevar espacios.');
        }
        $this->frenar('ia-conectar-' . $idEmpresa, self::CONEXIONES_POR_MINUTO, 'Probaste muchas llaves seguidas. Espera un minuto e inténtalo de nuevo.');

        $this->modelo->verificar($llave);
        $this->repositorio->guardarConexion($idEmpresa, $llave, 'sk-…' . substr($llave, -4));

        return $this->conexionVisible($idEmpresa);
    }

    public function desconectar(): void
    {
        $this->repositorio->eliminarConexion($this->idEmpresa());
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $agente = $this->buscar($this->idEmpresa(), $id);

        return $agente + ['conocimiento' => $this->repositorio->conocimiento($id)];
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function crear(array $datos): array
    {
        $idEmpresa = $this->idEmpresa();
        if (count($this->repositorio->agentes($idEmpresa)) >= self::MAX_AGENTES) {
            throw new DomainException('Llegaste al máximo de ' . self::MAX_AGENTES . ' asistentes.');
        }

        $campos = $this->campos($datos, null);
        $this->nombreLibre($idEmpresa, $campos['nombre'], null);

        return $this->obtener($this->repositorio->insertarAgente($idEmpresa, $campos));
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function actualizar(int $id, array $datos): array
    {
        $idEmpresa = $this->idEmpresa();
        $actual = $this->buscar($idEmpresa, $id);
        $campos = $this->campos($datos, $actual);
        $this->nombreLibre($idEmpresa, $campos['nombre'], $id);
        $this->repositorio->actualizarAgente($idEmpresa, $id, $campos);

        return $this->obtener($id);
    }

    /** Los chats que atendía siguen con el asistente como dueño hasta que alguien los tome; el reparto ya no lo elegirá. */
    public function eliminar(int $id): void
    {
        $idEmpresa = $this->idEmpresa();
        $this->buscar($idEmpresa, $id);
        $this->repositorio->eliminarAgente($idEmpresa, $id);
    }

    /** @param array<string, mixed> $datos @return list<array<string, mixed>> */
    public function agregarConocimiento(int $idAgente, array $datos): array
    {
        $this->buscar($this->idEmpresa(), $idAgente);
        if (count($this->repositorio->conocimiento($idAgente)) >= self::MAX_CONOCIMIENTOS) {
            throw new DomainException('Este asistente ya tiene ' . self::MAX_CONOCIMIENTOS . ' fichas de información. Junta algunas en una sola.');
        }
        [$titulo, $contenido] = $this->ficha($datos);
        $this->tituloLibre($idAgente, $titulo, null);
        $this->repositorio->insertarConocimiento($idAgente, $titulo, $contenido);

        return $this->repositorio->conocimiento($idAgente);
    }

    /** @param array<string, mixed> $datos @return list<array<string, mixed>> */
    public function actualizarConocimiento(int $idAgente, int $id, array $datos): array
    {
        $this->buscar($this->idEmpresa(), $idAgente);
        [$titulo, $contenido] = $this->ficha($datos);
        if (! in_array($id, array_column($this->repositorio->conocimiento($idAgente), 'id'), true)) {
            throw new DomainException('No encontramos esa ficha.');
        }
        $this->tituloLibre($idAgente, $titulo, $id);
        $this->repositorio->actualizarConocimiento($idAgente, $id, $titulo, $contenido);

        return $this->repositorio->conocimiento($idAgente);
    }

    /** @return list<array<string, mixed>> */
    public function eliminarConocimiento(int $idAgente, int $id): array
    {
        $this->buscar($this->idEmpresa(), $idAgente);
        $this->repositorio->eliminarConocimiento($idAgente, $id);

        return $this->repositorio->conocimiento($idAgente);
    }

    /**
     * Conversación de prueba con el asistente, sin tocar WhatsApp.
     *
     * @param mixed $charla list<{rol: cliente|asistente, texto}>
     * @return array{respuesta: ?string, traspaso: ?string}
     */
    public function probar(int $idAgente, mixed $charla): array
    {
        $idEmpresa = $this->idEmpresa();
        $agente = $this->buscar($idEmpresa, $idAgente);

        $lineas = [];
        foreach (is_array($charla) ? $charla : [] as $linea) {
            $texto = is_array($linea) ? self::limpiar($linea['texto'] ?? '') : '';
            if ($texto !== '') {
                $lineas[] = ['rol' => ($linea['rol'] ?? '') === 'asistente' ? 'asistente' : 'cliente', 'texto' => mb_substr($texto, 0, self::MAX_MENSAJE_PRUEBA)];
            }
        }
        $lineas = array_slice($lineas, -Indicaciones::MAX_HISTORIAL);
        $ultimo = end($lineas);
        if ($ultimo === false || $ultimo['rol'] !== 'cliente') {
            throw new DomainException('Escribe un mensaje como si fueras el cliente.');
        }

        if (Indicaciones::pideTraspaso($ultimo['texto'], $agente['palabras_traspaso'])) {
            return ['respuesta' => $agente['mensaje_traspaso'] ?: null, 'traspaso' => 'El cliente escribió una palabra para hablar con una persona'];
        }

        $this->frenar('ia-probar-' . $idEmpresa, self::PRUEBAS_POR_MINUTO, 'Vas muy rápido con las pruebas. Espera un minuto y sigue.');
        $respuesta = (new RespondedorIa($this->repositorio, $this->modelo))->responder($idEmpresa, $agente, $lineas);
        $texto = $respuesta['texto'];
        if ($respuesta['traspaso'] !== null && $texto === null) {
            $texto = $agente['mensaje_traspaso'] ?: null;
        }

        return ['respuesta' => $texto, 'traspaso' => $respuesta['traspaso']];
    }

    // ───────────── para el reparto (sin sesión: reciben la empresa) ─────────────

    /** @return list<array{id: int, nombre: string, activo: bool, listo: bool}> */
    public function resumenes(int $idEmpresa): array
    {
        $conectada = $this->repositorio->conexion($idEmpresa) !== null;

        return array_map(static fn (array $a): array => [
            'id' => $a['id'],
            'nombre' => $a['nombre'],
            'activo' => $a['activo'],
            'listo' => $a['activo'] && $conectada,
        ], $this->repositorio->agentes($idEmpresa));
    }

    public function disponible(int $idEmpresa, int $idAgente): bool
    {
        $agente = $this->repositorio->agente($idEmpresa, $idAgente);

        return $agente !== null && $agente['activo'] && $this->repositorio->conexion($idEmpresa) !== null;
    }

    // ───────────── internos ─────────────

    /** @return array{conectada: bool, pista: ?string, verificada_at: ?string} */
    private function conexionVisible(int $idEmpresa): array
    {
        $conexion = $this->repositorio->conexion($idEmpresa);

        return [
            'conectada' => $conexion !== null,
            'pista' => $conexion['pista'] ?? null,
            'verificada_at' => $conexion['verificada_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $d
     * @param array<string, mixed>|null $actual
     * @return array<string, mixed>
     */
    private function campos(array $d, ?array $actual): array
    {
        $d += $actual ?? [];
        $nombre = preg_replace('/\s+/u', ' ', self::limpiar($d['nombre'] ?? '')) ?? '';
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            throw new DomainException('Ponle un nombre al asistente (entre 2 y 60 letras).');
        }

        $modelo = (string) ($d['modelo'] ?? Indicaciones::MODELOS[0]);
        if (! in_array($modelo, Indicaciones::MODELOS, true)) {
            throw new DomainException('Elige uno de los modelos de la lista.');
        }

        $tono = (string) ($d['tono'] ?? 'amable');
        if (! isset(Indicaciones::TONOS[$tono])) {
            $tono = 'amable';
        }

        $palabras = [];
        foreach (is_array($d['palabras_traspaso'] ?? null) ? $d['palabras_traspaso'] : [] as $palabra) {
            $palabra = mb_substr(trim((string) $palabra), 0, 40);
            if ($palabra !== '') {
                $palabras[mb_strtolower($palabra)] ??= $palabra;
            }
        }
        if (count($palabras) > self::MAX_PALABRAS) {
            throw new DomainException('Usa como máximo ' . self::MAX_PALABRAS . ' palabras para pasar con una persona.');
        }

        $instrucciones = self::limpiar($d['instrucciones'] ?? '');
        if (mb_strlen($instrucciones) > self::MAX_INSTRUCCIONES) {
            throw new DomainException('Las indicaciones son muy largas (máximo ' . self::MAX_INSTRUCCIONES . ' letras). Pasa la información a «Lo que sabe».');
        }
        $mensaje = self::limpiar($d['mensaje_traspaso'] ?? '');
        if (mb_strlen($mensaje) > 300) {
            throw new DomainException('El mensaje al pasar el chat es muy largo (máximo 300 letras).');
        }
        if (isset($d['max_respuestas']) && (! is_numeric($d['max_respuestas']) || (int) $d['max_respuestas'] < 1 || (int) $d['max_respuestas'] > 100)) {
            throw new DomainException('El número de respuestas antes de pasar a una persona debe estar entre 1 y 100.');
        }

        return [
            'nombre' => $nombre,
            'activo' => (bool) ($d['activo'] ?? true),
            'modelo' => $modelo,
            'instrucciones' => $instrucciones === '' ? null : $instrucciones,
            'tono' => $tono,
            'usar_catalogo' => (bool) ($d['usar_catalogo'] ?? true),
            'max_respuestas' => max(1, min(100, (int) ($d['max_respuestas'] ?? 15))),
            'palabras_traspaso' => array_values($palabras),
            'mensaje_traspaso' => $mensaje === '' ? null : $mensaje,
            'temperatura' => round(max(0, min(1, (float) ($d['temperatura'] ?? 0.4))), 2),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function ficha(array $datos): array
    {
        $titulo = preg_replace('/\s+/u', ' ', self::limpiar($datos['titulo'] ?? '')) ?? '';
        $contenido = self::limpiar($datos['contenido'] ?? '');
        if ($titulo === '' || $contenido === '') {
            throw new DomainException('Ponle un título y escribe la información.');
        }
        if (mb_strlen($titulo) > 120) {
            throw new DomainException('El título es muy largo (máximo 120 letras).');
        }
        if (mb_strlen($contenido) > self::MAX_TEXTO_CONOCIMIENTO) {
            throw new DomainException('Esta ficha es muy larga (máximo ' . self::MAX_TEXTO_CONOCIMIENTO . ' letras). Divídela en varias.');
        }

        return [$titulo, $contenido];
    }

    /** Texto sin caracteres invisibles de control (llegan al copiar de PDFs o Word) y sin espacios a los lados. */
    private static function limpiar(mixed $valor): string
    {
        if (! is_scalar($valor)) {
            return '';
        }
        $texto = (string) $valor;
        if (! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }

        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $texto)) ?? '');
    }

    private function nombreLibre(int $idEmpresa, string $nombre, ?int $idPropio): void
    {
        foreach ($this->repositorio->agentes($idEmpresa) as $agente) {
            if ($agente['id'] !== $idPropio && mb_strtolower($agente['nombre']) === mb_strtolower($nombre)) {
                throw new DomainException('Ya tienes un asistente llamado «' . $agente['nombre'] . '». Usa otro nombre.');
            }
        }
    }

    private function tituloLibre(int $idAgente, string $titulo, ?int $idPropio): void
    {
        foreach ($this->repositorio->conocimiento($idAgente) as $ficha) {
            if ($ficha['id'] !== $idPropio && mb_strtolower($ficha['titulo']) === mb_strtolower($titulo)) {
                throw new DomainException('Ya hay una ficha llamada «' . $ficha['titulo'] . '». Edítala o usa otro título.');
            }
        }
    }

    private function frenar(string $clave, int $porMinuto, string $mensaje): void
    {
        if (! service('throttler')->check($clave, $porMinuto, MINUTE)) {
            throw new DomainException($mensaje);
        }
    }

    /** @return array<string, mixed> */
    private function buscar(int $idEmpresa, int $id): array
    {
        $agente = $this->repositorio->agente($idEmpresa, $id);
        if ($agente === null) {
            throw new DomainException('No encontramos ese asistente.');
        }

        return $agente;
    }

    private function idEmpresa(): int
    {
        helper('vip2system');
        $id = id_empresa_sesion();
        if ($id === null) {
            throw new DomainException('No se pudo identificar tu empresa.');
        }

        return $id;
    }
}
