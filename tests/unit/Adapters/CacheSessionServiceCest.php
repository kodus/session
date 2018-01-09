<?php

namespace Kodus\Session\Tests\Unit\Adapters;

use Kodus\Cache\MockCache;
use Kodus\Session\Adapters\SimpleCacheAdapter;
use Kodus\Session\SessionService;
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
        $cache = new MockCache(0);

        $storage = new SimpleCacheAdapter($cache);

        $service = new SessionService($storage, 3600, false); // Session lasts 3600 sec. = 1 hour

        $session = $service->createSession(new ServerRequest());

        $model_1 = $session->get(TestSessionModelA::class);
        $model_1->foo = "hello life time test";

        $model_2 = $session->get(TestSessionModelB::class);
        $model_2->foo = "hello again";

        $response = $service->commitSession($session, new Response());

        $cookies = $this->getCookies($response);

        $cache->skipTime(1800); //Half an hour passes

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertEquals($model_2, $session->get(TestSessionModelB::class));

        $response = $service->commitSession($session, new Response());
        $cookies = $this->getCookies($response);

        $cache->skipTime(1801); //1 hour and 1 second since session initiated, only ½ hours since last interaction.

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertEquals($model_2, $session->get(TestSessionModelB::class));

        $response = $service->commitSession($session, new Response());

        $cookies = $this->getCookies($response);

        $cache->skipTime(5401); //Over an hour since last interaction

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertNull($session->get(TestSessionModelA::class)->foo);
        $I->assertNull($session->get(TestSessionModelB::class)->foo);

        $I->assertNotEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertNotEquals($model_2, $session->get(TestSessionModelB::class));
    }

    protected function getSessionService(): SessionService
    {
        $cache = new MockCache(0);

        $storage = new SimpleCacheAdapter($cache);

        return new SessionService($storage, SessionService::TWO_WEEKS, false);
    }
}
