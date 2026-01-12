<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use Attribute;
use IfCastle\TypeDefinitions\NativeSerialization\AttributeNameInterface;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class FromGet implements AttributeNameInterface
{
    public function __construct(public string|null $name = null) {}

    #[\Override]
    public function getAttributeName(): string
    {
        return self::class;
    }
}
