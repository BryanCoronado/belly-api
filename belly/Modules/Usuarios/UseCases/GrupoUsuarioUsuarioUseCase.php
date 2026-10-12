<?php

namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Entities\GrupoUsuarioUsuarioEntity;
use Belly\Usuarios\Models\GrupoUsuarioUsuarioModel;

class GrupoUsuarioUsuarioUseCase
{
    protected ?GrupoUsuarioUsuarioModel $grupo_usuario_usuario_provider = NULL;
    protected UsuarioConfig $config;

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
    }
    
    public function getGrupoUsuarioUsuarioProvider(): GrupoUsuarioUsuarioModel
    {
        if ($this->grupo_usuario_usuario_provider !== null) {
            return $this->grupo_usuario_usuario_provider;
        }

        $className = $this->config->grupo_usuario_usuario_provider;
        $this->grupo_usuario_usuario_provider = new $className();

        return $this->grupo_usuario_usuario_provider;
    }

    public function listar()
    {
        $grupo_usuario_us_model = $this->getGrupoUsuarioUsuarioProvider();

        return $grupo_usuario_us_model->select(['id', 'id_grupo_usuario', 'id_usuario', 'id_grupo_usuario_rol'])->findAll();
    }

    public function listarPorGrupoUsuario($id_grupo_usuario){
        $grupo_usuario_usuario_model = $this->getGrupoUsuarioUsuarioProvider();

        $builder = $grupo_usuario_usuario_model->builder();
        $builder->select([
            'grupo_usuario_usuario.id',
            'grupo_usuario_usuario.id_grupo_usuario',
            'grupo_usuario_usuario.id_usuario',
            'grupo_usuario_usuario.id_grupo_usuario_rol',
            'usuario.usuario',
            'usuario_perfil.nombres',
            'usuario_perfil.apellidos',
            'tipo_documento.tipo as nombre_documento',
            'usuario_perfil.nro_documento',
            'usuario_perfil.correo',
            'grupo_usuario.grupo',
            'grupo_usuario_rol.rol'
        ]);
        $builder->join('usuario', 'usuario.id = grupo_usuario_usuario.id_usuario', 'inner');
        $builder->join('usuario_perfil', 'usuario_perfil.id_usuario = grupo_usuario_usuario.id_usuario', 'inner');
        $builder->join('tipo_documento', 'tipo_documento.id = usuario_perfil.id_tipo_documento', 'left');
        $builder->join('grupo_usuario', 'grupo_usuario.id = grupo_usuario_usuario.id_grupo_usuario', 'inner');
        $builder->join('grupo_usuario_rol', 'grupo_usuario_rol.id = grupo_usuario_usuario.id_grupo_usuario_rol', 'inner');
        $builder->where('grupo_usuario_usuario.id_grupo_usuario', $id_grupo_usuario);
        $builder->where('grupo_usuario_usuario.deleted_at', null);

        return $builder->get()->getResultArray();

    }


    public function crear(array $datos)
    {
        $grupo_usuario_us_model = $this->getGrupoUsuarioUsuarioProvider();

        $existe = $grupo_usuario_us_model
            ->where('id_grupo_usuario', $datos['id_grupo_usuario'])
            ->where('id_usuario', $datos['id_usuario'])
            ->where('id_grupo_usuario_rol', $datos['id_grupo_usuario_rol'])
            ->where('deleted_at', null)
            ->first();

        if ($existe) {
            throw new \Exception('El usuario ya está asignado a este grupo con el rol indicado.');
        }

        $grupo_usuario_us_entity = new GrupoUsuarioUsuarioEntity();
        $grupo_usuario_us_entity->id_grupo_usuario = $datos['id_grupo_usuario'];
        $grupo_usuario_us_entity->id_usuario = $datos['id_usuario'];
        $grupo_usuario_us_entity->id_grupo_usuario_rol = $datos['id_grupo_usuario_rol'];

        if (!$grupo_usuario_us_model->save($grupo_usuario_us_entity)) {
            throw new \Exception(json_encode($grupo_usuario_us_model->errors()));
        } else {
            $grupo_usuario_us_entity->id = $grupo_usuario_us_model->insertID;
            return $grupo_usuario_us_entity;
        }
    }

    public function editar(array $datos, int $id)
    {
        $grupo_usuario_us_model = $this->getGrupoUsuarioUsuarioProvider();

        if (isset($id)) {
            if (!empty($grupo_usuario_us_model->find($id))) {
                $grupo_usuario_us_entity = new GrupoUsuarioUsuarioEntity();
                $grupo_usuario_us_entity->id = $id;
                $grupo_usuario_us_entity->id_grupo_usuario = $datos['id_grupo_usuario'];
                $grupo_usuario_us_entity->id_usuario = $datos['id_usuario'];
                $grupo_usuario_us_entity->id_grupo_usuario_rol = $datos['id_grupo_usuario_rol'];

                if (!$grupo_usuario_us_model->save($grupo_usuario_us_entity)) {
                    throw new \Exception(json_encode($grupo_usuario_us_model->errors()));
                }
                return $grupo_usuario_us_entity;
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function eliminar(int $id)
    {
        $grupo_usuario_us_model = $this->getGrupoUsuarioUsuarioProvider();

        if (isset($id) && $id !== null) {
            $existe_grupo = $grupo_usuario_us_model->find($id);

            if (!empty($existe_grupo)) {
                if (!$grupo_usuario_us_model->delete($id)) {
                    throw new \Exception(lang('UsuariosL.noDeleteGrupoUserUsuario'));
                }

                return 'se eliminó correctamente';
            } else {
                throw new \Exception(lang('UsuariosL.noIdGrupoUserUsuario'));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function listBySelectUser()
    {
        $grupo_usuario_model = $this->getGrupoUsuarioUsuarioProvider();
        $grupo_usuario_model->select(['grupo_usuario.id', 'grupo_usuario.grupo']);
        $grupo_usuario_model->where('id_usuario', auth()->getUsuario()->id);
        $grupo_usuario_model->join('grupo_usuario', 'grupo_usuario.id = grupo_usuario_usuarios.id_grupo_usuario', 'inner');
        $grupo_usuario_model->orderBy('grupo_usuario.id', 'ASC');

        $result = $grupo_usuario_model->findAll();

        return $result;
    }

    public function listByOperaciones()
    {
        $grupo_usuario_model = $this->getGrupoUsuarioUsuarioProvider();
        $grupo_usuario_model->select(['usuario.id', "CONCAT(up.nombres, ' ', up.apellidos) as usuario"]);
        $grupo_usuario_model->where('grupo_usuario_usuarios.id_grupo_usuario', 3);
        $grupo_usuario_model->whereIn('grupo_usuario_usuarios.id_grupo_usuario_rol', [5, 6]);
        $grupo_usuario_model->join('grupo_usuario', 'grupo_usuario.id = grupo_usuario_usuarios.id_grupo_usuario', 'inner');
        $grupo_usuario_model->join('usuario', 'usuario.id = grupo_usuario_usuarios.id_usuario', 'inner');
        $grupo_usuario_model->join('usuario_perfil as up', 'up.id_usuario = usuario.id', 'inner');
        $grupo_usuario_model->orderBy('usuario.id', 'ASC');

        $result = $grupo_usuario_model->findAll();

        return $result;
    }
}
