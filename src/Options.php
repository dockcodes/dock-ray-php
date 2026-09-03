<?php

declare(strict_types=1);

namespace Dock\Ray;

use Symfony\Component\OptionsResolver\Options as SymfonyOptions;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class Options
{
    public const DEFAULT_MAX_BREADCRUMBS = 100;

    private array $options;

    private OptionsResolver $resolver;

    public function __construct(array $options = [])
    {
        $this->resolver = new OptionsResolver();

        $this->configureOptions($this->resolver);

        $this->options = $this->resolver->resolve($options);
    }

    public function getSendAttempts(): int
    {
        return $this->options['send_attempts'];
    }

    public function setSendAttempts(int $attemptsCount): void
    {
        $this->set('send_attempts', $attemptsCount);
    }

    public function getPrefixes(): array
    {
        return $this->options['prefixes'];
    }

    public function setPrefixes(array $prefixes): void
    {
        $this->set('prefixes', $prefixes);
    }

    public function getSampleRate(): float
    {
        return $this->options['sample_rate'];
    }

    public function setSampleRate(float $sampleRate): void
    {
        $this->set('sample_rate', $sampleRate);
    }

    public function getTracesSampleRate(): float
    {
        return $this->options['traces_sample_rate'];
    }

    public function setTracesSampleRate(float $sampleRate): void
    {
        $this->set('traces_sample_rate', $sampleRate);
    }

    public function isTracingEnabled(): bool
    {
        return 0.0 !== (float) $this->options['traces_sample_rate'] || null !== $this->options['traces_sampler'];
    }

    public function shouldAttachStacktrace(): bool
    {
        return $this->options['attach_stacktrace'];
    }

    public function setAttachStacktrace(bool $enable): void
    {
        $this->set('attach_stacktrace', $enable);
    }

    public function getContextLines(): ?int
    {
        return $this->options['context_lines'];
    }

    public function setContextLines(?int $contextLines): void
    {
        $this->set('context_lines', $contextLines);
    }

    public function shouldSendAfterResponse(): bool
    {
        return $this->options['send_after_response'];
    }

    public function setSendAfterResponse(bool $enabled): void
    {
        $this->set('send_after_response', $enabled);
    }

    public function isCompressionEnabled(): bool
    {
        return $this->options['enable_compression'];
    }

    public function setEnableCompression(bool $enabled): void
    {
        $this->set('enable_compression', $enabled);
    }

    public function getEnvironment(): ?string
    {
        return $this->options['environment'];
    }

    public function setEnvironment(?string $environment): void
    {
        $this->set('environment', $environment);
    }

    public function getInAppExcludedPaths(): array
    {
        return $this->options['in_app_exclude'];
    }

    public function setInAppExcludedPaths(array $paths): void
    {
        $this->set('in_app_exclude', $paths);
    }

    public function getInAppIncludedPaths(): array
    {
        return $this->options['in_app_include'];
    }

    public function setInAppIncludedPaths(array $paths): void
    {
        $this->set('in_app_include', $paths);
    }

    public function getRelease(): ?string
    {
        return $this->options['release'];
    }

    public function setRelease(?string $release): void
    {
        $this->set('release', $release);
    }

    public function getServerUrl(): string
    {
        return $this->options['url'];
    }

    public function getAuthData(): ?AuthData
    {
        return $this->options['auth_data'];
    }

    public function setAuthData(?AuthData $authData): void
    {
        $this->set('auth_data', $authData);
    }

    public function getLogger(): string
    {
        return $this->options['logger'];
    }

    public function setLogger(string $logger): void
    {
        $this->set('logger', $logger);
    }

    public function getServerName(): string
    {
        return $this->options['server_name'];
    }

    public function setServerName(string $serverName): void
    {
        $this->set('server_name', $serverName);
    }

    public function getBeforeSendCallback(): callable
    {
        return $this->options['before_send'];
    }

    public function setBeforeSendCallback(callable $callback): void
    {
        $this->set('before_send', $callback);
    }

    public function getErrorTypes(): int
    {
        return $this->options['error_types'] ?? error_reporting();
    }

    public function setErrorTypes(int $errorTypes): void
    {
        $this->set('error_types', $errorTypes);
    }

    public function getMaxBreadcrumbs(): int
    {
        return $this->options['max_breadcrumbs'];
    }

    public function setMaxBreadcrumbs(int $maxBreadcrumbs): void
    {
        $this->set('max_breadcrumbs', $maxBreadcrumbs);
    }

    public function getBeforeBreadcrumbCallback(): callable
    {
        return $this->options['before_breadcrumb'];
    }

    public function setBeforeBreadcrumbCallback(callable $callback): void
    {
        $this->set('before_breadcrumb', $callback);
    }

    public function getIntegrations(): array|callable
    {
        return $this->options['integrations'];
    }

    public function setIntegrations(array|callable $integrations): void
    {
        $this->set('integrations', $integrations);
    }

    public function shouldSendDefaultPii(): bool
    {
        return $this->options['send_default_pii'];
    }

    public function setSendDefaultPii(bool $enable): void
    {
        $this->set('send_default_pii', $enable);
    }

    public function hasDefaultIntegrations(): bool
    {
        return $this->options['default_integrations'];
    }

    public function setDefaultIntegrations(bool $enable): void
    {
        $this->set('default_integrations', $enable);
    }

    public function getMaxValueLength(): int
    {
        return $this->options['max_value_length'];
    }

    public function setMaxValueLength(int $maxValueLength): void
    {
        $this->set('max_value_length', $maxValueLength);
    }

    public function getHttpProxy(): ?string
    {
        return $this->options['http_proxy'];
    }

    public function setHttpProxy(?string $httpProxy): void
    {
        $this->set('http_proxy', $httpProxy);
    }

    public function shouldCaptureSilencedErrors(): bool
    {
        return $this->options['capture_silenced_errors'];
    }

    public function setCaptureSilencedErrors(bool $shouldCapture): void
    {
        $this->set('capture_silenced_errors', $shouldCapture);
    }

    public function getMaxRequestBodySize(): string
    {
        return $this->options['max_request_body_size'];
    }

    public function setMaxRequestBodySize(string $maxRequestBodySize): void
    {
        $this->set('max_request_body_size', $maxRequestBodySize);
    }

    public function getClassSerializers(): array
    {
        return $this->options['class_serializers'];
    }

    public function setClassSerializers(array $serializers): void
    {
        $this->set('class_serializers', $serializers);
    }

    public function getTracesSampler(): ?callable
    {
        return $this->options['traces_sampler'];
    }

    public function setTracesSampler(?callable $sampler): void
    {
        $this->set('traces_sampler', $sampler);
    }

    private function set(string $option, mixed $value): void
    {
        $this->options = $this->resolver->resolve(array_merge($this->options, [$option => $value]));
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'token' => $_SERVER['RAY_TOKEN'] ?? null,
            'private_key' => $_SERVER['RAY_PRIVATE_KEY'] ?? null,
            'url' => $_SERVER['RAY_URL'] ?? AuthData::DEFAULT_URL,
            'auth_data' => null,
            'integrations' => [],
            'default_integrations' => true,
            'send_attempts' => 3,
            'prefixes' => array_filter(explode(\PATH_SEPARATOR, get_include_path() ?: '')),
            'sample_rate' => 1,
            'traces_sample_rate' => 0,
            'traces_sampler' => null,
            'attach_stacktrace' => false,
            'context_lines' => 5,
            'enable_compression' => true,
            'send_after_response' => \PHP_SAPI !== 'cli',
            'environment' => $_SERVER['RAY_ENVIRONMENT'] ?? null,
            'logger' => 'php',
            'release' => $_SERVER['RAY_RELEASE'] ?? null,
            'server_name' => gethostname(),
            'before_send' => static fn (Event $event): Event => $event,
            'error_types' => null,
            'max_breadcrumbs' => self::DEFAULT_MAX_BREADCRUMBS,
            'before_breadcrumb' => static fn (Breadcrumb $breadcrumb): Breadcrumb => $breadcrumb,
            'in_app_exclude' => [],
            'in_app_include' => [],
            'send_default_pii' => false,
            'max_value_length' => 1024,
            'http_proxy' => null,
            'capture_silenced_errors' => false,
            'max_request_body_size' => 'medium',
            'class_serializers' => [],
        ]);

        $resolver->setAllowedTypes('token', ['null', 'string']);
        $resolver->setAllowedTypes('private_key', ['null', 'string']);
        $resolver->setAllowedTypes('url', 'string');
        $resolver->setAllowedTypes('auth_data', ['null', 'array', AuthData::class]);
        $resolver->setAllowedTypes('send_attempts', 'int');
        $resolver->setAllowedTypes('prefixes', 'string[]');
        $resolver->setAllowedTypes('sample_rate', ['int', 'float']);
        $resolver->setAllowedTypes('traces_sample_rate', ['int', 'float']);
        $resolver->setAllowedTypes('traces_sampler', ['null', 'callable']);
        $resolver->setAllowedTypes('attach_stacktrace', 'bool');
        $resolver->setAllowedTypes('context_lines', ['null', 'int']);
        $resolver->setAllowedTypes('enable_compression', 'bool');
        $resolver->setAllowedTypes('send_after_response', 'bool');
        $resolver->setAllowedTypes('environment', ['null', 'string']);
        $resolver->setAllowedTypes('in_app_exclude', 'string[]');
        $resolver->setAllowedTypes('in_app_include', 'string[]');
        $resolver->setAllowedTypes('logger', 'string');
        $resolver->setAllowedTypes('release', ['null', 'string']);
        $resolver->setAllowedTypes('server_name', 'string');
        $resolver->setAllowedTypes('before_send', 'callable');
        $resolver->setAllowedTypes('error_types', ['null', 'int']);
        $resolver->setAllowedTypes('max_breadcrumbs', 'int');
        $resolver->setAllowedTypes('before_breadcrumb', 'callable');
        $resolver->setAllowedTypes('integrations', ['Dock\Ray\Integration\IntegrationInterface[]', 'callable']);
        $resolver->setAllowedTypes('send_default_pii', 'bool');
        $resolver->setAllowedTypes('default_integrations', 'bool');
        $resolver->setAllowedTypes('max_value_length', 'int');
        $resolver->setAllowedTypes('http_proxy', ['null', 'string']);
        $resolver->setAllowedTypes('capture_silenced_errors', 'bool');
        $resolver->setAllowedTypes('max_request_body_size', 'string');
        $resolver->setAllowedTypes('class_serializers', 'array');

        $resolver->setAllowedValues('max_request_body_size', ['none', 'small', 'medium', 'always']);
        $resolver->setAllowedValues('max_breadcrumbs', static fn (int $value): bool => $value >= 0 && $value <= self::DEFAULT_MAX_BREADCRUMBS);
        $resolver->setAllowedValues('context_lines', static fn (?int $value): bool => null === $value || $value >= 0);
        $resolver->setAllowedValues('class_serializers', \Closure::fromCallable([$this, 'validateClassSerializers']));

        $resolver->setNormalizer('auth_data', \Closure::fromCallable([$this, 'normalizeAuthData']));
        $resolver->setNormalizer('prefixes', fn (SymfonyOptions $options, array $value): array => array_map([$this, 'normalizeAbsolutePath'], $value));
        $resolver->setNormalizer('in_app_exclude', fn (SymfonyOptions $options, array $value): array => array_map([$this, 'normalizeAbsolutePath'], $value));
        $resolver->setNormalizer('in_app_include', fn (SymfonyOptions $options, array $value): array => array_map([$this, 'normalizeAbsolutePath'], $value));
    }

    /**
     * Poświadczenia można podać na trzy sposoby: gotowym obiektem `AuthData`,
     * tablicą pod kluczem `auth_data` albo — najczęściej — parą `token`
     * i `private_key`. Niekompletna konfiguracja daje `null`, czyli transport,
     * który niczego nie wysyła; klucz-zaślepka wysyłałby zdarzenia w próżnię.
     */
    private function normalizeAuthData(SymfonyOptions $options, mixed $value): ?AuthData
    {
        if ($value instanceof AuthData) {
            return $value;
        }

        $token = (string) ($value['token'] ?? $options['token'] ?? '');
        $privateKey = (string) ($value['private_key'] ?? $options['private_key'] ?? '');
        $url = (string) ($value['url'] ?? $options['url']);

        if ($token === '' || $privateKey === '') {
            return null;
        }

        return AuthData::create($token, $privateKey, $url);
    }

    private function normalizeAbsolutePath(string $value): string
    {
        return @realpath($value) ?: $value;
    }

    private function validateClassSerializers(array $serializers): bool
    {
        foreach ($serializers as $class => $serializer) {
            if (!\is_string($class) || !\is_callable($serializer)) {
                return false;
            }
        }

        return true;
    }
}
