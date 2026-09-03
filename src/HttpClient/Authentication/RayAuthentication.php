<?php

declare(strict_types=1);

namespace Dock\Ray\HttpClient\Authentication;

use Dock\Ray\Options;
use Http\Message\Authentication as AuthenticationInterface;
use Psr\Http\Message\RequestInterface;

final class RayAuthentication implements AuthenticationInterface
{
    public function __construct(private readonly Options $options) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $authData = $this->options->getAuthData();

        if (null === $authData) {
            return $request;
        }

        return $request->withHeader('Authorization', 'Bearer ' . $authData->getPrivateKey());
    }
}
