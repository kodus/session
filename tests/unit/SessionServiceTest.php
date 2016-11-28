<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

/**
 * Generic test for implementations of Kodus\Session\SessionService.
 *
 * Test implementations by creating a codeception cest class that extends this.
 */
abstract class SessionServiceTest
{
    public function basicFunctionality(UnitTester $I)
    {
        $I->wantToTest("basic functionality");

        $session_service = $this->getSessionService();

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

        $cookie_1 = sprintf("%s=%s; Path=/;", SessionService::COOKIE_NAME, $session_id_1);
        $cookie_2 = sprintf("%s=%s; Path=/;", SessionService::COOKIE_NAME, $session_id_2);

        $I->assertTrue(in_array($cookie_1, $cookie_headers_1), "Session cookie is set in commitSession");
        $I->assertTrue(in_array($cookie_2, $cookie_headers_2), "Session cookie is set in commitSession");

        $next_request_1 = (new ServerRequest())->withCookieParams([SessionService::COOKIE_NAME => $session_id_1]);
        $next_request_2 = (new ServerRequest())->withCookieParams([SessionService::COOKIE_NAME => $session_id_2]);

        $session_data_1 = $session_service->createSession($next_request_1);
        $session_data_2 = $session_service->createSession($next_request_2);

        $I->assertSame($session_id_1, $session_data_1->getSessionID(), "Next requests has same session");
        $I->assertSame($session_id_2, $session_data_2->getSessionID(), "Next requests has same session");

        $I->assertEquals($session_model_a_1, $session_data_1->get(TestSessionModelA::class),
            "SessionData contains the saved session model");

        $I->assertEquals($session_model_a_2, $session_data_2->get(TestSessionModelA::class),
            "SessionData contains the saved session model");
    }

    abstract protected function getSessionService(): SessionService;
}
