kodus/session
=============

# TODO!!!!!!!!!! REWRITE COMPLETELY

A simple interface for storing and retrieving session data without the use of PHP's native session handling.

`kodus/session` requires PHP version 7.0 or newer.

## Installation
If your project is using composer, simply require the package:

`composer require kodus/session`

## Introduction
This library provides a way of working with session data that promotes type safety and simplicity, without
relying on PHP's native session handling.

Type safety is accomplished by gathering the session data into simple session model classes, whose attributes and methods
can be type hinted, and storing instances of these in session, rather than storing values individually.

This approach requires an added amount of boilerplate code. The advantage of these session models easily outweighs the
small amount of added workload of writing a small and simple data model.

Write operations to the session are deffered until the end of the request, when they are "committed" to the storage.
This prevents broken session states as a result of critical errors.

## How to use

### Session models

When storing data in session, the first step is to define you session model. 

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
        return $this->user_id;
    }
    
    public function setFullName(string $full_name)
    {
        $this->full_name = $full_name;
    }
    
    public function getFullName(): string
    {
        return $this->full_name;
    }
}
```

These models should be encapsulated to the current domain. In other words, don't be tempted to collect all session data
into a big "catch-all" session model.

In the example above, `UserSession` uses regular accessor methods to access data in the model. This design choice is
not dictated by the interfaces. You may choose public attributes, setting data through the constructor, or whatever
style you prefer.

The requirements for session models are:

**It is required that instances of `SessionModel` must be able to be serialized and deserialized by native PHP 
serialization functions without loss of data.**

**It is strongly encouraged that implementations of `SessionModel` should only have attributes and methods specifically 
related to storing session data.**

The interface `SessionModel` is an example of a Marker interface. 
[You can read more about the Marker Interface Pattern on wikipedia.](https://en.wikipedia.org/wiki/Marker_interface_pattern)

### Session service
#### Storing and retrieving data
When you've created your session model, you'll of course want to store it in session. This is done with the 
`SessionService` class.

```php
$user_session = new UserSession();
$user_session->setUserID(1234);
$user_session->setFullName("Batman Johnson");

$session_service->set($user_session);
```

Each session model are stored by their type. This means that only one instance of a specific session model can be
stored per session. Instances are then fetched from the service by its type:

```php
/** @var UserSession $user_session */
$user_session = $session_service->get(UserSession::class);

echo $user_session->getFullName(); // output: "Batman Johnson"
```
At this point you will need to add a type hint in a docblock, if you want your IDE to recognize that the object returned
is an instance of the type requested.

There currently is no way of fetching specific classes in a generic and type-safe way in PHP 
("[Dreaming of Generics](https://wiki.php.net/rfc/generics)"&trade;).

#### Flash messages
It is also possible to store data in the session as flash messages. Flash messages is data that only stays in the 
session for one additional request.

This is useful for message data, like errors or confirmation messages, that should only be presented to the end user
once.

Flash messages are stored in session models, just like normal session data.

```php
class ErrorMessages implements SessionModel
{
    /** @var string[] */
    public $messages = [];
}

$error_msg = new ErrorMessages();
$error_msg->messages[] = "An error occured. This message will only be displayed once";

$session_service->flash($error_msg);
```

#### Removing data
A session model can also be removed from the session storage completely:
```php
$session_service->unset(UserSession::class);
```

It is also possible to clear the session completely:
```php
$session_service->clear();
```

### Session storage

Session storage can be customized by providing custom implementations or extensions of the `SessionStorage` interface.

`SessionStorage` defines a simple key/value store API, that is very similar to the methods of `SessionService`.

In cases where you only need to store single values or otherwise find session models to be overkill, values can be 
stored directly to instances of `SessionStorage`.

```php
$session_storage->set("user id", 1234);

$user_id = $session_storage->get("user id"); // returns 1234

$session_storage->unset("user id");

$user_id = $session_storage->get("user id"); // returns null

$session_storage->clear();
```

### Transactional session storage
`TransactionalSessionStorage` defines an interface for hooking session storage into a middleware
stack, by passing a PSR-7 compliant request object to the storage before delegating, and passing the PSR-7 compliant
response object to the storage after delegating.

`SessionStorage` is exposed to the `SessionService` and the DI-container. `TransactionalSessionStorage` is exposed
to the middleware.

**Session storage adapters should implement both SessionStorage and TransactionalSessionStorage**

### SessionMiddleware
`SessionMiddleware` defines a middleware component according to the PSR-15 interface `MiddlewareInterface`.

`SessionMiddleware` is designed to be at the top of the middleware stack, before any middleware components that
invoke controllers or other software that might need to access the session service.

Use `SessionMiddleware` if your framework supports PSR-15 middleware components, or use it as a reference for how to 
implement a custom middleware component. 

## Adapters

### CacheSessionStorage
`CacheSessionStorage` uses an implementation of PSR-16's `CacheInterface` to store and fetch session data.

We recommend the ScrapBook library as a cache provider for `CacheSessionStorage`.

[Find ScrapBook on Github](https://github.com/matthiasmullie/scrapbook).


## Bootstrapping
This example shows how to bootstrap `SessionService` and `SessionMiddleware` using the  `CacheSessionStorage` adapter:
```php
// initialize $cache according to your cache provider of choice.

$storage = new CacheSessionStorage($cache);

$service = new SessionService($storage);

$middleware = new SessionMiddleware($service);

//Add $middleware to the middleware stack.
//Add $service to the DI-container.
```

## Tips & Tricks
It's possible to add type safety for fetching session models, if you are willing to accept an extra layer
of boiler plate code. This can be accomplished by making small, isolated services for working with specific session models.
```php
class UserSessionService
{
    /** @var SessionService */
    private $session_service;
    
    public function __construct(SessionService $session_service)
    {
        $this->session_service = $session_service;
    }
    
    public function set(UserSession $user_session)
    {
        $this->session_service->set($user_session);
    }
    
    public function get(): UserSession
    {
        return $this->session_service->get(UserSession::class);
    }
    //... etc.
}
```
This might seem like a lot of code to get a specific type hint on the get method, so we'll leave this design choice up
 to you. 