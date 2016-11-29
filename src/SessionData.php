<?php

namespace Kodus\Session;

use Kodus\Session\Exceptions\InvalidTypeException;

/**
 * This model represents a collection of session-data.
 */
class SessionData implements Session
{
    /**
     * @var string
     */
    private $session_id;

    /**
     * @var mixed[] key/value map of session-data
     */
    private $data = [];

    /**
     * @param string $session_id
     * @param array  $data
     */
    public function __construct($session_id, array $data)
    {
        $this->session_id = $session_id;
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getSessionID()
    {
        return $this->session_id;
    }

    /**
     * @return mixed[]
     */
    public function getData()
    {
        return $this->data;
    }

    public function get(string $type): SessionModel
    {
        if (! class_exists($type)) {
            throw new InvalidTypeException("The class {$type} does not exists");
        }

        if (isset($this->data[$type])) {
            return unserialize($this->data[$type]);
        }

        return new $type;
    }

    public function put(SessionModel $object)
    {
        $type = get_class($object);

        $this->data[$type] = serialize($object);
    }

    public function has(string $type): bool
    {
        return (isset($this->data[$type]) && class_exists($type));
    }

    public function remove(string $type)
    {
        unset($this->data[$type]);
    }

    public function clear()
    {
        $this->data = [];
    }
}
