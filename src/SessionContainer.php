<?php
namespace Kodus\Session;

use Closure;

interface SessionContainer
{
    public function __construct(SessionStorage $storage);

    public function flash(Closure $updater);

    public function write(Closure $updater);

    public function read(&$object);

    public function remove($type);

    public function clear();

    public function commit();
}
