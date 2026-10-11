<?php

namespace Belly\Auth\Config;

use CodeIgniter\Config\BaseConfig;
use Belly\Usuarios\Models\UsuarioModel;
use Belly\Auth\Models\UsuarioLoginModel;

class AuthConfig extends BaseConfig
{
    public string $login_provider = UsuarioLoginModel::class;
    public string $usuario_provider = UsuarioModel::class;
    public int $inttentos_permitidos = 3; // Intentos permitidos antes de bloquear la cuenta
    public int $tiempo_bloqueo = 5; // Tiempo en minutos que durara el bloqeo de cuenta
    public int $vigencia_token = 24; // Horas de la sesión
    public int $vigencia_token_descarga = 10; // Minutos del token de descarga
}
