<?php

namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Entities\GrupoUsuarioRolEntity;
use Belly\Usuarios\Models\GrupoUsuarioRolModel;

class GrupoUsuarioRolUseCase
{
    protected ?GrupoUsuarioRolModel $grupo_provider = NULL;
    protected UsuarioConfig $config;

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
    }

    public function getGrupoUsuarioRolProvider(): GrupoUsuarioRolModel
    {
        if ($this->grupo_provider !== null) {
            return $this->grupo_provider;
        }

        $className = $this->config->grupo_provider;
        $this->grupo_provider = new $className();

        return $this->grupo_provider;
    }

    public function listar_usuarios_rol(int $page = 1, $perPage = 10, ?string $search = null) 
    {
        $grupo_rol_model = $this->getGrupoUsuarioRolProvider();
        $grupo_rol_model->orderBy('rol', 'ASC');
        if (!empty($search)) {
            $grupo_rol_model->groupStart();
            $grupo_rol_model->like('rol', $search);
            $grupo_rol_model->orLike('alias', $search);
            $grupo_rol_model->groupEnd();
        }

        if($perPage == 'all'){
            $grupoRol = $grupo_rol_model->findAll();
            $total = count($grupoRol);
            return [
                'data' => $grupoRol,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ]
            ];
        }
        $result = $grupo_rol_model->paginate($perPage, 'default', $page);
        $pager = $grupo_rol_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPage = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = ($total > 0) ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = ($from + count($result)) - 1;


        return [
            'data' => $result,
            'pagination' => [
                'total' => $total,
                'currentPage' => $currentPage,
                'from' => $from,
                'to' => $to,
                'hasNext' => $currentPage < $lastPage,
                'hasPrev' => $currentPage > 1,
            ],
        ];
    }

    public function obtenerRol(int $id)
    {
        $grupo_rol_model = $this->getGrupoUsuarioRolProvider();
        if (isset($id) && $id !== null) {
            $grupo_rol_entity = $grupo_rol_model->find($id);
            if (!empty($grupo_rol_entity)) {
                return $grupo_rol_entity;
            } else {
                throw new \Exception(lang('UsuariosL.noIdGrupoUserRol'));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }
    public function crear(array $datos)
    {
        $grupo_rol_model = $this->getGrupoUsuarioRolProvider();

        $grupo_rol_entity = new GrupoUsuarioRolEntity();
        // $grupo_rol_entity->id_empresa = auth()->getUsuario()->id_empresa;
        $grupo_rol_entity->rol = $datos['rol'];
        $grupo_rol_entity->alias = $datos['alias'];
        $grupo_rol_entity->descripcion = $datos['descripcion'];

        if (!$grupo_rol_model->save($grupo_rol_entity)) {
            throw new \Exception(json_encode($grupo_rol_model->errors()));
        } else {
            return $grupo_rol_entity;
        }
    }

    public function editar(array $datos, int $id)
    {
        $grupo_rol_model = $this->getGrupoUsuarioRolProvider();

        if (isset($id)) {
            if (!empty($grupo_rol_model->find($id))) {
                $grupo_rol_entity = new GrupoUsuarioRolEntity();
                $grupo_rol_entity->id = $id;
                $grupo_rol_entity->rol = $datos['rol'];
                $grupo_rol_entity->alias = $datos['alias'];
                $grupo_rol_entity->descripcion = $datos['descripcion'];

                if (!$grupo_rol_model->save($grupo_rol_entity)) {
                    throw new \Exception(json_encode($grupo_rol_model->errors()));
                }
                return $grupo_rol_entity;
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function eliminar(int $id)
    {
        $grupo_rol_model = $this->getGrupoUsuarioRolProvider();
        if (isset($id) && $id !== null) {
            $existe_grupo = $grupo_rol_model->find($id);

            if (!empty($existe_grupo)) {
                if (!$grupo_rol_model->delete($id)) {
                    throw new \Exception(lang('UsuariosL.noDeleteGrupoUserRol'));
                }

                return 'se eliminó correctamente';
            } else {
                throw new \Exception(lang('UsuariosL.noIdGrupoUserRol'));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }
}
