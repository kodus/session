<?php

namespace Kodus\Session;

use InvalidArgumentException;
use Kodus\Helpers\UUID;
use Kodus\Helpers\UUIDv5;

abstract class SessionID
{
    /**
     * @internal internally hashes a given Client Session UUID v4 to a Session UUID v5
     *
     * @param string $client_session_id
     *
     * @return string Session UUID
     */
    public static function create(string $client_session_id): string
    {
        if (!UUID::isValid($client_session_id)) {
            throw new InvalidArgumentException("invalid Client Session ID (valid UUID v4 required)");
        }

        return UUIDv5::create(
            "05dcd3fe-edbc-4977-ab77-d6a3f0110244",
            pack('H*', str_replace('-', '', $client_session_id))
        );
    }
}
