# Upgrade Notes

## 3.0

This release makes several efforts to harden security overall.

While the API is backwards-compatible with that of the 2.x series, we consider these
changes "breaking" in the sense that it doesn't behave the same as before, in terms of
things like TTL in storage, and frequency/properties of the emitted session-cookie.

Note that the Session ID that gets persisted (as the key) to underlying storage has changed,
so this upgrade will invalidate any existing sessions that were started with version 2.x.
(you may wish to manually clear the underlying cache, but that's optional.)

## 2.0

This release makes changes to the internal abstraction, which affects bootstrapping of the
service - this means there will be breaking changes to the way you bootstrap `SessionService`,
which has been changed from an interface to a class, while introducing the new `SessionStorage`
interface, which provides better separation of server-side storage concerns from cookie management, etc.

There are no breaking changes to the `SessionService` API itself - this change affects bootstrapping only.

Let's assume you have existing version 1 bootstrapping like the following:

```php
$cache = new FileCache(); // a PSR-16 implementation

$service = new CacheSessionService($cache, CacheSessionService::TWO_WEEKS, false);
```

To upgrade, you must introduce an intermediary `SessionStorage` implementation, in this case the
`SimpleCacheAdapter` (which provides PSR-16 support) as follows:

```php
$cache = new FileCache(); // a PSR-16 implementation

$storage = new SimpleCacheAdapter($cache); // a SessionStorage implementation

$service = new SessionService($storage, SessionService::TWO_WEEKS, false);
```

If you're using a dependency injection container, you may wish to register the `SessionStorage`
implementation as a separate dependency.
