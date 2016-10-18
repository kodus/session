<?php

namespace Kodus\Test\Unit;

use Kodus\Session\SessionModel;
use Kodus\Session\SessionService;
use Kodus\Session\SessionStorage;
use RuntimeException;
use UnitTester;

class SessionServiceCest
{
    public function pokeAndProd(UnitTester $I)
    {
        $storage = new MockStorage("test");
        $service = new SessionService($storage);

        $first_value = "hello";
        $second_value = "bonjour!";

        $foo = new Foo();
        $foo->bar = $first_value;

        # How to write sessions
        $service->set($foo);

        $baz = new Baz();
        $baz->qux = $first_value;
        $service->set($baz);

        # How to read sessions
        /** @var Foo $foo */
        $foo = $service->get(Foo::class);
        /** @var Baz $baz */
        $baz = $service->get(Baz::class);

        $I->assertSame($first_value, $foo->bar);

        $service->commit();
        $service = new SessionService($storage);

        $I->assertEquals($storage->get(Foo::class), $foo);
        $I->assertEquals($storage->get(Baz::class), $baz);

        # Clearing
        $service->clear();

        $baz->qux = $second_value;

        $service->set($baz);

        $service->commit();

        $I->assertNotEquals($storage->get(Foo::class), $foo,
            "After clear() and commit(), the object should no longer be stored");
        $I->assertEquals($storage->get(Baz::class), $baz,
            "Calling write() after calling clear() should be a valid sequence of actions");

        # Flashes
        $foo->bar = $second_value;
        $service->flash($foo);

        $service->commit();

        /** @var Foo $foo */
        $foo = $service->get(Foo::class);

        $I->assertEquals($foo->bar, $second_value);

        $service->commit();

        $I->assertFalse($service->has(Foo::class));

        $exception_happened = false;
        try {
            $foo = $service->get(Foo::class);
        } catch (RuntimeException $e) {
            $exception_happened = true;
        }
        $I->assertTrue($exception_happened);
        # Remove
        $foo->bar = $first_value;
        $service->set($foo);
        $service->commit();

        /** @var Foo $foo */
        $foo = $service->get(Foo::class);
        $I->assertSame($first_value, $foo->bar);

        $service->unset(Foo::class);
        $I->assertFalse($service->has(Foo::class));

        $exception_happened = false;
        try {
            $service->get(Foo::class);
        } catch (RuntimeException $e) {
            $exception_happened = true;
        }
        $I->assertTrue($exception_happened);

        $service->commit();
        $I->assertFalse($service->has(Foo::class));

        $exception_happened = false;
        try {
            $service->get(Foo::class);
        } catch (RuntimeException $e) {
            $exception_happened = true;
        }
        $I->assertTrue($exception_happened);

        # Remove and subsequent write in same request
        $baz->qux = "gutentag";
        $service->set($baz);

        $service->unset(Baz::class);

        $baz->qux = $first_value;
        $service->set($baz);

        $service->commit();

        /** @var Baz $baz */
        $baz = $service->get(Baz::class);

        $I->assertSame($first_value, $baz->qux);

        $service->unset(Baz::class);

        $baz->qux = $second_value;
        $service->set($baz);

        $service->commit();

        /** @var Baz $baz */
        $baz = $service->get(Baz::class);

        $I->assertSame($second_value, $baz->qux);
    }
}

class Foo implements SessionModel
{
    public $bar;
}

class Baz implements SessionModel
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

    public function has($key)
    {
        return isset($this->cache[$key]);
    }
}
