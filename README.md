# DockRay PHP SDK

Framework-agnostic PHP client for [DockRay](https://dockray.io). It reports
uncaught exceptions, PHP errors and HTTP transactions to a DockRay project.

Framework bridges build on this package and are shipped separately:
`dock-ray-laravel`, `dock-ray-symfony`, `dock-ray-drupal`,
`dock-ray-joomla` and the `dockray` WordPress plugin.

## Installation

```shell
composer require dockcodes/dock-ray
```

Requires PHP 8.2 or newer.

## Configuration

A project is identified by two values taken from the DockRay panel:

| Value | Where it goes | Secret |
|---|---|---|
| project token | path of the ingest URL | no |
| private key | `Authorization: Bearer` header | **yes** |

```php
use function Dock\Ray\init;

init([
    'token' => 'PROJECT_TOKEN',
    'private_key' => 'PROJECT_PRIVATE_KEY',
    'environment' => 'production',
    'release' => '1.4.0',
]);
```

`token`, `private_key` and `url` also default to the `RAY_TOKEN`,
`RAY_PRIVATE_KEY` and `RAY_URL` server variables, so a deployment can
configure the SDK without touching code. When either credential is missing the
SDK stays loaded but sends nothing — it never throws for being unconfigured.

### Options worth knowing

| Option | Default | Meaning |
|---|---|---|
| `environment` | `null` | shown as the environment column in the panel |
| `release` | `null` | version of the deployed application |
| `error_types` | `error_reporting()` | which PHP errors become events |
| `sample_rate` | `1.0` | share of error events that get sent |
| `traces_sample_rate` | `0.0` | share of transactions that get sent; `0` disables tracing |
| `send_default_pii` | `false` | attaches user id, e-mail, IP and user agent |
| `enable_compression` | `true` | gzips the request body |
| `send_after_response` | `true` on web, `false` on CLI | queues events and sends them after the response |
| `max_request_body_size` | `medium` | `none`, `small`, `medium` or `always` |
| `in_app_exclude` | `[]` | paths whose frames are marked as vendor code |
| `before_send` | identity | last chance to modify or drop an event |

## Reporting

```php
use Dock\Ray\Breadcrumb;
use Dock\Ray\Severity;
use function Dock\Ray\{addBreadcrumb, captureException, captureMessage, configureScope};

captureMessage('Cache cleared', Severity::info());

try {
    $order->settle();
} catch (\Throwable $exception) {
    captureException($exception);
}

addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'auth', 'User logged in'));

configureScope(function (\Dock\Ray\State\Scope $scope) {
    $scope->setTag('feature', 'payments');
    $scope->setUser(['id' => 42, 'email' => 'user@example.com']);
});
```

Uncaught exceptions and fatal errors are captured automatically by the default
integrations; pass `'default_integrations' => false` to opt out.

## When events are sent

By default the SDK reports **after the response**. Events are queued during the
request and delivered from a shutdown handler, once PHP-FPM or LiteSpeed has
closed the connection to the browser. A slow or unreachable panel then costs
the visitor nothing.

Where the SAPI cannot close the connection early (mod_php), the queue is still
flushed at shutdown, so reporting never happens in the middle of handling a
request.

The queue holds at most 50 events per request; beyond that new events are
dropped, since the panel counts repeats by fingerprint anyway. Set
`'send_after_response' => false` to send inline — the default is already
`false` under CLI, where a long-running worker should not hold reports until
the process ends.

## Transactions

A transaction measures one HTTP request. The panel needs the request URL, the
method and the response status, so the SDK ships a helper that fills all three:

```php
use Dock\Ray\Framework\HttpTransaction;

$transaction = HttpTransaction::start('GET /checkout', $url, 'GET');
$transaction->measureHandling('app.handle');

// ... handle the request ...

$transaction->finish($response->getStatusCode());
```

Transactions are only sent when `traces_sample_rate` is above zero.

## Browser errors

A browser cannot authenticate against the panel: the private key would have to
sit in page source. The SDK therefore ships the collector script and the
server-side normaliser, and the application in the middle does the reporting:

```
browser  →  your application  →  DockRay
         (no key)          (project key)
```

```php
use Dock\Ray\Browser\BrowserEvent;
use Dock\Ray\Browser\Collector;

// Rendering the page: point the collector at your own endpoint.
$config = Collector::config(endpoint: '/errors/browser', token: $csrfToken);
// Collector::scriptPath() is the file to serve or enqueue.

// Receiving a report: everything in $payload is untrusted.
$event = BrowserEvent::fromArray($payload, $referer, $userAgent);

if ($event !== null) {
    $hub->captureEvent($event);
}
```

`fromArray()` returns `null` for anything it cannot make an event of, and
truncates every string it keeps. Guard the endpoint yourself — a CSRF token, a
body size limit and a per-IP rate limit are what the shipped plugins use.

The collector caps itself as well: one report per distinct error per page view,
ten per page view by default, with `ResizeObserver loop` and cross-origin
`Script error.` filtered out.

## Writing a bridge for another framework

Every bridge does the same four things, and the SDK has a seam for each:

1. **Boot** — call `init()` (or build a client with `ClientBuilder`) as early as
   the framework allows, so errors raised during bootstrap are still reported.
2. **Report** — hand the framework's exception handler to `captureException()`.
   Frameworks with a PSR-3 logger can instead register `Monolog\Handler` or any
   `LoggerInterface` decorator that forwards to the hub.
3. **Trace** — wrap request handling in `Framework\HttpTransaction`.
4. **Enrich** — set the user and tags on the scope, and mark framework code as
   vendor code through `in_app_exclude` so stack traces point at the
   application.

Anything the framework knows better than the SDK — how to read the current
request, who the current user is, where the application root lives — is passed
in through options and the scope. Nothing in `Dock\Ray` reaches back into a
framework.

## License

MIT. See [LICENSE](LICENSE).
