<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Session\Exceptions\InvalidTypeException;
use Kodus\Session\Session;
use Kodus\Session\SessionData;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelB;
use UnitTester;

class SessionDataCest
{
    /**
     * @var array
     */
    private $data = [];

    public function _before()
    {
        $this->data = [];
    }

    public function basicUsage(UnitTester $I)
    {
        $I->wantToTest("Session functionality");

        $session_id = "hi-im-a-session-id";

        $session = $this->getSession($session_id);

        $I->assertFalse($session->has(TestSessionModelA::class),
            "has() method returns false for type not stored in session data");

        /** @var TestSessionModelA $session_model_a */
        $session_model_a = $session->get(TestSessionModelA::class);

        $I->assertInstanceOf(TestSessionModelA::class, $session_model_a,
            "get() method will construct an empty session model, if none is found in the data");

        $I->assertEquals(new TestSessionModelA(), $session_model_a,
            "automatically constructed session model should be empty");

        $session_model_a->foo = "hello";
        $session_model_a->bar = "world";

        $I->assertNotEquals($session_model_a, $session->get(TestSessionModelA::class),
            "Session models aren't updated in the session until put() is called");

        $session->put($session_model_a);

        $I->assertTrue($session->has(TestSessionModelA::class),
            "has() method returns true for type stored in session data");

        $I->assertEquals($session_model_a, $session->get(TestSessionModelA::class),
            "put() method updates the session model in the session");

        $session_model_b = new TestSessionModelB();
        $session_model_b->foo = "Yo, wassup";
        $session_model_b->bar = "What's the happy-haps!?";

        $session->put($session_model_b);

        $session->remove($session_model_b); //Remove object
        $session->remove(TestSessionModelA::class); //remove by type

        $I->assertFalse($session->has(TestSessionModelA::class),
            "After remove(), has() returns false");

        $I->assertEquals(new TestSessionModelA(), $session->get(TestSessionModelA::class),
            "After remove(), get() returns default model");

        $session->put($session_model_a);
        $session->put($session_model_b);

        $session->clear();

        $I->assertFalse($session->has(TestSessionModelA::class), "After clear(), no models are stored in session");
        $I->assertFalse($session->has(TestSessionModelB::class), "After clear(), no models are stored in session");

        $exception = false;
        try {
            $session->get("Kodus\\An\\Unknown\\ClassName");
        } catch (InvalidTypeException $e) {
            $exception = true;
        }
        $I->assertTrue($exception, "If get() is called with an unknown class name, an InvalidTypeException is thrown");
    }

    private function getSession(string $session_id): SessionData
    {
        return new SessionData($session_id, $this->data);
    }
}
