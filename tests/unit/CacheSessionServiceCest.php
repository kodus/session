<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Session\Adapters\CacheSessionService;
use Kodus\Session\Tests\Unit\Mocks\DelegateMock;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class CacheSessionServiceCest
{
    public function basicFunctionality(UnitTester $I)
    {
        $I->wantToTest("basic functionality");

        $session_service = new CacheSessionService(new MockCache(), "a salty string", CacheSessionService::TWO_WEEKS);

        $request = new ServerRequest();

        $session_data_1 = $session_service->createSession($request);
        $session_data_2 = $session_service->createSession($request);

        $session_id_1 = $session_data_1->getSessionID();
        $session_id_2 = $session_data_2->getSessionID();

        $I->assertNotEquals($session_id_1, $session_id_2, "A request without a session cookie gets a new session");

        $session_model_a_1 = new TestSessionModelA();
        $session_model_a_1->foo = "hello";
        $session_model_a_1->bar = "world";

        $session_data_1->put($session_model_a_1);

        $session_model_a_2 = new TestSessionModelA();
        $session_model_a_2->foo = "bonjour";
        $session_model_a_2->bar = "le monde";

        $session_data_2->put($session_model_a_2);

        $response_1 = $session_service->commitSession($session_data_1, new Response());
        $response_2 = $session_service->commitSession($session_data_2, new Response());

        $cookie_headers_1 = $response_1->getHeader("Set-Cookie");
        $cookie_headers_2 = $response_2->getHeader("Set-Cookie");

        $cookie_1 = sprintf("%s=%s; Path=/;", CacheSessionService::COOKIE_NAME, $session_id_1);
        $cookie_2 = sprintf("%s=%s; Path=/;", CacheSessionService::COOKIE_NAME, $session_id_2);

        $I->assertTrue(in_array($cookie_1, $cookie_headers_1), "Session cookie is set in commitSession");
        $I->assertTrue(in_array($cookie_2, $cookie_headers_2), "Session cookie is set in commitSession");

        $next_request_1 = (new ServerRequest())->withCookieParams([CacheSessionService::COOKIE_NAME => $session_id_1]);
        $next_request_2 = (new ServerRequest())->withCookieParams([CacheSessionService::COOKIE_NAME => $session_id_2]);

        $session_data_1 = $session_service->createSession($next_request_1);
        $session_data_2 = $session_service->createSession($next_request_2);

        $I->assertTrue($session_id_1 = $session_data_1->getSessionID(), "Next requests has same session 1");
        $I->assertTrue($session_id_2 = $session_data_2->getSessionID(), "Next requests has same session 2");

        $I->assertEquals($session_model_a_1, $session_data_1->get(TestSessionModelA::class),
            "SessionData contains the saved session model");

        $I->assertEquals($session_model_a_2, $session_data_2->get(TestSessionModelA::class),
            "SessionData contains the saved session model");
    }
}
