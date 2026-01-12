<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\ServiceManager\AsServiceMethod;
use IfCastle\TypeDefinitions\Value\ValueUuid;

#[Rest('/base')]
final class SomeService
{
    #[AsServiceMethod]
    #[Rest('/some-method/{id}', methods: Rest::GET)]
    public function someMethod(string $id): string
    {
        return 'Hello, World!';
    }

    #[AsServiceMethod]
    #[Rest('/method-with-uuid/{uuid}', methods: Rest::GET)]
    public function methodWithUuid(ValueUuid $uuid, string $extraParameter): string
    {
        return $uuid->getValue() . '::' . $extraParameter;
    }

    #[AsServiceMethod]
    #[Rest('/method-with-integer-parameter/{id}', methods: Rest::GET)]
    public function methodWithIntegerParameter(int $id, string $optionalParameter = 'default'): string
    {
        return $id . '::' . $optionalParameter;
    }
}
