<?php

namespace Kodus\Session\Tests\Unit\Adapters;

use Kodus\Session\Adapters\CacheSessionService;
use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\Mocks\CacheMock;
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
        $cache = new CacheMock(0);

        $service = new CacheSessionService($cache, 3600, false); //Session lasts 3600 sec. = 1 hour

        $session = $service->createSession(new ServerRequest());

        $model_1 = new TestSessionModelA();
        $model_1->foo = "hello life time test";

        $model_2 = new TestSessionModelB();
        $model_2->foo = "hello again";

        $session->put($model_1);
        $session->put($model_2);

        $response = $service->commitSession($session, new Response());

        $cookies = $this->getCookies($response);

        $cache->time = 1800; //Half an hour passes

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertEquals($model_2, $session->get(TestSessionModelB::class));

        $response = $service->commitSession($session, new Response());
        $cookies = $this->getCookies($response);

        $cache->time = 3601; //1 hour and 1 second since session initiated, only ½ hours since last interaction.

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertEquals($model_2, $session->get(TestSessionModelB::class));

        $response = $service->commitSession($session, new Response());

        $cookies = $this->getCookies($response);

        $cache->time = 7202; //Over an hour since last interaction

        $session = $service->createSession((new ServerRequest())->withCookieParams($cookies));

        $I->assertFalse($session->has(TestSessionModelA::class));
        $I->assertFalse($session->has(TestSessionModelA::class));
        $I->assertNotEquals($model_1, $session->get(TestSessionModelA::class));
        $I->assertNotEquals($model_2, $session->get(TestSessionModelB::class));
    }

    protected function getSessionService(): SessionService
    {
        return new CacheSessionService(new CacheMock(0), CacheSessionService::TWO_WEEKS, false);
    }
}
