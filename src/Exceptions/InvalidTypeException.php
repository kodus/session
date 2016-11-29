<?php

namespace Kodus\Session\Exceptions;

use RuntimeException;

/**
 * This exception is thrown when an instance of a class that doesn't exist is requested from Kodus\Session\Session
 *
 * @see \Kodus\Session\Session
 */
class InvalidTypeException extends RuntimeException
{

}
