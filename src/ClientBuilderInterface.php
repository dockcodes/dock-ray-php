<?php

declare(strict_types=1);

namespace Dock\Ray;

use Psr\Log\LoggerInterface;
use Dock\Ray\Serializer\RepresentationSerializerInterface;
use Dock\Ray\Serializer\SerializerInterface;
use Dock\Ray\Transport\TransportFactoryInterface;

interface ClientBuilderInterface
{
    public static function create(array $options = []): self;

    public function getOptions(): Options;

    public function getClient(): ClientInterface;

    public function setSerializer(SerializerInterface $serializer): self;

    public function setRepresentationSerializer(RepresentationSerializerInterface $representationSerializer): self;

    public function setLogger(LoggerInterface $logger): ClientBuilderInterface;

    public function setTransportFactory(TransportFactoryInterface $transportFactory): ClientBuilderInterface;

    public function setSdkIdentifier(string $sdkIdentifier): self;

    public function setSdkVersion(string $sdkVersion): self;
}
