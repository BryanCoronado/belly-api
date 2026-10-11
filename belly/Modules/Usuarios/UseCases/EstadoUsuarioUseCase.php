<?php
namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Models\EstadoUsuarioModel;

class EstadoUsuarioUseCase {
    protected UsuarioConfig $config;
    protected ?EstadoUsuarioModel $estado_usuario_provider = NULL;


    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
    }

    public function getEstadoUsuarioProvider(): EstadoUsuarioModel
    {
        if ($this->estado_usuario_provider !== null) {
            return $this->estado_usuario_provider;
        }

        $class_name = $this->config->estado_usuario_provider;
        $this->estado_usuario_provider = new $class_name();

        return $this->estado_usuario_provider;
    }

    public function listarEstadosUsuarios()
    {
        $estado_usuario_model = $this->getEstadoUsuarioProvider();
        $estado_usuario_model->select(['id', 'nombre', 'descripcion', 'color']);
        $estado_usuario_model->where('deleted_at', null);
        $estado_usuario_model->orderBy('nombre', 'ASC');
        return $estado_usuario_model->findAll();
    }


}
