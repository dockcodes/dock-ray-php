<?php

declare(strict_types=1);

namespace Dock\Thor;

/**
 * Adres ingestu i para poświadczeń projektu.
 *
 * `token` jest publicznym identyfikatorem projektu i jedzie w ścieżce adresu,
 * `privateKey` to sekret projektu wysyłany nagłówkiem `Authorization: Bearer`.
 *
 * Domyślny adres jest tu jednym miejscem dla całego SDK — przy przejściu na
 * dockthor.io zmienia się `DEFAULT_URL`, a instalacje, które podały własny
 * adres, zostają nietknięte.
 */
final class AuthData implements \Stringable
{
    public const DEFAULT_URL = 'https://thor.dock.codes';

    private const API_PATH = '/api/v1';

    private string $scheme;

    private string $host;

    private ?int $port;

    private string $basePath;

    private string $token;

    private string $privateKey;

    private function __construct(string $url, string $token, string $privateKey)
    {
        if ($token === '') {
            throw new \InvalidArgumentException('The project token must not be empty.');
        }

        if ($privateKey === '') {
            throw new \InvalidArgumentException('The project private key must not be empty.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw new \InvalidArgumentException(sprintf('The "%s" value is not a valid DockTHOR server URL.', $url));
        }

        $this->scheme = $parts['scheme'] ?? 'https';
        $this->host = $parts['host'];
        $this->port = $parts['port'] ?? null;
        $this->basePath = rtrim($parts['path'] ?? '', '/');
        $this->token = $token;
        $this->privateKey = $privateKey;
    }

    public static function create(string $token, string $privateKey, string $url = self::DEFAULT_URL): self
    {
        return new self($url, $token, $privateKey);
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getServerUrl(): string
    {
        $url = $this->scheme . '://' . $this->host;

        if ($this->port !== null) {
            $url .= ':' . $this->port;
        }

        return $url . $this->basePath;
    }

    public function getProjectApiEndpointUrl(): string
    {
        return $this->getBaseEndpointUrl() . '/project';
    }

    public function getTransactionApiEndpointUrl(): string
    {
        return $this->getBaseEndpointUrl() . '/transaction';
    }

    public function __toString(): string
    {
        return $this->getBaseEndpointUrl();
    }

    private function getBaseEndpointUrl(): string
    {
        return $this->getServerUrl() . self::API_PATH . '/' . $this->token;
    }
}
