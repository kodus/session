<?php

namespace Kodus\Session\Tests\Unit\Middleware;


use Kodus\Session\Middleware\CacheSessionMiddleware;
use Kodus\Session\Service\CacheSessionService;
use Kodus\Session\Tests\Unit\Mocks\CacheSessionServiceMock;
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
        $I->wantToTest("CacheSessionMiddleware functionality");

        $cache = new MockCache();
        $service = new CacheSessionServiceMock($cache, 3600);
        $delegate = new DelegateMock();
        $middleware = new CacheSessionMiddleware($service); // Subject under test

        $I->assertFalse($service->has(FooSessionModel::class), "Before the request has been run, nothing is in cache");

        // When the middleware is called with this delegate mock, the "next" middleware will set a FooSessionModel
        // instance in session.
        $delegate->setNextClosure(function () use ($service) {
            $foo = new FooSessionModel();
            $foo->baz = "hello middleware";
            $service->set($foo);
        });

        $response = $middleware->process(new ServerRequest(), $delegate);

        $I->assertTrue($service->has(FooSessionModel::class),
            "After running the session middleware the changes should be visible in the service");

        /** @var FooSessionModel $foo */
        $foo = $service->get(FooSessionModel::class);

        $I->assertEquals("hello middleware", $foo->baz, "Checking the value of FooSessionModel was stored correctly");

        $I->assertTrue($response->hasHeader("set-cookie"));

        $headers = $response->getHeader("set-cookie");

        $I->assertEquals(1, count($headers), "Only one cookie header should be set from the session");
        $I->assertNotEmpty($service->getSessionID(), "Should have a non-empty session id");

        $expected_cookie = CacheSessionService::COOKIE_KEY . "=" . $service->getSessionID() . "; Max-Age=3600; Expires=3600; Path=/;";

        $I->assertSame($expected_cookie, $headers[0], "Cookie should match expected values and session ID");
    }
}
