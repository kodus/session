<?php

namespace Kodus\Session\Tests\Unit\Storage;

use Kodus\Session\Storage\CacheSessionStorage;
use Kodus\Session\SessionStorage;
use Kodus\Session\Tests\Unit\Mocks\CacheSessionStorageMock;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use Kodus\Session\Tests\Unit\SessionStorageTest;
use RuntimeException;
use UnitTester;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class CacheSessionStorageCest extends SessionStorageTest
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
     * Uses CacheSessionStorageMock, that is located in the test namespace to test the cookie string.
     *
     * The cookie string changes according to the value returned by time().
     *
     * This extension is specifically for testing functionality who's result is dependent on the value returned by time().
     *
     * CacheSessionStorage wraps the time() function into a method of the same name. This allows us to make a simple
     * extension that overrides this value, when we need to know it for testing.
     *
     * If the attribute CacheSessionStorageMock::time is set to the int 127, then the functionality will act as if
     * time() is always returning 127.
     *
     * @param UnitTester $I
     */
    public function sessionCookie(UnitTester $I)
    {
        $I->wantToTest("Session cookies");

        $service = $this->createSessionStorageMock(null, 3600, 10000);

        $response = $service->commit(new Response("php://temp", 200));

        $I->assertTrue($response->hasHeader("set-cookie"));

        $cookie_headers = $response->getHeader("set-cookie");

        $I->assertEquals(1, count($cookie_headers), "CacheSessionStorage should only add 1 set-cookie header");

        $cookie_string = $cookie_headers[0];

        $expected_cookie_string = "sessionID=" . $service->getSessionID() . "; Max-Age=3600; Expires=13600; Path=/;";

        $I->assertSame($expected_cookie_string, $cookie_string);
    }

    /**
     * Like the sessionCookie() test, this uses the test extension CacheSessionStorageMock, to control the current
     * time, in order to test time dependent functionality.
     *
     * @see CacheSessionStorageCest::sessionCookie()
     *
     * @param UnitTester $I
     */
    public function sessionExpiration(UnitTester $I)
    {
        $I->wantToTest("Session lifetime");

        $service = $this->createSessionStorageMock();
        $service->set("key", "value");

        $service->commit(new Response());

        // Two hours passes
        $session_id = $service->getSessionID();
        $service = $this->createSessionStorageMock($session_id, 3600, 7200);

        $I->assertNotEquals($session_id, $service->getSessionID(),
            "The previous session expired, so this should be a new session");
        $I->assertNull($service->get("key"),
            "The new session shouldn't remember anything from the last session");
    }

    /**
     * @param SessionStorage $storage       The session service that should be progressed to next request
     * @param int            $response_code Emulate the response code for the current request to be this value.
     *
     * @return SessionStorage
     */
    protected function nextRequest(SessionStorage $storage, $response_code = 200)
    {
        if (! $storage instanceof CacheSessionStorage) {
            throw new RuntimeException("This test implementation is for CacheSessionStorage, not " . get_class($storage));
        }

        $response = new Response('php://temp', $response_code);

        $storage->commit($response);

        $session_id = $storage->getSessionID();

        $new_service = $this->createStorage($session_id);

        return $new_service;
    }

    /**
     * @param string $session_id
     *
     * @param int    $session_ttl
     *
     * @return SessionStorage
     */
    protected function createStorage(string $session_id = null, int $session_ttl = 3600): SessionStorage
    {
        return $this->createSessionStorageMock($session_id, $session_ttl);
    }

    /**
     * @param string|null $session_id
     * @param int         $session_ttl
     * @param int         $static_time
     *
     * @return CacheSessionStorage
     */
    protected function createSessionStorageMock($session_id = null, $session_ttl = 3600, $static_time = 0)
    {
        $cookies = [CacheSessionStorageMock::COOKIE_KEY => $session_id];

        $request = new ServerRequest([], [], "/", "GET", 'php://input', [], $cookies);

        $storage = new CacheSessionStorageMock($this->cache, $session_ttl);

        $storage->time = $static_time;

        $storage->begin($request);

        return $storage;
    }
}