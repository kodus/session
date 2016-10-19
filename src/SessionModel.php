<?php

namespace Kodus\Session;

/**
 * SessionModel is a Marker Interface. This means that it's purpose is to mark a class as being a SessionModel.
 *
 * The interface SessionService will only write objects of the type SessionModel to the SessionStorage.
 *
 * This means that you cannot write any objects to the session. The class must be specficially marked as
 * "implements SessionModel".
 *
 * In the wikipedia page for Marker Interface pattern, a critique of this pattern is raised. The critique is that
 * a subclass of classes that implements this interface, will not be able to "unimplement" this interface and mark it
 * as "not a SessionModel".
 *
 * This critique does not apply to the SessionModel however, because SessionModel implementations should limit themselves
 * to be just that: a session model. Added responsibility breaks the Single Responsibility principle. If you make a
 * subclass of a session model class, then that should be a session model too.
 *
 * Example:
 * If you have an object of the type User, and you need to have the ID and email of that user in session,
 * don't be tempted to just add the line "implements SessionModel" in the User class.
 *
 * Instead write a specific session model named "UserSession", that has the attributes "user_id" and "email".
 *
 * @see https://en.wikipedia.org/wiki/Marker_interface_pattern
 */
interface SessionModel
{
    //Marker Interface / Tagging Interface
}
