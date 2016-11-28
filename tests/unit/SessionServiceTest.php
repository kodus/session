<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use Psr\Http\Message\ResponseInterface;
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
        $I->wantToTest("SessionService functionality");

        $session_service = $this->getSessionService();

        $request_1 = new ServerRequest(['REMOTE_ADDR' => "10.0.0.1"]);
        $request_2 = new ServerRequest(['REMOTE_ADDR' => "10.0.0.2"]);

        $session_data_1 = $session_service->createSession($request_1);
        $session_data_2 = $session_service->createSession($request_2);

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

        $cookies_1 = $this->getCookies($response_1);
        $cookies_2 = $this->getCookies($response_2);

        $next_request_1 = (new ServerRequest(['REMOTE_ADDR' => "10.0.0.1"]))->withCookieParams($cookies_1);
        $next_request_2 = (new ServerRequest(['REMOTE_ADDR' => "10.0.0.2"]))->withCookieParams($cookies_2);

        $session_data_1 = $session_service->createSession($next_request_1);
        $session_data_2 = $session_service->createSession($next_request_2);

        $I->assertSame($session_id_1, $session_data_1->getSessionID(), "Next requests has same session");
        $I->assertSame($session_id_2, $session_data_2->getSessionID(), "Next requests has same session");

        $I->assertEquals($session_model_a_1, $session_data_1->get(TestSessionModelA::class),
            "SessionData contains the saved session model");

        $I->assertEquals($session_model_a_2, $session_data_2->get(TestSessionModelA::class),
            "SessionData contains the saved session model");

        $next_request_1 = (new ServerRequest(['REMOTE_ADDR' => "10.0.0.3"]))->withCookieParams($cookies_1);

        $session_data_1 = $session_service->createSession($next_request_1);

        $I->assertNotEquals($session_id_1, $session_data_1->getSessionID(),
            "Same cookie from different IP results in a new session");

        $I->assertNotEquals($session_model_a_1, $session_data_1->get(TestSessionModelA::class),
            "Same cookie from different IP results in a new session");
    }

    /**
     * Get the instance of an implementation of SessionService to test.
     *
     * @return SessionService
     */
    abstract protected function getSessionService(): SessionService;

    private function getCookies(ResponseInterface $response)
    {
        $cookie_headers = $response->getHeader("Set-Cookie");

        $cookies = [];

        foreach ($cookie_headers as $cookie_string) {
            $cookie_pair = mb_substr($cookie_string, 0, mb_strpos($cookie_string, ";"));

            $cookie_key = mb_substr($cookie_pair, 0, mb_strpos($cookie_pair, "="));
            $cookie_value = mb_substr($cookie_pair, mb_strpos($cookie_pair, "=") + 1);

            $cookies[$cookie_key] = $cookie_value;
        }

        return $cookies;
    }
}
