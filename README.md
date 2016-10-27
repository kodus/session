kodus/session
=============
The `kodus/session` library provides a `SessionService` interface for storing and fetching values from a session storage with
type safe operations, along with a set of ready-to-use implementations.

Also included is a set of PSR-15 middlewares for the `SessionService` implementations.

The `kodus/session` library requires `PHP` &ge; `7.0`

# Installation

To install the `kodus/session` library, we recommend using composer packages.
Simply add it to you list of composer dependencies by calling:

```
user@device:~$ composer require kodus/session
```

## Disabling PHP native session
Depending on which implementation of `SessionService` you choose and whether or not you want to allow 3rd party
software to use native session handling, you might wish to disable native session handling.

[Read more about PHP's native session handling settings here.](http://php.net/manual/en/session.configuration.php)

# SessionModel interface

Assigning data to a session requires you to properly define a session data model class. This model class must
implement the `SessionModel` interface.

```php
interface SessionModel { /* Marker interface */ }
```

The first thing to notice about this interface is that it is empty. This interface is a *marker interface*. This means
that the interface only has one purpose: to mark classes as session model classes.

## Requirements and assumptions.

1. It must be possible to serialize and unserialize the session model without any loss of data.
*  A session model should only consist of the data stored for the session.
*  A session model should not have any other functionality.

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
    public int $id;
    
    /** 
     * @var string $email The email of the user currently logged in 
     */
    public string $email;
    
    /** 
     * @var string $full_name The full name of the user currently logged in 
     */
    public string $full_name;
}
```

## "Why can't I just store values individually?"

**The short answer:** *"You could do that, but then this is not the session library for you."*

**The long answer:** 

The general philosphy of the Kodus team is to keep things as type safe and simple as possible.
This is also the goal of the `kodus/session` library.

The general advantages to type safe code are many. Among them are code completion and code inspection. Modern
IDEs like PHPStorm or NetBeans can interpret type safe code and suggest or auto complete code. It can also make
inspections to make sure you haven't made any mistakes that can be caught by checking types. Some IDE's will, to a
certain extend, even refactor your code for you.

The down side of this approach is having a lot of "boilerplate" code. Every domain segment of session data needs a
new session class in the code base. The Kodus team considers this a very small price to pay for the major advantages
to type safe code.

With PHP there are however some limitations to type safety, some of which will be shown below.




[Read more about the Marker Interface Pattern here.](https://en.wikipedia.org/wiki/Marker_interface_pattern)

# SessionService interface

```php
interface SessionService
{
    public function set(SessionModel $object);

    public function flash(SessionModel $object);

    public function get(string $type): SessionModel;

    public function has(string $type): bool;

    public function unset(string $type);

    public function clear();

    public function sessionID(): string;
}
```

# Implementations

## CacheSessionService

## CacheSessionMiddleware