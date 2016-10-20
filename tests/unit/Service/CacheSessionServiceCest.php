<?php

namespace Kodus\Session\Tests\Unit\Service;


use Kodus\Session\Service\CacheSessionService;
use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\Mocks\FooSessionModel;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use Kodus\Session\Tests\Unit\Mocks\StaticTimeCacheSessionService;
use Kodus\Session\Tests\Unit\SessionServiceTest;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnitTester;
use Zend\Diactoros\Response;

class CacheSessionServiceCest extends SessionServiceTest
{
    /**
     * @var MockCache
     */
    private $cache;

    /**
     * @var CacheSessionService[]
     */
    private $services = [];

    public function _before(UnitTester $I)
    {
        $this->cache = new MockCache();
    }

    public function _after(UnitTester $I)
    {
        codecept_debug($this->cache);
    }

    /**
     *
     * Uses StaticTimeCacheSessionService, that is located in the test namespace to test the cookie string.
     *
     * The cookie string changes according to the value returned by time().
     *
     * This extension is specifically for testing functionality who's result is dependent on the value returned by time().
     *
     * CacheSessionService wraps the time() function into a method of the same name. This allows us to make a simple
     * extension that overrides this value, when we need to know it for testing.
     *
     * If the attribute StaticTimeCacheSessionService::time is set to the int 127, then the functionality will act as if
     * time() is always returning 127.
     *
     * @param UnitTester $I
     */
    public function sessionCookie(UnitTester $I)
    {
        $I->wantToTest("Session cookies");

        $service = new StaticTimeCacheSessionService($this->cache, 3600);

        $service->time = 10000;

        $response = $service->commit(new Response("php://temp", 200));

        $I->assertTrue($response->hasHeader("set-cookie"));

        $cookie_headers = $response->getHeader("set-cookie");

        $I->assertEquals(1, count($cookie_headers), "CacheSessionService should only add 1 set-cookie header");

        $cookie_string = $cookie_headers[0];

        $expected_cookie_string = "sessionID=" . $service->getSessionID() . "; Max-Age=3600; Expires=13600; Path=/;";

        $I->assertSame($expected_cookie_string, $cookie_string);
    }

    /**
     * Like the sessionCookie() test, this uses the test extension StaticTimeCacheSessionService, to control the current
     * time, in order to test time dependent functionality.
     *
     * @see CacheSessionServiceCest::sessionCookie()
     *
     * @param UnitTester $I
     */
    public function sessionExpiration(UnitTester $I)
    {
        $I->wantToTest("Session lifetime");

        $service = new StaticTimeCacheSessionService($this->cache, 3600);

        $foo_session = new FooSessionModel();
        $foo_session->baz = "Hello session world";

        $service->set($foo_session);

        $service->commit(new Response("php://temp", 200));

        $session_id = $service->getSessionID();

        $service = new StaticTimeCacheSessionService($this->cache, 3600, $session_id);

        $I->assertTrue($service->has(FooSessionModel::class));

        // Two hours passes
        $service = new StaticTimeCacheSessionService($this->cache, 3600, $session_id, 7200);

        $I->assertNotEquals($session_id, $service->getSessionID(), "The previous session expired, so this should be a new session");
        $I->assertFalse($service->has(FooSessionModel::class), "The new session shouldn't remember anything from the last session");
    }

    /**
     * @inheritdoc
     */
    protected function emulateNextRequest(SessionService $service, $response_code = 200)
    {
        if (! $service instanceof CacheSessionService) {
            throw new RuntimeException("This implementation is for CacheSessionService, not " . get_class($service));
        }

        $session_id = $service->getSessionID();

        $this->commit($service, $response_code);

        $this->services[$session_id] = $this->createSessionService($session_id);

        return $this->services[$session_id];
    }

    protected function createSessionService($session_id = null, $session_ttl = 3600)
    {
        $service = new CacheSessionService($this->cache, $session_ttl, $session_id);

        $this->services[$service->getSessionID()] = $service;

        return $service;
    }

    /**
     * @param CacheSessionService $service
     * @param int                 $response_code
     *
     * @return ResponseInterface
     */
    protected function commit(CacheSessionService $service, $response_code = 200)
    {
        $response = new Response('php://temp', $response_code);

        $response = $service->commit($response);

        return $response;
    }
}
