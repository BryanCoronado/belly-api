<?php
namespace Belly\Integraciones\Database\Migrations;
use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateCotizacionTable extends Migration

{

    public function up()

    {
        $this->createTableCotizacion();
    }

    public function down()

    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->forge->dropTable('cotizacion');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }
    private function createTableCotizacion(): void

    {

        $this->forge->addField([

            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Usuario que registra (tabla usuario)',
            ],

            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Conversación asociada (tabla conversacion)',
            ],

            'id_cliente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Cliente CRM si aplica (tabla cliente)',
            ],
            'id_familia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Familia de producto (id del servicio cotizador)',
            ],
            'moneda' => [
                'type'       => 'VARCHAR',
                'constraint' => 3,
                'default'    => 'PEN',
            ],
            'tipo_cambio' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,4',
                'default'    => '1.0000',
            ],
            'fecha_pedido' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'fecha_prog_inicio' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'fecha_entrega' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'tipo_doc' => [
                'type'       => 'ENUM',
                'constraint' => ['DNI', 'RUC'],
                'null'       => true,
            ],
            'nro_documento' => [
                'type'       => 'VARCHAR',
                'constraint' => 11,
                'null'       => true,
            ],
            'nombre_razon' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
            ],
            'direccion' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
                'null'       => true,
            ],
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'correo' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'nombre_contacto' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],

            'cargo_contacto' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            'numero_contacto' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],

            'comentario' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],

            'numero_correlativo' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
                'comment'    => 'Ej. COT-2026-000042 (único)',
            ],
            'estado' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'comment'    => '0=borrador, 1=final',
            ],
            'fecha_vencimiento' => [
                'type' => 'DATE',
                'null' => true,
                'comment' => 'Fecha de vencimiento del documento',
            ],
            'sub_total_ventas' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
                'comment'    => 'Sub total ventas (base imponible / valor sin IGV según moneda)',
            ],
            'descuento' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'valor_venta' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'igv' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
                'comment'    => 'IGV 18% (PEN)',
            ],
            'total' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
                'comment'    => 'Importe total de la cotización',
            ],
            'pdf_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
                'comment'    => 'URL pública (media_url): MEDIA_UPLOADS_BASE_URL/uploads/cotizaciones/AÑO/archivo.pdf',
            ],
            'pdf_generado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'voucher_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
                'comment'    => 'URL pública del comprobante de pago (mismo criterio que pdf_path / media)',
            ],
            'metodo_pago' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'comment'    => 'Método de pago: efectivo, yape, plin, bcp, bbva, interbank',
            ],
            'tipo_pago' => [
                'type'       => 'ENUM',
                'constraint' => ['parcial', 'completo'],
                'null'       => true,
                'comment'    => 'Pago parcial o completo',
            ],
            'monto' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
                'comment'    => 'Monto pagado (principalmente método efectivo)',
            ],

            'tc_sunat' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Respuesta SUNAT tipo de cambio (opcional)',
            ],

            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('id_usuario');
        $this->forge->addKey('id_conversacion');
        $this->forge->addKey(['id_conversacion', 'estado']);
        $this->forge->addKey('id_cliente');
        $this->forge->addKey('numero_correlativo', false, true);
        $this->forge->addForeignKey('id_usuario', 'usuario', 'id', 'CASCADE', 'SET NULL', 'fk_cotizacion_usuario');
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'SET NULL', 'fk_cotizacion_conversacion');
        $this->forge->addForeignKey('id_cliente', 'cliente', 'id', 'CASCADE', 'SET NULL', 'fk_cotizacion_cliente');
        $this->forge->createTable('cotizacion');
    }

}

