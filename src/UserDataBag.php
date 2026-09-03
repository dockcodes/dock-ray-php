<?php

declare(strict_types=1);

namespace Dock\Ray;

/**
 * This class stores the information about the authenticated user for a request.
 */
final class UserDataBag
{
    /**
     * @var mixed
     */
    private $id;

    /**
     * @var string|null
     */
    private $email = null;

    /**
     * @var string|null
     */
    private $ipAddress = null;

    /**
     * @var string|null
     */
    private $username = null;

    /**
     * @var string|null
     */
    private $agent = null;

    /**
     * @var array
     */
    private $metadata = [];

    public function __construct($id = null, ?string $email = null, ?string $ipAddress = null, ?string $username = null, ?string $agent = null)
    {
        $this->setId($id);
        $this->setEmail($email);
        $this->setIpAddress($ipAddress);
        $this->setUsername($username);
        $this->setAgent($agent);
    }

    public static function createFromUserIdentifier($id): self
    {
        return new self($id);
    }

    public static function createFromUserIpAddress(string $ipAddress): self
    {
        return new self(null, null, $ipAddress);
    }

    public static function createFromArray(array $data): self
    {
        $instance = new self();

        foreach ($data as $field => $value) {
            switch ($field) {
                case 'id':
                    $instance->setId($value);
                    break;
                case 'ip_address':
                    $instance->setIpAddress($value);
                    break;
                case 'email':
                    $instance->setEmail($value);
                    break;
                case 'username':
                    $instance->setUsername($value);
                    break;
                case 'agent':
                    $instance->setAgent($value);
                    break;
                default:
                    $instance->setMetadata($field, $value);
                    break;
            }
        }

        return $instance;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        if (null !== $id && !\is_string($id) && !\is_int($id)) {
            throw new \UnexpectedValueException(sprintf('Expected an integer or string value for the $id argument. Got: "%s".', get_debug_type($id)));
        }

        $this->id = $id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getAgent(): ?string
    {
        return $this->agent;
    }

    public function setAgent(?string $agent): void
    {
        $this->agent = $agent;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        if (null !== $ipAddress && false === filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(sprintf('The "%s" value is not a valid IP address.', $ipAddress));
        }

        $this->ipAddress = $ipAddress;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(string $name, $value): void
    {
        $this->metadata[$name] = $value;
    }

    public function removeMetadata(string $name): void
    {
        unset($this->metadata[$name]);
    }

    public function merge(self $other): self
    {
        $this->id = $other->id;
        $this->email = $other->email;
        $this->ipAddress = $other->ipAddress;
        $this->username = $other->username;
        $this->metadata = array_merge($this->metadata, $other->metadata);

        return $this;
    }
}
