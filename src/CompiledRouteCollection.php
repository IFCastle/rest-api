<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

final class CompiledRouteCollection
{
    /**
     * @param array<string, mixed> $collection
     */
    public function __construct(public array $collection) {}
}
