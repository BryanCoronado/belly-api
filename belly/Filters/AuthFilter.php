<?php

namespace Belly\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

class AuthFilter implements FilterInterface {

    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null) {

        $token = null;
        $authorization = $request->header('Authorization');

        if (isset($authorization) && ! empty($authorization)) {
            $parts = explode(' ', $authorization->getValue());
            $token = $parts[1] ?? null;
        }

        // Descargas por enlace/iframe: el navegador no envía Authorization en GET directo
        if (empty($token) && strtoupper($request->getMethod()) === 'GET') {
            $queryToken = $request->getGet('access_token');
            if (is_string($queryToken) && $queryToken !== '') {
                $token = $queryToken;
            }
        }

        if (empty($token)) {
            return $this->unAuthorizedResponse(lang('Auth.headerAuthorized'));
        }

        try {
            if (! auth()->isValid($token)) {
                throw new \Exception(lang('Auth.tokenInvalido'));
            }

            return $request;
        } catch (\Exception $exc) {
            return $this->unAuthorizedResponse($exc->getMessage());
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {
        //
    }

    private function unAuthorizedResponse(string $messages) {
        return service('response')->setJSON([
                    'status' => ResponseInterface::HTTP_UNAUTHORIZED,
                    'error' => 'AuthFilter',
                    'messages' => [
                        'error' => $messages
                    ],
                ])->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
    }
}
