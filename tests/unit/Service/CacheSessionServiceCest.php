<?php

namespace Kodus\Session\Tests\Unit\Service;


use Kodus\Session\Service\CacheSessionService;
use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\Mocks\FooSessionModel;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use Kodus\Session\Tests\Unit\Mocks\CacheSessionServiceMock;
use Kodus\Session\Tests\Unit\SessionServiceTest;
use RuntimeException;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class CacheSessionServiceCest extends SessionServiceTest
{
    /**
     * @var MockCache
     */
    private $cache;

    public function _before(UnitTester $I)
    {
        $this->cache = new MockCache();
    }

    /**
     * Uses CacheSessionServiceMock, that is located in the test namespace to test the cookie string.
     *
     * The cookie string changes according to the value returned by time().
     *
     * This extension is specifically for testing functionality who's result is dependent on the value returned by time().
     *
     * CacheSessionService wraps the time() function into a method of the same name. This allows us to make a simple
     * extension that overrides this value, when we need to know it for testing.
     *
     * If the attribute CacheSessionServiceMock::time is set to the int 127, then the functionality will act as if
     * time() is always returning 127.
     *
     * @param UnitTester $I
     */
    public function sessionCookie(UnitTester $I)
    {
        $I->wantToTest("Session cookies");

        $service = $this->createSessionServiceMock(null, 3600, 10000);

        $response = $service->commit(new Response("php://temp", 200));

        $I->assertTrue($response->hasHeader("set-cookie"));

        $cookie_headers = $response->getHeader("set-cookie");

        $I->assertEquals(1, count($cookie_headers), "CacheSessionService should only add 1 set-cookie header");

        $cookie_string = $cookie_headers[0];

        $expected_cookie_string = "sessionID=" . $service->getSessionID() . "; Max-Age=3600; Expires=13600; Path=/;";

        $I->assertSame($expected_cookie_string, $cookie_string);
    }

    /**
     * Like the sessionCookie() test, this uses the test extension CacheSessionServiceMock, to control the current
     * time, in order to test time dependent functionality.
     *
     * @see CacheSessionServiceCest::sessionCookie()
     *
     * @param UnitTester $I
     */
    public function sessionExpiration(UnitTester $I)
    {
        $I->wantToTest("Session lifetime");

        $service = $this->createSessionService();
        $service->set(new FooSessionModel());

        $service->commit(new Response());

        // Two hours passes
        $session_id = $service->getSessionID();
        $service = $this->createSessionServiceMock($session_id, 3600, 7200);

        $I->assertNotEquals($session_id, $service->getSessionID(),
            "The previous session expired, so this should be a new session");
        $I->assertFalse($service->has(FooSessionModel::class),
            "The new session shouldn't remember anything from the last session");
    }

    /**
     * @inheritdoc
     */
    protected function emulateNextRequest(SessionService $service, $response_code = 200)
    {
        if (! $service instanceof CacheSessionService) {
            throw new RuntimeException("This test implementation is for CacheSessionService, not " . get_class($service));
        }

        $response = new Response('php://temp', $response_code);

        $service->commit($response);

        $session_id = $service->getSessionID();

        $new_service = $this->createSessionService($session_id);

        return $new_service;
    }

    protected function createSessionService($session_id = null, $session_ttl = 3600)
    {
        return $this->createSessionServiceMock($session_id, $session_ttl);
    }

    /**
     * @param string|null $session_id
     * @param int         $session_ttl
     * @param int         $static_time
     *
     * @return CacheSessionService
     */
    protected function createSessionServiceMock($session_id = null, $session_ttl = 3600, $static_time = 0)
    {
        $cookies = [CacheSessionServiceMock::COOKIE_KEY => $session_id];

        $request = new ServerRequest([], [], "/", "GET", 'php://input', [], $cookies);

        $service = new CacheSessionServiceMock($this->cache, $session_ttl);

        $service->time = $static_time;

        $service->begin($request);

        return $service;
    }
}
