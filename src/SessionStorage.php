<?php

namespace Kodus\Session;

use Kodus\Session\Adapters\SimpleCacheAdapter;

/**
 * The Session Storage abstraction defines a contract for reading/writing/deleting raw Session Data.
 *
 * @see SimpleCacheAdapter
 */
interface SessionStorage
{
    /**
     * Read raw Session Data from underlying storage.
     *
     * @param string $session_id
     *
     * @return array|null
     */
    public function read(string $session_id): ?array;

    /**
     * Write raw Session Data to underlying storage.
     *
     * @param string $session_id
     * @param array  $data
     * @param int    $ttl time to live (in seconds)
     *
     * @return void
     */
    public function write(string $session_id, array $data, int $ttl);

    /**
     * Destroy the entire session by forcibly removing raw Session Data from underlying storage.
     *
     * Note that this differs substantially from {@see Session::clear()}, which is the appropriate
     * way to clear the current user's session - the `destroy()` method is used internally to
     * flush empty sessions from storage, but may also be useful for (rare) use-cases, such as
     * forcibly destroying the active session of a blocked/banned user.
     *
     * For actions such as users pressing a logout button, {@see Session::clear()} is more appropriate.
     *
     * @param string $session_id
     *
     * @return void
     */
    public function destroy(string $session_id);
}
