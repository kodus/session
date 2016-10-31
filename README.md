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

## `SessionService` TODO finish this
```php
SessionService::set(SessionModel $object): void
```

```php
SessionService::flash(SessionModel $object): void
```

```php
SessionService::get(string $type): SessionModel
```

```php
SessionService::has(string $type): bool
```

```php
SessionService::unset(string $type): void
```

```php
SessionService::clear(void): void
```

```php
SessionService::sessionID(void): string
```

## `SessionStorage`

The interface `SessionStorage` defines a set of methods for performing read and write operations to the session.

**Setting values** 
```php
SessionStorage::set(string $key, mixed $value): void
```
Adds a key/value pair to the session storage. It is assumed that the value is of a type that can be serialized and
deserialized by PHP. [More about serialization here](http://php.net/manual/en/function.serialize.php).

**Flash values**
```php
SessionStorage::flash(string $key, mixed $value): void
```
Adds a flash key/value pair to the session storage. Flash values only live through one succesful request.
A sucessful request is defined as a request that resolves to a response with status code &lt; 300.

**Fetching values**
```php
SessionStorage::get(string $key): mixed
```
Get the value stored under the key given. Implementations should throw a `RuntimeException` if no value is stored
under the given key.

Never call `SessionStorage::get($key)` if `SessionStorage::has($key)` returns false for the same key.

**Checking values**
```php
SessionStorage::get(string $key): mixed
```
Returns true if a value is stored in session under the given key. 

**Removing values**
```php
SessionStorage::unset(string $key): void
```
Remove any value stored in the current session for the given key.

**Clearing session**
```php
SessionStorage::clear(): void
```
Remove all values for the current session.

**Session ID**
```php
SessionStorage::sessionID(): string
```
Returns the session ID given to the specific client session.

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

# Usage examples TODO finish this - and maybe move to top, so usage is the first thing devs see.

## Example: user session

A good base example for session data is user data. If a user is logged in to the web page, that information will
typically be stored in session.

Let's look at a web page, where the user precense segment of the page needs to show the *email* and *full name* of the
user logged in. To save database queries, this information is stored in session when the user logs in.

With `kodus\session` it would make sense to group this information into one session model class for user session data.

That session model might look something like this:

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

## "Why not just use the simple key/value approach of `SessionStorage` for all session handling?

The general philosphy of the Kodus team is to keep things as type safe and simple as possible.
This is also the goal of the `kodus/session` library.

The àdvantages to type safe code are many. Among them are code completion and code inspection. Modern
IDEs like PHPStorm or NetBeans can interpret type safe code and suggest or auto complete code. It can also make
inspections to make sure you haven't made any mistakes that can be caught by checking types. Some IDE's will, to a
certain extend, even refactor your code for you.

The down side of this approach is having a lot of "boilerplate" code. Every domain segment of session data needs a
new session class in the code base. The Kodus team considers this a very small price to pay for the major advantages
to type safe code.

With PHP there are however some limitations to type safety, some of which will be shown below.


# Storage providers  TODO finish this

## CacheSessionService

### Why PSR-16 instead of PSR-6?

## CacheSessionMiddleware