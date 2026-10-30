<?php

namespace Belly\Flujos\UseCases;

use Belly\Flujos\Domain\Asignacion;
use Belly\Flujos\Domain\CatalogoAsesores;
use Belly\Flujos\Domain\Disparador;
use Belly\Flujos\Domain\Grafo;
use Belly\Flujos\Domain\MotorFlujo;
use Belly\Flujos\Domain\TiposNodo;
use Belly\Flujos\Domain\ValidadorFlujo;
use Belly\Flujos\Entities\FlujoEntity;
use Belly\Flujos\Models\FlujoModel;
use Belly\Flujos\Models\FlujoSesionModel;
use Belly\Flujos\Services\AsesoresCrm;
use Belly\Flujos\Services\TurnosCrm;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/** Administración de flujos: crear, editar, activar, duplicar, eliminar y probar. */
class FlujoUseCase
{
    private FlujoModel $flujos;
    private FlujoSesionModel $sesiones;
    private ValidadorFlujo $validador;
    private MotorFlujo $motor;
    private CatalogoAsesores $asesores;

    public function __construct(
        ?FlujoModel $flujos = null,
        ?FlujoSesionModel $sesiones = null,
        ?ValidadorFlujo $validador = null,
        ?MotorFlujo $motor = null,
        ?CatalogoAsesores $asesores = null
    ) {
        $this->flujos = $flujos ?? new FlujoModel();
        $this->sesiones = $sesiones ?? new FlujoSesionModel();
        $this->validador = $validador ?? new ValidadorFlujo();
        $this->motor = $motor ?? new MotorFlujo(new TurnosCrm());
        $this->asesores = $asesores ?? new AsesoresCrm();
    }

    /** Personas que pueden recibir chats: para elegirlas en el reparto del flujo. */
    public function asesores(): array
    {
        return $this->asesores->listar();
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $activas = [];
        $totales = [];
        $filas = $this->sesiones
            ->select('id_flujo, estado, COUNT(*) AS total')
            ->groupBy(['id_flujo', 'estado'])
            ->asArray()
            ->findAll();
        foreach ($filas as $fila) {
            $idFlujo = (int) $fila['id_flujo'];
            $total = (int) $fila['total'];
            $totales[$idFlujo]['total'] = ($totales[$idFlujo]['total'] ?? 0) + $total;
            $totales[$idFlujo][(string) $fila['estado']] = $total;
            if (in_array($fila['estado'], ['activa', 'esperando'], true)) {
                $activas[$idFlujo] = ($activas[$idFlujo] ?? 0) + $total;
            }
        }

        $lista = [];
        /** @var FlujoEntity $flujo */
        foreach ($this->flujos->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->findAll() as $flujo) {
            $lista[] = [
                'id' => (int) $flujo->id,
                'nombre' => $flujo->nombre,
                'descripcion' => $flujo->descripcion,
                'activo' => (bool) $flujo->activo,
                'pasos' => count((array) ($flujo->definicion['nodos'] ?? [])),
                'mensajes' => TiposNodo::cuentaMensajes((array) $flujo->definicion),
                'disparador' => $this->resumenDisparador((array) $flujo->definicion),
                'conversaciones_activas' => $activas[(int) $flujo->id] ?? 0,
                'estadisticas' => [
                    'total' => $totales[(int) $flujo->id]['total'] ?? 0,
                    'derivadas' => $totales[(int) $flujo->id]['derivada'] ?? 0,
                    'terminadas' => $totales[(int) $flujo->id]['terminada'] ?? 0,
                ],
                'actualizado' => $flujo->updated_at?->format('c') ?? $flujo->created_at?->format('c'),
            ];
        }

        return $lista;
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        return $this->aArray($this->buscar($id));
    }

    /** @param array{nombre?:mixed, descripcion?:mixed} $datos @return array<string, mixed> */
    public function crear(array $datos): array
    {
        $id = $this->flujos->insert([
            'id_empresa' => $this->idEmpresa(),
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'descripcion' => $this->descripcion($datos['descripcion'] ?? null),
            'activo' => 0,
            'definicion' => json_encode($this->definicionInicial(), JSON_UNESCAPED_UNICODE),
        ]);
        $this->exigirGuardado($id);

        return $this->obtener((int) $id);
    }

    /**
     * Guarda cambios. Si el flujo estaba activo y queda con errores, se pausa solo
     * para no dejar un bot a medias hablando con clientes.
     *
     * @return array<string, mixed>
     */
    public function actualizar(int $id, array $datos): array
    {
        $flujo = $this->buscar($id);
        $cambios = [];

        if (array_key_exists('nombre', $datos)) {
            $cambios['nombre'] = trim((string) $datos['nombre']);
        }
        if (array_key_exists('descripcion', $datos)) {
            $cambios['descripcion'] = $this->descripcion($datos['descripcion']);
        }

        $pausado = false;
        if (array_key_exists('definicion', $datos)) {
            $definicion = $this->limpiarDefinicion($datos['definicion']);
            $cambios['definicion'] = json_encode($definicion, JSON_UNESCAPED_UNICODE);
            if ($flujo->activo && $this->validador->validar($definicion)['errores'] !== []) {
                $cambios['activo'] = 0;
                $pausado = true;
            }
        }

        if ($cambios !== []) {
            $this->exigirGuardado($this->flujos->update($id, $cambios));
        }

        return $this->obtener($id) + ['pausado_por_errores' => $pausado];
    }

    /** @return array<string, mixed> */
    public function cambiarEstado(int $id, bool $activo, bool $forzar = false): array
    {
        $flujo = $this->buscar($id);

        if ($activo) {
            $errores = $this->validador->validar($flujo->definicion)['errores'];
            if ($errores !== []) {
                throw new DomainException('Antes de activarlo corrige esto: ' . $errores[0]['mensaje']);
            }
            $mensajes = TiposNodo::cuentaMensajes((array) $flujo->definicion);
            if (empresa()->modoAhorro() && $mensajes > TiposNodo::TOPE_AHORRO && ! $forzar) {
                throw new DomainException(
                    "Este flujo manda unos {$mensajes} mensajes. En modo ahorro conviene 1 o 2. Confirmá si igual lo querés prender."
                );
            }
        } else {
            $this->cerrarSesiones($id);
        }

        $this->exigirGuardado($this->flujos->update($id, ['activo' => $activo ? 1 : 0]));

        return $this->obtener($id);
    }

    /** @return array<string, mixed> */
    public function duplicar(int $id): array
    {
        $original = $this->buscar($id);
        $nombre = mb_substr($original->nombre, 0, 110) . ' (copia)';

        $nuevoId = $this->flujos->insert([
            'id_empresa' => $this->idEmpresa(),
            'nombre' => $nombre,
            'descripcion' => $original->descripcion,
            'activo' => 0,
            'definicion' => json_encode($original->definicion, JSON_UNESCAPED_UNICODE),
        ]);
        $this->exigirGuardado($nuevoId);

        return $this->obtener((int) $nuevoId);
    }

    public function eliminar(int $id): void
    {
        $this->buscar($id);
        $this->cerrarSesiones($id);
        $this->flujos->delete($id);
    }

    /** @return array{errores:list<array>, avisos:list<array>} */
    public function validar(mixed $definicion): array
    {
        $definicion = $this->limpiarDefinicion($definicion);
        $resultado = $this->validador->validar($definicion);

        return $this->avisarSinTurnos($definicion, $this->avisarAsesoresInexistentes($definicion, $resultado));
    }

    /**
     * @param array{errores:list<array>, avisos:list<array>} $resultado
     * @return array{errores:list<array>, avisos:list<array>}
     */
    private function avisarSinTurnos(array $definicion, array $resultado): array
    {
        $pasos = array_filter($definicion['nodos'], static fn (array $n): bool => $n['tipo'] === TiposNodo::HORARIO);
        if ($pasos === [] || (new TurnosCrm())->listar() !== []) {
            return $resultado;
        }
        foreach ($pasos as $nodo) {
            $resultado['avisos'][] = [
                'nodo' => is_string($nodo['id']) ? $nodo['id'] : null,
                'mensaje' => 'Aún no tienes grupos tipo turno en Asesores → Grupos: este paso siempre irá por «En turno».',
            ];
        }

        return $resultado;
    }

    /**
     * Avisa si un paso «Pasar a un asesor» apunta a alguien que ya no está (baja o cambio de rol).
     * No es un error: en ese caso el bot deja el chat con quien ya lo tenía.
     *
     * @param array{errores:list<array>, avisos:list<array>} $resultado
     * @return array{errores:list<array>, avisos:list<array>}
     */
    private function avisarAsesoresInexistentes(array $definicion, array $resultado): array
    {
        $conAsesor = array_filter($definicion['nodos'], static fn (array $n): bool => $n['tipo'] === TiposNodo::ASESOR
            && (int) ($n['datos']['asesor_id'] ?? 0) > 0);
        if ($conAsesor === []) {
            return $resultado;
        }

        $vigentes = array_map('intval', array_column($this->asesores->listar(), 'id'));
        foreach ($conAsesor as $nodo) {
            if (! in_array((int) $nodo['datos']['asesor_id'], $vigentes, true)) {
                $resultado['avisos'][] = [
                    'nodo' => is_string($nodo['id']) ? $nodo['id'] : null,
                    'mensaje' => 'El asesor elegido ya no puede recibir chats. Elige otro o deja que siga con quien lo asignó Reparto de chats.',
                ];
            }
        }

        return $resultado;
    }

    /**
     * Prueba un flujo sin enviar nada. El editor conserva el estado entre pasos.
     *
     * @param array{definicion?:mixed, estado?:mixed, entrada?:mixed, nombre?:mixed} $datos
     * @return array{mensajes:list<array>, estado:array}
     */
    public function simular(array $datos): array
    {
        $definicion = $this->limpiarDefinicion($datos['definicion'] ?? null);
        $errores = $this->validador->validar($definicion)['errores'];
        if ($errores !== []) {
            throw new DomainException($errores[0]['mensaje']);
        }

        $ahora = new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
        $estado = $datos['estado'] ?? null;

        if (! is_array($estado) || ! isset($estado['estado'])) {
            $nombre = mb_substr(trim((string) ($datos['nombre'] ?? 'Cliente de prueba')), 0, 60);

            return $this->motor->iniciar($definicion, [
                'nombre' => $nombre,
                'nombre_completo' => $nombre,
                'telefono' => '51999999999',
                'ultimo_mensaje' => '',
            ], $ahora);
        }

        $entrada = is_array($datos['entrada'] ?? null) ? $datos['entrada'] : [];

        $estadoLimpio = [
            'estado' => (string) $estado['estado'],
            'nodo' => isset($estado['nodo']) ? (string) $estado['nodo'] : null,
            'variables' => is_array($estado['variables'] ?? null) ? $estado['variables'] : [],
            'intentos' => (int) ($estado['intentos'] ?? 0),
            'reanudar_en' => isset($estado['reanudar_en']) ? (int) $estado['reanudar_en'] : null,
        ];

        // El tiempo ya pasó (la espera terminó o el cliente no respondió): se retoma el flujo.
        if (! empty($entrada['reanudar'])) {
            return $this->motor->reanudar($definicion, $estadoLimpio, $ahora);
        }

        return $this->motor->continuar($definicion, $estadoLimpio, [
            'texto' => mb_substr((string) ($entrada['texto'] ?? ''), 0, 1000),
            'respuesta_id' => isset($entrada['respuesta_id']) ? (string) $entrada['respuesta_id'] : null,
        ], $ahora);
    }

    // ───────────────────────── internos ─────────────────────────

    private function buscar(int $id): FlujoEntity
    {
        $flujo = $this->flujos->find($id);
        if (! $flujo instanceof FlujoEntity) {
            throw new DomainException('No encontramos ese flujo.');
        }

        return $flujo;
    }

    /** @return array<string, mixed> */
    private function aArray(FlujoEntity $flujo): array
    {
        return [
            'id' => (int) $flujo->id,
            'nombre' => $flujo->nombre,
            'descripcion' => $flujo->descripcion,
            'activo' => (bool) $flujo->activo,
            'definicion' => $flujo->definicion,
            'actualizado' => $flujo->updated_at?->format('c') ?? $flujo->created_at?->format('c'),
        ];
    }

    private function cerrarSesiones(int $idFlujo): void
    {
        $this->sesiones->where('id_flujo', $idFlujo)->whereIn('estado', ['activa', 'esperando'])->set(['estado' => 'terminada', 'nodo_actual' => null, 'reanudar_at' => null])->update();
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

    private function descripcion(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : mb_substr($texto, 0, 255);
    }

    /** @param int|bool|string $resultado */
    private function exigirGuardado(mixed $resultado): void
    {
        if ($resultado === false || $resultado === 0 || $resultado === '' || $resultado === null) {
            $errores = $this->flujos->errors();
            throw new DomainException($errores !== [] ? (string) reset($errores) : 'No se pudo guardar el flujo.');
        }
    }

    /**
     * Solo deja pasar la estructura conocida (descarta cualquier campo extra del cliente).
     *
     * @return array{nodos:list<array>, conexiones:list<array>}
     */
    private function limpiarDefinicion(mixed $definicion): array
    {
        $nodos = [];
        $conexiones = [];

        foreach (is_array($definicion['nodos'] ?? null) ? $definicion['nodos'] : [] as $n) {
            if (! is_array($n)) {
                continue;
            }
            $datos = is_array($n['datos'] ?? null) ? $n['datos'] : [];
            $tipo = $n['tipo'] ?? null;
            if ($tipo === TiposNodo::INICIO) {
                unset($datos['reparto'], $datos['equipo'], $datos['horario_activo']);
            }
            if ($tipo === TiposNodo::HORARIO) {
                $datos = [];
            }
            if ($tipo === TiposNodo::ASESOR) {
                $datos = Asignacion::limpiarPaso($datos);
            }
            $nodos[] = [
                'id' => $n['id'] ?? null,
                'tipo' => $tipo,
                'x' => is_numeric($n['x'] ?? null) ? round((float) $n['x'], 1) : 0,
                'y' => is_numeric($n['y'] ?? null) ? round((float) $n['y'], 1) : 0,
                'datos' => $datos,
            ];
        }
        foreach (is_array($definicion['conexiones'] ?? null) ? $definicion['conexiones'] : [] as $c) {
            if (! is_array($c)) {
                continue;
            }
            $conexiones[] = [
                'id' => (string) ($c['id'] ?? ''),
                'desde' => $c['desde'] ?? null,
                'salida' => $c['salida'] ?? null,
                'hacia' => $c['hacia'] ?? null,
            ];
        }

        return ['nodos' => $nodos, 'conexiones' => $conexiones];
    }

    /** @return array{nodos:list<array>, conexiones:list<array>} */
    private function definicionInicial(): array
    {
        return [
            'nodos' => [
                ['id' => 'inicio', 'tipo' => TiposNodo::INICIO, 'x' => 0, 'y' => 120, 'datos' => [
                    'disparador' => Disparador::PRIMER_MENSAJE,
                    'palabras' => [],
                    'exacta' => false,
                    'audiencia' => 'nuevos',
                    'repetir' => 'una_vez',
                ]],
                ['id' => 'saludo', 'tipo' => TiposNodo::MENSAJE, 'x' => 340, 'y' => 100, 'datos' => [
                    'texto' => '¡Hola {{nombre}}! 👋 Gracias por escribirnos. ¿En qué te podemos ayudar?',
                    'media' => 'ninguno',
                ]],
            ],
            'conexiones' => [
                ['id' => 'c1', 'desde' => 'inicio', 'salida' => TiposNodo::SALIDA_SIGUIENTE, 'hacia' => 'saludo'],
            ],
        ];
    }

    private function resumenDisparador(array $definicion): array
    {
        $datos = (array) ((new Grafo($definicion))->inicio()['datos'] ?? []);

        $tipo = (string) ($datos['disparador'] ?? Disparador::PRIMER_MENSAJE);

        return [
            'tipo' => $tipo,
            'palabras' => array_values(array_filter((array) ($datos['palabras'] ?? []), 'is_string')),
            'audiencia' => Disparador::audienciaDe($datos, $tipo),
            'repetir' => Disparador::repeticionDe($datos, $tipo),
        ];
    }
}
