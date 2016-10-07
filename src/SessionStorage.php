<?php


namespace Kodus\Session;

interface SessionStorage
{
    public function __construct($namespace); //????????

    public function get($key);

    public function set($key, $value);

    public function remove($key);

    public function clear();
}
