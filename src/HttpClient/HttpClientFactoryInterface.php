<?php

declare(strict_types=1);

namespace Dock\Ray\HttpClient;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Dock\Ray\Options;

interface HttpClientFactoryInterface
{
    public function create(Options $options): HttpAsyncClientInterface;
}
