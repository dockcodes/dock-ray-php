<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Http\Discovery\Psr17FactoryDiscovery;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;

final class RequestFetcher implements RequestFetcherInterface
{
    public function fetchRequest(): ?ServerRequestInterface
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || \PHP_SAPI === 'cli') {
            return null;
        }

        $creator = new ServerRequestCreator(
            Psr17FactoryDiscovery::findServerRequestFactory(),
            Psr17FactoryDiscovery::findUriFactory(),
            Psr17FactoryDiscovery::findUploadedFileFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        return $creator->fromGlobals();
    }
}
