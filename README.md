kodus/session
=============

The `kodus/session` library provides a set of classes and interfaces for working with session data.

The goal is to provide a simple, yet type-safe way of working with session values.

The `kodus/session` library requires `PHP` &ge; `7.0`

# Installation

To install the `kodus/session` library, we recommend using composer packages.
Simply add it to you list of composer dependencies by calling:

```
user@device:~$ composer require kodus/session
```

# Terminology

**Storage provider:** A specific implementation of the provided `SessionStorage` interface.

**Physical storage:** The actual storage solution used by an implementation of `SessionStorage`.
E.g. The *storage provider* `CacheSessionStorage` uses a cache provider as its *physical storage*.

## Disabling PHP native session
Depending on which implementation of `SessionService` you choose and whether or not you want to allow 3rd party
software to use native session handling, you might wish to disable native session handling.

[Read more about PHP's native session handling settings here.](http://php.net/manual/en/session.configuration.php)

# Overview
This library provides a service class `SessionService` and two interfaces `SessionModel`, and `SessionStorage`. 

`SessionService` is a service layer on top of `SessionStorage`, that provides an API for working with dedicated session
model classes.

`SessionStorage` defines a key/value API for storing values to and fetching values from the session storage.

## Session models

When storing a set of related values in storage, we wish to work with type hinted values, that are grouped by domain.

This can be done by collecting these values in a session model class. An example of a set of session values, might
be the values stored in session for a user that sucessfully logged in to your site:

```php
namespace Vendor\MyProject\UserModule;

class MyUserSession
{
    /** 
     * @var int $id The id of the user currently logged in 
     */
    public $id;
    
    /** 
     * @var string $email The email of the user currently logged in 
     */
    public $email;
    
    /** 
     * @var string $full_name The full name of the user currently logged in 
     */
    public $full_name;
}
```
In the example above, the values are kept in public attributes of the class. These are only type-hinted by doc-blocks.

If you prefer strict types, then you can change the attributes to private, and implement accessor methods with type hints.

```php 

declare(strict_types = 1);

namespace Vendor\MyProject\UserModule;

class MyUserSession
{
    /** 
     * @var int $id The id of the user currently logged in 
     */
    private $id;
    
    /** 
     * @var string $email The email of the user currently logged in 
     */
    private $email;
    
    /** 
     * @var string $full_name The full name of the user currently logged in 
     */
    private $full_name;
    
    public function getID(): int
    {
        return $this->id;
    }
    
    public function setID(int $id)
    {
        $this->id = $id;
    }
    
    //Etc.
}
```

**For this type of model class we assume the following:**

1. It must be possible to serialize and unserialize the session model without any loss of data.
*  A session model should only consist of the data stored for the session.
*  A session model should not have any other functionality, not related to storing the values.
 
To ensure that session models are dedicated session model classes, we introduce a marker interface `SessionModel`.

```php
interface SessionModel { /* Marker interface */ }
```

This interface only has one purpose: to mark classes as session model classes.

```php
class MyUserSession extends Kodus\Session\SessionModel
{
    //....
}
```

[Read more about the Marker Interface Pattern here.](https://en.wikipedia.org/wiki/Marker_interface_pattern)

As you will see in the next section, `SessionService` does not accept any objects that are not implementations of 
`SessionModel` even though it is an empty interface. 

This serves two purposes:

1. Since there is no general "object" type in PHP, this prevents anyone from trying to store scalar types or closures
with `SessionService`.

2. This forces developers to at least try to separate the concern of storing session values from other concerns.

*You could at this point object that a developer could simply add `implements SessionModel` to a class that is not dedicated
for session storage. That's true. We wish that guy the best of luck. In the meantime the rest of us can read up on 
the [Single Responsibility Principle](https://en.wikipedia.org/wiki/Single_responsibility_principle) and
[Separation of Concerns](https://en.wikipedia.org/wiki/Separation_of_concerns)*

## SessionService
The `SessionService` class provides an API for storing instances of `SessionModel` in the session storage.

#### set()
```php
SessionService::set(SessionModel $object): void
```
Save the state of a specific `SessionModel` object to the session storage.

#### flash()
```php
SessionService::flash(SessionModel $object): void
```
Flash the state of a specific `SessionModel` object to the session storage.

Flashed objects only live through one succesful request. A sucessful request is defined as a
request that resolves to a response with status code &lt; 300.

#### get()
```php
SessionService::get(string $type): SessionModel
```
Fetch an instance of a session model stored in session by it's type.

PHP limits us to typehinting the return value as `SessionModel` 
(*dreaming of [generics](https://en.wikipedia.org/wiki/Generic_programming)&trade;*).

So when using the `get()` method, we recommend you use docblocks for correct typehinting.

**Example:**
```php
// In this example we fetch the stored instance of MyUserSession from the previous examples. 

/** @var MyUserSession $my_user_session */
$my_user_session = $session_service->get(MyUserSession::class);

$user = $user_repository->getUser($my_user_session->id);
```
Docblocks has no runtime effect, but your IDE will be able to inspect, auto-complete and all the other neat things, your IDE does
(providing you use an IDE with these features of course).

#### has()
```php
SessionService::has(string $type): bool
```
Returns true if an instance of the given type has been stored in the session.

#### unset()
```php
SessionService::unset(string $type): void
```
Removes any instance of the given type from the session.

#### clear()
```php
SessionService::clear(void): void
```
Clears all values from the session storage.

#### sessionID()
```php
SessionService::sessionID(void): string
```
Returns the unique identifier for the current session.

---

## SessionStorage

The interface `SessionStorage` defines a set of methods for performing read and write operations to the session.

#### set()
```php
SessionStorage::set(string $key, mixed $value): void
```
Adds a key/value pair to the session storage. It is assumed that the value is of a type that can be serialized and
deserialized by PHP. [More about serialization here](http://php.net/manual/en/function.serialize.php).

#### flash()
```php
SessionStorage::flash(string $key, mixed $value): void
```
Adds a flash key/value pair to the session storage. Flash values only live through one succesful request.
A sucessful request is defined as a request that resolves to a response with status code &lt; 300.

#### get()
```php
SessionStorage::get(string $key): mixed
```
Get the value stored under the key given. Implementations should throw a `RuntimeException` if no value is stored
under the given key.

Never call `SessionStorage::get($key)` if `SessionStorage::has($key)` returns false for the same key.

#### has()
```php
SessionStorage::has(string $key): bool
```
Returns true if a value is stored in session under the given key. 

#### unset()
```php
SessionStorage::unset(string $key): void
```
Remove any value stored in the current session for the given key.

#### clear()
```php
SessionStorage::clear(): void
```
Remove all values for the current session.

#### sessionID()
```php
SessionStorage::sessionID(): string
```
Returns the session ID given to the specific client session.

---

### Why do I have to call `has()` before calling `get()`
If you are wondering why `SessionStorage::get()` throws an exception instead of returning `null` or `false`, then let's
see what happens if we were to do that:

```php
// Imagine that get() returns false if nothing is stored for a requested key:
$value = $session_storage->get("a boolean value");

if (! $value) {
    // So... does this happen because false was stored or because nothing was stored?
}
```
If `get()` returns any value on a non-hit (false, null, 0, "nothing here", etc.) then you aren't "able" to store that
value. It might actually be stored in the physical storage, but when you call `get()` it would be impossible to tell
 if that value or nothing was stored.

Instead we do this:
```php

if ($session_storage->has("a boolean value") {
    $value = $session_storage->get("a boolean value");
    if (! $value) {
        //This time we know exactly why we are here.
    }
}

```

### Deferring `SessionStorage` operations
Implementations of `SessionStorage` may choose to defer write or remove operations to the *physical storage*.

*Implementations of `SessionStorage`, that defer operations, MUST behave as if the operations are not deferred.* 

In the example below, we imagine a storage provider, that defers write operations to the physical storage until the end
of the request life cycle. 

```php
$session_storage->set("a key", "a value");

//The key/value has not yet been written to the physical storage.
//But we can still fetch its value as if it was.

if ($session_storage->has("a key")) {
    echo $session_storage->get("a key");
} else {
    //This doesn't happen.
}
```

# Usage

In the examples above session model class for a user session was shown, `MyUserSession`.

Let's have a look at how you would use this in a typical MVC structure. 

We use the basic version, with public attributes instead of accessor methods.
```php
namespace Vendor\MyProject\UserModule;

class MyUserSession implements Kodus\Session\SessionModel
{
    /** 
     * @var int $id The id of the user currently logged in 
     */
    public $id;
    
    /** 
     * @var string $email The email of the user currently logged in 
     */
    public $email;
    
    /** 
     * @var string $full_name The full name of the user currently logged in 
     */
    public $full_name;
}
```

A likely place for storing an instance of this session model would be a Controller class that logs in a user.

```php
namespace Vendor\MyProject\UserModule;

use Kodus\Session\SessionService;

class LoginController
{
    /** @var UserService */
    private $user_service;
    /** @var SessionService */
    private $session;
    
    public function __construct(UserService $user_service, SessionService $session)
    {
        $this->user_service = $user_service;
        $this->session = $session;
    }
    
    public function run($username, $password)
    {
        $authenticated = $this->user_service->authenticate($username, $password);
        if ($authenticated) {
            $user = $this->user_service->getUser($username);
            
            $user_session = new MyUserSession();
            $user_session->id = $user->getID();
            $user_session->email = $user->getEmail();
            $user_session->full_name = $user->getFirstName() . ' ' . $user->getLastName();
            
            $this->session->set($user_session);
            
            //Redirect to relevant page here
        }
        
        //Redirect to login page here 
    }
}

```

Let's look at a controller where you might want to use the session model we just stored in session.
Notice again here, that we are typehinting the fetched instance with at docblock.

```php
class WelcomePageController
{
    /** @var SessionService */
    private $session;
    
    public function __construct(SessionService $session)
    {
        $this->session = $session;
    }
    
    public function run($username, $password)
    {
        if ($this->session->has(MyUserSession::class)) {
            /** @var MyUserSession $user_session */
            $user_session = $this->get(MyUserSession::class);
            
            $view = new WelcomePageView();
            $view->setMessage("Welcome to Kodus, {$user_session->fullname}.");
            
            //Render view and return
        } else {
            //Redirect to login page here 
        }
    }
}

```

If you are tired of writing docblocks, you could wrap your specific session model handling into a simple wrapper class:

```php
class UserSessionService
{
    /** @var SessionService */
    private $session;
    
    public function __construct(SessionService $session)
    {
        $this->session = $session;
    }
    
    public function set(UserSession $user_session)
    {
        $this->session->set($user_session);
    }
    
    public function get(): UserSession
    {
        if ($this->session->has(UserSession::class) {
            return $this->session->get(UserSession::class);
        }
        
        return null;
    }
    
    public function unset()
    {
        $this->session->unset(UserSession::class);
    }
}
```
With this wrapper class you would get a neat little handler class you could dependency inject into your controller
instead of the general `SessionService`. 

You might think it's a bit much to avoid writing a docblock once in a while.
You might be right. But it is a small and simple pattern, that won't change, so it shouldn't take more than 5 minutes to
write a wrapper class like this.


## "Why not just use the simple key/value approach of `SessionStorage` for all session handling?

The general philosphy of the Kodus team is to keep things as type safe and simple as possible.
This is also the goal of the `kodus/session` library.

The advantages to type safe code are many. Among them are code completion and code inspection. Modern
IDEs like PHPStorm or NetBeans can interpret type safe code and suggest or auto complete code. It can also make
inspections to make sure you haven't made any mistakes that can be caught by checking types. Some IDE's will, to a
certain extend, even refactor your code for you.

The down side of this approach is having a lot of "boilerplate" code. Every domain segment of session data needs a
new session class in the code base. The Kodus team considers this a very small price to pay for the major advantages
to type safe code.


# Storage providers

## CacheSessionService

The class `Kodus\Session\Storage\CacheSessionStorage` provides an implementation of `SessionStorage` that uses a
PSR-16 compliant cache provider as it's physical storage.

**Bootstrapping `SessionService` with `CacheSessionStorage`:**
```php
    $cache = new GenericPSR16CacheProvider();
    $storage = new CacheSessionStorage($cache);
    $service = new SessionService($storage); //et voilà!
```

### Usage
`CacheSessionService` is designed to work with PSR-7 HTTP message interfaces and being hooked up with middleware.

A session is initialised by calling `CacheSessionStorage::begin($request)`. The session is chosen by the session
cookie in the request object (or a new session ID is created).

The changes to the session are committed when `CacheSessionStorage::commit($response)` is called. The method
 also adds a session cookie to the response.

For an example of this have a look at the `SessionMiddleware` class

An implementation of the PSR-15 middleware interface is also provided, and described in the next section.

If you need a PSR-16 cache provider for you caching technology, we recommend having a 
look at [https://github.com/matthiasmullie/scrapbook](https://github.com/matthiasmullie/scrapbook).

### Why PSR-16 instead of PSR-6?
If you are familiar with the PSR standards, you might ask why we would use a standard that hasn't yet been approved,
when there is already an official cache standard in PSR-6? That's a good question dear reader, nice work!

[PSR-6](http://www.php-fig.org/psr/psr-6/) has been a controversial one. Have a look at these articles: 
["PSR-6 has serious problems"](http://andrewcarteruk.github.io/programming/2015/12/07/psr-6-has-serious-problems.html) - 
Andrew Carter, and [An open letter to PHP-FIG](http://blog.ircmaxell.com/2014/10/an-open-letter-to-php-fig.html) - Anthony Ferrara.

The reason we choose PSR-16 over PSR-6 is that the PSR-6 interfaces have an unfortunate structure, that makes it 
impossible to implement functionality that works with the interfaces provided by PSR-6 without knowing the specific 
implementation. *That is a major problem for an interface!*

Too see why this is the case, have a look at what happens if you try to implement the `SessionStorage::set()` method.

```php
class PSR6CacheSessionStorage implements SessionStorage
{
    public function __construct(CacheItemPoolInterface $cache_pool}
    {
        //Here we use an (unknown) implementation of CacheItemPoolInterface 
        $this->cache_pool = $cache_pool;
    }
    public function set(string $key, $value)
    {
        //To feed a key/value pair to the cache we need to create an instance of CacheItemInterface
        //For this we need a specific implementation to work with...
        $cache_item = new CustomCacheItem($key);
        $cache_item->set($value);
        $this->cache_pool->save($cache_item);
    }
}
```
So at this point we've chosen a specific implementation of `CacheItemInterface`. But that's not a problem, because
interfaces define a generic way of interacting with an object, so as long as we implement the interface, we should be good,
right? Well... turns out that it won't.

If you notice that `CacheItemInterface` doesn't define any way of retrieving important values from the instances.
There is no defined way of fetching expiration dates or the key of the key/value pair(!). 

So let's say we put the key in a private attribute called `$key`, and we can set it in the constructor:
```php
class CustomCacheItem implements Psr\Cache\CacheItemInterface
{
    private $key;
    
    public function __construct($key)
    {
        $this->key = $key;
    }
    //... methods go here
}
```

If we implement the class like this, we can only use it with an implementation of `CacheItemPoolInterface` that we know
will use class reflection to fetch the private variable.

We could change it up and make a public `getKey()` method. But it is not part of the interface, so again, we need
to use an implementation of the pool interface that we know uses the `getKey()` method.

So the conclusion is: If you need to create new values for the cache, you can not depend on the generic PSR-6 interfaces.
You will need to know the specific implementation, thus rendering the interfaces useless. 
This leads to the general conclusion, that you can only depend on PSR-6 interfaces if you only need to read from the 
cache.

So as you might have guessed, this is a statement against PSR-6. Come on PSR-16, we are rooting for you!

## SessionMiddleware
`SessionMiddleware` is an implementation of the middleware interface from the proposed PSR-15 standard.

The `CacheSessionStorage` instance is given as a constructor argument. 

The middleware begins the current session from the PSR-7 `RequestInterface` instance, 
delegates to the next middleware, and adds the cookie to the response before returning.

```php
    public function __construct(CacheSessionStorage $session)
    {
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $this->session->begin($request);

        $response = $delegate->process($request);

        $response = $this->session->commit($response);

        return $response;
    }
```
The CacheSessionStorage defers operations until the commit method is called.
