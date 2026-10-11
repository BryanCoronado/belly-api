<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class HorarioGrupoUsuarioRolController extends BaseController
{
    public function listar()
    {
        try {
            $page = $this->request->getGet('page') ?? 1;
            $perPage = $this->request->getGet('perPage') ?? 10;
            $idGrupoUsuarioRol = $this->request->getGet('id_grupo_usuario_rol');

            $resultado = horario_grupo_usuario_rol()->listar(
                $idGrupoUsuarioRol ? (int) $idGrupoUsuarioRol : null,
                (int) $page,
                $perPage === 'all' ? 'all' : (int) $perPage
            );

            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado['data'] ?? [],
                'pagination' => $resultado['pagination'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    public function listarRolesSinHorario()
    {
        try {
            $roles = horario_grupo_usuario_rol()->listarRolesSinHorario();

            return $this->response->setJSON([
                'success' => true,
                'data' => $roles,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    public function listarPorRol(int $id_grupo_usuario_rol)
    {
        try {
            $horarios = horario_grupo_usuario_rol()->listarPorRol($id_grupo_usuario_rol);

            return $this->response->setJSON([
                'success' => true,
                'data' => $horarios,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    public function ver(int $id = 0)
    {
        try {
            $horario = horario_grupo_usuario_rol()->obtener($id);

            return $this->response->setJSON([
                'success' => true,
                'data' => $horario,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            if (empty($datos)) {
                throw new \Exception('Datos requeridos.');
            }

            $horario = horario_grupo_usuario_rol()->crear($datos);
            $db->transComplete();

            return $this->response->setJSON([
                'success' => true,
                'data' => $horario,
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    public function editar(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            if (empty($datos)) {
                throw new \Exception('Datos requeridos.');
            }

            $horario = horario_grupo_usuario_rol()->editar($datos, $id);
            $db->transComplete();

            return $this->response->setJSON([
                'success' => true,
                'data' => $horario,
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $mensaje = horario_grupo_usuario_rol()->eliminar($id);

            return $this->response->setJSON([
                'success' => true,
                'message' => $mensaje,
                'data' => $id,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }
}
