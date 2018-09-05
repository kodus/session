<?php
namespace Kodus\Session;

use Kodus\Session\Exceptions\InvalidTypeException;

/**
 * This interface defines the public portion of the SessionData model.
 *
 * Consumers should type-hint against this as a dependency.
 */
interface Session
{
    /**
     * @param string $type fully qualified class name (e.g. MyModel::class)
     *
     * @return SessionModel
     *
     * @throws InvalidTypeException is thrown if the class name given does not exist.
     */
    public function get(string $type): SessionModel;

    /**
     * Clear all session data, evict all objects from this Session, and {@see renew() renew}
     * the Session ID.
     *
     * Note that references to any session model objects obtained via `get()` during the
     * same request will be *orphaned* from this Session - they will *not* be commited
     * to session state at the end of the request.
     *
     * (This is not as bad as it may sound, as very likely the only practical use-case for
     * `clear()` is a logout controller/action, during which likely no other session models
     * would be used or manipulated.)
     *
     * @return void
     */
    public function clear();

    /**
     * Explicitly renew the Session ID while *preserving* any Session data.
     *
     * The likely use-case is a login controller/action, where issuing a new Session ID,
     * while invalidating the previous Session ID, can provide an extra measure of security,
     * e.g. by avoiding very long-lived valid Session IDs.
     *
     * Note that periodic renewal of the Session ID is *not* recommended - issuing a new
     * Session ID should be done only after authentication, e.g. after successful validation
     * of user-supplied login credentials over a secure connection.
     *
     * @return void
     */
    public function renew();

    /**
     * @return string Session ID (UUID v4)
     */
    public function getSessionID(): string;
}
