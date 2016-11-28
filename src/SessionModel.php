<?php

namespace Kodus\Session;

/**
 * Implementations of Kodus\Session\SessionModel MUST have an empty constructor or have default values for all
 * constructor arguments.
 *
 * Implementations of Kodus\Session\SessionModel MUST be able to be serialized and deserialized by PHP's native methods
 * serialize() and deserialize() without any loss of data.
 */
interface SessionModel
{
    //Marker interface
}
