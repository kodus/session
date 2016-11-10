<?php

namespace Kodus\Session\Interfaces;

/**
 * This interface defines an API for reading and writing key/value pairs in session.
 *
 * All write/remove/clear operations MUST be deffered until the end of the request.
 *
 * Implementations of SessionStorage should also implement TransactionalSessionStorage.
 *
 * @see \Kodus\Session\Interfaces\TransactionalSessionStorage
 */
interface SessionStorage
{
    /**
     * Set a key/value pair in session storage
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function set(string $key, $value);

    /**
     * Set a flash value in session storage. If a value was set under the same key, this value will be overriden.
     *
     * "Flash" messages will only live through the next request, that returns 2xx HTML response codes.
     *
     * That means that you can expect the flash messages to live through a redirect result, so for example if you have
     * multiple redirects in a POST-Redirect-GET pattern solution, the flash message is still available when you reach
     * the GET request.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function flash(string $key, $value);

    /**
     * Return the value stored under the given key or Null if nothing was stored
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * Remove any value stored under the given key.
     *
     * @param string $key
     *
     * @return void
     */
    public function unset(string $key);

    /**
     * Clear all values in session storage
     *
     * Any subsequent writes to the session are still valid.
     *
     * @return void
     */
    public function clear();

    /**
     * @return string Returns the unique id for the current session.
     */
    public function getSessionID(): string;
}
