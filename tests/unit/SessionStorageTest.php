<?php

namespace Kodus\Session\Tests\Unit;

use Closure;
use Exception;
use Kodus\Session\SessionStorage;
use UnitTester;

/**
 * Abstract test class for testing implementations of Kodus\Session\SessionStorage. Extend this test when testing
 * specific implementations.
 *
 * Extending classes should implement tests for session cookies and session expiration. Because SessionStorage does not
 * define the session cookie or how it's set, these can't be implemented generically.
 *
 * For an example look at
 *
 * @see \Kodus\Session\Tests\Unit\Storage\CacheSessionStorageCest::nextRequest()
 */
abstract class SessionStorageTest
{
    public function setAndGet(UnitTester $I)
    {
        $I->wantToTest("Basic get and set functionality");

        $storage = $this->createStorage();

        $key_1 = "1st key";
        $key_2 = "2nd key";
        $value_1 = "1st value";
        $value_2 = "2nd value";

        $I->assertNull($storage->get($key_1), "SessionStorage::get(X) returns null if nothing stored under X");
        $I->assertNull($storage->get($key_2), "SessionStorage::get(X) returns null if nothing stored under X");

        $storage->set($key_1, $value_1);
        $storage->set($key_2, $value_2);

        $I->assertSame($value_1, $storage->get($key_1), "Get value before committing to storage");
        $I->assertSame($value_2, $storage->get($key_2), "Get value before committing to storage");

        $storage = $this->nextRequest($storage);

        $I->assertSame($value_1, $storage->get($key_1), "Get value from storage");
        $I->assertSame($value_2, $storage->get($key_2), "Get value from storage");

        $storage->unset($key_1);
        $storage->unset($key_2);
        //Test setting value under $key_2 immediately after unsetting the old value.
        $storage->set($key_2, $value_1);

        $I->assertNull($storage->get($key_1), "After unsetting \$key_1, SessionStorage::get(\$key_1) returns null");
        $I->assertSame($value_1, $storage->get($key_2), "Values can be set after being unset");

        $storage = $this->nextRequest($storage);

        $I->assertNull($storage->get($key_1), "After unsetting \$key_1, SessionStorage::get(\$key_1) returns null");
        $I->assertSame($value_1, $storage->get($key_2), "Values can be set after being unset");
    }

    public function clearingSession(UnitTester $I)
    {
        $I->wantToTest("Clearing Session");

        $storage = $this->createStorage();

        $key_1 = "1st key";
        $key_2 = "2nd key";
        $value_1 = "1st value";
        $value_2 = "2nd value";
        $value_3 = "3rd value";

        $storage->set($key_1, $value_1);

        $storage = $this->nextRequest($storage);

        $storage->set($key_2, $value_2);

        $storage->clear();

        $I->assertNull($storage->get($key_1), "After clearing storage, previously stored values are gone");
        $I->assertNull($storage->get($key_2), "After clearing storage, previously stored values are gone");

        $storage->set($key_2, $value_3);

        $I->assertSame($value_3, $storage->get($key_2), "Values can be still be added after clearing storage");

        $storage = $this->nextRequest($storage);

        $I->assertNull($storage->get($key_1), "After clearing storage, previously stored values are gone");
        $I->assertSame($value_3, $storage->get($key_2), "SessionStorage should reflect the most recent values stored");
    }

    public function flashSessions(UnitTester $I)
    {
        $I->wantToTest("Flash session functionality");

        $storage = $this->createStorage();

        $key_1 = "1st key";
        $key_2 = "2nd key";
        $value_1 = "1st value";
        $value_2 = "2nd value";

        $storage->flash($key_1, $value_1);

        $storage->flash($key_2, $value_2);
        $storage->set($key_2, $value_2); //Make sure that you can override a flash message with a regular message

        $storage = $this->nextRequest($storage);

        $I->assertSame($value_1, $storage->get($key_1));
        $I->assertSame($value_2, $storage->get($key_2));

        $storage = $this->nextRequest($storage);

        $I->assertNull($storage->get($key_1), "In the second request, the flash value is no longer available");
        $I->assertSame($value_2, $storage->get($key_2), "In the second request, the regular value is still available");

        $storage->flash($key_1, $value_1);

        $storage = $this->nextRequest($storage);

        $storage = $this->nextRequest($storage, 302);
        $storage = $this->nextRequest($storage, 301);
        $storage = $this->nextRequest($storage, 500);

        $I->assertSame($value_1, $storage->get($key_1), "Flash message stays in storage until first succesful request");

        $storage = $this->nextRequest($storage);

        $I->assertNull($storage->get($key_1), "After the first succesfull request the flash message dissapears");

        $storage->flash($key_1, $value_1);
        $storage->unset($key_1);

        $I->assertNull($storage->get($key_1), "Flash messages can be removed after being set");

        $storage = $this->nextRequest($storage);

        $I->assertNull($storage->get($key_1), "Flash messages can be removed after being set");
    }

    public function multipleSessions(UnitTester $I)
    {
        $I->wantToTest("Storing multiple sessions in same storage");

        $key_1 = "1st key";
        $key_2 = "2nd key";

        $shared_key = "Both sessions will have a value under this key";

        $value_1 = "1st value";
        $value_2 = "2nd value";

        $service_1 = $this->createStorage();

        $service_1->set($key_1, $value_1);
        $service_1->set($shared_key, $value_1);

        $service_1 = $this->nextRequest($service_1);

        $service_2 = $this->createStorage();

        $service_2->set($key_2, $value_2);
        $service_2->set($shared_key, $value_2);

        $service_2 = $this->nextRequest($service_2);

        $I->assertSame($value_1, $service_1->get($key_1));
        $I->assertNull($service_1->get($key_2));
        $I->assertSame($value_1, $service_1->get($shared_key));

        $I->assertNull($service_2->get($key_1));
        $I->assertSame($value_2, $service_2->get($key_2));
        $I->assertSame($value_2, $service_2->get($shared_key));
    }

    /**
     * SessionStorage does not define how or when session data is stored in the actual storage, be it the filesystem,
     * a database or other.
     *
     * This method takes the current session storage under test, stores any deferred write/remove/clear operations
     * to the underlying storage, and returns a "fresh" instance of SessionStorage with the same session ID.
     *
     * @param SessionStorage $storage       The session service that should be progressed to next request
     * @param int            $response_code Emulate the response code for the current request to be this value.
     *
     * @return SessionStorage
     */
    abstract protected function nextRequest(SessionStorage $storage, $response_code = 200);

    /**
     * @param string $session_id  The session ID for the new SessionStorage
     * @param int    $session_ttl The session time-to-live in seconds
     *
     * @return SessionStorage
     */
    abstract protected function createStorage(string $session_id = null, int $session_ttl = 3600): SessionStorage;

    /**
     * @param string  $type    The fully qualified class name of the exception to check for.
     * @param Closure $closure The closure containing the code to test for exceptions.
     *
     * @return bool Returns true if the closure given throws an exception of the type givne by the $type argument.
     * @throws Exception
     */
    protected function catchException(string $type, Closure $closure): bool
    {
        try {
            $closure();
        } catch (Exception $e) {
            if ($e instanceof $type) {
                return true;
            } else {
                throw $e;
            }
        }

        return false;
    }
}
