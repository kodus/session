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
        $service->write($foo);

        $baz = new Baz();
        $baz->qux = $first_value;
        $service->write($baz);

        # How to read sessions
        /** @var Foo $foo */
        $foo = $service->read(Foo::class);
        /** @var Baz $baz */
        $baz = $service->read(Baz::class);

        $I->assertSame($first_value, $foo->bar);

        $service->commit();
        $service = new SessionService($storage);

        $I->assertEquals($storage->get(Foo::class), $foo);
        $I->assertEquals($storage->get(Baz::class), $baz);

        # Clearing
        $service->clear();

        $baz->qux = $second_value;

        $service->write($baz);

        $service->commit();

        $I->assertNotEquals($storage->get(Foo::class), $foo,
            "After clear() and commit(), the object should no longer be stored");
        $I->assertEquals($storage->get(Baz::class), $baz,
            "Calling write() after calling clear() should be a valid sequence of actions");

        # Flashes
        $foo->bar = $second_value;
        $service->write($foo, true);

        $service->commit();

        /** @var Foo $foo */
        $foo = $service->read(Foo::class);

        $I->assertEquals($foo->bar, $second_value);

        $service->commit();

        $I->assertFalse($service->has(Foo::class));

        $exception_happened = false;
        try {
            $foo = $service->read(Foo::class);
        } catch (RuntimeException $e) {
            $exception_happened = true;
        }
        $I->assertTrue($exception_happened);
        # Remove
        $foo->bar = $first_value;
        $service->write($foo);
        $service->commit();

        /** @var Foo $foo */
        $foo = $service->read(Foo::class);
        $I->assertSame($first_value, $foo->bar);

        $service->remove(Foo::class);
        $I->assertFalse($service->has(Foo::class));

        $exception_happened = false;
        try {
            $service->read(Foo::class);
        } catch (RuntimeException $e) {
            $exception_happened = true;
        }
        $I->assertTrue($exception_happened);

        $service->commit();
        $I->assertFalse($service->has(Foo::class));

        $exception_happened = false;
        try {
            $service->read(Foo::class);
        } catch (RuntimeException $e) {
            $exception_happened = true;
        }
        $I->assertTrue($exception_happened);

        # Remove and subsequent write in same request
        $baz->qux = "gutentag";
        $service->write($baz);

        $service->remove(Baz::class);

        $baz->qux = $first_value;
        $service->write($baz);

        $service->commit();

        /** @var Baz $baz */
        $baz = $service->read(Baz::class);

        $I->assertSame($first_value, $baz->qux);

        $service->remove(Baz::class);

        $baz->qux = $second_value;
        $service->write($baz);

        $service->commit();

        /** @var Baz $baz */
        $baz = $service->read(Baz::class);

        $I->assertSame($second_value, $baz->qux);
    }
}

class Foo extends SessionModel
{
    public $bar;
}

class Baz extends SessionModel
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
