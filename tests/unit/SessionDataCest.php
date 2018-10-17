<?php

namespace Kodus\Session\Tests\Unit;

use Kodus\Helpers\UUID;
use Kodus\Session\SessionData;
use Kodus\Session\SessionID;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelA;
use Kodus\Session\Tests\Unit\SessionModels\TestSessionModelB;
use UnitTester;

class SessionDataCest
{
    const FOO_VALUE = "hello!";

    public function manageSessionData(UnitTester $I)
    {
        $client_session_id = UUID::create();

        $session_id = SessionID::create($client_session_id);

        $session = new SessionData($client_session_id, [], true);

        $I->assertSame($client_session_id, $session->getClientSessionID());

        $I->assertSame($session_id, $session->getSessionID());

        /**
         * @var TestSessionModelA $model
         */

        $model = $session->get(TestSessionModelA::class);

        $session->get(TestSessionModelB::class); // activate the object in session (but we leave this one empty)

        $I->assertInstanceOf(TestSessionModelA::class, $model, "it creates a new model instance");

        $model->foo = self::FOO_VALUE;

        $I->assertSame($model, $session->get(TestSessionModelA::class), "it returns the same instance every time");

        $data = $session->getData();

        $I->assertArrayHasKey(TestSessionModelA::class, $data, "it stores the non-empty session model");

        $I->assertArrayNotHasKey(TestSessionModelB::class, $data, "it discards the empty session model");

        $session = new SessionData($client_session_id, $data, false);

        $I->assertEquals($model, $session->get(TestSessionModelA::class), "it restores the model instance from data");

        $I->assertSame($model->foo, $session->get(TestSessionModelA::class)->foo, "it preserves the model state");

        $session->clear();

        $I->assertSame([], $session->getData(), "can clear session state");

        $I->assertNotSame($model, $session->get(TestSessionModelA::class), "it creates a new session model after clear()");
    }
}
