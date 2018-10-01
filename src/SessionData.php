<?php

namespace Kodus\Session;

use Kodus\Helpers\UUID;
use Kodus\Helpers\UUIDv5;
use Kodus\Session\Exceptions\InvalidTypeException;
use ReflectionClass;

/**
 * This model represents a collection of session-data.
 */
class SessionData implements Session
{
    /**
     * @var string Client Session ID (UUID)
     */
    private $client_session_id;

    /**
     * @var string|null old Session ID (if this Session was renewed)
     *
     * @see renew()
     */
    private $old_session_id;

    /**
     * @var mixed[] map where fully-qualified class-name => [checksum, serialized data]
     */
    private $data = [];

    /**
     * @var SessionModel[] map where fully-qualified class-name => instance
     */
    private $objects = [];

    /**
     * @var bool indicates whether this is a new Session (for which no Session Cookie has been set yet)
     */
    private $is_new = false;

    /**
     * @param string $client_session_id
     * @param array  $data
     * @param bool   $is_new
     */
    public function __construct(string $client_session_id, array $data, bool $is_new)
    {
        $this->client_session_id = $client_session_id;
        $this->data = $data;
        $this->is_new = $is_new;
    }

    /**
     * @return string Session UUID (derived from Client Session UUID using a one-way hash - may be stored on the server)
     */
    public function getSessionID(): string
    {
        return SessionID::create($this->client_session_id);
    }

    /**
     * @internal please regard the Client Session ID as a private/internal implementation detail.
     *
     * @return string Client Session UUID (only persisted by the client in their cookie - never stored on the server)
     */
    public function getClientSessionID(): string
    {
        return $this->client_session_id;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        $data = $this->data;

        foreach ($this->objects as $object) {
            $type = get_class($object);

            if ($object->isEmpty()) {
                unset($data[$type]);
            } else {
                $data[$type] = [$this->checksum($type), serialize($object)];
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
            if (isset($this->data[$type])) {
                list($checksum, $serialized) = $this->data[$type];

                $this->objects[$type] = $checksum === $this->checksum($type)
                    ? unserialize($serialized)
                    : new $type; // checksum invalid (session model implementation has changed)
            } else {
                $this->objects[$type] = new $type;
            }
        }

        return $this->objects[$type];
    }

    public function clear()
    {
        $this->data = [];
        $this->objects = [];

        // in case data is added to the session after clearing it, we'll consider that a new
        // session - renewing the Session ensures a new Session ID gets assigned in that case:

        $this->renew();
    }

    public function renew()
    {
        if (! $this->isRenewed()) {
            $this->old_session_id = $this->getSessionID();
            $this->client_session_id = UUID::create();
        }
    }

    public function isNew(): bool
    {
        return $this->is_new;
    }

    public function isRenewed(): bool
    {
        return $this->old_session_id !== null;
    }

    public function getOldSessionID(): ?string
    {
        return $this->old_session_id;
    }

    /**
     * Internally checksum a class implementation.
     *
     * Any change to the class source-file will cause invalidation of the session-model, such
     * that changes to the code will effectively cause session-models to re-initialize to their
     * default state - this is necessary because even a change to a type-hint in a doc-block
     * could cause an unserialize() call to inject the wrong type of value.
     *
     * @param string $type fully-qualified class-name
     *
     * @return string MD5 checksum
     */
    protected function checksum(string $type): string
    {
        static $checksum = [];

        if (!isset($checksum[$type])) {
            $reflection = new ReflectionClass($type);

            $checksum[$type] = md5_file($reflection->getFileName());
        }

        return $checksum[$type];
    }
}
