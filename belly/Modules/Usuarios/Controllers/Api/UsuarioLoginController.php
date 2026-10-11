<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use Belly\Usuarios\Entities\UsuarioEntity;

class UsuarioLoginController extends BaseController
{

    use ResponseTrait;

    public function user()
    {
        $usuario = auth()->getUsuario();
        $usuario->perfil = $usuario->perfil;

        unset($usuario->codigo_recuperacion, $usuario->deleted_at, $usuario->fecha_bloqueo, $usuario->fecha_recuperacion);
        unset($usuario->login_fallidos, $usuario->password, $usuario->fecha_bloqueo, $usuario->updated_at);
        unset($usuario->perfil->id_usuario, $usuario->perfil->created_at, $usuario->perfil->updated_at, $usuario->perfil->deleted_at);

        // Obtener nombre y color del estado
        $db = \Config\Database::connect();
        $estado = $db->table('estado_usuario')
            ->select('nombre, color')
            ->where('id', $usuario->id_estado_usuario)
            ->get()
            ->getRow();

        // Construir el array de datos para la respuesta, insertando los campos en la posición deseada
        $data = $usuario->toArray();
        $estado_nombre = $estado ? $estado->nombre : null;
        $estado_color = $estado ? $estado->color : null;

        // Reordenar para que queden después de id_estado_usuario
        $data = array_merge(
            array_slice($data, 0, array_search('id_estado_usuario', array_keys($data)) + 1),
            [
                'nombre_estado_usuario' => $estado_nombre,
                'color_estado_usuario' => $estado_color
            ],
            array_slice($data, array_search('id_estado_usuario', array_keys($data)) + 1)
        );

        if (is_array($data['perfil'] ?? null)) {
            unset(
                $data['perfil']['sip_password'],
                $data['perfil']['sip_en_uso'],
                $data['perfil']['sip_lock_token'],
                $data['perfil']['sip_lock_at'],
                $data['perfil']['sip_lock_user_agent'],
            );
        }

        if ($usuario instanceof \Belly\Usuarios\Entities\UsuarioEntity) {
            $response = [
                'success' => TRUE,
                'data' => $data
            ];
            return $this->respond($response, 200);
        }

        return $this->fail($usuario->errors, 404, NULL, 'Resource Not Found');
    }
}
