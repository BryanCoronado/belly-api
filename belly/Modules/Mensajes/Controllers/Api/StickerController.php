<?php

namespace Belly\Mensajes\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Mensajes\UseCases\StickerUseCase;

class StickerController extends BaseController
{
    public function listar()
    {
        $busqueda = trim((string) $this->request->getGet('q'));
        $offset = (int) $this->request->getGet('offset');
        $datos = (new StickerUseCase())->listar($busqueda, $offset);

        return $this->response->setJSON([
            'success' => true,
            'data' => $datos['lista'],
            'aviso' => $datos['aviso'],
        ]);
    }
}
