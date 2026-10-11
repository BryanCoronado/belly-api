<?php

namespace Belly\Modulos\UseCases;

use CodeIgniter\I18n\Time;
use Belly\Modulos\Config\ModulosConfig;
use Belly\Modulos\Entities\ModuloEntity;
use Belly\Modulos\Models\ModuloModel;

class ModulosUseCase {

    protected ModulosConfig $config;
    protected ?ModuloModel $modulo_provider = NULL;

    public function __construct(ModulosConfig $config) {
        $this->config = $config;
    }

    public function getModuloProvider(): ModuloModel {
        if ($this->modulo_provider !== null) {
            return $this->modulo_provider;
        }

        $class_name = $this->config->modulo_provider;
        $this->modulo_provider = new $class_name();

        return $this->modulo_provider;
    }

    public function getAlias(array $datos) {
        $nombre = str_replace(' ', '_', mb_strtolower($datos['nombre']));

        if(isset($datos['parent_id']) && $datos['parent_id'] != null){
            $alias_padre = $this->obtenerModulo($datos['parent_id'])->alias;
            $alias = trim($alias_padre . '_' . $nombre);
            return $alias;
        } else if (isset($datos['nombre']) && !empty($datos['nombre'])) {
            $alias = trim('mod_' . $nombre);
            return $alias;
        } else {
            throw new \Exception(lang('Modulos.noExisteNombre'));
        }
    }

    public function crearModulo(array $datos) {
        $modulo_model = $this->getModuloProvider();
        $alias = $this->getAlias($datos);

        // Validar que el grupo permiso exista sea  igual a id_modulo_grupo
        $db = \Config\Database::connect();
        $grupoPermiso = $db->table('grupo_permiso')->where('id', $datos['id_modulo_grupo'])->get()->getRow();
        if (!$grupoPermiso) {
            throw new \Exception('modulo_grupo no es iagual a un grupo_permiso existente');
        }

        $modulo_entity = new ModuloEntity();
        $modulo_entity->id_modulo_grupo = $datos['id_modulo_grupo'];
        $modulo_entity->nombre = ucfirst($datos['nombre']);
        $modulo_entity->alias = $alias;
        $modulo_entity->descripcion = $datos['descripcion'] ?? null;
        $modulo_entity->activo = $datos['activo'][0] ?? false;
        $modulo_entity->orden = $datos['orden'] ?? 0.0;
        $modulo_entity->url = $datos['url'] ?? null;
        $modulo_entity->icon = $datos['icon'] ?? null;
        $modulo_entity->parent_id = $datos['parent_id'] ?? null;

        if ($modulo_model->save($modulo_entity)) {
            $modulo_entity->id = $modulo_model->insertID;
            // creamos el permiso
            $dataPermiso = [
                'id_grupo_permiso' => $modulo_entity->id_modulo_grupo,
                'permiso' => 'Acceso al Mod.'.$modulo_entity->nombre,
                'alias' => $modulo_entity->alias.'_access',
                'descripcion' => null,
                'activo' => 1,
            ];
            $permiso = permiso()->crear($dataPermiso);
            $modulo_entity->id_permiso = $permiso->id;
            $modulo_model->update($modulo_entity->id, ['id_permiso' => $permiso->id]);
        } else {
            throw new \Exception(json_encode($modulo_model->errors()));
        }

        return $modulo_entity;
    }

    public function obtenerModulo(int $id) {
        if (!isset($id)) {
            throw new \Exception(lang('Modulos.noIdModulo'));
        }
        $modulo_model = $this->getModuloProvider();

        $modulo = $modulo_model->find($id);
        if(!$modulo){
            throw new \Exception($modulo_model->error()['message']);
        }
        return $modulo;
    }

    public function listarModulos($page = 1, $perPage = 10, $search = null) {
        $modulo_model = $this->getModuloProvider();
        $modulo_model->orderBy('orden', 'ASC');
        if (!empty($search)) {
            $modulo_model->like('nombre', $search);
        }
        $modulo_model->where('deleted_at', null);

        // Si perPage es 'all', traer todos los registros
        if ($perPage === 'all') {
            $result = $modulo_model->select(['id', 'nombre', 'url', 'icon','parent_id', 'descripcion', 'activo', 'orden'])->findAll();
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

        $result = $modulo_model->select(['id', 'nombre', 'descripcion', 'activo', 'orden', 'parent_id'])->paginate($perPage, 'default', $page);

        $pager = $modulo_model->pager;
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

    public function editarModulo(int $id, array $datos) {
        $modulo_model = $this->getModuloProvider();

        if (!isset($id)) {
            throw new \Exception(lang('Modulos.noIdModulo'));
        }
        $alias = $this->getAlias($datos);

        $modulo_model->find($id);
        if (!empty($modulo_model->find($id))) {
            $modulo_entity = new ModuloEntity();
            $modulo_entity->id = $id;
            //$modulo_entity->id_permiso = $datos['id_permiso'];
            $modulo_entity->nombre = $datos['nombre'];
            $modulo_entity->alias = $alias;
            $modulo_entity->descripcion = $datos['descripcion'] ?? null;
            $modulo_entity->parent_id = $datos['parent_id'] ?? null;
            $modulo_entity->activo = $datos['activo'][0] ?? false;
            $modulo_entity->orden = $datos['orden'] ?? 0.0;

            if ($modulo_model->save($modulo_entity)) {
                $modulo_entity->id = $id;
                return $modulo_entity;
            } else {
                throw new \Exception(lang('Modulos.noEditModulo'));
            }
        } else {
            throw new \Exception(lang('Modulos.noModulo'));
        }
    }

    public function borrarModulo(int $id = 0) {
        $modulo_model = $this->getModuloProvider();
        $modulo_model->find($id);

        if (isset($id) && !empty($modulo_model->find($id))) {
            if (!$modulo_model->delete($id)) {
                throw new \Exception(lang('Modulos.noDeleteModulo'));
            }
            return 'Se eliminó correctamente';
        } else {
            throw new \Exception(lang('Modulos.noIdModulo'));
        }
    }
    
    public function getMenu(){
        $modulo_model = $this->getModuloProvider();

        $modulos = $modulo_model->select([
            'modulo.id',
            'modulo.nombre',
            'modulo.url',
            'modulo.icon',
            'modulo.descripcion',
            'modulo.activo',
            'modulo.orden',
            'modulo.id_permiso',
            'modulo.parent_id',
            'permiso.alias as alias'
            ])
            ->join('permiso', 'permiso.id = modulo.id_permiso', 'left')
            ->where('modulo.activo', 1)
            ->whereNotIn('modulo.id_modulo_grupo', [1])
            ->where('modulo.deleted_at', null)
            ->orderBy('modulo.orden', 'ASC')
            ->get()->getResultArray();

        return $this->construirArbolModulos($modulos);
    }

    private function construirArbolModulos(array $modulos, $parentId = null): array
    {
        $rama = [];

        foreach ($modulos as $modulo) {
            if ($modulo['parent_id'] == $parentId) {
                $hijos = $this->construirArbolModulos($modulos, $modulo['id']);
                $modulo['parents'] = $hijos;
                $rama[] = $modulo;
            }
        }

        return $rama;
    }
}
