<?php

namespace Belly\Flujos\UseCases;

use Belly\Flujos\Domain\AccionesConversacion;
use Belly\Flujos\Domain\Disparador;
use Belly\Flujos\Domain\EnviadorMensajes;
use Belly\Flujos\Domain\MotorFlujo;
use Belly\Flujos\Entities\FlujoEntity;
use Belly\Flujos\Entities\FlujoSesionEntity;
use Belly\Flujos\Models\FlujoModel;
use Belly\Flujos\Models\FlujoSesionModel;
use Belly\Flujos\Services\AccionesCrm;
use Belly\Flujos\Services\EnviadorWhatsapp;
use Belly\Flujos\Services\TurnosCrm;
use Belly\Services\ContextoEmpresa;
use CodeIgniter\Database\RawSql;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Bot en vivo: recibe cada mensaje entrante, decide si arranca o continúa un flujo
 * y envía las respuestas. Todo corre dentro del contexto de la empresa del webhook.
 * También retoma los flujos que estaban esperando (pasos «Esperar» y «sin respuesta»).
 */
class FlujoBotUseCase
{
    /** Tiempo máximo (segundos) que una sola petición puede dormir por las pausas «escribiendo…». */
    private const PRESUPUESTO_PAUSAS = 12;

    /** Datos que el bot siempre tiene; no aportan al resumen del asesor. */
    private const VARIABLES_DEL_SISTEMA = ['fecha', 'hora', 'ultimo_mensaje', 'ultima_opcion', 'nombre', 'nombre_completo', 'telefono', 'asesor'];

    private FlujoModel $flujos;
    private FlujoSesionModel $sesiones;
    private MotorFlujo $motor;
    private EnviadorMensajes $enviador;
    private AccionesConversacion $acciones;

    public function __construct(
        ?FlujoModel $flujos = null,
        ?FlujoSesionModel $sesiones = null,
        ?MotorFlujo $motor = null,
        ?EnviadorMensajes $enviador = null,
        ?AccionesConversacion $acciones = null
    ) {
        $this->flujos = $flujos ?? new FlujoModel();
        $this->sesiones = $sesiones ?? new FlujoSesionModel();
        $this->motor = $motor ?? new MotorFlujo(new TurnosCrm());
        $this->enviador = $enviador ?? new EnviadorWhatsapp();
        $this->acciones = $acciones ?? new AccionesCrm();
    }

    /**
     * Lo que el bot recogió en esta conversación, para que el asesor no tenga que leer todo el chat.
     *
     * @return array{flujo:string, estado:string, ruta:list<string>, datos:list<array{clave:string, valor:string}>, actualizado:?string}|null
     */
    public function resumenDeConversacion(int $idConversacion): ?array
    {
        helper('vip2system');
        $idEmpresa = (int) (id_empresa_sesion() ?? 0);
        $existe = $idEmpresa > 0 && db_connect()->table('conversacion')->where('id', $idConversacion)->where('id_empresa', $idEmpresa)->countAllResults() > 0;

        $sesion = $existe ? $this->sesiones->where('id_conversacion', $idConversacion)->orderBy('id', 'DESC')->first() : null;
        if (! $sesion instanceof FlujoSesionEntity) {
            return null;
        }

        $flujo = $this->flujos->withDeleted()->find((int) $sesion->id_flujo);
        $variables = (array) $sesion->variables;
        $ruta = (string) ($variables[MotorFlujo::VARIABLE_RUTA] ?? '');

        $datos = [];
        foreach ($variables as $clave => $valor) {
            $clave = (string) $clave;
            if (! is_scalar($valor) || trim((string) $valor) === '' || str_starts_with($clave, '_') || in_array($clave, self::VARIABLES_DEL_SISTEMA, true)) {
                continue;
            }
            $datos[] = ['clave' => $clave, 'valor' => mb_substr((string) $valor, 0, 300)];
        }

        return [
            'flujo' => $flujo instanceof FlujoEntity ? $flujo->nombre : 'Flujo eliminado',
            'estado' => (string) $sesion->estado,
            'ruta' => $ruta === '' ? [] : explode(MotorFlujo::SEPARADOR_RUTA, $ruta),
            'datos' => $datos,
            'actualizado' => $sesion->updated_at?->format('c') ?? $sesion->created_at?->format('c'),
        ];
    }

    /**
     * @param string $respuestaId Id del botón o fila que tocó el cliente (vacío si escribió).
     */
    public function alRecibirMensaje(int $idConversacion, string $texto, string $respuestaId, bool $esPrimerMensaje): void
    {
        helper('vip2system');
        $idEmpresa = id_empresa_sesion();
        if ($idEmpresa === null || $idConversacion <= 0) {
            return;
        }

        /** @var list<FlujoEntity> $activos */
        $activos = $this->flujos->where('activo', 1)->orderBy('id', 'ASC')->findAll();
        if ($activos === []) {
            return;
        }

        $this->conBloqueo($idConversacion, function () use ($idEmpresa, $idConversacion, $texto, $respuestaId, $esPrimerMensaje, $activos): void {
            $ultima = $this->sesiones->where('id_conversacion', $idConversacion)->orderBy('id', 'DESC')->first();

            if ($ultima instanceof FlujoSesionEntity && in_array($ultima->estado, ['derivada', 'esperando'], true)) {
                return; // Lo atiende una persona, o el bot está en una espera programada.
            }

            $ahora = $this->ahora();
            $entrada = ['texto' => $texto, 'respuesta_id' => $respuestaId !== '' ? $respuestaId : null];

            if ($ultima instanceof FlujoSesionEntity && $ultima->estado === 'activa') {
                $flujo = $this->buscarActivo($activos, (int) $ultima->id_flujo);
                if ($flujo !== null) {
                    $resultado = $this->motor->continuar($flujo->definicion, $this->estadoDe($ultima), $entrada, $ahora);
                    $this->guardarSesion($ultima, $resultado['estado']);
                    $this->enviarTodos($idConversacion, $resultado['mensajes']);

                    return;
                }
                // El flujo se pausó o se eliminó: se cierra y se evalúa como mensaje nuevo.
                $this->sesiones->update($ultima->id, ['estado' => 'terminada', 'nodo_actual' => null, 'reanudar_at' => null]);
            }

            $contexto = $this->contexto($idConversacion, $esPrimerMensaje, $ultima === null);
            $flujo = $this->elegirParaIniciar($activos, $texto, $contexto);
            if ($flujo === null) {
                return;
            }

            $resultado = $this->motor->iniciar($flujo->definicion, $this->variablesDelCliente($idConversacion, $texto), $ahora);
            $nueva = new FlujoSesionEntity([
                'id_empresa' => $idEmpresa,
                'id_flujo' => $flujo->id,
                'id_conversacion' => $idConversacion,
            ]);
            $this->guardarSesion($nueva, $resultado['estado']);
            $this->enviarTodos($idConversacion, $resultado['mensajes']);
        });
    }

    /** Un asesor escribió en el chat: el bot deja de contestar en esa conversación. */
    public function pausarPorAsesor(int $idConversacion): void
    {
        $this->sesiones
            ->where('id_conversacion', $idConversacion)
            ->whereIn('estado', ['activa', 'esperando'])
            ->set(['estado' => 'derivada', 'nodo_actual' => null, 'reanudar_at' => null])
            ->update();
    }

    /**
     * Retoma los flujos cuya espera ya terminó (todas las empresas).
     * Lo llama un proceso en segundo plano cada pocos segundos.
     *
     * @return int cuántas conversaciones se retomaron
     */
    public function procesarPendientes(int $limite = 50): int
    {
        $pendientes = db_connect()->table('flujo_sesion')
            ->select('id, id_empresa')
            ->whereIn('estado', ['activa', 'esperando'])
            ->where('reanudar_at IS NOT NULL', null, false)
            ->where('reanudar_at <= NOW()', null, false)
            ->orderBy('reanudar_at', 'ASC')
            ->limit($limite)
            ->get()->getResultArray();

        $hechos = 0;
        foreach ($pendientes as $fila) {
            try {
                $hechos += ContextoEmpresa::ejecutarComo(
                    (int) $fila['id_empresa'],
                    fn (): int => $this->retomarSesion((int) $fila['id'])
                );
            } catch (\Throwable $e) {
                log_message('error', '[BOT] No se pudo retomar la sesión ' . $fila['id'] . ': ' . $e->getMessage());
                $this->sesiones->update((int) $fila['id'], ['reanudar_at' => null]);
            }
        }

        return $hechos;
    }

    // ───────────────────────── internos ─────────────────────────

    private function retomarSesion(int $idSesion): int
    {
        $sesion = $this->sesiones->find($idSesion);
        if (! $sesion instanceof FlujoSesionEntity) {
            return 0;
        }

        $hecho = 0;
        $this->conBloqueo((int) $sesion->id_conversacion, function () use ($idSesion, &$hecho): void {
            // Se vuelve a leer ya con el bloqueo: el cliente pudo responder mientras tanto.
            $sesion = $this->sesiones
                ->where('id', $idSesion)
                ->whereIn('estado', ['activa', 'esperando'])
                ->where('reanudar_at IS NOT NULL', null, false)
                ->where('reanudar_at <= NOW()', null, false)
                ->first();
            if (! $sesion instanceof FlujoSesionEntity) {
                return;
            }

            $flujo = $this->flujos->where('activo', 1)->find((int) $sesion->id_flujo);
            if (! $flujo instanceof FlujoEntity) {
                $this->sesiones->update($sesion->id, ['estado' => 'terminada', 'nodo_actual' => null, 'reanudar_at' => null]);

                return;
            }

            $resultado = $this->motor->reanudar($flujo->definicion, $this->estadoDe($sesion), $this->ahora());
            $this->guardarSesion($sesion, $resultado['estado']);
            $this->enviarTodos((int) $sesion->id_conversacion, $resultado['mensajes']);
            $hecho = 1;
        });

        return $hecho;
    }

    private function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
    }

    private function estadoDe(FlujoSesionEntity $sesion): array
    {
        return [
            'estado' => $sesion->estado,
            'nodo' => $sesion->nodo_actual,
            'variables' => (array) $sesion->variables,
            'intentos' => (int) $sesion->intentos,
            'reanudar_en' => null,
        ];
    }

    /** @param list<FlujoEntity> $activos */
    private function buscarActivo(array $activos, int $idFlujo): ?FlujoEntity
    {
        foreach ($activos as $flujo) {
            if ((int) $flujo->id === $idFlujo) {
                return $flujo;
            }
        }

        return null;
    }

    /**
     * Datos del cliente que usan las reglas de «a quién responde» y «cada cuánto se repite».
     *
     * @return array{primer_mensaje:bool, sin_bot:bool, contacto_nuevo:bool, por_flujo:array<int, array{veces:int, horas:?float}>}
     */
    private function contexto(int $idConversacion, bool $esPrimerMensaje, bool $sinBot): array
    {
        $db = db_connect();
        $cliente = (int) ($db->table('conversacion')->select('id_cliente')->where('id', $idConversacion)->get()->getRow()->id_cliente ?? 0);

        $otras = $db->table('conversacion')->where('id_cliente', $cliente)->where('id !=', $idConversacion)->countAllResults();
        $mensajes = $db->table('mensaje')->where('id_conversacion', $idConversacion)->where('deleted_at', null)->countAllResults();

        $porFlujo = [];
        $filas = $db->table('flujo_sesion s')
            ->select('s.id_flujo, COUNT(*) AS veces, TIMESTAMPDIFF(SECOND, MAX(s.created_at), NOW()) AS segundos', false)
            ->join('conversacion cv', 'cv.id = s.id_conversacion')
            ->where('cv.id_cliente', $cliente)
            ->groupBy('s.id_flujo')
            ->get()->getResultArray();
        foreach ($filas as $fila) {
            $porFlujo[(int) $fila['id_flujo']] = ['veces' => (int) $fila['veces'], 'horas' => $fila['segundos'] === null ? null : max(0, (float) $fila['segundos']) / 3600];
        }

        return [
            'primer_mensaje' => $esPrimerMensaje,
            'sin_bot' => $sinBot,
            // Nuevo = nunca habíamos hablado con él: ni en otros chats ni antes en este (solo existe el mensaje que acaba de llegar).
            'contacto_nuevo' => $otras === 0 && $mensajes <= 1,
            'por_flujo' => $porFlujo,
        ];
    }

    /** @param list<FlujoEntity> $activos */
    private function elegirParaIniciar(array $activos, string $texto, array $contexto): ?FlujoEntity
    {
        $elegido = null;
        $mejor = 0;
        foreach ($activos as $flujo) {
            $propio = $contexto['por_flujo'][(int) $flujo->id] ?? ['veces' => 0, 'horas' => null];
            $prioridad = Disparador::prioridad((array) $flujo->definicion, $texto, [
                'primer_mensaje' => $contexto['primer_mensaje'],
                'sin_bot' => $contexto['sin_bot'],
                'contacto_nuevo' => $contexto['contacto_nuevo'],
                'veces' => $propio['veces'],
                'horas_desde_ultimo' => $propio['horas'],
            ]);
            if ($prioridad > $mejor) {
                $mejor = $prioridad;
                $elegido = $flujo;
            }
        }

        return $elegido;
    }

    private function guardarSesion(FlujoSesionEntity $sesion, array $estado): void
    {
        $segundos = isset($estado['reanudar_en']) ? (int) $estado['reanudar_en'] : 0;

        $datos = [
            'estado' => $estado['estado'],
            'nodo_actual' => $estado['nodo'],
            'intentos' => (int) $estado['intentos'],
            'variables' => json_encode($estado['variables'], JSON_UNESCAPED_UNICODE),
            // Se calcula con el reloj de la base de datos, el mismo con el que luego se compara.
            'reanudar_at' => $segundos > 0 ? new RawSql('DATE_ADD(NOW(), INTERVAL ' . $segundos . ' SECOND)') : null,
        ];

        if (empty($sesion->id)) {
            $this->sesiones->insert($datos + [
                'id_empresa' => $sesion->id_empresa,
                'id_flujo' => $sesion->id_flujo,
                'id_conversacion' => $sesion->id_conversacion,
            ]);

            return;
        }
        $this->sesiones->update($sesion->id, $datos);
    }

    /**
     * Envía los mensajes en orden. Las pausas cortas se respetan (con un tope por petición)
     * y las esperas largas no se envían: las retoma el proceso en segundo plano.
     *
     * @param list<array> $mensajes
     */
    private function enviarTodos(int $idConversacion, array $mensajes): void
    {
        $presupuesto = self::PRESUPUESTO_PAUSAS;
        $asesor = null;

        foreach ($mensajes as $mensaje) {
            $tipo = $mensaje['tipo'] ?? '';

            if ($tipo === 'espera') {
                continue;
            }
            if ($tipo === 'asignar' || $tipo === 'temperatura') {
                $asesor = $this->ejecutarAccion($idConversacion, $mensaje) ?? $asesor;
                continue;
            }
            if (($mensaje['texto'] ?? null) !== null && str_contains((string) $mensaje['texto'], MotorFlujo::MARCA_ASESOR)) {
                $mensaje['texto'] = str_replace(MotorFlujo::MARCA_ASESOR, $asesor ?? 'un asesor de nuestro equipo', $mensaje['texto']);
            }
            if ($tipo === 'pausa') {
                $segundos = min((int) ($mensaje['segundos'] ?? 0), $presupuesto);
                if ($segundos > 0) {
                    sleep($segundos);
                    $presupuesto -= $segundos;
                }
                continue;
            }
            if (service('CostosWhatsappUseCase')->automaticoDebeCallar()) {
                continue;
            }
            if (! $this->enviador->enviar($idConversacion, $mensaje)) {
                break;
            }
        }
    }

    /**
     * Ejecuta una acción del bot en el CRM. Un fallo aquí nunca debe frenar el resto de la conversación.
     *
     * @return string|null nombre del asesor si la acción fue una asignación
     */
    private function ejecutarAccion(int $idConversacion, array $accion): ?string
    {
        try {
            if (($accion['tipo'] ?? '') === 'asignar') {
                return $this->acciones->asignar($idConversacion, $accion);
            }
            $this->acciones->calificar($idConversacion, (string) ($accion['valor'] ?? ''));
        } catch (\Throwable $e) {
            log_message('error', '[BOT] Acción ' . ($accion['tipo'] ?? '?') . ' falló conv=' . $idConversacion . ': ' . $e->getMessage());
        }

        return null;
    }

    /** @return array<string, string> */
    private function variablesDelCliente(int $idConversacion, string $texto): array
    {
        $fila = db_connect()->table('conversacion cv')
            ->select('c.telefono, p.nombre, p.nombre_completo')
            ->join('cliente c', 'c.id = cv.id_cliente')
            ->join('cliente_perfil p', 'p.id_cliente = c.id AND p.deleted_at IS NULL', 'left')
            ->where('cv.id', $idConversacion)
            ->get()->getRowArray() ?: [];

        $completo = trim((string) ($fila['nombre_completo'] ?? ''));
        $nombre = trim((string) ($fila['nombre'] ?? ''));
        if ($nombre === '' && $completo !== '') {
            $nombre = explode(' ', $completo)[0];
        }

        return [
            'nombre' => $nombre,
            'nombre_completo' => $completo !== '' ? $completo : $nombre,
            'telefono' => (string) ($fila['telefono'] ?? ''),
            'ultimo_mensaje' => $texto,
        ];
    }

    /** Evita que dos mensajes seguidos del mismo chat avancen el flujo a la vez. */
    private function conBloqueo(int $idConversacion, callable $accion): void
    {
        $db = db_connect();
        $nombre = 'bot_conv_' . $idConversacion;
        $obtenido = (int) ($db->query('SELECT GET_LOCK(?, 5) AS ok', [$nombre])->getRow()->ok ?? 0);
        if ($obtenido !== 1) {
            log_message('warning', '[BOT] No se pudo bloquear la conversación ' . $idConversacion);

            return;
        }

        try {
            $accion();
        } finally {
            $db->query('SELECT RELEASE_LOCK(?)', [$nombre]);
        }
    }
}
