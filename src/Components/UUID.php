<?php

namespace Kodus\Session\Components;

use RuntimeException;

/**
 * This helper generates random version 4 UUIDs
 *
 * @link https://en.wikipedia.org/wiki/Universally_unique_identifier
 */
abstract class UUID
{
    /**
     * @type string path to the dev/urandom device on Linux
     */
    const DEV_URANDOM = '/dev/urandom';

    /**
     * Creates a new, random UUID v4
     *
     * @return string UUID v4
     */
    public static function create()
    {
        $r = unpack('v*', self::createEntropy(16));

        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            $r[1], $r[2], $r[3], $r[4] & 0x0fff | 0x4000,
            $r[5] & 0x3fff | 0x8000, $r[6], $r[7], $r[8]);
    }

    /**
     * @param int $length entropy length (in bytes)
     *
     * @return string entropy
     *
     * @throws RuntimeException
     */
    protected static function createEntropy($length)
    {
        // random_bytes() provides the most reliable solution on PHP 7:

        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }

        // mcrypt provides the best/safest entropy on systems when available:

        if (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        }

        // openssl is another option:

        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }

        // on Linux, the dev/urandom device provides good entropy:

        if (@is_readable(self::DEV_URANDOM)) {
            $random = fopen(self::DEV_URANDOM, 'rb');

            if ($random) {
                $entropy = fread($random, $length);
                fclose($random);

                return $entropy;
            }
        }

        // on other systems, we'll make do with pseudo-random numbers and PID:

        $entropy = '';

        mt_srand(microtime(true));

        $seed = mt_rand();

        if (function_exists('getmypid')) {
            $seed .= getmypid();
        }

        while (strlen($entropy) < $length) {
            $seed = sha1(mt_rand() . $seed . microtime(true), true);
            $entropy .= sha1($seed, true);
        }

        return substr($entropy, 0, $length);
    }
}
