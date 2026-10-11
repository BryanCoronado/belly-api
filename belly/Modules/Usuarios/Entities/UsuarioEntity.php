<?php

namespace Belly\Usuarios\Entities;

use CodeIgniter\Entity\Entity;
use Belly\Auth\Models\UsuarioLoginModel;
use Belly\Usuarios\Models\UsuarioPerfilModel;


class UsuarioEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];

    protected $usuario_perfil = null;
    protected $usuario_login = null;

    public function getPerfil() {
        if ($this->usuario_perfil !== null) {
            return $this->usuario_perfil;
        }

        $perfil_model = model(UsuarioPerfilModel::class);
        $this->usuario_perfil = $perfil_model->find($this->attributes['id']);

        return $this->usuario_perfil;
    }

    public function getLogin() {
        if ($this->usuario_login !== null) {
            return $this->usuario_login;
        }

        $login_model = model(UsuarioLoginModel::class);
        $login_model->where(['id_usuario' => $this->attributes['id']]);
        $login_model->orderBy('created_at', 'DESC');

        $this->usuario_login = $login_model->first();

        return $this->usuario_login;
    }
}
