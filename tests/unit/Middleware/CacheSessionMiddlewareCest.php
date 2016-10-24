<?php

namespace Kodus\Session\Tests\Unit\Middleware;


use Kodus\Session\Middleware\CacheSessionMiddleware;
use Kodus\Session\Service\CacheSessionService;
use Kodus\Session\Tests\Unit\Mocks\DelegateMock;
use Kodus\Session\Tests\Unit\Mocks\FooSessionModel;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class CacheSessionMiddlewareCest
{
    public function test(UnitTester $I)
    {
        $cache = new MockCache();
        $session_service = new CacheSessionService($cache);
        $middleware = new CacheSessionMiddleware($session_service);

        $foo = new FooSessionModel();
        $foo->baz = "hello";

        $delegate = new DelegateMock(function () use ($session_service, $foo) {
           $session_service->set($foo);
        });

        $request = new ServerRequest([], [], "/foo", "GET");
        $middleware->process($request, $delegate);

        $session_id = $session_service->getSessionID();

        $delegate->stuff_to_do = function () use ($session_service, $foo, $I) {
            $I->assertTrue($session_service->has(FooSessionModel::class));
            $I->assertEquals($foo, $session_service->get(FooSessionModel::class));
        };

        $request = new ServerRequest([], [], "/foo", "GET", 'php://input', [], [CacheSessionService::COOKIE_KEY => $session_id]);

        $middleware->process($request, $delegate);
    }
}
