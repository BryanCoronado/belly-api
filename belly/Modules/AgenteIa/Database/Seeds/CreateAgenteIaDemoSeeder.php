<?php

namespace Belly\AgenteIa\Database\Seeds;

use Belly\Database\Seeds\EmpresaDemo;
use CodeIgniter\Database\Seeder;

/**
 * Asistente de IA de ejemplo con algo de información del negocio. No conecta OpenAI: eso lo hace cada empresa con su llave.
 * No toca nada si la empresa ya tiene asistentes.
 *
 * php spark db:seed "Belly\AgenteIa\Database\Seeds\CreateAgenteIaDemoSeeder"
 */
class CreateAgenteIaDemoSeeder extends Seeder
{
    use EmpresaDemo;

    public const NOMBRE = 'Sofía (asistente)';

    public function run()
    {
        $idEmpresa = $this->idEmpresaDemo();
        if ($this->db->table('agente_ia')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->countAllResults() > 0) {
            return;
        }

        $this->db->table('agente_ia')->insert([
            'id_empresa' => $idEmpresa,
            'nombre' => self::NOMBRE,
            'activo' => 1,
            'modelo' => 'gpt-4o-mini',
            'instrucciones' => "Saluda y pregunta en qué puedes ayudar.\nResponde dudas de horarios, envíos, formas de pago y productos.\nSi el cliente quiere comprar, pídele su nombre y qué producto le interesa, y pásalo con un asesor.",
            'tono' => 'amable',
            'usar_catalogo' => 1,
            'max_respuestas' => 15,
            'palabras_traspaso' => json_encode(['asesor', 'persona', 'humano', 'reclamo'], JSON_UNESCAPED_UNICODE),
            'mensaje_traspaso' => 'Te paso con una persona de nuestro equipo, en un momento te escribe 🙌',
            'temperatura' => 0.40,
        ]);
        $idAgente = (int) $this->db->insertID();

        $fichas = [
            ['Horario de atención', "Lunes a viernes de 8:00 a 20:00.\nSábados de 9:00 a 13:00.\nDomingos y feriados no atendemos, pero puedes dejar tu mensaje."],
            ['Envíos y pagos', "Hacemos envíos a todo el país en 24 a 72 horas.\nAceptamos transferencia, tarjeta y pago contra entrega en la ciudad.\nEl envío es gratis en compras mayores a 200."],
            ['Cambios y devoluciones', "Tienes 7 días para cambios con la boleta y el producto sin usar.\nLas devoluciones de dinero las revisa un asesor."],
        ];
        foreach ($fichas as [$titulo, $contenido]) {
            $this->db->table('agente_ia_conocimiento')->insert(['id_agente' => $idAgente, 'titulo' => $titulo, 'contenido' => $contenido]);
        }
    }
}
