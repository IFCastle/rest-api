<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\Application\RequestEnvironment\RequestEnvironmentInterface;
use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\FunctionDescriptorInterface;

interface ExtractParameterInterface
{
    /**
     * Extract a parameter from the raw parameters.
     *
     * @param array<string, mixed>        $rawParameters
     * @param array<string, mixed>        $routeParameters
     *
     */
    public function extractParameter(DefinitionInterface         $parameter,
        FunctionDescriptorInterface $methodDescriptor,
        array                       $rawParameters,
        array                       $routeParameters,
        RequestEnvironmentInterface $requestEnvironment
    ): mixed;
}
