<?php

declare(strict_types=1);

namespace Dock\Ray;

use Jean85\PrettyVersions;
use Dock\Ray\Context\OsContext;
use Dock\Ray\Context\RuntimeContext;

final class Event
{
    public const DEFAULT_ENVIRONMENT = 'production';

    /**
     * @var EventId
     */
    private $id;

    /**
     * @var float|null
     */
    private $timestamp = null;

    /**
     * @var float|null
     */
    private $startTimestamp = null;

    /**
     * @var Severity|null
     */
    private $level = null;

    /**
     * @var string|null
     */
    private $logger = null;

    /**
     * @var string|null
     */
    private $transaction = null;

    /**
     * @var string|null
     */
    private $serverName = null;

    /**
     * @var string|null
     */
    private $release = null;

    /**
     * @var string|null
     */
    private $message = null;

    /**
     * @var string|null
     */
    private $messageFormatted = null;

    /**
     * @var array
     */
    private $messageParams = [];

    /**
     * @var string|null
     */
    private $environment = null;

    /**
     * @var array
     */
    private $modules = [];

    /**
     * @var array
     */
    private $request = [];

    /**
     * @var array
     */
    private $tags = [];

    /**
     * @var OsContext|null
     */
    private $osContext = null;

    /**
     * @var RuntimeContext|null
     */
    private $runtimeContext = null;

    /**
     * @var UserDataBag|null
     */
    private $user = null;

    /**
     * @var array
     */
    private $contexts = [];

    /**
     * @var array
     */
    private $extra = [];

    /**
     * @var array
     */
    private $fingerprint = [];

    /**
     * @var array
     */
    private $breadcrumbs = [];

    /**
     * @var array
     */
    private $spans = [];

    /**
     * @var array
     */
    private $exceptions = [];

    /**
     * @var Stacktrace|null
     */
    private $stacktrace = null;

    /**
     * @var string
     */
    private $sdkIdentifier = Client::SDK_IDENTIFIER;

    /**
     * @var string
     */
    private $sdkVersion;

    /**
     * @var EventType
     */
    private $type;

    private function __construct(?EventId $eventId, EventType $eventType)
    {
        $this->id = $eventId ?? EventId::generate();
        $this->timestamp = microtime(true);
        $this->sdkVersion = PrettyVersions::getVersion('dockcodes/dock-ray')->getPrettyVersion();
        $this->type = $eventType;
    }

    public static function createEvent(?EventId $eventId = null): self
    {
        return new self($eventId, EventType::default());
    }

    public static function createTransaction(?EventId $eventId = null): self
    {
        return new self($eventId, EventType::transaction());
    }

    public function getId(): EventId
    {
        return $this->id;
    }

    public function getSdkIdentifier(): string
    {
        return $this->sdkIdentifier;
    }

    public function setSdkIdentifier(string $sdkIdentifier): void
    {
        $this->sdkIdentifier = $sdkIdentifier;
    }

    public function getSdkVersion(): string
    {
        return $this->sdkVersion;
    }

    public function setSdkVersion(string $sdkVersion): void
    {
        $this->sdkVersion = $sdkVersion;
    }

    public function getTimestamp(): ?float
    {
        return $this->timestamp;
    }

    public function setTimestamp(?float $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getLevel(): ?Severity
    {
        return $this->level;
    }

    public function setLevel(?Severity $level): void
    {
        $this->level = $level;
    }

    public function getLogger(): ?string
    {
        return $this->logger;
    }

    public function setLogger(?string $logger): void
    {
        $this->logger = $logger;
    }

    public function getTransaction(): ?string
    {
        return $this->transaction;
    }

    public function setTransaction(?string $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    public function setServerName(?string $serverName): void
    {
        $this->serverName = $serverName;
    }

    public function getRelease(): ?string
    {
        return $this->release;
    }

    public function setRelease(?string $release): void
    {
        $this->release = $release;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getMessageFormatted(): ?string
    {
        return $this->messageFormatted;
    }

    public function getMessageParams(): array
    {
        return $this->messageParams;
    }

    public function setMessage(string $message, array $params = [], ?string $formatted = null): void
    {
        $this->message = $message;
        $this->messageParams = $params;
        $this->messageFormatted = $formatted;
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function setModules(array $modules): void
    {
        $this->modules = $modules;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    private string $platform = 'php';

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): void
    {
        $this->platform = $platform;
    }

    public function setRequest(array $request): void
    {
        $this->request = $request;
    }

    public function getContexts(): array
    {
        return $this->contexts;
    }

    public function setContext(string $name, array $data): self
    {
        $this->contexts[$name] = $data;

        return $this;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }

    public function setExtra(array $extra): void
    {
        $this->extra = $extra;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getUser(): ?UserDataBag
    {
        return $this->user;
    }

    public function setUser(?UserDataBag $user): void
    {
        $this->user = $user;
    }

    public function getOsContext(): ?OsContext
    {
        return $this->osContext;
    }

    public function setOsContext(?OsContext $osContext): void
    {
        $this->osContext = $osContext;
    }

    public function getRuntimeContext(): ?RuntimeContext
    {
        return $this->runtimeContext;
    }

    public function setRuntimeContext(?RuntimeContext $runtimeContext): void
    {
        $this->runtimeContext = $runtimeContext;
    }

    public function getFingerprint(): array
    {
        return $this->fingerprint;
    }

    public function setFingerprint(array $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): void
    {
        $this->environment = $environment;
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function setBreadcrumb(array $breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function setExceptions(array $exceptions): void
    {
        foreach ($exceptions as $exception) {
            if (!$exception instanceof ExceptionDataBag) {
                throw new \UnexpectedValueException(sprintf('Expected an instance of the "%s" class. Got: "%s".', ExceptionDataBag::class, get_debug_type($exception)));
            }
        }

        $this->exceptions = $exceptions;
    }

    public function getStacktrace(): ?Stacktrace
    {
        return $this->stacktrace;
    }

    public function setStacktrace(?Stacktrace $stacktrace): void
    {
        $this->stacktrace = $stacktrace;
    }

    public function getType(): EventType
    {
        return $this->type;
    }

    public function getStartTimestamp(): ?float
    {
        return $this->startTimestamp;
    }

    public function setStartTimestamp(?float $startTimestamp): void
    {
        $this->startTimestamp = $startTimestamp;
    }

    public function getSpans(): array
    {
        return $this->spans;
    }

    public function setSpans(array $spans): void
    {
        $this->spans = $spans;
    }
}
