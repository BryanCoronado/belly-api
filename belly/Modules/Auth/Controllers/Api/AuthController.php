<?php

namespace Belly\Auth\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Auth\UseCases\RegistroValidador;
use Belly\Services\Autorizacion;
use Belly\Services\Ritmo;
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
            Ritmo::exigir('registro', 5, 600);
            $validador = new RegistroValidador();
            $limpios = $validador->validar($datos);

            if ($this->correoYaUsado($limpios['correo'])) {
                return $this->fail('Ese correo ya tiene una cuenta. Entra con él.', 400);
            }

            $usuario = trim((string) ($datos['username'] ?? ''));
            if ($usuario === '') {
                $usuario = $this->usuarioDesdeCorreo($limpios['correo']);
            }

            $creada = empresa()->registrar([
                'nombre' => $limpios['nombre'],
                'slug' => '',
                'admin' => [
                    'usuario' => $usuario,
                    'password' => $limpios['password'],
                    'nombres' => $limpios['nombres'],
                    'apellidos' => $limpios['apellidos'],
                    'correo' => $limpios['correo'],
                    'celular' => $limpios['celular'],
                    'nro_documento' => $datos['nro_documento'] ?? '',
                    'id_tipo_documento' => 1,
                ],
            ]);

            $validador->marcarAlta();

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

        try {
            Ritmo::exigir('login', 12, 60);
        } catch (\RuntimeException $e) {
            return $this->failUnauthorized($e->getMessage(), 'Auth');
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
                if ($usuario !== [] && ! str_contains($identificador, '@') && (int) ($usuario['operador'] ?? 0) !== 1) {
                    return $this->failUnauthorized('Entra con tu correo.', 'Auth');
                }
                $empresa = $usuario !== []
                    ? empresa()->buscarPorId((int) ($usuario['id_empresa'] ?? 0))
                    : null;
                if ($empresa === null && $usuario !== []) {
                    return $this->failUnauthorized('No encontramos esa cuenta.', 'Auth');
                }
                $esDueño = $usuario !== [] && Autorizacion::esOperadorDe((object) $usuario);
                if ($empresa !== null && (int) $empresa->estado !== 1 && ! $esDueño) {
                    return $this->failUnauthorized(lang('Auth.empresaPausada'), 'Auth');
                }
            }
        } catch (\RuntimeException $exc) {
            if ($exc->getMessage() === 'VARIAS_CUENTAS') {
                return $this->failUnauthorized('No encontramos esa cuenta.', 'Auth');
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
                    'nombres' => $this->textoUtf8($perfil->nombres ?? null),
                    'apellidos' => $this->textoUtf8($perfil->apellidos ?? null),
                    'foto' => $perfil->foto_url ?? null,
                    'sudo' => (int) ($usuario->sudo ?? 0),
                    'operador' => \Belly\Services\Autorizacion::esOperadorDe($usuario),
                    'modo_ahorro' => empresa()->modoAhorro((int) ($usuario->id_empresa ?? 0)),
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
     * JWT para operar como otro usuario de la misma empresa (admin) o de cualquier empresa (dueño).
     */
    public function impersonar(int $idAsesor)
    {
        $supervisor = auth()->getUsuario();

        if (! $supervisor) {
            return $this->failUnauthorized('No autenticado.', 'Auth');
        }

        $dueño = Autorizacion::esOperadorPlataforma();
        if (! $dueño && ! Autorizacion::esAdministradorEmpresa()) {
            return $this->failForbidden('No tienes permisos para supervisar usuarios.', 'Auth');
        }

        if ($idAsesor === (int) $supervisor->id) {
            return $this->fail('No puedes supervisarte a ti mismo.', 400, null, 'Auth');
        }

        $asesor = db_connect()->table('usuario')
            ->select('id, id_empresa, sudo, operador, activo, deleted_at')
            ->where('id', $idAsesor)
            ->get()
            ->getRow();

        if (! $asesor || $asesor->deleted_at !== null) {
            return $this->fail('El usuario solicitado no existe.', 404, null, 'Auth');
        }

        if (! $dueño && (int) $asesor->id_empresa !== (int) ($supervisor->id_empresa ?? 0)) {
            return $this->fail('El usuario solicitado no existe.', 404, null, 'Auth');
        }

        if ((int) $asesor->activo === 0) {
            return $this->fail('El usuario solicitado está inactivo.', 422, null, 'Auth');
        }

        if ((int) ($asesor->operador ?? 0) === 1 || ((int) $asesor->sudo === 1 && ! $dueño)) {
            return $this->failForbidden('No se puede supervisar a ese usuario.', 'Auth');
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

    private function textoUtf8(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = (string) $valor;
        if ($texto === '') {
            return '';
        }
        if (! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        return $texto;
    }
}