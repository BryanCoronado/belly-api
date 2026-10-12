<?php

namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Entities\GrupoUsuarioEntity;
use Belly\Usuarios\Models\GrupoUsuarioModel;

class GrupoUsuarioUseCase
{
    protected ?GrupoUsuarioModel $grupo_usuario_provider = NULL;
    protected UsuarioConfig $config;

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
    }

    public function getGrupoUsuarioProvider(): GrupoUsuarioModel
    {
        if ($this->grupo_usuario_provider !== null) {
            return $this->grupo_usuario_provider;
        }

        $className = $this->config->grupo_usuario_provider;
        $this->grupo_usuario_provider = new $className();

        return $this->grupo_usuario_provider;
    }

    public function listar($page = 1, $perPage = 10, $search = null)
    {
        $grupo_usuario_model = $this->getGrupoUsuarioProvider();
        // $grupo_usuario_model->where('id_empresa', auth()->getUsuario()->id_empresa); // preguntar a luis o quitar
        $grupo_usuario_model->orderBy('grupo', 'ASC');

        if (!empty($search)) {
            $grupo_usuario_model->groupStart();
            $grupo_usuario_model->like('grupo', $search);
            $grupo_usuario_model->orLike('descripcion', $search);
            $grupo_usuario_model->groupEnd();
        }

        if ($perPage == 'all') {
            $grupo = $grupo_usuario_model->findAll();
            $total = count($grupo);
            return [
                'data' => $grupo,
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

        $result = $grupo_usuario_model->paginate($perPage, 'default', $page);
        $pager = $grupo_usuario_model->pager;
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

    public function crear(array $datos)
    {
        $grupo_usuario_model = $this->getGrupoUsuarioProvider();

        helper('vip2system');
        $idEmpresa = (int) (id_empresa_sesion() ?? 0);
        if ($idEmpresa <= 0) {
            throw new \Exception('No hay empresa en sesión.');
        }

        $grupo_usuario_entity = new GrupoUsuarioEntity();
        $grupo_usuario_entity->id_empresa = $idEmpresa;
        $grupo_usuario_entity->grupo = $datos['grupo'];
        $grupo_usuario_entity->descripcion = $datos['descripcion'];

        if (!$grupo_usuario_model->save($grupo_usuario_entity)) {
            throw new \Exception(json_encode($grupo_usuario_model->errors()));
        } else {
            $grupo_usuario_entity->id = $grupo_usuario_model->insertID;
            return $grupo_usuario_entity;
        }
    }

    public function getById(int $id)
    {
        $grupo_usuario_model = $this->getGrupoUsuarioProvider();

        if (isset($id) && $id !== null) {
            $grupo_usuario_entity = $grupo_usuario_model->find($id);
            if (!empty($grupo_usuario_entity)) {
                return $grupo_usuario_entity;
            } else {
                throw new \Exception(lang('UsuariosL.noIdGrupoUser'));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function editar(array $datos, int $id)
    {
        $grupo_usuario_model = $this->getGrupoUsuarioProvider();

        if (isset($id)) {
            if (!empty($grupo_usuario_model->find($id))) {
                $grupo_usuario_entity = new GrupoUsuarioEntity();
                $grupo_usuario_entity->id = $id;
                $grupo_usuario_entity->grupo = $datos['grupo'];
                $grupo_usuario_entity->descripcion = $datos['descripcion'];

                if (!$grupo_usuario_model->save($grupo_usuario_entity)) {
                    throw new \Exception(json_encode($grupo_usuario_model->errors()));
                }
                return $grupo_usuario_entity;
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function eliminar(int $id)
    {
        $grupo_usuario_model = $this->getGrupoUsuarioProvider();

        if (isset($id) && $id !== null) {
            $existe_grupo = $grupo_usuario_model->find($id);

            if (!empty($existe_grupo)) {
                if (!$grupo_usuario_model->delete($id)) {
                    throw new \Exception(lang('UsuariosL.noDeleteGrupoUser'));
                }

                return 'se eliminó correctamente';
            } else {
                throw new \Exception(lang('UsuariosL.noIdGrupoUser'));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function listBySelect()
    {
        $grupo_usuario_model = $this->getGrupoUsuarioProvider();
        $grupo_usuario_model->select(['id', 'grupo']);
        // $grupo_usuario_model->where('id_empresa', auth()->getUsuario()->id_empresa); // preguntar a luis o quitar
        $grupo_usuario_model->orderBy('grupo', 'ASC');

        $result = $grupo_usuario_model->findAll();

        return $result;
    }
}
