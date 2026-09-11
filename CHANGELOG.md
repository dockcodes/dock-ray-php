# CHANGELOG

## Unreleased

- Lowered the requirement to PHP 7.4: no promoted or readonly properties,
  `match`, nullsafe calls, union types or PHP 8 string functions in the source.
  `Dock\Ray\Util\Compat` replaces `str_starts_with()`, `str_contains()` and
  `get_debug_type()` on PHP 7.4 with identical results.
- The Monolog handler supports Monolog 1, 2 and 3. `write()` is declared by
  `CompatibilityProcessingHandlerTrait` against whichever signature the
  installed Monolog uses; the handler itself implements `doWrite(array)`.
- HTTP client discovery no longer lets a half-installed client abort the
  request: every candidate is tried in isolation and a failing one falls
  through to the next. `HttplugClient` from symfony/http-client 5.4 throws
  while being autoloaded when `php-http/message-factory` is missing.