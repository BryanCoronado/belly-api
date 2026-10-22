<?php

namespace Belly\Crm\UseCases;

use Belly\Crm\Models\EnvioMasivoModel;
use Belly\Crm\Services\ConsultaClientes;
use Belly\Services\Autorizacion;
use Belly\Services\ContextoEmpresa;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DomainException;

/**
 * Envíos de WhatsApp a muchos clientes a la vez.
 * - Texto libre: solo llega a quien escribió en las últimas 24 horas (regla de Meta).
 * - Plantilla aprobada: sirve para empezar la conversación con cualquiera.
 * Los mensajes salen por tandas desde el servidor de tiempo real.
 */
class EnvioMasivoUseCase
{
    private const MAX_DESTINATARIOS = 5000;
    private const MINUTOS_ATASCADO = 10;
    private const TANDA_INICIAL = 5;

    private BaseConnection $db;
    private ConsultaClientes $consulta;
    private AjustesCrmUseCase $ajustes;

    public function __construct(?ConsultaClientes $consulta = null, ?AjustesCrmUseCase $ajustes = null)
    {
        $this->db = db_connect();
        $this->consulta = $consulta ?? new ConsultaClientes($this->db);
        $this->ajustes = $ajustes ?? new AjustesCrmUseCase($this->consulta);
    }

    /** Cuántos clientes recibirían el envío con esos filtros. @return array<string, int> */
    public function audiencia(array $filtros): array
    {
        $filtros = ConsultaClientes::filtrosDe($filtros);
        $primera = $this->ajustes->idPrimeraEtapa();

        $total = $this->contactables($this->consulta->builder($filtros, $primera))->countAllResults();
        $enVentana = $this->contactables($this->consulta->builder(['ventana' => 'abierta'] + $filtros, $primera))->countAllResults();
        $bloqueados = $this->consulta->builder($filtros, $primera)->where($this->sqlBloqueado(), null, false)->countAllResults();

        return [
            'total' => $total,
            'en_ventana' => $enVentana,
            'fuera_ventana' => max(0, $total - $enVentana),
            'bloqueados' => $bloqueados,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function plantillas(): array
    {
        $app = $this->appDeEmpresa();
        $resultado = plantilla()->obtenerPlantillasTodas(['id_app' => (int) $app['id'], 'per_page' => 100]);

        // Sin marca interna cuenta como activa: basta con que Meta la haya aprobado.
        return array_values(array_filter(
            (array) ($resultado['data'] ?? []),
            static fn ($p) => is_array($p)
                && strtoupper((string) ($p['status'] ?? '')) === 'APPROVED'
                && in_array($p['id_estado_sistema'] ?? null, [null, 1, '1'], true)
        ));
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function crear(array $datos): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 120) {
            throw new DomainException('Ponle un nombre al envío (hasta 120 letras).');
        }
        $modo = (string) ($datos['modo'] ?? '');
        if (! in_array($modo, ['texto', 'plantilla'], true)) {
            throw new DomainException('Elige si mandas un texto o una plantilla.');
        }

        $mensaje = null;
        $plantilla = null;
        if ($modo === 'texto') {
            $mensaje = trim((string) ($datos['mensaje'] ?? ''));
            if ($mensaje === '' || mb_strlen($mensaje) > 4000) {
                throw new DomainException('Escribe el mensaje (hasta 4000 letras).');
            }
        } else {
            $plantilla = $this->plantillaValida($datos['plantilla'] ?? null);
        }

        $app = $this->appDeEmpresa();
        $usuario = Autorizacion::usuario();
        $filtros = ConsultaClientes::filtrosDe((array) ($datos['filtros'] ?? []));
        $filtrosAudiencia = $modo === 'texto' ? ['ventana' => 'abierta'] + $filtros : $filtros;

        $ids = array_map('intval', array_column(
            $this->contactables($this->consulta->builder($filtrosAudiencia, $this->ajustes->idPrimeraEtapa()))
                ->select('c.id')
                ->orderBy('c.id', 'ASC')
                ->limit(self::MAX_DESTINATARIOS + 1)
                ->get()->getResultArray(),
            'id'
        ));
        if ($ids === []) {
            throw new DomainException($modo === 'texto'
                ? 'Nadie de ese grupo te escribió en las últimas 24 horas. Usa una plantilla para llegarles.'
                : 'No hay clientes con WhatsApp en ese grupo.');
        }
        if (count($ids) > self::MAX_DESTINATARIOS) {
            throw new DomainException('Puedes enviar a hasta ' . self::MAX_DESTINATARIOS . ' clientes a la vez. Filtra un poco más.');
        }

        $idEmpresa = $this->consulta->idEmpresa();
        $ahora = date('Y-m-d H:i:s');
        $this->db->transStart();
        $modelo = new EnvioMasivoModel();
        $idEnvio = (int) $modelo->insert([
            'id_empresa' => $idEmpresa,
            'id_app' => (int) $app['id'],
            'id_usuario' => (int) $usuario->id,
            'nombre' => $nombre,
            'modo' => $modo,
            'mensaje' => $mensaje,
            'plantilla' => $plantilla !== null ? json_encode($plantilla, JSON_UNESCAPED_UNICODE) : null,
            'filtros' => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            'estado' => 'enviando',
            'total' => count($ids),
        ]);
        foreach (array_chunk($ids, 500) as $tanda) {
            $this->db->table('envio_masivo_destinatario')->insertBatch(array_map(static fn (int $idCliente) => [
                'id_envio' => $idEnvio,
                'id_empresa' => $idEmpresa,
                'id_cliente' => $idCliente,
                'estado' => 'pendiente',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $tanda));
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new DomainException('No se pudo crear el envío. Inténtalo otra vez.');
        }

        $envio = $this->db->table('envio_masivo')->where('id', $idEnvio)->get()->getRowArray();
        $this->procesarEnvio($envio, self::TANDA_INICIAL);

        return $this->ver($idEnvio);
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $envios = (new EnvioMasivoModel())
            ->select('envio_masivo.*, up.nombres AS creador')
            ->join('usuario_perfil up', 'up.id_usuario = envio_masivo.id_usuario AND up.deleted_at IS NULL', 'left')
            ->orderBy('envio_masivo.id', 'DESC')
            ->limit(100)
            ->findAll();
        $resumenes = $this->resumenes(array_map(static fn (array $e) => (int) $e['id'], $envios));

        return array_map(fn (array $e) => $this->presentar($e, $resumenes[(int) $e['id']] ?? []), $envios);
    }

    /** @return array<string, mixed> */
    public function ver(int $id): array
    {
        $envio = (new EnvioMasivoModel())
            ->select('envio_masivo.*, up.nombres AS creador')
            ->join('usuario_perfil up', 'up.id_usuario = envio_masivo.id_usuario AND up.deleted_at IS NULL', 'left')
            ->find($id);
        if (! is_array($envio)) {
            throw new DomainException('No encontramos ese envío.');
        }

        $destinatarios = $this->db->table('envio_masivo_destinatario d')
            ->select('d.id, d.id_cliente, d.id_conversacion, d.estado, d.detalle, d.enviado_at, c.telefono, cp.nombre_completo, cp.nombre')
            ->join('cliente c', 'c.id = d.id_cliente')
            ->join('cliente_perfil cp', 'cp.id_cliente = c.id AND cp.deleted_at IS NULL', 'left')
            ->where('d.id_envio', $id)
            ->where('d.id_empresa', $this->consulta->idEmpresa())
            ->orderBy("FIELD(d.estado, 'fallido', 'enviando', 'pendiente', 'omitido', 'enviado')", '', false)
            ->orderBy('d.id', 'ASC')
            ->limit(1000)
            ->get()->getResultArray();

        return $this->presentar($envio, $this->resumenes([$id])[$id] ?? []) + [
            'destinatarios' => array_map(static fn (array $d) => [
                'id' => (int) $d['id'],
                'id_cliente' => (int) $d['id_cliente'],
                'id_conversacion' => $d['id_conversacion'] !== null ? (int) $d['id_conversacion'] : null,
                'nombre' => trim((string) ($d['nombre_completo'] ?: $d['nombre'] ?: '')),
                'telefono' => $d['telefono'],
                'estado' => $d['estado'],
                'detalle' => $d['detalle'],
                'enviado_at' => $d['enviado_at'],
            ], $destinatarios),
        ];
    }

    /** Los que aún no salieron ya no se envían. */
    public function cancelar(int $id): array
    {
        $envio = (new EnvioMasivoModel())->find($id);
        if (! is_array($envio)) {
            throw new DomainException('No encontramos ese envío.');
        }
        if ($envio['estado'] !== 'enviando') {
            throw new DomainException('Este envío ya terminó.');
        }

        $ahora = date('Y-m-d H:i:s');
        $this->db->table('envio_masivo_destinatario')
            ->where('id_envio', $id)->where('estado', 'pendiente')
            ->update(['estado' => 'omitido', 'detalle' => 'Cancelaste el envío.', 'updated_at' => $ahora]);
        (new EnvioMasivoModel())->update($id, ['estado' => 'cancelado', 'terminado_at' => $ahora]);

        return $this->ver($id);
    }

    /** Lo llama el servidor de tiempo real cada pocos segundos. Devuelve cuántos mensajes procesó. */
    public function procesarPendientes(int $lote = 30): int
    {
        $limite = date('Y-m-d H:i:s', time() - self::MINUTOS_ATASCADO * 60);
        $this->db->table('envio_masivo_destinatario')
            ->where('estado', 'enviando')->where('updated_at <', $limite)
            ->update(['estado' => 'fallido', 'detalle' => 'El envío se interrumpió antes de confirmar.', 'updated_at' => date('Y-m-d H:i:s')]);

        $envios = $this->db->table('envio_masivo')
            ->where('estado', 'enviando')->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $hechos = 0;
        foreach ($envios as $envio) {
            if ($hechos >= $lote) {
                break;
            }
            $hechos += ContextoEmpresa::ejecutarComo(
                (int) $envio['id_empresa'],
                fn () => $this->procesarEnvio($envio, $lote - $hechos)
            );
        }

        return $hechos;
    }

    /** @param array<string, mixed> $envio */
    private function procesarEnvio(array $envio, int $maximo): int
    {
        $idEnvio = (int) $envio['id'];
        $pendientes = $this->db->table('envio_masivo_destinatario')
            ->where('id_envio', $idEnvio)->where('estado', 'pendiente')
            ->orderBy('id', 'ASC')->limit(max(1, $maximo))
            ->get()->getResultArray();

        $hechos = 0;
        foreach ($pendientes as $destinatario) {
            if (! $this->sigueEnviando($idEnvio)) {
                break;
            }
            $this->db->table('envio_masivo_destinatario')
                ->where('id', $destinatario['id'])->where('estado', 'pendiente')
                ->update(['estado' => 'enviando', 'updated_at' => date('Y-m-d H:i:s')]);
            if ($this->db->affectedRows() === 0) {
                continue;
            }

            try {
                [$estado, $detalle, $idConversacion] = $this->enviarA($envio, (int) $destinatario['id_cliente']);
            } catch (\Throwable $e) {
                log_message('error', '[ENVIO MASIVO] envio=' . $idEnvio . ' cliente=' . $destinatario['id_cliente'] . ' ' . $e->getMessage());
                [$estado, $detalle, $idConversacion] = ['fallido', 'No se pudo enviar.', null];
            }

            $this->db->table('envio_masivo_destinatario')->where('id', $destinatario['id'])->update([
                'estado' => $estado,
                'detalle' => $detalle !== null ? mb_substr($detalle, 0, 255) : null,
                'id_conversacion' => $idConversacion,
                'enviado_at' => $estado === 'enviado' ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $hechos++;
        }

        $quedan = $this->db->table('envio_masivo_destinatario')
            ->where('id_envio', $idEnvio)->whereIn('estado', ['pendiente', 'enviando'])
            ->countAllResults();
        if ($quedan === 0) {
            $this->db->table('envio_masivo')
                ->where('id', $idEnvio)->where('estado', 'enviando')
                ->update(['estado' => 'terminado', 'terminado_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }

        return $hechos;
    }

    /**
     * @param array<string, mixed> $envio
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    private function enviarA(array $envio, int $idCliente): array
    {
        $cliente = $this->db->table('cliente c')
            ->select('c.id, c.telefono, c.wa_user_id, cp.nombre, cp.nombre_completo')
            ->join('cliente_perfil cp', 'cp.id_cliente = c.id AND cp.deleted_at IS NULL', 'left')
            ->where('c.id', $idCliente)->where('c.id_empresa', $envio['id_empresa'])->where('c.deleted_at', null)
            ->get()->getRowArray();
        if ($cliente === null) {
            return ['omitido', 'El cliente ya no existe.', null];
        }
        if ($this->estaBloqueado((int) $envio['id_empresa'], $cliente['telefono'])) {
            return ['omitido', 'El número está en la lista negra.', null];
        }

        if ($envio['modo'] === 'texto') {
            $ultimo = $this->db->query(
                'SELECT ' . $this->consulta->sqlUltimoMensajeCliente('c') . ' AS ultimo FROM cliente c WHERE c.id = ?',
                [$idCliente]
            )->getRowArray()['ultimo'] ?? null;
            if ($ultimo === null || $ultimo < $this->consulta->inicioVentana()) {
                return ['omitido', 'Pasaron más de 24 horas desde su último mensaje.', null];
            }
        }

        [$idConversacion, $esNueva] = $this->conversacionPara($envio, $idCliente);
        $nombre = $this->primerNombre((string) ($cliente['nombre'] ?: $cliente['nombre_completo'] ?: ''));
        $idUsuario = (int) $envio['id_usuario'];

        if ($envio['modo'] === 'texto') {
            $resultado = mensaje()->crearMensajeYEnviarWhatsapp([
                'id_conversacion' => $idConversacion,
                'mensaje' => $this->personalizar((string) $envio['mensaje'], $nombre, false),
                'contesta' => 'usuario',
                'id_usuario' => $idUsuario,
                'request_id' => 'masivo_' . $envio['id'] . '_' . $idCliente,
            ]);
        } else {
            $plantilla = json_decode((string) $envio['plantilla'], true) ?: [];
            $resultado = mensaje()->enviarPlantillaWhatsapp([
                'id_conversacion' => $idConversacion,
                'template_id' => $plantilla['id'] ?? '',
                'template_name' => $plantilla['name'] ?? '',
                'language' => $plantilla['language'] ?? 'es',
                'parameters' => array_map(fn ($valor) => ['type' => 'text', 'text' => $this->personalizar((string) $valor, $nombre, true)], $plantilla['parametros'] ?? []),
                'id_usuario' => $idUsuario,
            ]);
        }

        if (! ($resultado['success'] ?? false)) {
            if ($esNueva) {
                $this->db->table('conversacion')->where('id', $idConversacion)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            }

            return ['fallido', $this->motivoFallo((string) ($resultado['error'] ?? '')), $esNueva ? null : $idConversacion];
        }

        return ['enviado', null, $idConversacion];
    }

    /**
     * Usa el chat abierto del cliente; si no tiene, abre uno con quien lo atendió la última vez
     * (o con quien creó el envío) para que vea la respuesta.
     *
     * @param array<string, mixed> $envio
     * @return array{0: int, 1: bool}
     */
    private function conversacionPara(array $envio, int $idCliente): array
    {
        $ultima = $this->db->table('conversacion cv')
            ->select('cv.id, cv.id_estado, cv.id_usuario, cv.id_app, u.es_bot, u.activo')
            ->join('usuario u', 'u.id = cv.id_usuario', 'left')
            ->where('cv.id_cliente', $idCliente)->where('cv.id_empresa', $envio['id_empresa'])->where('cv.deleted_at', null)
            ->orderBy('cv.id', 'DESC')->limit(1)
            ->get()->getRowArray();

        if ($ultima !== null && in_array((int) $ultima['id_estado'], [1, 3], true) && (int) $ultima['id_app'] === (int) $envio['id_app']) {
            return [(int) $ultima['id'], false];
        }

        $idUsuario = $ultima !== null && (int) ($ultima['es_bot'] ?? 0) === 0 && (int) ($ultima['activo'] ?? 0) === 1
            ? (int) $ultima['id_usuario']
            : (int) $envio['id_usuario'];

        $creada = conversaciones()->crearConversacion([
            'id_empresa' => (int) $envio['id_empresa'],
            'id_cliente' => $idCliente,
            'id_usuario' => $idUsuario,
            'id_app' => (int) $envio['id_app'],
            'id_estado' => 1,
            'origen' => 'masivo',
            'registro' => 'masivo',
            'id_asignado_por' => (int) $envio['id_usuario'],
            'asignado_at' => date('Y-m-d H:i:s'),
        ]);

        return [(int) $creada['id'], true];
    }

    private function sigueEnviando(int $idEnvio): bool
    {
        return $this->db->table('envio_masivo')->where('id', $idEnvio)->where('estado', 'enviando')->countAllResults() > 0;
    }

    /** Solo clientes a los que WhatsApp puede llegar y que no están bloqueados. */
    private function contactables(BaseBuilder $builder): BaseBuilder
    {
        return $builder
            ->groupStart()->where('c.telefono IS NOT NULL', null, false)->where("c.telefono <> ''", null, false)
            ->orWhere('c.wa_user_id IS NOT NULL', null, false)->groupEnd()
            ->where('NOT ' . $this->sqlBloqueado(), null, false);
    }

    private function sqlBloqueado(): string
    {
        return '(c.telefono IS NOT NULL AND EXISTS (SELECT 1 FROM lista_negra ln
                  WHERE ln.id_empresa = c.id_empresa AND ln.telefono = c.telefono AND ln.deleted_at IS NULL))';
    }

    private function estaBloqueado(int $idEmpresa, ?string $telefono): bool
    {
        if ($telefono === null || $telefono === '') {
            return false;
        }

        return $this->db->table('lista_negra')
            ->where('id_empresa', $idEmpresa)->where('telefono', $telefono)->where('deleted_at', null)
            ->countAllResults() > 0;
    }

    /** @return array<string, mixed> */
    private function appDeEmpresa(): array
    {
        return $this->consulta->appWhatsapp('Conecta tu número de WhatsApp antes de hacer envíos.');
    }

    /** @return array<string, mixed> */
    private function plantillaValida(mixed $plantilla): array
    {
        if (! is_array($plantilla) || trim((string) ($plantilla['id'] ?? '')) === '' || trim((string) ($plantilla['name'] ?? '')) === '') {
            throw new DomainException('Elige una plantilla aprobada.');
        }
        $parametros = array_values(array_map(static fn ($v) => trim((string) $v), (array) ($plantilla['parametros'] ?? [])));
        if (count($parametros) > 20) {
            throw new DomainException('La plantilla tiene demasiadas variables.');
        }
        foreach ($parametros as $valor) {
            if ($valor === '') {
                throw new DomainException('Completa todas las variables de la plantilla.');
            }
            if (mb_strlen($valor) > 500) {
                throw new DomainException('Cada variable puede tener hasta 500 letras.');
            }
        }

        return [
            'id' => (string) $plantilla['id'],
            'name' => (string) $plantilla['name'],
            'language' => (string) ($plantilla['language'] ?? 'es'),
            'parametros' => $parametros,
            'vista' => mb_substr(trim((string) ($plantilla['vista'] ?? '')), 0, 2000),
        ];
    }

    /** {{nombre}} se cambia por el primer nombre del cliente. */
    private function personalizar(string $texto, string $nombre, bool $nuncaVacio): string
    {
        $resultado = trim(preg_replace('/\{\{\s*nombre\s*\}\}/iu', $nombre, $texto) ?? $texto);
        $resultado = preg_replace('/[ \t]+([,.!?])/u', '$1', $resultado) ?? $resultado;

        return $nuncaVacio && $resultado === '' ? 'cliente' : $resultado;
    }

    private function primerNombre(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre)) ?: [];

        return mb_convert_case((string) ($partes[0] ?? ''), MB_CASE_TITLE, 'UTF-8');
    }

    private function motivoFallo(string $error): string
    {
        $conocidos = [
            '131047' => 'Pasaron más de 24 horas desde su último mensaje. Usa una plantilla.',
            '131026' => 'Ese número no puede recibir mensajes de WhatsApp.',
            '131049' => 'Meta no lo entregó para no saturar al usuario con mensajes de marketing.',
            '130472' => 'Meta no entrega marketing a este número por ahora.',
            '131030' => 'El número no está en la lista de prueba de tu cuenta de Meta.',
            '132000' => 'Las variables no coinciden con la plantilla.',
            '132001' => 'La plantilla no existe o no está aprobada en ese idioma.',
            '132015' => 'La plantilla está pausada en Meta.',
            '132016' => 'La plantilla fue desactivada en Meta.',
            '131056' => 'Demasiados mensajes seguidos a este número. Inténtalo más tarde.',
            '130429' => 'Se alcanzó el límite de envíos de Meta. Inténtalo más tarde.',
        ];
        foreach ($conocidos as $codigo => $texto) {
            if (str_contains($error, (string) $codigo)) {
                return $texto;
            }
        }
        if (preg_match('/"message"\s*:\s*"([^"]+)"/', $error, $m) === 1) {
            return 'WhatsApp respondió: ' . $m[1];
        }

        return $error !== '' ? mb_substr($error, 0, 250) : 'No se pudo enviar.';
    }

    /** @param list<int> $ids @return array<int, array<string, int>> */
    private function resumenes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $filas = $this->db->table('envio_masivo_destinatario')
            ->select('id_envio, estado, COUNT(*) AS total')
            ->whereIn('id_envio', $ids)
            ->where('id_empresa', $this->consulta->idEmpresa())
            ->groupBy(['id_envio', 'estado'])
            ->get()->getResultArray();

        $resumenes = [];
        foreach ($filas as $fila) {
            $resumenes[(int) $fila['id_envio']][$fila['estado']] = (int) $fila['total'];
        }

        return $resumenes;
    }

    /** @param array<string, mixed> $envio @param array<string, int> $resumen @return array<string, mixed> */
    private function presentar(array $envio, array $resumen): array
    {
        $plantilla = $envio['plantilla'] ? (json_decode((string) $envio['plantilla'], true) ?: null) : null;

        return [
            'id' => (int) $envio['id'],
            'nombre' => $envio['nombre'],
            'modo' => $envio['modo'],
            'mensaje' => $envio['mensaje'],
            'plantilla' => $plantilla,
            'filtros' => json_decode((string) ($envio['filtros'] ?? ''), true) ?: [],
            'estado' => $envio['estado'],
            'creador' => $envio['creador'] ?? null,
            'creado' => $envio['created_at'],
            'terminado' => $envio['terminado_at'],
            'total' => (int) $envio['total'],
            'resumen' => [
                'pendiente' => ($resumen['pendiente'] ?? 0) + ($resumen['enviando'] ?? 0),
                'enviado' => $resumen['enviado'] ?? 0,
                'fallido' => $resumen['fallido'] ?? 0,
                'omitido' => $resumen['omitido'] ?? 0,
            ],
        ];
    }
}
