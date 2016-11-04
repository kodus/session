<?php

namespace Kodus\Session\Tests\Unit;

use Closure;
use Exception;
use Kodus\Session\SessionService;
use Kodus\Session\SessionStorage;
use Kodus\Session\Storage\CacheSessionStorage;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelB;
use Kodus\Session\Tests\Unit\Mocks\CacheSessionStorageMock;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use RuntimeException;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class SessionServiceCest
{
    /**
     * @var CacheSessionStorage[]
     */
    private $storages = [];

    /**
     * @var MockCache
     */
    private $cache;

    public function _before(UnitTester $I)
    {
        $this->cache = new MockCache();
    }

    public function test(UnitTester $I)
    {
        $I->wantToTest("SessionService functionality");

        $service = $this->createSessionService();

        $model_a = new TestSessionModelA();
        $model_a->foo = "Hello";
        $model_a->bar = "session";

        $model_b = new TestSessionModelB();
        $model_b->foo = "Bonjour";
        $model_b->bar = "Monsieur";

        $service->set($model_a);

        $I->assertEquals($model_a, $service->get(TestSessionModelA::class), "Returns equal object to what was stored");
        $I->assertNull($service->get(TestSessionModelB::class), "Returns null when nothing stored");

        $service = $this->nextRequest($service);

        $I->assertEquals($model_a, $service->get(TestSessionModelA::class), "Returns equal object to what was stored");
        $I->assertNull($service->get(TestSessionModelB::class), "Returns null when nothing stored");

        $service->unset(TestSessionModelA::class);

        $I->assertNull($service->get(TestSessionModelA::class), "After calling unset(x), x is not in session");

        $service = $this->nextRequest($service);

        $I->assertNull($service->get(TestSessionModelA::class), "After calling unset(x), x is not in session");

        $service->flash($model_a);

        $I->assertEquals($model_a, $service->get(TestSessionModelA::class), "Flashes can be fetched in same request.");

        $service = $this->nextRequest($service, 301);
        $service = $this->nextRequest($service, 302);
        $service = $this->nextRequest($service, 303);
        $service = $this->nextRequest($service, 500);

        $I->assertEquals($model_a, $service->get(TestSessionModelA::class),
            "Flash messages are present after a series of redirect or fail responses");

        $service = $this->nextRequest($service);

        $I->assertNull($service->get(TestSessionModelA::class),
            "Flash messages are removed after the first request returning 200");

        $service->set($model_a);
        $service->set($model_b);

        $service = $this->nextRequest($service);

        $service->clear();

        $I->assertNull($service->get(TestSessionModelA::class), "No models in storage after clear()");
        $I->assertNull($service->get(TestSessionModelB::class), "No models in storage after clear()");

        $service->set($model_a);
        $I->assertEquals($model_a, $service->get(TestSessionModelA::class), "Set models in storage after clear()");

        $service = $this->nextRequest($service);

        $I->assertEquals($model_a, $service->get(TestSessionModelA::class), "Set models in storage after clear()");
        $I->assertNull($service->get(TestSessionModelB::class), "No models in storage after clear()");
    }

    /**
     * @inheritdoc
     */
    protected function nextRequest(SessionService $service, $response_code = 200)
    {
        $response = new Response('php://temp', $response_code);

        $session_id = $service->getSessionID();

        $this->storages[$session_id]->commit($response);

        $new_service = $this->createSessionService($session_id);

        return $new_service;
    }

    protected function createSessionService($session_id = null, $session_ttl = 3600)
    {
        $storage = $this->createSessionStorage($session_id, $session_ttl);

        return new SessionService($storage);
    }

    /**
     * @param string|null $session_id
     * @param int         $session_ttl
     * @param int         $static_time
     *
     * @return SessionStorage
     */
    protected function createSessionStorage($session_id = null, $session_ttl = 3600, $static_time = 0)
    {
        $cookies = [CacheSessionStorageMock::COOKIE_KEY => $session_id];

        $request = new ServerRequest([], [], "/", "GET", 'php://input', [], $cookies);

        $storage = new CacheSessionStorageMock($this->cache, $session_ttl);

        $storage->time = $static_time;

        $storage->begin($request);

        $this->storages[$storage->getSessionID()] = $storage;

        return $storage;
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
