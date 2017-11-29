kodus/session
=============

A simple interface for storing and retrieving session data without the use of PHP's native session handling.

`kodus/session` requires PHP version 7.0 or newer.

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

This means you can always assume that an instance of the session model is available.
 
#### Deleting session models

Because session models are always available, you will never delete a session model as such. If you want to easily
clear the state of the session model, you can add a clear method to the session model:

```php
class UserSession implements SessionModel
{
    private $user_id;
    private $full_name;
    
    public function clear()
    {
        unset($this->user_id);
        unset($this->full_name);
    }

    //Rest of the methods here
}
```

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

## Session Service

`SessionService` defines an interface for creating an instance of the class `SessionData` from a PSR-7 `ServerRequestInterface`
adapter, committing changes made to `SessionData` to storage, and adding a session cookie to a PSR-7 `ResponseInterface`
adapter.

`SessionData` implements `Session`. 

`SessionService::beginSession(ServerRequestInterface $request): SessionData`

`SessionService::commitSession(SessionData $session, ResponseInterface $response): ResponseInterface`

### Adapters

Customizing session storage and cookies is done by implementing adapters of the `SessionService` interface.

Currently `kodus/session` comes with the adapter `CacheSessionService`. `CacheSessionService` depends on an
implementation of the PSR-16 cache interface for storing data.

We recommend the ScrapBook library as a cache provider for `CacheSessionService`.

[Find ScrapBook on Github](https://github.com/matthiasmullie/scrapbook).

## Middleware

`kodus/session` comes with an implementation of the PSR-15 middleware interface. Use this with your PSR-15
compatible middleware stack, or use it as reference for making a middleware that is compatible with your middleware
stack.

## Bootstrapping

Here's an example of how to bootstrap `CacheSessionService` using SQLLite and ScrapBook for storage:

```php
$client = new \PDO('sqlite:cache.db');
$cache = new \MatthiasMullie\Scrapbook\Adapters\SQLite($client);
$simplecache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache($cache);

$service = new \Kodus\Session\Adapters\CacheSessionService($simplecache, "salt string for session cookie checksum");

$middleware = new \Kodus\Session\SessionMiddleware($service);
```
