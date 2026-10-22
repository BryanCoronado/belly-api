<?php

use CodeIgniter\Router\RouteCollection;

$routes->group('v1', ['filter' => ['authFilter', 'permisosFilter:mod_sistemas_access,acc_lectura']], static function ($routes) {
    $routes->group('usuario', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'UsuarioController::listar', ['as' => 'apiv1_usuarios_listar']);
        $routes->get('(:num)', 'UsuarioController::ver/$1', ['as' => 'apiv1_usuarios_ver']);
        $routes->get('por-rol/(:num)', 'UsuarioController::listarPorRol/$1', ['as' => 'apiv1_usuarios_listar_por_rol']);
        $routes->group('logueado', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
            $routes->get('/', 'UsuarioLoginController::user', ['as' => 'apiv1_usuariologin_read']);
        });
        $routes->group('reporte', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
            $routes->get('historial-estados-asesores', 'UsuarioController::historialEstadosAsesores', ['as' => 'apiv1_usuario_reporte_historial_estados_asesores']);
        });
    });

    $routes->group('estado_usuario', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'EstadoUsuarioController::listar', ['as' => 'apiv1_estado_usuario_list']);
        $routes->put('(:num)', 'EstadoUsuarioController::ver/$1', ['as' => 'apiv1_estado_usuario_read']);
    });
});

// Cada usuario puede cambiar su propia disponibilidad; el caso de uso valida que sea él o un administrador.
$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {
    $routes->group('usuario', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->put('estado-usuario/(:num)', 'UsuarioController::actualizarEstadoUsuario/$1', ['as' => 'apiv1_usuarios_update_estado_id']);
    });
});

$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:admin']], static function ($routes) {
    $routes->group('usuario', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->post('crear', 'UsuarioController::crear', ['as' => 'apiv1_usuarios_crear']);
        $routes->put('editar/(:num)', 'UsuarioController::editar/$1', ['as' => 'apiv1_usuarios_editar']);
        $routes->put('estado/(:num)', 'UsuarioController::actualizarEstado/$1', ['as' => 'apiv1_usuarios_update_estado']);
        $routes->delete('(:num)', 'UsuarioController::eliminar/$1', ['as' => 'apiv1_usuarios_delete']);
    });

    $routes->group('asesores', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'AsesorControllerApi::listar', ['as' => 'apiv1_asesores']);
        $routes->post('/', 'AsesorControllerApi::crear', ['as' => 'apiv1_asesores_crear']);
        $routes->put('(:num)/estado', 'AsesorControllerApi::cambiarEstado/$1', ['as' => 'apiv1_asesores_estado']);
        $routes->post('(:num)/foto', 'AsesorControllerApi::subirFoto/$1', ['as' => 'apiv1_asesores_foto']);
    });

    $routes->group('acceso', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('roles', 'AccesoController::roles');
        $routes->post('roles', 'AccesoController::crearRol');
        $routes->get('roles/(:num)', 'AccesoController::verRol/$1');
        $routes->put('roles/(:num)', 'AccesoController::editarRol/$1');
        $routes->delete('roles/(:num)', 'AccesoController::eliminarRol/$1');
        $routes->get('roles/(:num)/permisos', 'AccesoController::permisosRol/$1');
        $routes->put('roles/(:num)/permisos', 'AccesoController::guardarPermisosRol/$1');
        $routes->get('permisos', 'AccesoController::catalogo');
        $routes->get('grupos', 'AccesoController::grupos');
        $routes->post('grupos', 'AccesoController::crearGrupo');
        $routes->get('grupos/(:num)', 'AccesoController::verGrupo/$1');
        $routes->put('grupos/(:num)', 'AccesoController::editarGrupo/$1');
        $routes->delete('grupos/(:num)', 'AccesoController::eliminarGrupo/$1');
    });
});

$routes->group('v1', ['filter' => 'authFilter'], static function ($routes) {
    $routes->group('acceso', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('menu', 'AccesoController::menu');
    });
});

// Endpoints heredados que trabajan con ids crudos de grupos/roles/permisos: solo el operador de la plataforma.
$routes->group('v1', ['filter' => ['authFilter', 'rolFilter:operador']], static function ($routes) {
    $routes->group('grupo_usuario', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'GrupoUsuarioController::listar', ['as' => 'apiv1_grupo_usuario_list']);
        $routes->get('(:num)', 'GrupoUsuarioController::ver/$1', ['as' => 'apiv1_grupo_usuario_read']);
        $routes->get('list-grupos', 'GrupoUsuarioController::listBySelect', ['as' => 'apiv1_grupo_usuario_list_grupos_select']);
        $routes->post('crear', 'GrupoUsuarioController::crear', ['as' => 'apiv1_grupo_usuario_create']);
        $routes->put('(:num)', 'GrupoUsuarioController::editar/$1', ['as' => 'apiv1_grupo_usuario_update']);
        $routes->delete('(:num)', 'GrupoUsuarioController::eliminar/$1', ['as' => 'apiv1_grupo_usuario_delete']);
    });

    $routes->group('grupo_usuario_rol', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'GrupoUsuarioRolController::listar', ['as' => 'apiv1_grupo_usuario_rol_list']);
        $routes->get('(:num)', 'GrupoUsuarioRolController::ver/$1', ['as' => 'apiv1_grupo_usuario_rol_read']);
        $routes->post('crear', 'GrupoUsuarioRolController::crear', ['as' => 'apiv1_grupo_usuario_rol_create']);
        $routes->put('(:num)', 'GrupoUsuarioRolController::editar/$1', ['as' => 'apiv1_grupo_usuario_rol_update']);
        $routes->delete('(:num)', 'GrupoUsuarioRolController::eliminar/$1', ['as' => 'apiv1_grupo_usuario_rol_delete']);
    });

    $routes->group('grupo_usuario_usuario', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('/', 'GrupoUsuarioUsuarioController::listar', ['as' => 'apiv1_grupo_usuario_usuario_list']);
        $routes->get('listar-por-grupo-usuario/(:num)', 'GrupoUsuarioUsuarioController::listarPorGrupoUsuario/$1', ['as' => 'apiv1_grupo_usuario_usuario_listByGrupoUsuario']);
        $routes->post('crear', 'GrupoUsuarioUsuarioController::crear', ['as' => 'apiv1_grupo_usuario_usuario_create']);
        $routes->get('list-grupos-por-usuario', 'GrupoUsuarioUsuarioController::listBySelectByUser', ['as' => 'apiv1_grupo_usuario_usuario_list_grupos_select_usuario']);
        $routes->put('(:num)', 'GrupoUsuarioUsuarioController::update/$1', ['as' => 'apiv1_grupo_usuario_usuario_update']);
        $routes->delete('(:num)', 'GrupoUsuarioUsuarioController::delete/$1', ['as' => 'apiv1_grupo_usuario_usuario_delete']);
    });

    $routes->group('grupo_usuario_permiso_rol', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('(:num)/permisos/(:num)', 'GrupoUsuarioPermisoRolController::obtenerPermisosPorGrupoUsuarioYGrupoUsuarioRol/$1/$2', ['as' => 'apiv1_grupo_usuario_permiso_rol_permisos']);
        $routes->put('/', 'GrupoUsuarioPermisoRolController::actualizarPermisosPorGrupoUsuarioYGrupoUsuarioRol', ['as' => 'apiv1_grupo_usuario_permiso_rol_update_permisos']);
        $routes->delete('(:num)/(:num)/(:num)', 'GrupoUsuarioPermisoRolController::eliminar/$1/$2/$3', ['as' => 'apiv1_grupo_usuario_permiso_rol_delete']);
    });

    $routes->group('grupo_usuarios_permisos_usuario', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('(:num)/(:num)', 'GrupoUsuarioPermisoUsuarioController::obtenerGrupoUsuarioPermisoUsuario/$1/$2', ['as' => 'apiv1_grupo_usuarios_permisos_usuario_listByGrupoUsuario']);
        $routes->put('/', 'GrupoUsuarioPermisoUsuarioController::actualizarPorGrupoUsuarioIDUsuarioID', ['as' => 'apiv1_grupo_usuarios_permisos_usuario_update']);
        $routes->delete('(:num)/(:num)/(:num)', 'GrupoUsuarioPermisoUsuarioController::delete/$1/$2/$3', ['as' => 'apiv1_grupo_usuarios_permisos_usuario_delete']);
    });

    $routes->group('permiso_especial', ['namespace' => 'Belly\Usuarios\Controllers\Api'], static function ($routes) {
        $routes->get('(:num)', 'UsuarioPermisoEspecialController::obtenerpermisoEspecialPorUsuario/$1', ['as' => 'apiv1_permiso_especial_read']);
        $routes->get('/', 'UsuarioPermisoEspecialController::list', ['as' => 'apiv1_permiso_especial_list']);
        $routes->post('create', 'UsuarioPermisoEspecialController::crear', ['as' => 'apiv1_permiso_especial_create']);
        $routes->put('/', 'UsuarioPermisoEspecialController::actualizar', ['as' => 'apiv1_permiso_especial_update']);
        $routes->delete('(:num)', 'UsuarioPermisoEspecialController::eliminar/$1', ['as' => 'apiv1_permiso_especial_delete']);
    });
});
