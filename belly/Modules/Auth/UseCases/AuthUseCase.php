<?php

namespace Belly\Auth\UseCases;

use CodeIgniter\I18n\Time;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CodeIgniter\DataCaster\DataCaster;
use Belly\Auth\Config\AuthConfig;
use Belly\Auth\Entities\UsuarioLoginEntity;
use Belly\Usuarios\Entities\UsuarioEntity;
use Belly\Usuarios\Models\UsuarioModel;
use Belly\Auth\Models\UsuarioLoginModel;

class AuthUseCase
{
    protected AuthConfig $config;
    protected ?UsuarioModel $usuario_provider = NULL;
    protected ?UsuarioLoginModel $login_provider = NULL;
    protected ?UsuarioEntity $usuario = NULL;
    protected ?UsuarioLoginEntity $login_sesion = NULL;

    // --- Providers arriba ---
    public function __construct(AuthConfig $config)
    {
        $this->config = $config;
    }


    public function getUsuario(): ?UsuarioEntity
    {
        return $this->usuario;
    }


    public function validarUsuarioRegistrado(string $username, ?int $idEmpresa = null): array {
        $usuario_model = $this->getUsuarioProvider();
        $usuario_model->where([
            'usuario' => $username
        ]);
        if ($idEmpresa !== null && $idEmpresa > 0) {
            $usuario_model->where('id_empresa', $idEmpresa);
        }

        $this->usuario = $usuario_model->first();

        return (isset($this->usuario) && !empty($this->usuario)) ? $this->usuario->toArray() : [];
    }

    /** Entra con correo o, si no se cruza, con el usuario. */
    public function validarUsuarioPorIdentificador(string $identificador): array
    {
        $identificador = trim($identificador);
        $this->usuario = null;
        if ($identificador === '') {
            return [];
        }

        $db = db_connect();
        $modelo = $this->getUsuarioProvider();

        if (str_contains($identificador, '@')) {
            $fila = $db->table('usuario_perfil p')
                ->select('p.id_usuario')
                ->join('usuario u', 'u.id = p.id_usuario')
                ->where('p.correo', strtolower($identificador))
                ->where('p.deleted_at', null)
                ->where('u.deleted_at', null)
                ->get()
                ->getRow();
            $this->usuario = $fila ? $modelo->find((int) $fila->id_usuario) : null;
        } else {
            $filas = $db->table('usuario')
                ->select('id')
                ->where('usuario', $identificador)
                ->where('deleted_at', null)
                ->get()
                ->getResult();
            if (count($filas) > 1) {
                throw new \RuntimeException('VARIAS_CUENTAS');
            }
            $this->usuario = $filas !== [] ? $modelo->find((int) $filas[0]->id) : null;
        }

        return $this->usuario ? $this->usuario->toArray() : [];
    }

    public function validarPassword(string $password)
    {
        return password_verify($password, $this->usuario->password);
    }

    public function incrementarIntentosFallidos() {
        $usuario_model = $this->getUsuarioProvider();
        $this->usuario->login_fallidos = $this->usuario->login_fallidos + 1;

        if ($this->usuario->login_fallidos >= $this->config->inttentos_permitidos) {
            $this->bloquearCuenta();
        }

        $usuario_model->save($this->usuario);
    }

    public function bloquearCuenta() {
        $this->usuario->cuenta_bloqueada = 1;

        $fecha_bloqueo = new Time('+' . $this->config->tiempo_bloqueo . ' minutes');
        $this->usuario->fecha_bloqueo = $fecha_bloqueo->setSecond(0);
    }

    public function desbloquearCuenta() {
        $usuario_model = $this->getUsuarioProvider();

        $this->usuario->cuenta_bloqueada = 0;
        $this->usuario->fecha_bloqueo = NULL;
        $this->usuario->login_fallidos = 0;

        if ($this->usuario->hasChanged()) {
            $usuario_model->save($this->usuario);
        }
    }

    public function generarJWTKey(string $hash)
    {
        return password_hash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function generarToken(): string
    {
        return $this->emitirToken('sesion', $this->config->vigencia_token, 'hours');
    }

    public function generarTokenDescarga(): string
    {
        if ($this->usuario === null) {
            throw new \Exception('No autenticado.');
        }

        return $this->emitirToken('descarga', $this->config->vigencia_token_descarga, 'minutes');
    }

    public function cerrarSesionesUsuario(int $idUsuario): void
    {
        if ($idUsuario <= 0) {
            return;
        }

        $modelo = new ($this->config->login_provider)();
        $modelo->where('id_usuario', $idUsuario)->delete();
    }

    /**
     * Genera un JWT de impersonación: el supervisor (ya autenticado) obtiene
     * un token válido para operar como $idAsesor.
     * Solo puede hacerlo un usuario con sudo=1 o roles 1 (Programador) / 2 (Administrador).
     *
     * @param int $idAsesor  ID del usuario a impersonar
     * @param int $idSupervisor  ID de quien solicita la impersonación (para log)
     * @return string JWT
     * @throws \Exception si no tiene permisos o el asesor no existe
     */
    public function generarTokenImpersonacion(int $idAsesor, int $idSupervisor): string
    {
        $usuario_model = $this->getUsuarioProvider();
        $asesor = $usuario_model->find($idAsesor);

        if (!$asesor) {
            throw new \Exception('El usuario a impersonar no existe.');
        }

        if (intval($asesor->activo) === 0) {
            throw new \Exception('El usuario a impersonar está inactivo.');
        }

        // Guardar el usuario original, usar temporalmente el del asesor
        $usuarioOriginal = $this->usuario;
        $this->usuario   = $asesor;

        $key = $this->generarJWTKey($asesor->password);
        $payload = $this->generarPayloadImpersonacion($key, $idSupervisor);
        $jwt = JWT::encode($payload, $key, 'HS256');

        $this->usuario = $usuarioOriginal;

        return $jwt;
    }

    private function generarPayloadImpersonacion(string $tokenKey, int $idSupervisor): array
    {
        $emision = (new Time())->subMinutes(3);
        $expira = (clone $emision)->addHours(8);
        $jti = $this->registrarSesion($tokenKey);

        $db = \Config\Database::connect();
        $db->table('impersonacion_log')->insert([
            'id_supervisor' => $idSupervisor,
            'id_asesor' => $this->usuario->id,
            'id_login' => $jti,
            'ip_address' => service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->armarPayload($jti, $emision, $expira, 'sesion', [
            'imp' => true,
            'imp_by' => $idSupervisor,
        ]);
    }

    public function isValid(string $header_token, string $usoEsperado = 'sesion'): bool {
        $payload = $this->getPayloadFromHeader($header_token);
        $this->login_sesion = null;

        if (! isset($payload->jti, $payload->sub, $payload->inf->id)) {
            return false;
        }

        $idUsuario = intval($payload->sub) === intval($payload->inf->id) ? (int) $payload->sub : 0;
        if ($idUsuario === 0) {
            return false;
        }

        $login_model = $this->getLoginProvider();
        $token = $login_model
            ->where('id', (int) $payload->jti)
            ->where('id_usuario', $idUsuario)
            ->first();

        if (! isset($token) || empty($token)) {
            return false;
        }

        $decode = JWT::decode($header_token, new Key($token->token_key, 'HS256'));
        $uso = (string) ($decode->uso ?? 'sesion');
        if ($uso !== $usoEsperado) {
            return false;
        }

        $usuario_model = $this->getUsuarioProvider();
        $usuario_model->where([
            'id' => $decode->inf->id,
            'usuario' => $decode->inf->usuario,
        ]);
        $idEmpresaToken = (int) ($decode->inf->id_empresa ?? $decode->inf->id_entidad ?? 0);
        if ($idEmpresaToken > 0) {
            $usuario_model->where('id_empresa', $idEmpresaToken);
        }

        $usuario = $usuario_model->first();

        if (isset($usuario) && !empty($usuario)) {
            if (intval($usuario->activo) === 0) {
                throw new \Exception(lang('Auth.cuentaInactiva'));

                return FALSE;
            }

            if (intval($usuario->cuenta_bloqueada) === 1) {
                throw new \Exception(lang('Auth.cuentaBloqueadaPorAdmin'));

                return FALSE;
            }

            $this->usuario = $usuario;
            $this->login_sesion = $token;

            return TRUE;
        }

        return FALSE;
    }

    public function getSesionActual(): ?UsuarioLoginEntity
    {
        return $this->login_sesion;
    }

    public function borrarToken() {
        $login_model = $this->getLoginProvider();

        if ($this->login_sesion === null) {
            return false;
        }

        $delete = $login_model->delete($this->login_sesion->id);

        return $delete;
    }

    private function getPayloadFromHeader(string $header) {
        $tks = explode('.', $header);

        list($headb64, $bodyb64, $cryptob64) = $tks;

        $payloadRaw = JWT::urlsafeB64Decode($bodyb64);
        $payload = JWT::jsonDecode($payloadRaw);

        return $payload;
    }

    private function emitirToken(string $uso, int $vigencia, string $unidad): string
    {
        $key = $this->generarJWTKey($this->usuario->password);
        $emision = (new Time())->subMinutes(3);
        $expira = clone $emision;
        if ($unidad === 'minutes') {
            $expira = $expira->addMinutes($vigencia);
        } else {
            $expira = $expira->addHours($vigencia);
        }

        $payload = $this->armarPayload($this->registrarSesion($key), $emision, $expira, $uso);

        return JWT::encode($payload, $key, 'HS256');
    }

    private function registrarSesion(string $tokenKey): int
    {
        $login = new UsuarioLoginEntity();
        $login->fill([
            'id_usuario' => $this->usuario->id,
            'token_key' => $tokenKey,
            'ip_address' => service('request')->getIPAddress(),
        ]);

        $loginModel = $this->getLoginProvider();
        if (! $loginModel->save($login)) {
            throw new \Exception(lang('Auth.errorTokenKey'));
        }

        return (int) $loginModel->insertID;
    }

    private function datosIdentidad(): array
    {
        $idEmpresa = (int) ($this->usuario->id_empresa ?? 0);

        return [
            'id' => (int) $this->usuario->id,
            'id_empresa' => $idEmpresa,
            'id_entidad' => $idEmpresa,
            'usuario' => $this->usuario->usuario,
            'activo' => (int) $this->usuario->activo,
            'maestro' => (int) $this->usuario->sudo,
            'cambio_passw' => (int) $this->usuario->forzar_cambio_pw,
        ];
    }

    private function armarPayload(int $jti, Time $emision, Time $expira, string $uso, array $extra = []): array
    {
        return array_merge([
            'iss' => base_url(),
            'aud' => base_url(),
            'iat' => $emision->getTimestamp(),
            'nbf' => $emision->getTimestamp(),
            'exp' => $expira->getTimestamp(),
            'jti' => $jti,
            'sub' => (int) $this->usuario->id,
            'uso' => $uso,
            'inf' => $this->datosIdentidad(),
        ], $extra);
    }

    /*
     * Crea una instancia del modelo UsuarioLoginModel para registrar los inicios de sesion 
     * de los usuarios
     */

    public function getLoginProvider(): UsuarioLoginModel {
        if ($this->login_provider !== null) {
            return $this->login_provider;
        }

        $className = $this->config->login_provider;
        $this->login_provider = new $className();

        return $this->login_provider;
    }

    /*
     * Crea una instancia del modelo UsuarioModel para trabajar con los datos de los usuarios
     */

    public function getUsuarioProvider(): UsuarioModel {
        if ($this->usuario_provider !== null) {
            return $this->usuario_provider;
        }

        $className = $this->config->usuario_provider;
        $this->usuario_provider = new $className();

        return $this->usuario_provider;
    }

    public function getProcedure() {
        $types = [
            'id_usuario' => 'int',
            'id_permiso' => 'int',
            'acc_crear' => '?int-bool',
            'acc_lectura' => '?int-bool',
            'acc_actualizar' => '?int-bool',
            'acc_eliminar' => '?int-bool',
        ];
        $id_user = $this->usuario->id;

        $db = \Config\Database::connect();
        $query = $db->query("CALL get_usuario_permisos('$id_user');");
        $results = $query->getResult();

        $unique_results = [];
        foreach ($results as $result) {
            $unique_results[$result->id_permiso] = $result;
        }
        $results = array_values($unique_results);

        $dataCaster = new DataCaster([], $types);

        foreach ($results as $i => $result) {
            foreach (array_keys($types) as $field) {
                if (property_exists($result, $field)) {
                    $result->$field = $dataCaster->castAs($result->$field, $field, 'get');
                }
            }
        }

        return $results;
    }

    public function getRoles() {
        $id_user = $this->usuario->id;

        $db = \Config\Database::connect();
        $roles = $db->table('grupo_usuario_usuario as guu')
            ->select('gur.id, gur.rol, gur.alias')
            ->join('grupo_usuario_rol as gur', 'gur.id = guu.id_grupo_usuario_rol', 'inner')
            ->where('guu.id_usuario', $id_user)
            ->where('guu.deleted_at', null)
            ->where('gur.deleted_at', null)
            ->orderBy('gur.rol', 'ASC')
            ->get()
            ->getResult();

        return $roles;
    }
}
