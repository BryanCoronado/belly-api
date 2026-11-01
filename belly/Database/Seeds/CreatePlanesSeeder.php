<?php

namespace Belly\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Planes de Belly. Se puede repetir: solo crea los que falten.
 *
 * php spark db:seed "Belly\Database\Seeds\CreatePlanesSeeder"
 */
class CreatePlanesSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('plan')) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');
        foreach ($this->planes() as $plan) {
            $existe = $this->db->table('plan')->where('clave', $plan['clave'])->where('deleted_at', null)->countAllResults();
            if ($existe > 0) {
                continue;
            }
            $this->db->table('plan')->insert($plan + ['created_at' => $ahora]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function planes(): array
    {
        return [
            [
                'clave' => 'gratis',
                'nombre' => 'Gratis',
                'descripcion' => 'Chats, Mi Catálogo y un equipo chico para probar.',
                'precio_mensual' => 0,
                'moneda' => 'PEN',
                'max_conversaciones' => 300,
                'max_equipos' => 1,
                'max_personas_equipo' => 2,
                'max_personas' => 3,
                'max_flujos' => 1,
                'max_envios' => 0,
                'max_numeros' => 1,
                'incluye_agente_ia' => 0,
                'activo' => 1,
                'orden' => 1,
            ],
            [
                'clave' => 'base',
                'nombre' => 'Base',
                'descripcion' => 'Atención diaria, Mi Catálogo, flujos y un par de equipos.',
                'precio_mensual' => 79,
                'moneda' => 'PEN',
                'max_conversaciones' => 2000,
                'max_equipos' => 2,
                'max_personas_equipo' => 5,
                'max_personas' => 8,
                'max_flujos' => 8,
                'max_envios' => 5,
                'max_numeros' => 1,
                'incluye_agente_ia' => 0,
                'activo' => 1,
                'orden' => 2,
            ],
            [
                'clave' => 'pro',
                'nombre' => 'Pro',
                'descripcion' => 'Más chats, Mi Catálogo, envíos y el asistente IA.',
                'precio_mensual' => 179,
                'moneda' => 'PEN',
                'max_conversaciones' => 10000,
                'max_equipos' => 6,
                'max_personas_equipo' => 10,
                'max_personas' => 30,
                'max_flujos' => 30,
                'max_envios' => 30,
                'max_numeros' => 2,
                'incluye_agente_ia' => 1,
                'activo' => 1,
                'orden' => 3,
            ],
            [
                'clave' => 'empresa',
                'nombre' => 'Empresa',
                'descripcion' => 'Sin tope. Incluye Mi Catálogo, IA, equipos y flujos.',
                'precio_mensual' => 349,
                'moneda' => 'PEN',
                'max_conversaciones' => 0,
                'max_equipos' => 0,
                'max_personas_equipo' => 0,
                'max_personas' => 0,
                'max_flujos' => 0,
                'max_envios' => 0,
                'max_numeros' => 5,
                'incluye_agente_ia' => 1,
                'activo' => 1,
                'orden' => 4,
            ],
        ];
    }
}
