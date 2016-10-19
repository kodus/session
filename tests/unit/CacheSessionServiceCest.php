<?php

namespace Kodus\Tests\Unit;

use Closure;
use Exception;
use Kodus\Session\Service\CacheSessionService;
use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\Mocks\BarSessionModel;
use Kodus\Session\Tests\Unit\Mocks\FooSessionModel;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use RuntimeException;
use UnitTester;

class CacheSessionServiceCest
{
    /**
     * @var MockCache
     */
    private $cache;

    public function __construct()
    {
        $this->cache = new MockCache();
    }

    public function _before(UnitTester $I)
    {
        $this->cache = new MockCache();
    }

    public function setAndGet(UnitTester $I)
    {
        $I->wantToTest("Basic get and set functionality of " . CacheSessionService::class);

        $service = $this->createSessionService();

        $value_1 = "1st value";
        $value_2 = "2nd value";

        $foo_session = new FooSessionModel();
        $foo_session->baz = $value_1;

        $bar_session = new BarSessionModel();
        $bar_session->qux = $value_2;

        $I->assertFalse($service->has(FooSessionModel::class),
            "The SessionService::has(X) only returns true if X is stored in session");
        $I->assertFalse($service->has(BarSessionModel::class),
            "The SessionService::has(X) only returns true if X is stored in session");

        $service->set($foo_session);
        $service->set($bar_session);

        $I->assertTrue($service->has(FooSessionModel::class), "After setting X SessionService::has(X) returns true");
        $I->assertTrue($service->has(BarSessionModel::class), "After setting X SessionService::has(X) returns true");

        $I->assertEquals($foo_session, $service->get(FooSessionModel::class),
            "It should be possible to get models set before committing to storage");
        $I->assertEquals($bar_session, $service->get(BarSessionModel::class),
            "It should be possible to get models set before committing to storage");

        $service->commit();

        $service = $this->createSessionService(); //New service - same cache

        $I->assertTrue($service->has(FooSessionModel::class), "After setting X SessionService::has(X) returns true");
        $I->assertTrue($service->has(BarSessionModel::class), "After setting X SessionService::has(X) returns true");

        $I->assertEquals($foo_session, $service->get(FooSessionModel::class),
            "It should be possible to get models set before committing to storage");
        $I->assertEquals($bar_session, $service->get(BarSessionModel::class),
            "It should be possible to get models set before committing to storage");
    }

    public function clearingSession(UnitTester $I)
    {
        $I->wantToTest("Clearing Session");

        $service = $this->createSessionService();

        $value_1 = "1st value";
        $value_2 = "2nd value";

        $foo_session = new FooSessionModel();
        $foo_session->baz = $value_1;

        $bar_session = new BarSessionModel();
        $bar_session->qux = $value_2;

        $service->set($foo_session);
        $service->set($bar_session);

        $service->commit();

        $service = $this->createSessionService(); // New service - same cache

        $service->clear();

        $I->assertFalse($service->has(FooSessionModel::class),
            "Immediately after calling SessionService::clear(), SessionService::has(X) should false for all X");
        $I->assertFalse($service->has(BarSessionModel::class),
            "Immediately after calling SessionService::clear(), SessionService::has(X) should false for all X");

        $I->assertTrue(
            $this->catchException(RuntimeException::class, function () use ($service) {
                $service->get(FooSessionModel::class);
            }),
            "Calling get for a session model not stored in session, should result in a RuntimeException");

        $service->commit();

        $service = $this->createSessionService(); // New service - same cache

        $I->assertFalse($service->has(FooSessionModel::class),
            "Immediately after calling SessionService::clear(), SessionService::has(X) should false for all X");
        $I->assertFalse($service->has(BarSessionModel::class),
            "Immediately after calling SessionService::clear(), SessionService::has(X) should false for all X");

        $I->assertTrue(
            $this->catchException(RuntimeException::class, function () use ($service) {
                $service->get(FooSessionModel::class);
            }),
            "Calling get for a session model not stored in session, should result in a RuntimeException");
    }

    /**
     * @param UnitTester $I
     */
    public function flashSessions(UnitTester $I)
    {
        $I->wantToTest("Flash session functionality");

        $service = $this->createSessionService();

        $foo_session = new FooSessionModel();
        $foo_session->baz = "A random value";

        $bar_session = new BarSessionModel();
        $bar_session->qux = "Another random value";

        $service->flash($foo_session);

        $service->flash($bar_session);
        $service->set($bar_session); //Make sure that you can override flash message with regular message

        $service->commit();
        $service = $this->createSessionService(); //New service - same storage

        $I->assertTrue($service->has(FooSessionModel::class),
            "First request after committing should contain flashed session object");
        $I->assertTrue($service->has(BarSessionModel::class),
            "First request after committing regular session object");

        $I->assertEquals($foo_session, $service->get(FooSessionModel::class));
        $I->assertEquals($bar_session, $service->get(BarSessionModel::class));

        $service->commit();
        $service = $this->createSessionService(); //New service - same storage

        $I->assertFalse($service->has(FooSessionModel::class),
            "In the second request, the flash session object is no longer available");
        $I->assertTrue($service->has(BarSessionModel::class),
            "In the second request, the regular session object is still available");

        $I->assertEquals($bar_session, $service->get(BarSessionModel::class),
            "In the second request, the regular session object is still available");

        //TODO add test for 3xx responses
    }

    public function clearAndSetNewStuff(UnitTester $I)
    {
        $I->wantToTest("Clearing session and writing new session in one transaction");

        $service = $this->createSessionService();

        $value_1 = "1st value";
        $value_2 = "2nd value";
        $value_3 = "3rd value";

        $foo_session = new FooSessionModel();
        $foo_session->baz = $value_1;

        $service->set($foo_session);

        $service->commit();
        $service = $this->createSessionService(); // New service - same cache

        $bar_session = new BarSessionModel();
        $bar_session->qux = $value_2;

        $service->set($bar_session);

        $service->clear();

        $foo_session->baz = $value_3;
        $service->set($foo_session);

        $I->assertTrue($service->has(FooSessionModel::class),
            "FooSessionModel was added after clear() and should be available");
        $I->assertFalse($service->has(BarSessionModel::class),
            "BarSessionModel was added before clear() and shouldn't be available");
        $I->assertEquals($foo_session, $service->get(FooSessionModel::class),
            "FooSessionModel should reflect the most recent state stored to SessionService");

        $service->commit();
        $service = $this->createSessionService(); // New service - same cache

        $I->assertTrue($service->has(FooSessionModel::class),
            "FooSessionModel was added after clear() and should be available in next request");
        $I->assertFalse($service->has(BarSessionModel::class),
            "BarSessionModel was added before clear() and shouldn't be available in next request");
        $I->assertEquals($foo_session, $service->get(FooSessionModel::class),
            "FooSessionModel should reflect the most recent state stored to SessionService in next request");
    }

    public function unsetSessionModel(UnitTester $I)
    {
        $I->wantToTest("Removing specific session model objects from the session");

        $service = $this->createSessionService();

        $value_1 = "1st value";
        $value_2 = "2nd value";

        $foo_session = new FooSessionModel();
        $foo_session->baz = $value_1;

        $bar_session = new BarSessionModel();
        $bar_session->qux = $value_2;

        $service->set($foo_session);
        $service->unset(FooSessionModel::class);

        $service->set($bar_session);
        $service->unset(BarSessionModel::class);
        $service->set($bar_session);

        $I->assertFalse($service->has(FooSessionModel::class),
            "SessionService::has(X) should return false after SessionService::unset(X)");
        $I->assertTrue($service->has(BarSessionModel::class),
            "SessionService should have BarSessionModel after setting");

        $service->set($foo_session);
        $service->commit();

        $service = $this->createSessionService();

        $I->assertTrue($service->has(FooSessionModel::class), "FooSessionModel was added before commit()");
        $I->assertTrue($service->has(BarSessionModel::class), "BarSessionModel was added before commit()");

        $service->unset(BarSessionModel::class);
        $I->assertFalse($service->has(BarSessionModel::class),
            "SessionService::has(X) should return false after SessionService::unset(X) even if it is stored in SessionStorage still");

        $service->commit();
        $service = $this->createSessionService();

        $I->assertFalse($service->has(BarSessionModel::class),
            "SessionService::has(X) should return false after SessionService::unset(X)");
    }

    /**
     * @return SessionService
     */
    protected function createSessionService()
    {
        return new CacheSessionService($this->cache);
    }

    /**
     * @param string  $type    The fully qualified class name of the exception to check for.
     * @param Closure $closure The closure containing the code to test for exceptions.
     *
     * @return bool Returns true if the closure given throws an exception of the type givne by the $type argument.
     * @throws Exception
     */
    protected function catchException($type, Closure $closure)
    {
        try {
            $closure();
        } catch (Exception $e) {
            if ($e instanceof $type) {
                return true;
            } else {
                throw $e;
            }
        }

        return false;
    }
}
