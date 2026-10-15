<?php

namespace Belly\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Retoma los flujos del bot que estaban esperando: pasos «Esperar» y clientes que no respondieron a tiempo.
 * El servidor de tiempo real ya lo hace solo cada pocos segundos; este comando sirve para
 * ejecutarlo a mano o desde un cron cuando no se usa ese servidor.
 */
class ProcesarFlujos extends BaseCommand
{
    protected $group = 'Belly';
    protected $name = 'flujos:procesar';
    protected $description = 'Retoma los flujos del bot que ya cumplieron su tiempo de espera.';
    protected $usage = 'flujos:procesar [--loop]';
    protected $options = ['--loop' => 'Se queda corriendo y revisa cada 10 segundos.'];

    public function run(array $params)
    {
        helper('vip2system');

        do {
            $total = bot_flujos()->procesarPendientes();
            if ($total > 0 || ! CLI::getOption('loop')) {
                CLI::write("Conversaciones retomadas: {$total}");
            }
            if (CLI::getOption('loop')) {
                sleep(10);
            }
        } while (CLI::getOption('loop'));

        return EXIT_SUCCESS;
    }
}
