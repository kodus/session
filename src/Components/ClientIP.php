<?php
namespace Kodus\Session\Components;

use Psr\Http\Message\ServerRequestInterface;


/**
 * A helper class for extracting the client IP from a ServerRequestInterface object.
 *
 * This is adapted from the middlewares/clientIp middleware
 * @see https://github.com/middlewares/client-ip
 */
class ClientIP
{
    /**
     * @var array The trusted headers
     */
    private $headers = [
        'Forwarded',
        'Forwarded-For',
        'Client-Ip',
        'X-Forwarded',
        'X-Forwarded-For',
        'X-Cluster-Client-Ip',
    ];

    /**
     * Configure the trusted headers.
     *
     * @param array $headers
     *
     * @return self
     */
    public function headers(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Detect and return the ip from the request.
     *
     * @param ServerRequestInterface $request
     *
     * @return string|null
     */
    public function getIp(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();

        if (!empty($server['REMOTE_ADDR']) && self::isValid($server['REMOTE_ADDR'])) {
            return $server['REMOTE_ADDR'];
        }

        foreach ($this->headers as $name) {
            if ($request->hasHeader($name) && ($ip = self::getHeaderIp($request->getHeaderLine($name))) !== null) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Returns the first valid ip found in the header.
     *
     * @param string $header
     *
     * @return string|null
     */
    private static function getHeaderIp($header)
    {
        foreach (array_map('trim', explode(',', $header)) as $ip) {
            if (self::isValid($ip)) {
                return $ip;
            }
        }
    }

    /**
     * Check that a given string is a valid IP address.
     *
     * @param string $ip
     *
     * @return bool
     */
    private static function isValid($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }
}
