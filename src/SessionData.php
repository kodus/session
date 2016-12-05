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
     * @var mixed[] map where fully-qualified class-name => serialized data
     */
    private $data = [];

    /**
     * @var SessionModel[] map where fully-qualified class-name => instance
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
        $data = $this->data;

        foreach ($this->objects as $object) {
            $key = get_class($object);

            if ($object->isEmpty()) {
                unset($data[$key]);
            } else {
                $data[$key] = serialize($object);
            }
        }

        return $data;
    }

    public function get(string $type): SessionModel
    {
        if (! class_exists($type)) {
            throw new InvalidTypeException("The class {$type} does not exists");
        }

        if (! isset($this->objects[$type])) {
            $this->objects[$type] = isset($this->data[$type])
                ? unserialize($this->data[$type])
                : new $type;
        }

        return $this->objects[$type];
    }
}
