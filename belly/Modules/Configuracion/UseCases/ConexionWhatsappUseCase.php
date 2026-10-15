<?php

namespace Belly\Configuracion\UseCases;

use Belly\Configuracion\Config\ConfiguracionConfig;
use Belly\Configuracion\Entities\AppEntity;
use Belly\Configuracion\Entities\ConexionMetaEntity;
use Belly\Configuracion\Models\AppModel;
use Belly\Configuracion\Models\ConexionMetaModel;
use Belly\Services\Autorizacion;
use Belly\Services\Cifrado;
use Belly\Services\MetaGraph;

/**
 * Conexión de una empresa con WhatsApp Cloud API, paso a paso:
 *  1. App de Meta (App ID + App Secret con el que Meta firma los eventos).
 *  2. Webhook propio de la empresa (URL con clave única + token de verificación generado).
 *  3. Números (phone_number_id + WABA + token), validados contra Meta antes de guardarse.
 */
class ConexionWhatsappUseCase
{
    /** @var array<int, bool> */
    private array $conectadas = [];

    public function __construct(
        protected ConfiguracionConfig $config,
        protected ?MetaGraph $meta = null,
    ) {
        $this->meta ??= new MetaGraph();
    }

    /** @return array<string, mixed> */
    public function estado(): array
    {
        $this->exigirAdministrador();
        $conexion = $this->conexionDeEmpresa($this->idEmpresa());

        $numeros = array_map(fn (AppEntity $app) => $this->presentarNumero($app), $this->numerosDeEmpresa());
        $conNumero = array_filter($numeros, static fn (array $n) => $n['verificado']) !== [];
        $plataforma = config('Plataforma');
        $urlApi = $plataforma->urlApi();

        return [
            'conectado' => $conNumero,
            'pasos' => [
                'app_meta' => $conexion->tieneAppSecret(),
                'webhook' => $conexion->webhook_verificado_at !== null,
                'numero' => $conNumero,
            ],
            'app_meta' => [
                'meta_app_id' => $conexion->meta_app_id,
                'tiene_app_secret' => $conexion->tieneAppSecret(),
            ],
            'webhook' => [
                'url' => $urlApi . '/v1/webhooks/meta/' . $conexion->webhook_clave,
                'verify_token' => $conexion->verify_token,
                'url_configurada' => trim($plataforma->urlPublicaApi) !== '',
                'url_https' => str_starts_with($urlApi, 'https://'),
                'verificado_at' => $this->fecha($conexion->webhook_verificado_at),
                'ultimo_evento_at' => $this->fecha($conexion->ultimo_evento_at),
            ],
            'numeros' => array_values($numeros),
        ];
    }

    /** @param array<string, mixed> $datos */
    public function guardarAppMeta(array $datos): array
    {
        $this->exigirAdministrador();
        $conexion = $this->conexionDeEmpresa($this->idEmpresa());

        $appId = preg_replace('/\D+/', '', (string) ($datos['meta_app_id'] ?? '')) ?? '';
        $secret = trim((string) ($datos['app_secret'] ?? ''));
        if ($appId === '') {
            throw new \InvalidArgumentException('Falta el identificador de la app.');
        }
        if ($secret === '' && ! $conexion->tieneAppSecret()) {
            throw new \InvalidArgumentException('Falta la clave secreta.');
        }
        if ($secret !== '' && ! preg_match('/^[a-f0-9]{32}$/i', $secret)) {
            throw new \InvalidArgumentException('La clave secreta no parece correcta. Cópiala de nuevo desde Meta.');
        }

        $conexion->meta_app_id = $appId;
        if ($secret !== '') {
            $conexion->app_secret = $secret;
        }
        $this->guardarConexion($conexion);

        return $this->estado();
    }

    /** Un token nuevo invalida la verificación anterior: hay que volver a verificar en Meta. */
    public function regenerarToken(): array
    {
        $this->exigirAdministrador();
        $conexion = $this->conexionDeEmpresa($this->idEmpresa());
        $conexion->verify_token = Cifrado::tokenAleatorio();
        $conexion->webhook_verificado_at = null;
        $this->guardarConexion($conexion);

        return $this->estado();
    }

    /** @param array<string, mixed> $datos */
    public function conectarNumero(array $datos): array
    {
        $this->exigirAdministrador();
        $idEmpresa = $this->idEmpresa();

        $phoneNumberId = preg_replace('/\D+/', '', (string) ($datos['phone_number_id'] ?? '')) ?? '';
        $wabaId = preg_replace('/\D+/', '', (string) ($datos['whatsapp_business_account_id'] ?? '')) ?? '';
        $token = trim((string) ($datos['access_token'] ?? ''));
        if ($phoneNumberId === '' || $wabaId === '' || $token === '') {
            throw new \InvalidArgumentException('Completa los tres datos del número.');
        }

        $conexion = $this->conexionDeEmpresa($idEmpresa);
        if (! $conexion->tieneAppSecret()) {
            throw new \DomainException('Primero completa el paso 1: los datos de tu app de Meta.');
        }
        if ($conexion->webhook_verificado_at === null) {
            throw new \DomainException('Primero completa el paso 2: conecta la recepción de mensajes en Meta.');
        }

        $existente = $this->appPorPhoneNumberId($phoneNumberId);
        if ($existente !== null && (int) $existente->id_empresa !== $idEmpresa && $existente->deleted_at === null) {
            throw new \DomainException('Este número ya está conectado en otra empresa.');
        }

        $numero = $this->meta->obtenerNumero($phoneNumberId, $token);
        if (! $this->meta->numeroPerteneceACuenta($wabaId, $phoneNumberId, $token)) {
            throw new \DomainException('Ese número no pertenece a la cuenta de WhatsApp Business que pegaste.');
        }
        $this->meta->suscribirApp($wabaId, $token);

        $db = db_connect();
        if ($existente !== null && (int) $existente->id_empresa !== $idEmpresa) {
            // Número que otra empresa desconectó: se libera para esta.
            $db->table('app')->where('id', $existente->id)->update(['phone_number_id' => null]);
            $existente = null;
        }

        $nombre = trim((string) ($datos['nombre'] ?? '')) ?: ($numero['verified_name'] ?: 'WhatsApp');
        $app = $existente ?? new AppEntity();
        $app->id_empresa = $idEmpresa;
        $app->id_portafolio = $app->id_portafolio ?: $this->portafolioDeEmpresa($idEmpresa);
        $app->nombre = mb_substr($nombre, 0, 100);
        $app->telefono = mb_substr(preg_replace('/\D+/', '', $numero['display_phone_number']) ?? '', 0, 20);
        $app->phone_number_id = $phoneNumberId;
        $app->whatsapp_business_account_id = $wabaId;
        $app->access_token = $token;
        $app->canal = 'whatsapp';
        $app->modo = $app->modo ?: 'individual';
        $app->tipo = $app->tipo ?: 'NORMAL';
        $app->max_usuarios = $app->max_usuarios ?: 1;
        $app->estado = 1;
        $app->verificado_at = date('Y-m-d H:i:s');

        $modelo = $this->apps();
        if ($existente !== null && $existente->deleted_at !== null) {
            $db->table('app')->where('id', $existente->id)->update(['deleted_at' => null]);
        }
        if (! $modelo->save($app)) {
            throw new \DomainException(implode(' ', $modelo->errors()));
        }
        $idApp = (int) ($app->id ?: $modelo->getInsertID());
        $this->asignarAdministrador($idApp);
        unset($this->conectadas[$idEmpresa]);

        return $this->estado();
    }

    public function desconectarNumero(int $idApp): array
    {
        $this->exigirAdministrador();
        $app = $this->apps()->find($idApp);
        if ($app === null) {
            throw new \DomainException('El número no existe en esta empresa.');
        }

        $this->apps()->update($idApp, ['estado' => 0]);
        $this->apps()->delete($idApp);
        unset($this->conectadas[$this->idEmpresa()]);

        return $this->estado();
    }

    /**
     * Corta toda la conexión: números, clave de la app y verificación del webhook.
     * El enlace anterior queda inservible. Las conversaciones y los mensajes se conservan.
     */
    public function desconectar(): array
    {
        $this->exigirAdministrador();
        $idEmpresa = $this->idEmpresa();
        $ahora = date('Y-m-d H:i:s');
        $db = db_connect();

        $db->transStart();
        $db->table('app')
            ->where('id_empresa', $idEmpresa)
            ->where('canal', 'whatsapp')
            ->where('deleted_at', null)
            ->update(['estado' => 0, 'access_token' => null, 'verificado_at' => null, 'deleted_at' => $ahora]);
        $db->table('conexion_meta')
            ->where('id_empresa', $idEmpresa)
            ->update([
                'meta_app_id' => null,
                'app_secret' => null,
                'webhook_clave' => Cifrado::tokenAleatorio(16),
                'verify_token' => Cifrado::tokenAleatorio(),
                'webhook_verificado_at' => null,
                'ultimo_evento_at' => null,
            ]);
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new \RuntimeException('No pudimos desconectar WhatsApp. Intenta de nuevo.');
        }
        unset($this->conectadas[$idEmpresa]);

        return $this->estado();
    }

    /** Hay al menos un número verificado con Meta y activo. */
    public function estaConectada(?int $idEmpresa = null): bool
    {
        $idEmpresa ??= $this->idEmpresaOpcional();
        if ($idEmpresa === null) {
            return false;
        }

        return $this->conectadas[$idEmpresa] ??= db_connect()->table('app')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', 1)
            ->where('deleted_at', null)
            ->where('verificado_at IS NOT NULL', null, false)
            ->where('phone_number_id IS NOT NULL', null, false)
            ->where('access_token IS NOT NULL', null, false)
            ->countAllResults() > 0;
    }

    /* ---------- Webhook (sin sesión: se identifica la empresa por la clave de la URL) ---------- */

    public function conexionPorClave(string $clave): ?ConexionMetaEntity
    {
        if (! preg_match('/^[a-f0-9]{32,64}$/', $clave)) {
            return null;
        }

        return model(ConexionMetaModel::class, false)->where('webhook_clave', $clave)->first();
    }

    /** Devuelve el challenge si Meta envió el token de esta empresa. */
    public function verificarSuscripcion(ConexionMetaEntity $conexion, string $modo, string $token, string $challenge): ?string
    {
        if ($modo !== 'subscribe' || $token === '' || ! hash_equals((string) $conexion->verify_token, $token)) {
            return null;
        }

        db_connect()->table('conexion_meta')->where('id', $conexion->id)->update([
            'webhook_verificado_at' => date('Y-m-d H:i:s'),
        ]);

        return $challenge;
    }

    public function firmaValida(ConexionMetaEntity $conexion, string $cuerpo, string $firma): bool
    {
        $secret = $conexion->app_secret;
        if ($secret === null || $secret === '' || ! str_starts_with($firma, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $cuerpo, $secret), $firma);
    }

    public function registrarEvento(ConexionMetaEntity $conexion): void
    {
        db_connect()->table('conexion_meta')->where('id', $conexion->id)->update([
            'ultimo_evento_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /* ---------- Internos ---------- */

    private function conexionDeEmpresa(int $idEmpresa): ConexionMetaEntity
    {
        $modelo = model(ConexionMetaModel::class, false);
        $conexion = $modelo->where('id_empresa', $idEmpresa)->first();
        if ($conexion !== null) {
            return $conexion;
        }

        $conexion = new ConexionMetaEntity([
            'id_empresa' => $idEmpresa,
            'webhook_clave' => Cifrado::tokenAleatorio(16),
            'verify_token' => Cifrado::tokenAleatorio(),
        ]);
        $this->guardarConexion($conexion);

        return $modelo->where('id_empresa', $idEmpresa)->first();
    }

    private function guardarConexion(ConexionMetaEntity $conexion): void
    {
        $modelo = model(ConexionMetaModel::class, false);
        if (! $conexion->hasChanged() && $conexion->id) {
            return;
        }
        if (! $modelo->save($conexion)) {
            throw new \DomainException(implode(' ', $modelo->errors()));
        }
    }

    /** @return list<AppEntity> */
    private function numerosDeEmpresa(): array
    {
        return $this->apps()->where('canal', 'whatsapp')->orderBy('id', 'ASC')->findAll();
    }

    /** @return array<string, mixed> */
    private function presentarNumero(AppEntity $app): array
    {
        $verificado = (int) $app->estado === 1 && $app->verificado_at !== null && $app->tieneToken() && ! empty($app->phone_number_id);

        return [
            'id' => (int) $app->id,
            'nombre' => (string) $app->nombre,
            'telefono' => (string) ($app->telefono ?? ''),
            'phone_number_id' => (string) ($app->phone_number_id ?? ''),
            'whatsapp_business_account_id' => (string) ($app->whatsapp_business_account_id ?? ''),
            'tiene_token' => $app->tieneToken(),
            'verificado' => $verificado,
            'verificado_at' => $this->fecha($app->verificado_at),
        ];
    }

    private function appPorPhoneNumberId(string $phoneNumberId): ?AppEntity
    {
        $fila = db_connect()->table('app')->where('phone_number_id', $phoneNumberId)->get()->getRowArray();
        if ($fila === null) {
            return null;
        }

        $app = new AppEntity();
        $app->injectRawData($fila);

        return $app;
    }

    private function portafolioDeEmpresa(int $idEmpresa): int
    {
        $db = db_connect();
        $fila = $db->table('portafolio')
            ->select('id')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($fila !== null) {
            return (int) $fila['id'];
        }

        $db->table('portafolio')->insert([
            'id_empresa' => $idEmpresa,
            'nombre' => 'Principal',
            'estado' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    /** En modo individual los chats nuevos se asignan a quien conectó el número. */
    private function asignarAdministrador(int $idApp): void
    {
        $usuario = auth()->getUsuario();
        if ($usuario === null) {
            return;
        }

        $db = db_connect();
        $asignados = $db->table('usuario_app')->where('id_app', $idApp)->where('deleted_at', null)->countAllResults();
        if ($asignados > 0) {
            return;
        }

        $db->table('usuario_app')->insert([
            'id_app' => $idApp,
            'id_usuario' => (int) $usuario->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function apps(): AppModel
    {
        $clase = $this->config->app_provider;

        return new $clase();
    }

    private function fecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return $valor instanceof \DateTimeInterface ? $valor->format('Y-m-d H:i:s') : (string) $valor;
    }

    private function idEmpresa(): int
    {
        $id = $this->idEmpresaOpcional();
        if ($id === null) {
            throw new \DomainException('No hay empresa en sesión.');
        }

        return $id;
    }

    private function idEmpresaOpcional(): ?int
    {
        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);

        return $id > 0 ? $id : null;
    }

    private function exigirAdministrador(): void
    {
        Autorizacion::exigirAdministradorEmpresa('Solo el administrador puede conectar WhatsApp.');
    }
}
