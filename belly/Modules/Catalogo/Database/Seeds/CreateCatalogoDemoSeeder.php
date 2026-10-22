<?php

namespace Belly\Catalogo\Database\Seeds;

use Belly\Database\Seeds\EmpresaDemo;
use CodeIgniter\Database\Seeder;

/**
 * Familias y productos de ejemplo para cualquier tipo de negocio (mezcla servicios y productos).
 *
 * php spark db:seed "Belly\Catalogo\Database\Seeds\CreateCatalogoDemoSeeder"
 */
class CreateCatalogoDemoSeeder extends Seeder
{
    use EmpresaDemo;

    /** @return array<string, list<array<string, mixed>>> */
    private function catalogo(): array
    {
        return [
            'Servicios' => [
                ['nombre' => 'Consulta inicial', 'precio' => 50, 'unidad' => 'sesión', 'descripcion' => 'Primera reunión para conocer lo que necesitas.', 'detalles' => [['nombre' => 'Duración', 'valor' => '45 minutos'], ['nombre' => 'Modalidad', 'valor' => 'Presencial o por videollamada']]],
                ['nombre' => 'Instalación a domicilio', 'precio' => 120, 'unidad' => 'servicio', 'descripcion' => 'Vamos hasta tu casa u oficina.', 'detalles' => [['nombre' => 'Cobertura', 'valor' => 'Toda la ciudad']]],
                ['nombre' => 'Plan mensual básico', 'precio' => 89.90, 'unidad' => 'mes', 'descripcion' => 'Atención y soporte todo el mes.', 'detalles' => [['nombre' => 'Soporte', 'valor' => 'Por WhatsApp de lunes a sábado']]],
            ],
            'Ropa' => [
                ['nombre' => 'Polo básico de algodón', 'precio' => 39.90, 'unidad' => 'unidad', 'codigo' => 'POLO-001', 'descripcion' => 'Polo cómodo para todos los días.', 'detalles' => [['nombre' => 'Tallas', 'valor' => 'S, M, L, XL'], ['nombre' => 'Colores', 'valor' => 'Blanco, negro, azul']]],
                ['nombre' => 'Zapatillas urbanas', 'precio' => 189, 'unidad' => 'par', 'codigo' => 'ZAP-010', 'descripcion' => 'Livianas y resistentes.', 'detalles' => [['nombre' => 'Tallas', 'valor' => 'Del 36 al 44']]],
            ],
            'Accesorios' => [
                ['nombre' => 'Gorra ajustable', 'precio' => 25, 'unidad' => 'unidad', 'codigo' => 'ACC-003', 'detalles' => []],
                ['nombre' => 'Mochila de 20 litros', 'precio' => 79, 'unidad' => 'unidad', 'codigo' => 'ACC-004', 'descripcion' => 'Con espacio para laptop.', 'detalles' => [['nombre' => 'Material', 'valor' => 'Poliéster resistente al agua']]],
                ['nombre' => 'Producto a cotizar', 'precio' => null, 'unidad' => null, 'descripcion' => 'Sin precio fijo: se cotiza según lo que pida el cliente.', 'detalles' => []],
            ],
        ];
    }

    public function run()
    {
        $idEmpresa = $this->idEmpresaDemo();
        $ahora = date('Y-m-d H:i:s');
        $orden = 0;

        foreach ($this->catalogo() as $familia => $productos) {
            $orden++;
            $existente = $this->db->table('familia_producto')
                ->where('id_empresa', $idEmpresa)
                ->where('nombre', $familia)
                ->where('deleted_at', null)
                ->get(1)
                ->getRowArray();
            if ($existente !== null) {
                continue;
            }

            $this->db->table('familia_producto')->insert([
                'id_empresa' => $idEmpresa,
                'nombre' => $familia,
                'orden' => $orden,
                'activo' => 1,
                'created_at' => $ahora,
            ]);
            $idFamilia = (int) $this->db->insertID();

            foreach ($productos as $producto) {
                $this->db->table('producto')->insert([
                    'id_empresa' => $idEmpresa,
                    'id_familia' => $idFamilia,
                    'nombre' => $producto['nombre'],
                    'descripcion' => $producto['descripcion'] ?? null,
                    'codigo' => $producto['codigo'] ?? null,
                    'precio' => $producto['precio'],
                    'moneda' => 'PEN',
                    'unidad' => $producto['unidad'],
                    'detalles' => json_encode($producto['detalles'], JSON_UNESCAPED_UNICODE),
                    'activo' => 1,
                    'created_at' => $ahora,
                ]);
            }
        }
    }
}
