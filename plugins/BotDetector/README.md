# BotDetector

BotDetector is a small optional KamiCore service plugin that heuristically detects known crawlers and automated HTTP clients by `User-Agent`.

It is intended for analytics filtering, logging, caching decisions, and other non-security behavior optimizations. A positive match only means that the request likely comes from an automated client rather than a normal interactive browser. `User-Agent` values are client-controlled and can be spoofed, so BotDetector must not be used for authentication, authorization, blocking, or access control.

## API

```php
$isBot = $botDetector->userIsBot();
$isBot = $botDetector->userIsBot($userAgent);
```

When no argument is provided, the current request `User-Agent` is used. An empty `User-Agent` is treated as an automated client.

## Signatures

The `Automated client signatures` setting contains one case-insensitive substring per line. Empty lines and lines starting with `#` are ignored.
