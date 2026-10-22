<?php

namespace Belly\Usuarios\Database\Seeds;

use Belly\Database\Seeds\EmpresaDemo;
use CodeIgniter\Database\Seeder;

/**
 * Asesores de prueba (rol «Asesor»), uno de ellos desactivado para ver los dos estados.
 * Contraseña de todos: Asesor.123
 *
 * php spark db:seed "Belly\Usuarios\Database\Seeds\CreateAsesoresDemoSeeder"
 */
class CreateAsesoresDemoSeeder extends Seeder
{
    use EmpresaDemo;

    private const PASSWORD = 'Asesor.123';

    private const ASESORES = [
        ['usuario' => 'mtorres', 'nombres' => 'María', 'apellidos' => 'Torres', 'celular' => '987000101', 'activo' => 1],
        ['usuario' => 'lramirez', 'nombres' => 'Luis', 'apellidos' => 'Ramírez', 'celular' => '987000102', 'activo' => 1],
        ['usuario' => 'cvega', 'nombres' => 'Carla', 'apellidos' => 'Vega', 'celular' => '987000103', 'activo' => 1],
        ['usuario' => 'dsalas', 'nombres' => 'Diego', 'apellidos' => 'Salas', 'celular' => '987000104', 'activo' => 1],
        ['usuario' => 'jpaz', 'nombres' => 'Jorge', 'apellidos' => 'Paz', 'celular' => '987000105', 'activo' => 0],
    ];

    public function run()
    {
        helper('vip2system');
        $idEmpresa = $this->idEmpresaDemo();
        $idRol = (int) acceso()->rolPorDefecto($idEmpresa)['id'];

        foreach (self::ASESORES as $asesor) {
            $existe = $this->db->table('usuario')
                ->where('id_empresa', $idEmpresa)
                ->where('usuario', $asesor['usuario'])
                ->countAllResults();
            if ($existe > 0) {
                continue;
            }

            $this->db->table('usuario')->insert([
                'id_empresa' => $idEmpresa,
                'usuario' => $asesor['usuario'],
                'password' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
                'activo' => $asesor['activo'],
                'id_estado_usuario' => 1,
                'sudo' => 0,
                'es_bot' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $idUsuario = (int) $this->db->insertID();

            $this->db->table('usuario_perfil')->insert([
                'id_usuario' => $idUsuario,
                'nombres' => $asesor['nombres'],
                'apellidos' => $asesor['apellidos'],
                'correo' => $asesor['usuario'] . '@demo.belly.test',
                'celular' => $asesor['celular'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            acceso()->asignarUsuarioARol($idUsuario, $idRol, $idEmpresa);
        }
    }
}
