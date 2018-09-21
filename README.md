kodus/session
=============

A simple interface for storing and retrieving session data without the use of PHP's native session handling.

[![PHP Version](https://img.shields.io/badge/php-7.1%2B-blue.svg)](https://packagist.org/packages/kodus/session)
[![Build Status](https://travis-ci.org/kodus/session.svg?branch=master)](https://travis-ci.org/kodus/session)

## Installation

If your project is using composer, simply require the package:

    composer require kodus/session

## Introduction

This library provides a way of working with session data that promotes type safety and simplicity, without
relying on PHP's native session handling.

Type safety is accomplished by gathering the session data into simple session model classes, whose attributes and methods
can be type hinted, and storing instances of these in session, rather than storing values individually.

This approach requires an added amount of boilerplate code. The advantage of these session models easily outweighs the
small amount of added workload of writing a small and simple data model.

Write operations to the session are deffered until the end of the request, when they are saved to the storage.
This prevents broken session states as a result of critical errors.

Starting and ending sessions happens implicitly - for example, a session won't persist (and no session-cookie
will be emitted) unless there is any data in the session; likewise, session will automatically terminate if
session-data is cleared and/or the last session-model gets garbage-collected.

## Session models

When storing data in session, the first step is to define your session model. You can think of a session model class
as a container class for your session data.

A good example of a session model could be a user session model. Let's look at a user session, where both the ID and the
full name of the user needs to be stored in session:

```php
class UserSession implements SessionModel
{
    private $user_id;
    private $full_name;
    
    public function setUserID(int $user_id)
    {
        $this->user_id = $user_id;
    }
    
    public function getUserID(): int
    {
        return $this->user_id ?: 0;
    }
    
    public function setFullName(string $full_name)
    {
        $this->full_name = $full_name;
    }
    
    public function getFullName(): string
    {
        return $this->full_name ?: "";
    }
        
    public function isEmpty(): bool
    {
        return empty($this->user_id) && empty($this->full_name);
    }
}
```

These models should be encapsulated to the current domain. In other words, don't be tempted to collect all session data
into a big "catch-all" session model.

The interface `SessionModel` only requires you to implement the method `isEmpty()`, so you can define you session models
to your preferred style, as long as the following holds true:

  * **Instances of `SessionModel` MUST be able to be serialized and deserialized by native PHP
    serialization functions without loss of data.**

  * **Instances of `SessionModel` MUST have a constructor method with no arguments, or default values for all constructor arguments.**

It is strongly encouraged that implementations of `SessionModel` should only have attributes and methods specifically 
related to storing session data.

`SessionModel::isEmpty(): bool`: This method should only return true when a session models state is the same as when it was first constructed. When
serializing the session models this is used for garbage collection, removing empty models from storage.

## Session

The interface `Session` defines a locator for your session models. You get session models for the current session by
calling the `get` method.

The `get()` method returns a reference to the session model. All changes made to the instance are stored at the end of the request.

```php
/** @var UserSession $user_session */
$user_session = $session->get(UserSession::class);
```

#### Session models are always available

Only one instance of a session model is available per session, and it is always available. If an instance of the 
session model was not stored in cache, a new instance will be created and returned by `get()`.

This means you can assume that an instance of the session model is always available.

#### Deleting individual session models

Because session models are always available, you will never delete a session model directly.

Instead, your models must indicate via the `SessionModel::isEmpty()` method whether your model considers
itself to be empty - if so, the Session Service will garbage-collect it at the end of the request.

If your individual session model can be "cleared", your model must define what that means - for example,
the following model implementation supports a `clear()` method:

```php
class UserSession implements SessionModel
{
    private $user_id;

    public function clear()
    {
        unset($this->user_id);
    }

    public function isEmpty(): bool
    {
        return empty($this->user_id);
    }

    // ...
}
```

#### Clearing session state

You can clear an entire session, typically in a log-out controller/action:

```php
$session->clear();
```

Note that clearing the session will *orphan* any objects previously obtained via `get()`.

Clearing the session also implicitly renews the session, as described below.

#### Renewing sessions

You can renew an existing session, typically in a log-in controller/action:

```php
$session->renew();
```

Renewing a session will *retain* the current session state, but changes the Session ID,
and destroys the session data associated with the old Session ID.

Note that periodic renewal of the Session ID is *not* recommended - issuing a new
Session ID should be done only after authentication, e.g. after successful validation
of user-supplied login credentials over a secure connection.

#### Destroying a specific session

In rare cases, you may wish to destroy a specific session. For example, you may wish to forcibly destroy
the active session of a user who has been blocked/banned from the site by an administrator.

You can do this by accessing the underlying `SessionStorage` implementation:

```php
$storage->delete($session_id);
```

Note that this requires you to track the active Session ID for your users, which is outside the
scope of this package - you could, for example, store the most recent Session ID in a column in
your user-table in the database.

If you're using a dependency injection container, you should bootstrap the `SessionStorage` as
a separate component, so you can access it independently of the `SessionService`.

#### Flash messages

A flash message is a message that can only be read once, e.g. notifications. With the session model concept this 
becomes a trivial task:

```php
class Notifications implements SessionModel
{
    private $notifications = [];

    public function add(string $message)
    {
        $this->notifications[] = $message;
    }
    
    public function take()
    {
        $notifications = $this->notifications;
        
        $this->notifications = [];
        
        return $notifications;
    }

    public function isEmpty(): bool
    {
        return count($this->notifications) == 0;
    }
}
```

## Middleware

The package includes a PSR-15 compliant `SessionMiddleware` for easy integration of `SessionService`.

Either use this with your PSR-15 compatible middleware stack, or use it as reference for making custom middleware
that integrates more deeply with the rest of your stack.

Put this near the top of your middleware stack - it will initialize the session state, then delegate
unconditionally to the rest of your middleware stack, and finally commits any changes to session storage.

The request will be decorated with a `SessionData` instance, which can be obtained from the request, in lower
layers of your middleware stack, using e.g. `$request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)`.

## Session Service

This section details how to integrate `SessionService` into your own stack.

To initialize a session (at the beginning of a request) use the `beginSession()` method, which returns an instance
of `SessionData`, which represents the session state for the request you're processing.

`SessionData` implements `Session`, which defines the public facet of `SessionData` - type-hint against `Session`,
for example, when providing this (e.g. via constructor-injection) to your controllers.

To commit changes to session state (at the end of a request) use the `commitSession()` method, which also adds a
session cookie to a PSR-7 `ResponseInterface` instance.

Refer to `SessionMiddleware` for a working implementation of this pattern.

## Storage Adapters

Customizing session storage is possible by implementing the `SessionStorage` interface.

Currently `kodus/session` comes with a `SimpleCacheAdapter`, which depends on an
implementation of the PSR-16 cache interface for the physical storage of raw Session Data.

We recommend the [ScrapBook](https://github.com/matthiasmullie/scrapbook) package as a cache provider for `SimpleCacheAdapter`.

## Bootstrapping

Here's an example of how to fully bootstrap all layers of the session abstraction, from a `PDO` database
connection at the lowest level, over ScrapBook's SQLLite cache provider, to our PSR-16 Session Storage adapter,
and finally to PSR-15 middleware:

```php
use PDO;
use MatthiasMullie\Scrapbook\Adapters\SQLite;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use Kodus\Session\Adapters\SimpleCacheAdapter;
use Kodus\Session\SessionMiddleware;

$connection = new PDO('sqlite:cache.db');

$cache = new SimpleCache(new SQLite($connection));

$storage = new SimpleCacheAdapter($cache);

$service = new SessionService($storage);

$middleware = new SessionMiddleware($service);
```
