<?php

namespace Kodus\Session\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface TransactionalSessionStorage
{
    /**
     * Initiate the session from the cookie params found in the server request
     *
     * @param ServerRequestInterface $request
     */
    public function begin(ServerRequestInterface $request);

    /**
     * Save Session data into the cache storage and add session cookie to response
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function commit(ResponseInterface $response): ResponseInterface;
}
