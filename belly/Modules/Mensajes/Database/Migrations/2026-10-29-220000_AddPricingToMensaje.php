<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Tipo y categoría de cobro que Meta manda en el status (cupo gratis vs cobrado). */
class AddPricingToMensaje extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('pricing_type', 'mensaje')) {
            $this->forge->addColumn('mensaje', [
                'pricing_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => true,
                    'after'      => 'cod_error',
                ],
            ]);
        }
        if (! $this->db->fieldExists('pricing_category', 'mensaje')) {
            $this->forge->addColumn('mensaje', [
                'pricing_category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => true,
                    'after'      => 'pricing_type',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('pricing_category', 'mensaje')) {
            $this->forge->dropColumn('mensaje', 'pricing_category');
        }
        if ($this->db->fieldExists('pricing_type', 'mensaje')) {
            $this->forge->dropColumn('mensaje', 'pricing_type');
        }
    }
}
