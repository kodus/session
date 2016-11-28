<?php

namespace Kodus\Session\Adapters;

use Kodus\Session\Exceptions\InvalidTypeException;
use Kodus\Session\Session;
use Kodus\Session\SessionModel;

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
     * @var SessionModel[] key/value map of unpacked session model objects
     */
    private $objects = [];

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

        if (isset($this->objects[$type])) {
            return clone $this->objects[$type];
        }

        if (isset($this->data[$type])) {
            $object = unserialize($this->data[$type]);

            $this->objects[$type] = $object instanceof $type ? $object : new $type;
        } else {
            $this->objects[$type] = new $type;
        }

        return clone $this->objects[$type];
    }

    public function put(SessionModel $object)
    {
        $type = get_class($object);

        $this->objects[$type] = $object;

        $this->data[$type] = serialize($object);
    }

    public function has(string $type): bool
    {
        return (isset($this->data[$type]) && class_exists($type));
    }

    public function remove(string $type)
    {
        unset($this->data[$type]);
        unset($this->objects[$type]);
    }

    public function clear()
    {
        $this->data = [];
        $this->objects = [];
    }
}
