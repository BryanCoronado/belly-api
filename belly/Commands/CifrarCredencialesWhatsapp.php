<?php

namespace Belly\Commands;

use Belly\Services\Cifrado;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Cifra los tokens de Meta y App Secrets que quedaron en texto plano.
 * Requiere encryption.key en .env (php spark key:generate).
 */
class CifrarCredencialesWhatsapp extends BaseCommand
{
    protected $group = 'Belly';
    protected $name = 'whatsapp:cifrar-credenciales';
    protected $description = 'Cifra access_token de las apps y app_secret de las conexiones de Meta.';

    public function run(array $params)
    {
        if (trim((string) config('Encryption')->key) === '') {
            CLI::error('Falta encryption.key en .env. Ejecuta primero: php spark key:generate');

            return EXIT_ERROR;
        }

        $apps = $this->cifrarColumna('app', 'access_token');
        $secretos = $this->cifrarColumna('conexion_meta', 'app_secret');

        CLI::write("Tokens cifrados: {$apps}. App Secrets cifrados: {$secretos}.", 'green');

        return EXIT_SUCCESS;
    }

    private function cifrarColumna(string $tabla, string $columna): int
    {
        $db = db_connect();
        $filas = $db->table($tabla)
            ->select("id, {$columna}")
            ->where("{$columna} IS NOT NULL", null, false)
            ->where("{$columna} NOT LIKE 'enc:%'", null, false)
            ->get()
            ->getResultArray();

        foreach ($filas as $fila) {
            $db->table($tabla)->where('id', $fila['id'])->update([$columna => Cifrado::cifrar((string) $fila[$columna])]);
        }

        return count($filas);
    }
}
