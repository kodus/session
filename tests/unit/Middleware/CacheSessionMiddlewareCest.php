<?php

namespace Kodus\Session\Tests\Unit\Middleware;


use Kodus\Session\Middleware\CacheSessionMiddleware;
use Kodus\Session\Tests\Unit\Mocks\CacheSessionStorageMock;
use Kodus\Session\Tests\Unit\Mocks\DelegateMock;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use UnitTester;
use Zend\Diactoros\ServerRequest;

class CacheSessionMiddlewareCest
{
    public function test(UnitTester $I)
    {
        $I->wantToTest("CacheSessionMiddleware functionality");

        $cache = new MockCache();
        $storage = new CacheSessionStorageMock($cache, 3600);
        $delegate = new DelegateMock();
        $middleware = new CacheSessionMiddleware($storage); // Subject under test

        $key = "hello key";
        $value = "hello middleware";

        $I->assertNull($storage->get($key), "Before the request has been run, nothing is in cache");

        $delegate->setNext(function () use ($storage, $key, $value) {
            $storage->set($key, $value);
        });

        $response = $middleware->process(new ServerRequest(), $delegate);

        $value = $storage->get($key);

        $I->assertEquals("hello middleware", $value, "Checking the value of TestSessionModelA was stored correctly");

        $I->assertTrue($response->hasHeader("set-cookie"));

        $headers = $response->getHeader("set-cookie");

        $I->assertEquals(1, count($headers), "Only one cookie header should be set from the session");
        $I->assertNotEmpty($storage->getSessionID(), "Should have a non-empty session id");

        $expected_cookie = CacheSessionStorageMock::COOKIE_KEY . "=" . $storage->getSessionID() . "; Max-Age=3600; Expires=3600; Path=/;";

        $I->assertSame($expected_cookie, $headers[0], "Cookie should match expected values and session ID");
    }
}
