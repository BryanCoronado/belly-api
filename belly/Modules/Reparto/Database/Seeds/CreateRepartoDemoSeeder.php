<?php

namespace Belly\Reparto\Database\Seeds;

use Belly\AgenteIa\Database\Seeds\CreateAgenteIaDemoSeeder;
use Belly\Database\Seeds\EmpresaDemo;
use CodeIgniter\Database\Seeder;

/**
 * Grupos y lienzo de llegada de ejemplo:
 * vuelve con su asesor → mañana: grupo «Turno mañana» · tarde: grupo «Turno tarde» · fuera de horario: asistente de IA,
 * y si nadie puede, reparto parejo entre todos.
 * No toca nada si la empresa ya guardó su lienzo. Se siembra después de los asesores y del asistente de prueba.
 *
 * php spark db:seed "Belly\Reparto\Database\Seeds\CreateRepartoDemoSeeder"
 */
class CreateRepartoDemoSeeder extends Seeder
{
    use EmpresaDemo;

    private const GRUPOS = [
        'manana' => [
            'nombre' => 'Turno mañana', 'descripcion' => 'Atienden de 8:00 a 14:00', 'color' => '#f59e0b', 'miembros' => ['mtorres', 'lramirez'],
            'tipo' => 'turno', 'turno_dias' => [1, 2, 3, 4, 5], 'turno_desde' => '08:00', 'turno_hasta' => '14:00',
        ],
        'tarde' => [
            'nombre' => 'Turno tarde', 'descripcion' => 'Atienden de 14:00 a 20:00', 'color' => '#6366f1', 'miembros' => ['cvega', 'dsalas'],
            'tipo' => 'turno', 'turno_dias' => [1, 2, 3, 4, 5], 'turno_desde' => '14:00', 'turno_hasta' => '20:00',
        ],
    ];

    public function run()
    {
        $idEmpresa = $this->idEmpresaDemo();

        $grupos = [];
        foreach (self::GRUPOS as $clave => $grupo) {
            $grupos[$clave] = $this->grupo($idEmpresa, $grupo);
        }

        if ($this->db->table('reparto_flujo')->where('id_empresa', $idEmpresa)->countAllResults() > 0) {
            return;
        }

        $agente = $this->db->table('agente_ia')->select('id')
            ->where('id_empresa', $idEmpresa)->where('nombre', CreateAgenteIaDemoSeeder::NOMBRE)->where('deleted_at', null)
            ->get(1)->getRowArray();

        $this->db->table('reparto_flujo')->insert([
            'id_empresa' => $idEmpresa,
            'definicion' => json_encode($this->lienzo($grupos, $agente !== null ? (int) $agente['id'] : null), JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @param array<string, int> $grupos @return array<string, mixed> */
    private function lienzo(array $grupos, ?int $idAgente): array
    {
        $repartir = static fn (?int $idGrupo, string $estrategia): array => ['id_grupo' => $idGrupo, 'estrategia' => $estrategia, 'limite_chats' => null];
        $idsTurno = [
            ['id' => $grupos['manana']],
            ['id' => $grupos['tarde']],
        ];

        return [
            'nodos' => [
                ['id' => 'inicio', 'tipo' => 'inicio', 'x' => 0, 'y' => 0, 'datos' => ['id_usuario_respaldo' => null]],
                ['id' => 'previo', 'tipo' => 'asesor_previo', 'x' => 0, 'y' => 150, 'datos' => []],
                ['id' => 'turnos', 'tipo' => 'turnos_grupos', 'x' => 0, 'y' => 300, 'datos' => ['grupos_turno' => $idsTurno]],
                ['id' => 'g_manana', 'tipo' => 'repartir', 'x' => -380, 'y' => 500, 'datos' => $repartir($grupos['manana'], 'equitativo')],
                ['id' => 'g_tarde', 'tipo' => 'repartir', 'x' => 0, 'y' => 500, 'datos' => $repartir($grupos['tarde'], 'menos_carga')],
                ['id' => 'ia', 'tipo' => 'agente_ia', 'x' => 380, 'y' => 500, 'datos' => ['id_agente' => $idAgente]],
                ['id' => 'todos', 'tipo' => 'repartir', 'x' => 0, 'y' => 720, 'datos' => $repartir(null, 'equitativo')],
            ],
            'conexiones' => [
                ['id' => 'c1', 'desde' => 'inicio', 'salida' => 'siguiente', 'hacia' => 'previo'],
                ['id' => 'c2', 'desde' => 'previo', 'salida' => 'no', 'hacia' => 'turnos'],
                ['id' => 'c3', 'desde' => 'turnos', 'salida' => 'g_' . $grupos['manana'], 'hacia' => 'g_manana'],
                ['id' => 'c4', 'desde' => 'turnos', 'salida' => 'g_' . $grupos['tarde'], 'hacia' => 'g_tarde'],
                ['id' => 'c5', 'desde' => 'turnos', 'salida' => 'fuera', 'hacia' => $idAgente !== null ? 'ia' : 'todos'],
                ['id' => 'c6', 'desde' => 'g_manana', 'salida' => 'nadie', 'hacia' => 'todos'],
                ['id' => 'c7', 'desde' => 'g_tarde', 'salida' => 'nadie', 'hacia' => 'todos'],
                ['id' => 'c8', 'desde' => 'ia', 'salida' => 'humano', 'hacia' => 'todos'],
            ],
        ];
    }

    /** Crea el grupo si no existe (por nombre) y le pone sus miembros de prueba. @param array<string, mixed> $grupo */
    private function grupo(int $idEmpresa, array $grupo): int
    {
        $fila = $this->db->table('grupo_atencion')->select('id')
            ->where('id_empresa', $idEmpresa)->where('nombre', $grupo['nombre'])->where('deleted_at', null)
            ->get(1)->getRowArray();
        if ($fila !== null) {
            $id = (int) $fila['id'];
            if (($grupo['tipo'] ?? '') === 'turno') {
                $this->db->table('grupo_atencion')->where('id', $id)->update([
                    'tipo' => 'turno',
                    'turno_dias' => json_encode($grupo['turno_dias']),
                    'turno_desde' => $grupo['turno_desde'],
                    'turno_hasta' => $grupo['turno_hasta'],
                ]);
            }

            return $id;
        }

        $this->db->table('grupo_atencion')->insert([
            'id_empresa' => $idEmpresa,
            'nombre' => $grupo['nombre'],
            'descripcion' => $grupo['descripcion'],
            'color' => $grupo['color'],
            'tipo' => $grupo['tipo'] ?? 'general',
            'turno_dias' => isset($grupo['turno_dias']) ? json_encode($grupo['turno_dias']) : null,
            'turno_desde' => $grupo['turno_desde'] ?? null,
            'turno_hasta' => $grupo['turno_hasta'] ?? null,
        ]);
        $id = (int) $this->db->insertID();

        foreach ($grupo['miembros'] as $orden => $usuario) {
            $miembro = $this->db->table('usuario')->select('id')
                ->where('id_empresa', $idEmpresa)->where('usuario', $usuario)->where('deleted_at', null)
                ->get(1)->getRowArray();
            if ($miembro !== null) {
                $this->db->table('grupo_atencion_miembro')->insert(['id_grupo' => $id, 'id_usuario' => (int) $miembro['id'], 'orden' => $orden]);
            }
        }

        return $id;
    }
}
