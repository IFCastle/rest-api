<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use Attribute;
use IfCastle\TypeDefinitions\NativeSerialization\AttributeNameInterface;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class RequestBody implements AttributeNameInterface
{
    /**
     * @param string[] $mimeTypes
     */
    public function __construct(
        public array $mimeTypes = [],
    ) {}

    #[\Override]
    public function getAttributeName(): string
    {
        return self::class;
    }
}
