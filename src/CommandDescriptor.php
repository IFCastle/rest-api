<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\ServiceManager\CommandDescriptorInterface;

class CommandDescriptor implements CommandDescriptorInterface
{
    /**
     * @var array<string, mixed>|null
     */
    protected array|null $parameters = null;

    /**
     * @var callable():array<string, mixed>|null
     */
    protected mixed $extractParameters = null;

    /**
     * @param callable():array<string, mixed> $extractParameters
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly string $methodName,
        callable $extractParameters
    ) {
        $this->extractParameters    = $extractParameters;
    }

    #[\Override]
    public function getServiceNamespace(): string
    {
        return '';
    }

    #[\Override]
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    #[\Override]
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    #[\Override]
    public function getCommandName(): string
    {
        return $this->serviceName . '.' . $this->methodName;
    }

    #[\Override]
    public function getParameters(): array
    {
        if ($this->parameters === null) {
            $this->parameters       = [];
            $extractParameters      = $this->extractParameters;
            $this->extractParameters = null;
            $this->parameters       = $extractParameters();
        }

        return $this->parameters;
    }
}
