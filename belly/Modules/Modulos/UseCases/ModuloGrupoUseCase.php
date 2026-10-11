<?php

namespace Belly\Modulos\UseCases;

use Belly\Modulos\Config\ModulosConfig;
use Belly\Modulos\Entities\ModuloGrupoEntity;
use Belly\Modulos\Models\ModuloGrupoModel;

class ModuloGrupoUseCase
{
    protected ModulosConfig $modulos_config;
    protected ?ModuloGrupoModel $modulo_grupo_model = NULL;

    public function __construct(ModulosConfig $modulos_config)
    {
        $this->modulos_config = $modulos_config;
    }


        public function getModuloGrupoProvider(): ModuloGrupoModel
    {
        if ($this->modulo_grupo_model !== null) {
            return $this->modulo_grupo_model;
        }

        $class_name = $this->modulos_config->modulo_grupo_provider;
        $this->modulo_grupo_model = new $class_name;
        return $this->modulo_grupo_model;
    }

    public function crearGrupo(array $datos)
    {
        $modulo_grupo_model = $this->getModuloGrupoProvider();

        $modulo_grupo_entity = new ModuloGrupoEntity();
        
        $modulo_grupo_entity->grupo = ucfirst($datos['grupo']);

        if ($modulo_grupo_model->save($modulo_grupo_entity)) {
            $modulo_grupo_entity->id = $modulo_grupo_model->insertID;
        } else {
            throw new \Exception(json_encode($modulo_grupo_model->errors()));
        }

        return $modulo_grupo_entity;
    }


    public function listarGrupos()
    {
        $modulo_grupo_model = $this->getModuloGrupoProvider();

        return $modulo_grupo_model->select(['id', 'grupo'])->paginate(10);
    }

    public function editar(int $id, array $datos)
    {
        $modulo_grupo_model = $this->getModuloGrupoProvider();

        $modulo_grupo_model->find($id);

        if (isset($id) && !empty($modulo_grupo_model->find($id))) {
            $modulo_grupo_entity = new ModuloGrupoEntity();
            $modulo_grupo_entity->id = $id;
            $modulo_grupo_entity->grupo = ucfirst($datos['grupo']);
    
            if ($modulo_grupo_model->save($modulo_grupo_entity)) {
                $modulo_grupo_entity->id = $id;
            } else {
                throw new \Exception(lang('Modulos.noEditGrupo'));
            }
            return [
                'grupo' => $modulo_grupo_entity,
            ];
        }else {
            throw new \Exception(lang('Modulos.noIdGrupo'));
        }
    }

    public function eliminar(int $id = 0): void
    {
        $modulo_grupo_model = $this->getModuloGrupoProvider();
        $modulo_grupo_model->find($id);
        if (isset($id) && !empty($modulo_grupo_model->find($id))) {
            if (!$modulo_grupo_model->delete($id)) {
                throw new \Exception(lang('Modulos.noDeleteModulo'));
            }
        } else {
            throw new \Exception(lang('Modulos.noIdGrupo'));
        }
    }
}
