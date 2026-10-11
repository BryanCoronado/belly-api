<?php

namespace Belly\Integraciones\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateCotizacionLineaTable extends Migration
{
    public function up()
    {
        $this->createTableCotizacionLinea();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('cotizacion_linea');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableCotizacionLinea(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_cotizacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'num_item' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 1,
                'comment'    => 'Orden de la línea (1, 2, 3…)',
            ],
            'codigo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'tipo_item' => [
                'type'       => 'ENUM',
                'constraint' => ['Bien', 'Servicio'],
                'default'    => 'Bien',
            ],
            'pct_detraccion' => [
                'type'       => 'DECIMAL',
                'constraint' => '6,2',
                'null'       => true,
            ],
            'cod_detraccion' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'descripcion' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'unidad' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'UN',
                'comment'    => 'UN, LM, etc.',
            ],
            'stock' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,4',
                'null'       => true,
            ],
            'observacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
                'null'       => true,
            ],
            'ancho' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,4',
                'null'       => true,
            ],
            'largo' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,4',
                'null'       => true,
            ],
            'desarrollo' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,4',
                'null'       => true,
            ],
            'cantidad' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,4',
            ],
            'precio_unitario' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,4',
            ],
            'importe' => [
                'type'       => 'DECIMAL',
                'constraint' => '16,4',
                'null'       => true,
                'comment'    => 'cantidad * precio_unitario (opcional persistido)',
            ],
            'precio_max' =>  [
                'type'       => 'DECIMAL',
                'constraint' => '16,4',
                'null'       => true,
                'comment'    => 'precio maximo del momento',
            ],
            'precio_min' => [
                'type'       => 'DECIMAL',
                'constraint' => '16,4',
                'null'       => true,
                'comment'    => 'precio minimo del momento',
            ],
            'rango_precio' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'rango de precio del momento (PRECIO MAX  - PRECIO MIN - PRECIO NEGOCIABLE)',
            ],
            'motivo_negociacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'motivo de negociacion',
            ],
            'encargado_confirmacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'encargado de confirmar la negociacion (el que autoriza la negociacion)',
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('id_cotizacion');
        $this->forge->addKey(['id_cotizacion', 'num_item']);
        $this->forge->addForeignKey(
            'id_cotizacion',
            'cotizacion',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_cotizacion_linea_cotizacion',
        );

        $this->forge->createTable('cotizacion_linea');
    }
}
