<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Cache\MockCache;
use Kodus\Session\Adapters\SimpleCacheAdapter;
use Kodus\Session\Session;
use Kodus\Session\SessionMiddleware;
use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\Mocks\DelegateMock;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use UnitTester;

class SessionMiddlewareCest
{
    const FOO_VALUE = "Hello World";

    public function basicFunctionality(UnitTester $I)
    {
        $I->wantToTest("SessionMiddleware");

        $cache = new MockCache();

        $storage = new SimpleCacheAdapter($cache);

        $service = new SessionService($storage, SessionService::TWO_WEEKS, false);

        $middleware = new SessionMiddleware($service);

        $model = null;

        // During the first request, we generate a session model:

        $delegate = new DelegateMock(function (ServerRequestInterface $request) use ($I, &$model) {

            /** @var Session $session */
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            $I->assertInstanceOf(Session::class, $session,
                "SessionMiddleware adds an instance of Session to server request attributes");

            $I->assertNotEmpty($session->getSessionID());

            /** @var TestSessionModelA $model */
            $model = $session->get(TestSessionModelA::class);

            $model->foo = self::FOO_VALUE;

            return new Response();
        });

        $request_1 = new ServerRequest('GET', '/');

        $response = $middleware->process($request_1, $delegate);

        // During the second request, we obtain the session model created during the first request:

        $cookies = $this->getCookies($response);

        $delegate->next = function (ServerRequestInterface $request) use ($I, $model) {
            /** @var Session $session */
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            $I->assertEquals($model, $session->get(TestSessionModelA::class), "Session models are available in next request with the cookie returned in the previous");

            return new Response();
        };

        $request_2 = (new ServerRequest('GET', ''))->withCookieParams($cookies);

        $middleware->process($request_2, $delegate);
    }

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
