<?php

declare(strict_types=1);

namespace Dock\Thor;

use Http\Discovery\Psr17FactoryDiscovery;
use Jean85\PrettyVersions;
use Dock\Thor\HttpClient\HttpClientFactory;
use Dock\Thor\Serializer\RepresentationSerializerInterface;
use Dock\Thor\Serializer\SerializerInterface;
use Dock\Thor\Transport\DefaultTransportFactory;
use Dock\Thor\Transport\TransportFactoryInterface;
use Dock\Thor\Transport\TransportInterface;
use Psr\Log\LoggerInterface;

final class ClientBuilder implements ClientBuilderInterface
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @var TransportFactoryInterface|null
     */
    private $transportFactory = null;

    /**
     * @var TransportInterface|null
     */
    private $transport = null;

    /**
     * @var SerializerInterface|null
     */
    private $serializer = null;

    /**
     * @var RepresentationSerializerInterface|null
     */
    private $representationSerializer = null;

    /**
     * @var LoggerInterface|null
     */
    private $logger = null;

    /**
     * @var string
     */
    private $sdkIdentifier = Client::SDK_IDENTIFIER;

    /**
     * @var string
     */
    private $sdkVersion;

    public function __construct(?Options $options = null)
    {
        $this->options = $options ?? new Options();
        $this->sdkVersion = self::detectSdkVersion();
    }

    public static function create(array $options = []): ClientBuilderInterface
    {
        return new self(new Options($options));
    }

    private static function detectSdkVersion(): string
    {
        try {
            return PrettyVersions::getVersion('dockcodes/dock-thor')->getPrettyVersion();
        } catch (\Throwable) {
            return Client::SDK_VERSION;
        }
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function setSerializer(SerializerInterface $serializer): ClientBuilderInterface
    {
        $this->serializer = $serializer;

        return $this;
    }

    public function setRepresentationSerializer(RepresentationSerializerInterface $representationSerializer): ClientBuilderInterface
    {
        $this->representationSerializer = $representationSerializer;

        return $this;
    }

    public function setLogger(LoggerInterface $logger): ClientBuilderInterface
    {
        $this->logger = $logger;

        return $this;
    }

    public function setSdkIdentifier(string $sdkIdentifier): ClientBuilderInterface
    {
        $this->sdkIdentifier = $sdkIdentifier;

        return $this;
    }

    public function setSdkVersion(string $sdkVersion): ClientBuilderInterface
    {
        $this->sdkVersion = $sdkVersion;

        return $this;
    }

    public function setTransportFactory(TransportFactoryInterface $transportFactory): ClientBuilderInterface
    {
        $this->transportFactory = $transportFactory;

        return $this;
    }

    public function getClient(): ClientInterface
    {
        $this->transport = $this->transport ?? $this->createTransportInstance();

        return new Client($this->options, $this->transport, $this->sdkIdentifier, $this->sdkVersion, $this->serializer, $this->representationSerializer, $this->logger);
    }

    private function createTransportInstance(): TransportInterface
    {
        if (null !== $this->transport) {
            return $this->transport;
        }

        $transportFactory = $this->transportFactory ?? $this->createDefaultTransportFactory();

        return $transportFactory->create($this->options);
    }

    private function createDefaultTransportFactory(): DefaultTransportFactory
    {
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        return new DefaultTransportFactory(
            $streamFactory,
            Psr17FactoryDiscovery::findRequestFactory(),
            new HttpClientFactory($streamFactory, null, $this->sdkIdentifier, $this->sdkVersion),
            $this->logger
        );
    }
}
