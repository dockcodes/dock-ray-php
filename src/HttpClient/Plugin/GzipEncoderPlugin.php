<?php

declare(strict_types=1);

namespace Dock\Ray\HttpClient\Plugin;

use Http\Client\Common\Plugin as PluginInterface;
use Http\Promise\Promise as PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;


final class GzipEncoderPlugin implements PluginInterface
{
    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    public function __construct(StreamFactoryInterface $streamFactory)
    {
        if (!\extension_loaded('zlib')) {
            throw new \RuntimeException('The "zlib" extension must be enabled to use this plugin.');
        }

        $this->streamFactory = $streamFactory;
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): PromiseInterface
    {
        $requestBody = $request->getBody();

        if ($requestBody->isSeekable()) {
            $requestBody->rewind();
        }


        $encodedBody = gzencode($requestBody->getContents());

        if (false === $encodedBody) {
            throw new \RuntimeException('Failed to GZIP-encode the request body.');
        }

        $request = $request->withHeader('Content-Encoding', 'gzip');
        $request = $request->withBody($this->streamFactory->createStream($encodedBody));

        return $next($request);
    }
}
