<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Helpers\UUID;
use Kodus\Session\SessionService;
use Kodus\Session\SessionStorage;
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
    /**
     * Session ID must be a valid UUID v4
     */
    public function createValidSessionID(UnitTester $I)
    {
        $session_service = $this->createSessionService();

        $session = $session_service->createSession(new ServerRequest());

        $I->assertTrue(UUID::isValid($session->getSessionID()), "Session ID is a valid UUID v4");
    }

    /**
     * Unique Sessions must be generated for unique Requests
     */
    public function createUniqueSessions(UnitTester $I)
    {
        $session_service = $this->createSessionService();

        $request_1 = new ServerRequest();
        $request_2 = new ServerRequest();

        $session_data_1 = $session_service->createSession($request_1);
        $session_data_2 = $session_service->createSession($request_2);

        $session_id_1 = $session_data_1->getSessionID();
        $session_id_2 = $session_data_2->getSessionID();

        $I->assertNotEquals($session_id_1, $session_id_2, "creates unique Session IDs");
    }

    /**
     * Session ID must must be preserved in a cookie.
     *
     * A resumed Session must contain the restored Session Models from the previous Request.
     *
     * An updated Session must have the same Session ID and Session Models from the previous Request.
     */
    public function createAndResumeSession(UnitTester $I)
    {
        $service = $this->createSessionService();

        $first_session = $service->createSession(new ServerRequest());

        $model = $first_session->get(TestSessionModelA::class);

        $model->foo = "hello";
        $model->bar = "world";

        $first_response = $service->commitSession($first_session, new Response());

        $I->assertSame(
            SessionService::COOKIE_NAME . "=" . $first_session->getSessionID() . "; Path=/; HTTPOnly; SameSite=Lax; Expires=Thursday, 30-Aug-2018 13:22:47 GMT+0000",
            $first_response->getHeaderLine("Set-Cookie"),
            "committing emits a Session cookie"
        );

        $second_request = (new ServerRequest())->withCookieParams($this->parseSetCookie($first_response));

        $second_session = $service->createSession($second_request);

        $I->assertSame($first_session->getSessionID(), $second_session->getSessionID(), "second Request has same Session ID");

        $I->assertEquals(
            $model,
            $second_session->get(TestSessionModelA::class),
            "second Session contains the saved session model"
        );

        $model->bar = "universe";

        $second_response = $service->commitSession($second_session, new Response());

        $I->assertEmpty($second_response->getHeaderLine("Set-Cookie"), "resuming a Session does not re-emit cookie");
    }

    /**
     * A resumed session, if empty, should destroy the Session ID and revoke the Session Cookie.
     */
    public function destroyEmptySession(UnitTester $I)
    {
        $service = $this->createSessionService();

        $first_session = $service->createSession(new ServerRequest());

        $model = $first_session->get(TestSessionModelA::class);

        $model->foo = "hello";

        $first_response = $service->commitSession($first_session, new Response());

        $I->assertNotEmpty($first_response->getHeaderLine("Set-Cookie"));

        $second_request = (new ServerRequest())->withCookieParams($this->parseSetCookie($first_response));

        $second_session = $service->createSession($second_request);

        $second_session->clear();

        $second_response = $service->commitSession($second_session, new Response());

        $I->assertSame(
            SessionService::COOKIE_NAME . "=; Path=/; HTTPOnly; SameSite=Lax; Expires=Thursday, 01-Jan-1970 00:00:00 GMT+0000",
            $second_response->getHeaderLine("Set-Cookie"),
            "committing an resumed, empty session erases and expires the Session Cookie"
        );
    }

    /**
     * When the Session is empty, the Session ID should *not* a preserved in a cookie.
     */
    public function doNotCreateEmptySessions(UnitTester $I)
    {
        $session_service = $this->createSessionService();

        $session_data = $session_service->createSession(new ServerRequest());

        $response = $session_service->commitSession($session_data, new Response());

        $I->assertEmpty(
            $response->getHeaderLine("Set-Cookie"),
            "committing an empty Session does not emit a Session cookie"
        );
    }

    /**
     * Parse the `Set-Cookie` header from a given Response
     */
    protected function parseSetCookie(ResponseInterface $response)
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

    /**
     * Create an instance of a SessionStorage implementation for testing.
     */
    abstract protected function createSessionStorage(): SessionStorage;

    /**
     * Create an instance of a SessionService implementation for testing.
     */
    protected function createSessionService()
    {
        $storage = $this->createSessionStorage();

        return new class($storage) extends SessionService {
            private $time = 1534425767;

            public function skipTime(int $seconds)
            {
                $this->time += $seconds;
            }

            protected function getTime(): int
            {
                return $this->time;
            }
        };
    }
}
