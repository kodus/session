<?php

namespace Kodus\Session;


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
     * @param string $key
     *
     * @return bool Returns true if a key/value pair with the given key is stored in session
     */
    public function has(string $key): bool;

    /**
     * Return the value stored under the given key
     *
     * Don't call this method if self::has($key) returns false
     *
     * Implementations should throw a \RuntimeException if nothing is stored under the given key.
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
    public function sessionID(): string;
}
