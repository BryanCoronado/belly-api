<?php

namespace Belly\Permisos\UseCases;

use Belly\Permisos\Config\PermisosConfig;
use Belly\Permisos\Entities\GrupoPermisoEntity;
use Belly\Permisos\Models\GrupoPermisoModel;

class GrupoPermisoUseCase
{
    protected ?GrupoPermisoModel $grupo_permiso_provider = NULL;
    protected PermisosConfig $config;

    public function __construct(PermisosConfig $config)
    {
        $this->config = $config;
    }

    public function getGrupoPermisoProvider(): GrupoPermisoModel
    {
        if ($this->grupo_permiso_provider !== null) {
            return $this->grupo_permiso_provider;
        }

        $className = $this->config->grupo_permiso_provider;
        $this->grupo_permiso_provider = new $className();

        return $this->grupo_permiso_provider;
    }

    public function listar()
    {
        $grupo_model = $this->getGrupoPermisoProvider();
        $result = $grupo_model->select(['id', 'grupo', 'descripcion'])->paginate(10);
        if (empty($result)) {
            return [
                'success' => false,
                'message' => 'No hay grupos de permisos registrados.',
                'data' => []
            ];
        }
        return [
            'success' => true,
            'data' => $result
        ];
    }

    public function crear(array $datos)
    {
        $grupo_permiso_model = $this->getGrupoPermisoProvider();

        $grupo_permiso_entity = new GrupoPermisoEntity();
        $grupo_permiso_entity->grupo = $datos['grupo'];
        $grupo_permiso_entity->descripcion = $datos['descripcion'];

        if (!$grupo_permiso_model->save($grupo_permiso_entity)) {
            throw new \Exception(json_encode($grupo_permiso_model->errors()));
        } else {
            $grupo_permiso_entity->id = $grupo_permiso_model->insertID;
            return $grupo_permiso_entity;
        }
    }

    public function obtenerGrupo(int $id)
    {
        $grupo_permiso_model = $this->getGrupoPermisoProvider();

        if ($id !== null && isset($id)) {
            return $grupo_permiso_model->select(['id', 'grupo', 'descripcion'])->where(['id' => $id])->first();
        } else {
            throw new \Exception(lang('Permisos.noExisteId'));
        }
    }

    public function editar(array $datos, int $id)
    {
        $grupo_permiso_model = $this->getGrupoPermisoProvider();

        if (isset($id)) {
            if (!empty($grupo_permiso_model->find($id))) {
                $grupo_permiso_entity = new GrupoPermisoEntity();
                $grupo_permiso_entity->id = $id;
                $grupo_permiso_entity->grupo = $datos['grupo'];
                $grupo_permiso_entity->descripcion = $datos['descripcion'];

                if (!$grupo_permiso_model->save($grupo_permiso_entity)) {
                    throw new \Exception(json_encode($grupo_permiso_model->errors()));
                }
                return $grupo_permiso_entity;
            }
        } else {
            throw new \Exception(lang('Permisos.noIdGrupoPermiso'));
        }
    }

    public function eliminar(int $id)
    {
        $grupo_permiso_model = $this->getGrupoPermisoProvider();

        if (isset($id) && $id !== null) {
            $existe_grupo = $grupo_permiso_model->find($id);

            if (!empty($existe_grupo)) {
                if (!$grupo_permiso_model->delete($id)) {
                    throw new \Exception(lang('Permisos.noDeleteGrupoPermisos'));
                }

                return 'se eliminó correctamente';
            } else {
                throw new \Exception(lang('Permisos.noIdGrupoPermiso'));
            }
        } else {
            throw new \Exception(lang('Permisos.noExisteId'));
        }
    }
}
