<?php

namespace Belly\Configuracion\Controllers\Api;

use App\Controllers\BaseController;

class IntegracionWhatsappControllerApi extends BaseController
{
    public function estado()
    {
        return $this->ejecutar(static fn () => conexion_whatsapp()->estado());
    }

    public function conectado()
    {
        return $this->ejecutar(static fn () => ['conectado' => conexion_whatsapp()->estaConectada()]);
    }

    public function guardarAppMeta()
    {
        return $this->ejecutar(fn () => conexion_whatsapp()->guardarAppMeta($this->datos()));
    }

    public function regenerarToken()
    {
        return $this->ejecutar(static fn () => conexion_whatsapp()->regenerarToken());
    }

    public function conectarNumero()
    {
        return $this->ejecutar(fn () => conexion_whatsapp()->conectarNumero($this->datos()));
    }

    public function desconectarNumero(int $id)
    {
        return $this->ejecutar(static fn () => conexion_whatsapp()->desconectarNumero($id));
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $datos = $this->request->getJSON(true);

        return is_array($datos) ? $datos : [];
    }

    private function ejecutar(callable $accion)
    {
        try {
            return $this->response->setJSON(['success' => true, 'data' => $accion()]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->error(422, $e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->error(502, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[INTEGRACION WA] ' . $e->getMessage());

            return $this->error(400, $e->getMessage());
        }
    }

    private function error(int $codigo, string $mensaje)
    {
        return $this->response->setStatusCode($codigo)->setJSON(['success' => false, 'error' => $mensaje]);
    }
}
