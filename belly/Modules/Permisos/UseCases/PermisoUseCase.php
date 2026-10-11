<?php

namespace Belly\Permisos\UseCases;

use Belly\Permisos\Config\PermisosConfig;
use Belly\Permisos\Entities\PermisoEntity;
use Belly\Permisos\Models\PermisoModel;

class PermisoUseCase
{
    protected ?PermisoModel $permiso_provider = NULL;
    protected PermisosConfig $config;

    public function __construct(PermisosConfig $config)
    {
        $this->config = $config;
    }

    public function getPermisoProvider(): PermisoModel
    {
        if ($this->permiso_provider !== null) {
            return $this->permiso_provider;
        }

        $className = $this->config->permiso_provider;
        $this->permiso_provider = new $className();

        return $this->permiso_provider;
    }

    public function listar($page = 1, $perPage = 10, $search = null)
    {
        $permiso_model = $this->getPermisoProvider();
        $permiso_model->select(['permiso.id', 'permiso.id_grupo_permiso', 'gp.grupo', 'permiso.permiso', 'permiso.alias', 'permiso.descripcion', 'permiso.activo']);
        $permiso_model->join('grupo_permiso as gp', 'gp.id = permiso.id_grupo_permiso', 'inner');

        if (!empty($search)) {
            $permiso_model->groupStart();
            $permiso_model->like('permiso.permiso', $search);
            $permiso_model->orLike('gp.grupo', $search);
            $permiso_model->groupEnd();
        }
        $permiso_model->where('permiso.deleted_at', null);

        // Si perPage es 'all', traer todos los registros
        if ($perPage === 'all') {
            $result = $permiso_model->findAll();

            $total = count($result);
            return [
                'data' => $result,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ],
            ];
        }

        $result = $permiso_model->paginate($perPage, 'default', $page);

        $pager = $permiso_model->pager;
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
        $permiso_model = $this->getPermisoProvider();
        
        $permiso_entity = new PermisoEntity();
        $permiso_entity->id_grupo_permiso = $datos['id_grupo_permiso'] ?? null;
        $permiso_entity->permiso = $datos['permiso'];
        $permiso_entity->alias = $datos['alias'];
        $permiso_entity->descripcion = $datos['descripcion'];
        $permiso_entity->activo = true;
  
        if (!$permiso_model->save($permiso_entity)) {
            throw new \Exception(json_encode($permiso_model->errors()));
        } else {
            $permiso_entity->id = $permiso_model->insertID;
            return $permiso_entity;
        }
    }

    public function obtenerPermiso(int $id)
    {
        $permiso_model = $this->getPermisoProvider();

        if ($id !== null && isset($id)) {
            if (!empty($permiso_model->find($id))) {
                return $permiso_model
                    ->select(['permiso.id', 'permiso.id_grupo_permiso', 'gp.grupo', 'permiso.permiso', 'permiso.alias', 'permiso.descripcion', 'permiso.activo'])
                    ->join('grupo_permiso as gp', 'gp.id = permiso.id_grupo_permiso', 'inner')
                    ->where(['permiso.id' => $id])->first();
            } else {
                throw new \Exception(lang('Permisos.noIdPermiso'));
            }
        } else {
            throw new \Exception(lang('Permisos.noExisteId'));
        }
    }

    public function editar(array $datos, int $id)
    {
        $permiso_model = $this->getPermisoProvider();

        if (isset($id)) {
            if (!empty($permiso_model->find($id))) {
                $permiso_entity = new PermisoEntity();
                $permiso_entity->id = $id;
                $permiso_entity->id_grupo_permiso = $datos['id_grupo_permiso'];
                $permiso_entity->permiso = $datos['permiso'];
                $permiso_entity->alias = $datos['alias'];
                $permiso_entity->descripcion = $datos['descripcion'];
                $permiso_entity->activo = $datos['activo'];

                if (!$permiso_model->save($permiso_entity)) {
                    throw new \Exception(json_encode($permiso_model->errors()));
                }
                return $permiso_entity;
            }
        } else {
            throw new \Exception(lang('Permisos.noIdPermiso'));
        }
    }

    public function cambiarEstadoPermiso(array $datos, int $id)
    {
        $permiso_model = $this->getPermisoProvider();
        $existe_permiso = $permiso_model->find($id);
        
        error_log("🔍 ID: " . $id);
        error_log("📦 Datos recibidos: " . json_encode($datos));
        error_log("🔍 Permiso encontrado: " . json_encode($existe_permiso));
        
        if (!empty($existe_permiso)) {
            error_log("🔄 Estado actual: " . $existe_permiso->activo);
            error_log("🔄 Estado nuevo: " . $datos['activo']);
            
            $resultado = $permiso_model->update($id, ['activo' => $datos['activo']]);
            
            error_log("✅ Resultado update: " . ($resultado ? 'true' : 'false'));
            
            if ($resultado) {
                return [
                    'id_usuario' => $existe_permiso->id,
                    'usuario' => $existe_permiso->permiso,
                    'activo' => $datos['activo']
                ];
            } else {
                error_log("❌ Errores del modelo: " . json_encode($permiso_model->errors()));
                throw new \Exception(json_encode($permiso_model->errors()));
            }
        } else {
            throw new \Exception(lang('Permisos.noIdPermiso'));
        }
    }

    public function eliminar(int $id)
    {
        $permiso_model = $this->getPermisoProvider();

        if (isset($id) && $id !== null) {
            $existe_permiso = $permiso_model->find($id);

            if (!empty($existe_permiso)) {
                if (!$permiso_model->delete($id)) {
                    throw new \Exception(lang('Permisos.noDeletePermisos'));
                }

                return 'se eliminó correctamente';
            } else {
                throw new \Exception(lang('Permisos.noIdPermiso'));
            }
        } else {
            throw new \Exception(lang('Permisos.noExisteId'));
        }
    }
}
