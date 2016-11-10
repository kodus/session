<?php

namespace Kodus\Session\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * This interface defines an API for beginning a new session based on a PSR-7 RequestInterface object,
 * and committing deferred changes to the storage.
 *
 * Implementations of TransactionalSessionStorage should also implement SessionStorage.
 *
 * @see \Kodus\Session\SessionStorage
 */
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
