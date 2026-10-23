<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\ServiceManager\AsServiceMethod;

/**
 * A second service with a method named as one of SomeService's, on its own path.
 */
#[Rest('/other')]
final class SameMethodNameService
{
    #[AsServiceMethod]
    #[Rest('/some-method/{id}', methods: Rest::GET)]
    public function someMethod(string $id): string
    {
        return $id;
    }
}
