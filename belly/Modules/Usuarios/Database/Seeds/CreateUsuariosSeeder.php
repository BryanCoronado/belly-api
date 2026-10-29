<?php

namespace Belly\Usuarios\Database\Seeds;

use Belly\Database\Seeds\SoloDesarrollo;
use CodeIgniter\Database\Seeder;

/**
 * Solo desarrollo: empresa demo y los dos administradores de prueba.
 *
 * php spark db:seed "Belly\Usuarios\Database\Seeds\CreateUsuariosSeeder"
 */
class CreateUsuariosSeeder extends Seeder
{
    use SoloDesarrollo;

    private const ID_EMPRESA = 1;

    public function run()
    {
        $this->exigirDesarrollo();
        $this->asegurarEmpresaPrincipal();

        helper('vip2system');
        $roles = acceso()->crearRolesIniciales(self::ID_EMPRESA);

        $administradores = [
            ['usuario' => 'developer', 'password' => 'Dev.123', 'nombres' => 'Developer', 'apellidos' => 'Belly', 'correo' => 'developer@demo.belly.test'],
            ['usuario' => 'administrador', 'password' => 'Admin.123', 'nombres' => 'Administrador', 'apellidos' => 'Belly', 'correo' => 'administrador@demo.belly.test'],
        ];
        foreach ($administradores as $datos) {
            $this->crearUsuario($datos, $roles['admin'], true);
        }
    }

    /** @param array{usuario:string, password:string, nombres:string, apellidos:string, correo:string} $datos */
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
            'correo' => $datos['correo'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        acceso()->asignarUsuarioARol($idUsuario, $idRol, self::ID_EMPRESA);
    }

    private function asegurarEmpresaPrincipal(): void
    {
        if ($this->db->table('empresa')->where('id', self::ID_EMPRESA)->countAllResults() > 0) {
            return;
        }

        $fila = [
            'id' => self::ID_EMPRESA,
            'nombre' => 'Empresa demo',
            'slug' => 'demo',
            'plan' => 'base',
            'estado' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db->fieldExists('correo', 'empresa')) {
            $fila['correo'] = 'contacto@demo.belly.test';
        }
        $this->db->table('empresa')->insert($fila);
    }
}
