<?php

namespace Kodus\Test\Unit;

use Kodus\Session\SessionService;
use Kodus\Session\SessionStorage;
use UnitTester;

class SessionServiceCest
{
    public function pokeAndProd(UnitTester $I)
    {
        $storage = new MockStorage("test");
        $service = new SessionService($storage);

        $value = "hello";

        # How to write sessions
        $service->write(function (Foo $foo) use ($value) {
            $foo->bar = $value;
        });

        $service->write(function (Baz $baz) use ($value) {
            $baz->qux = $value;
        });

        # How to read sessions
        $foo = new Foo();
        $service->read($foo);

        $baz = new Baz();
        $service->read($baz);

        $I->assertSame($value, $foo->bar);

        $service->commit();
        $service = new SessionService($storage);

        $I->assertEquals($storage->get(Foo::class), $foo);
        $I->assertEquals($storage->get(Baz::class), $baz);

        # Clearing
        $service->clear();

        $new_value = "new value";

        $service->write(function (Baz $baz) use ($new_value) {
            $baz->qux = $new_value;
        });

        $service->read($baz);

        $service->commit();

        $I->assertNotEquals($storage->get(Foo::class), $foo);
        $I->assertEquals($storage->get(Baz::class), $baz);
        $I->assertSame($new_value, $baz->qux);

        # Flashes
        $service->flash(function (Foo $foo) use ($new_value) {
            $foo->bar = $new_value;
        });

        $service->commit();

        $service->read($foo);

        $I->assertEquals($foo->bar, $new_value);

        $service->commit();

        $service->read($foo);

        $I->assertNull($foo->bar);

        # Remove
        $service->write(function (Foo $foo) use ($value) {
            $foo->bar = $value;
        });
        $service->commit();
        $service->read($foo);
        $I->assertSame($value, $foo->bar);

        $service->remove(Foo::class);
        $service->read($foo);
        $I->assertNull($foo->bar);

        $service->commit();
        $service->read($foo);
        $I->assertNull($foo->bar);

        # Remove and subsequent write in same request
        $service->write(function (Baz $baz) {
           $baz->qux = "nonsense";
        });

        $service->remove(Baz::class);

        $service->write(function (Baz $baz) use ($value) {
            $baz->qux = $value;
        });

        $service->commit();

        $service->read($baz);

        $I->assertSame($value, $baz->qux);

        $service->remove(Baz::class);

        $service->write(function (Baz $baz) use ($new_value) {
            $baz->qux = $new_value;
        });

        $service->commit();

        $service->read($baz);

        $I->assertSame($new_value, $baz->qux);
    }
}

class Foo
{
    public $bar;
}

class Baz
{
    public $qux;
}

class MockStorage implements SessionStorage
{

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var array
     */
    private $cache;

    public function __construct($namespace)
    {
        $this->namespace = $namespace;
        $this->cache = [];
    }

    public function get($key)
    {
        return @$this->cache[$key] ?: null;
    }

    public function set($key, $value)
    {
        $this->cache[$key] = $value;
    }

    public function clear()
    {
        $this->cache = [];
    }

    public function remove($key)
    {
        unset($this->cache[$key]);
    }
}
