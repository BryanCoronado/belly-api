<?php

namespace Belly\Usuarios\Database\Seeds;

use Belly\Modulos\Database\Seeds\CreateGruposModulosSeeder;
use Belly\Modulos\Database\Seeds\CreateModulosSeeder;
use Belly\Permisos\Database\Seeds\CreatePermisosGruposSeeder;
use Belly\Permisos\Database\Seeds\CreatePermisosSeeder;
use CodeIgniter\Database\Seeder;

/**
 * Instalación base: empresa principal, catálogo de acceso, roles (Administrador y Asesor)
 * y los dos usuarios administradores iniciales.
 *
 * php spark db:seed "Belly\Usuarios\Database\Seeds\CreateUsuariosSeeder"
 */
class CreateUsuariosSeeder extends Seeder
{
    private const ID_EMPRESA = 1;

    public function run()
    {
        $this->asegurarEmpresaPrincipal();

        $this->call(TiposDocumentosSeeder::class);
        $this->call(CreateGruposModulosSeeder::class);
        $this->call(CreatePermisosGruposSeeder::class);
        $this->call(CreatePermisosSeeder::class);
        $this->call(CreateModulosSeeder::class);

        helper('vip2system');
        $roles = acceso()->crearRolesIniciales(self::ID_EMPRESA);

        $administradores = [
            ['usuario' => 'developer', 'password' => 'Dev.123', 'nombres' => 'Developer', 'apellidos' => 'Belly'],
            ['usuario' => 'administrador', 'password' => 'Admin.123', 'nombres' => 'Administrador', 'apellidos' => 'Belly'],
        ];
        foreach ($administradores as $datos) {
            $this->crearUsuario($datos, $roles['admin'], true);
        }
    }

    /** @param array{usuario:string, password:string, nombres:string, apellidos:string} $datos */
    private function crearUsuario(array $datos, int $idRol, bool $esAdmin): void
    {
        $existe = $this->db->table('usuario')
            ->where('id_empresa', self::ID_EMPRESA)
            ->where('usuario', $datos['usuario'])
            ->countAllResults();
        if ($existe > 0) {
            return;
        }

        $this->db->table('usuario')->insert([
            'id_empresa' => self::ID_EMPRESA,
            'usuario' => $datos['usuario'],
            'password' => password_hash($datos['password'], PASSWORD_DEFAULT),
            'activo' => 1,
            'id_estado_usuario' => 1,
            'sudo' => $esAdmin ? 1 : 0,
            'es_bot' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $idUsuario = (int) $this->db->insertID();

        $this->db->table('usuario_perfil')->insert([
            'id_usuario' => $idUsuario,
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        acceso()->asignarUsuarioARol($idUsuario, $idRol, self::ID_EMPRESA);
    }

    private function asegurarEmpresaPrincipal(): void
    {
        if ($this->db->table('empresa')->where('id', self::ID_EMPRESA)->countAllResults() > 0) {
            return;
        }

        $this->db->table('empresa')->insert([
            'id' => self::ID_EMPRESA,
            'nombre' => 'Empresa demo',
            'slug' => 'demo',
            'plan' => 'base',
            'estado' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
