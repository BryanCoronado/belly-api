<?php

namespace Belly\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Bloquea chat, mensajes y plantillas si la empresa no tiene un número de WhatsApp conectado.
 * Debe ir después de authFilter.
 */
class WhatsappFilter implements FilterInterface
{
    public const CODIGO = 'whatsapp_no_conectado';

    public function before(RequestInterface $request, $arguments = null)
    {
        helper('vip2system');
        if (conexion_whatsapp()->estaConectada()) {
            return null;
        }

        return service('response')
            ->setStatusCode(ResponseInterface::HTTP_CONFLICT)
            ->setJSON([
                'success' => false,
                'codigo' => self::CODIGO,
                'error' => 'WhatsApp no está conectado.',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
