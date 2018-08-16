<?php

namespace Kodus\Session\Tests\Unit\Adapters;

use Kodus\Cache\MockCache;
use Kodus\Session\Adapters\SimpleCacheAdapter;
use Kodus\Session\SessionService;
use Kodus\Session\SessionStorage;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelB;
use Kodus\Session\Tests\Unit\SessionServiceTest;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class CacheSessionServiceCest extends SessionServiceTest
{
    public function sessionLifeTime(UnitTester $I)
    {
        // TODO review and determine the purpose of this test, if any?
        //
        //      it appears to be testing the behavior of MockCache,
        //      but it most likely only needs to test the interaction
        //      between the adapter and the cache?

        $cache = new MockCache(0);

        $storage = new SimpleCacheAdapter($cache);

        $service = new SessionService($storage, 60*60, false); // Session lasts 60 minutes

        $session = $service->createSession(new ServerRequest());

        $model_1 = $session->get(TestSessionModelA::class);
        $model_1->foo = "hello life time test";

        $model_2 = $session->get(TestSessionModelB::class);
        $model_2->foo = "hello again";

        $response = $service->commitSession($session, new Response());

        $cookies = $this->parseSetCookie($response);

        $cache->skipTime(30*60); // 30 minutes pass by

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertEquals($model_2, $session->get(TestSessionModelB::class));

        $response = $service->commitSession($session, new Response());

        $cache->skipTime(30*60-1); //1 hour and 1 second since session initiated, only ½ hours since last interaction.

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertEquals($model_2, $session->get(TestSessionModelB::class));

        $response = $service->commitSession($session, new Response());

        $cookies = $this->parseSetCookie($response);

        $cache->skipTime(5401); //Over an hour since last interaction

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertNull($session->get(TestSessionModelA::class)->foo);
        $I->assertNull($session->get(TestSessionModelB::class)->foo);

        $I->assertNotEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertNotEquals($model_2, $session->get(TestSessionModelB::class));
    }

    protected function createSessionStorage(): SessionStorage
    {
        $cache = new MockCache(0);

        return new SimpleCacheAdapter($cache);
    }
}
