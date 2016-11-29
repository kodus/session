<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Session\Adapters\CacheSessionService;
use Kodus\Session\Session;
use Kodus\Session\SessionMiddleware;
use Kodus\Session\Tests\Unit\Mocks\CacheMock;
use Kodus\Session\Tests\Unit\Mocks\DelegateMock;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class SessionMiddlewareCest
{
    public function basicFunctionality(UnitTester $I)
    {
        $I->wantToTest("SessionMiddleware");

        $cache = new CacheMock();

        $model = new TestSessionModelA();
        $model->foo = "hello foo world";

        $service = new CacheSessionService($cache, CacheSessionService::TWO_WEEKS, false);

        $middleware = new SessionMiddleware($service);


        # First request
        $delegate = new DelegateMock(function (ServerRequestInterface $request) use ($I, $model) {

            /** @var Session $session */
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            $I->assertInstanceOf(Session::class, $session,
                "SessionMiddleware adds an instance of Session to server request attributes");

            $session->put($model);

            return new Response();
        });

        $request_1 = new ServerRequest();

        $response = $middleware->process($request_1, $delegate);

        $cookies = $this->getCookies($response);

        $delegate->next = function (ServerRequestInterface $request) use ($I, $model) {
            /** @var Session $session */
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            $I->assertEquals($model, $session->get(TestSessionModelA::class), "Session models are available in next request with the cookie returned in the previous");

            return new Response();
        };

        $request_2 = (new ServerRequest())->withCookieParams($cookies);

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
