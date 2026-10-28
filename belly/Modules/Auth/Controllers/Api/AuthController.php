<?php

namespace Belly\Auth\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\I18n\Time;

class AuthController extends BaseController
{
    use ResponseTrait;

    public function registro()
    {
        $datos = $this->request->getJSON(true);
        if (! is_array($datos)) {
            return $this->fail('Datos inválidos.', 400);
        }

        try {
            $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
            if ($correo === '' || ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return $this->fail('El correo es obligatorio.', 400);
            }
            if ($this->correoYaUsado($correo)) {
                return $this->fail('Ese correo ya tiene una cuenta. Entra con él.', 400);
            }

            $usuario = trim((string) ($datos['username'] ?? ''));
            if ($usuario === '') {
                $usuario = $this->usuarioDesdeCorreo($correo);
            }

            $creada = empresa()->registrar([
                'nombre' => $datos['nombre'] ?? '',
                'slug' => '',
                'admin' => [
                    'usuario' => $usuario,
                    'password' => $datos['password'] ?? '',
                    'nombres' => $datos['nombres'] ?? '',
                    'apellidos' => $datos['apellidos'] ?? '',
                    'correo' => $correo,
                    'celular' => $this->celularRegistro($datos),
                    'nro_documento' => $datos['nro_documento'] ?? '',
                    'id_tipo_documento' => 1,
                ],
            ]);

            return $this->respond([
                'success' => true,
                'empresa' => $creada['slug'],
                'usuario' => $creada['admin']['usuario'],
            ], 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function login()
    {
        $datos = $this->request->getJSON(true);
        if (! is_array($datos)) {
            return $this->failUnauthorized('Datos de acceso inválidos.', 'Auth');
        }

        $slug = trim((string) ($datos['empresa'] ?? ''));
        $username = trim((string) ($datos['username'] ?? ''));
        $correo = trim((string) ($datos['correo'] ?? ''));

        try {
            if ($slug !== '' && $username !== '') {
                $empresa = empresa()->buscarActivaPorSlug($slug);
                if ($empresa === null) {
                    return $this->failUnauthorized('No encontramos esa cuenta.', 'Auth');
                }
                $usuario = auth()->validarUsuarioRegistrado($username, (int) $empresa->id);
            } else {
                $identificador = $correo !== '' ? $correo : $username;
                if ($identificador === '') {
                    return $this->failUnauthorized('El correo y la contraseña son obligatorios.', 'Auth');
                }
                $usuario = auth()->validarUsuarioPorIdentificador($identificador);
                $empresa = $usuario !== []
                    ? empresa()->buscarActivaPorId((int) ($usuario['id_empresa'] ?? 0))
                    : null;
                if ($empresa === null && $usuario !== []) {
                    return $this->failUnauthorized('Empresa no encontrada o inactiva.', 'Auth');
                }
            }
        } catch (\RuntimeException $exc) {
            if ($exc->getMessage() === 'VARIAS_CUENTAS') {
                return $this->failUnauthorized('Hay más de una cuenta con ese usuario. Entra con tu correo.', 'Auth');
            }
            throw $exc;
        }

        if (empty($usuario) || ! isset($empresa)) {
            return $this->failUnauthorized('No encontramos esa cuenta.', 'Auth');
        }

        if (intval($usuario['activo']) === 0) {
            return $this->failUnauthorized(lang('Auth.cuentaInactiva'), 'Auth');
        }

        if (intval($usuario['cuenta_bloqueada']) === 1) {
            $tiempo = $usuario['fecha_bloqueo']->addSeconds(60)->difference(Time::now());
            if ($tiempo->getSeconds() < -60) {
                return $this->failUnauthorized(lang('Auth.cuentaBloqueada', [$usuario['fecha_bloqueo']->addSeconds(60)->humanize()]), 'Auth');
            }
        }

        if (!auth()->validarPassword($datos['password'])) {
            auth()->incrementarIntentosFallidos();
            return $this->failUnauthorized(lang('Auth.datosIncorrectos'), 'Auth');
        }

        auth()->desbloquearCuenta();
        $usuario = auth()->getUsuario();

        $usuarioModel = model('UsuarioModel');
        $usuarioModel->update($usuario->id, ['id_estado_usuario' => 1]);

        try {
            $perfil = $usuario->perfil;
            $response = [
                'success' => true,
                'messages' => 'Inicio de sesión exitoso.',
                'token' => auth()->generarToken(),
                'usuario' => [
                    'id' => (int) $usuario->id,
                    'id_empresa' => (int) ($usuario->id_empresa ?? 0),
                    'empresa' => $empresa->slug,
                    'empresa_nombre' => $empresa->nombre,
                    'usuario' => $usuario->usuario,
                    'nombres' => $perfil->nombres ?? null,
                    'apellidos' => $perfil->apellidos ?? null,
                    'foto' => $perfil->foto_url ?? null,
                    'sudo' => (int) ($usuario->sudo ?? 0),
                ],
            ];
            return $this->respond($response, 200);
        } catch (\Exception $exc) {
            return $this->failUnauthorized($exc->getMessage(), 'Auth');
        }
    }

    public function tokenDescarga()
    {
        try {
            return $this->respond([
                'success' => true,
                'token' => auth()->generarTokenDescarga(),
                'expira_en' => config('AuthConfig')->vigencia_token_descarga * 60,
            ], 200);
        } catch (\Exception $exc) {
            return $this->failUnauthorized($exc->getMessage(), 'Auth');
        }
    }

    /** Lo consulta el servidor de sockets para saber a qué empresa unir la conexión. */
    public function tiempoReal()
    {
        $usuario = auth()->getUsuario();
        $idEmpresa = id_empresa_sesion();
        if (! $usuario || ! $idEmpresa) {
            return $this->failUnauthorized('No autenticado.', 'Auth');
        }

        return $this->respond([
            'success' => true,
            'data' => ['id_usuario' => (int) $usuario->id, 'id_empresa' => $idEmpresa],
        ], 200);
    }

    public function logout()
    {
        $usuario  = auth()->getUsuario();
        $sesion   = auth()->getSesionActual(); // UsuarioLoginEntity de la sesión activa

        // Si la sesión es de impersonación (token generado por supervisor),
        // solo borrar el token sin cambiar el estado real de la asesora.
        $esImpersonacion = false;
        if ($sesion) {
            // Verificar si existe registro en impersonacion_log para este login
            $db = \Config\Database::connect();
            $esImpersonacion = (bool) $db->table('impersonacion_log')
                ->where('id_login', $sesion->id)
                ->countAllResults();
        }

        if (!$esImpersonacion && $usuario) {
            // Solo cambiar a offline en logout real, no en cierre de sesión de supervisión
            $usuarioModel = model('UsuarioModel');
            $usuarioModel->update($usuario->id, ['id_estado_usuario' => 6]);
        }

        $success = auth()->borrarToken();
        return $this->respond([
            'success'  => $success,
            'messages' => $success ? 'Sesión cerrada correctamente.' : 'No se pudo cerrar la sesión.'
        ], 200);
    }

    public function getProcedure()
    {
        try {
            $procedure = auth()->getProcedure();
            return $this->respond([
                'success' => true,
                'messages' => 'Permisos.',
                'data' => $procedure
            ], 200);
        } catch (\Exception $exc) {
            return $this->failUnauthorized($exc->getMessage(), 'Auth');
        }
    }

    public function getRoles()
    {
        try {
            $roles = auth()->getRoles();
            return $this->respond([
                'success' => true,
                'messages' => 'Roles.',
                'data' => $roles
            ], 200);
        } catch (\Exception $exc) {
            return $this->failUnauthorized($exc->getMessage(), 'Auth');
        }
    }

    /**
     * Genera un JWT para operar como otro usuario.
     * Solo accesible por roles 1 (Programador), 2 (Administrador), 7 (Personal Marketing) o sudo=1.
     *
     * POST /v1/auth/impersonar/{idAsesor}
     *
     * Reglas de seguridad:
     *  - El supervisor debe tener sudo=1 o rol 1/2/7
     *  - No se puede impersonar a un usuario sudo
     *  - No se puede impersonar a uno mismo
     */
    public function impersonar(int $idAsesor)
    {
        $supervisor = auth()->getUsuario();

        if (!$supervisor) {
            return $this->failUnauthorized('No autenticado.', 'Auth');
        }

        $db = \Config\Database::connect();

        // Verificar que tenga rol 1, 2, 7 o sea sudo
        if (intval($supervisor->sudo) !== 1) {
            $roles = $db->table('grupo_usuario_usuario guu')
                ->select('gur.id as id_rol')
                ->join('grupo_usuario_rol gur', 'gur.id = guu.id_grupo_usuario_rol')
                ->where('guu.id_usuario', $supervisor->id)
                ->where('guu.deleted_at', null)
                ->whereIn('gur.id', [1, 2, 7])
                ->get()
                ->getResult();

            if (empty($roles)) {
                return $this->failForbidden('No tienes permisos para supervisar usuarios.', 'Auth');
            }
        }

        // No impersonarse a uno mismo
        if ($idAsesor === (int) $supervisor->id) {
            return $this->fail('No puedes supervisarte a ti mismo.', 400, null, 'Auth');
        }

        // No impersonar a usuarios con sudo (administradores)
        $asesor = $db->table('usuario')
            ->select('id, sudo, activo, deleted_at')
            ->where('id', $idAsesor)
            ->get()
            ->getRow();

        if (!$asesor || $asesor->deleted_at !== null) {
            return $this->fail('El usuario solicitado no existe.', 404, null, 'Auth');
        }

        if (intval($asesor->activo) === 0) {
            return $this->fail('El usuario solicitado está inactivo.', 422, null, 'Auth');
        }

        if (intval($asesor->sudo) === 1) {
            return $this->failForbidden('No se puede supervisar a un usuario administrador.', 'Auth');
        }

        try {
            $token = auth()->generarTokenImpersonacion($idAsesor, $supervisor->id);
            return $this->respond([
                'success'  => true,
                'messages' => 'Sesión de supervisión iniciada.',
                'token'    => $token,
            ], 200);
        } catch (\Exception $exc) {
            return $this->failServerError($exc->getMessage(), 'Auth');
        }
    }

    private function correoYaUsado(string $correo): bool
    {
        return db_connect()->table('usuario_perfil')
            ->where('correo', $correo)
            ->where('deleted_at', null)
            ->countAllResults() > 0;
    }

    private function usuarioDesdeCorreo(string $correo): string
    {
        $base = strtolower((string) strstr($correo, '@', true));
        $base = preg_replace('/[^a-z0-9]+/', '', $base) ?? '';
        $base = substr($base, 0, 20);

        return $base !== '' ? $base : 'admin';
    }

    /** Deja el celular con código de país, solo dígitos. */
    private function celularRegistro(array $datos): ?string
    {
        $codigo = preg_replace('/\D/', '', (string) ($datos['codigo_pais'] ?? '')) ?? '';
        $numero = preg_replace('/\D/', '', (string) ($datos['celular'] ?? '')) ?? '';
        if ($numero === '') {
            return null;
        }
        if ($codigo !== '' && ! str_starts_with($numero, $codigo)) {
            $numero = $codigo . ltrim($numero, '0');
        }

        $largo = strlen($numero);

        return $largo >= 8 && $largo <= 15 ? $numero : null;
    }
}