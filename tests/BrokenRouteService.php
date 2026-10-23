<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use IfCastle\ServiceManager\AsServiceMethod;

/**
 * A service whose second route names a parameter the method does not take.
 */
#[Rest('/broken')]
final class BrokenRouteService
{
    #[AsServiceMethod]
    #[Rest('/valid', methods: Rest::GET)]
    public function validMethod(): string
    {
        return 'valid';
    }

    #[AsServiceMethod]
    #[Rest('/item/{id}', methods: Rest::GET)]
    public function methodWithoutRouteArgument(): string
    {
        return 'unreachable';
    }
}
