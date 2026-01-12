<?php

declare(strict_types=1);

namespace IfCastle\RestApi;

use Attribute;
use IfCastle\TypeDefinitions\NativeSerialization\AttributeNameInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Rest extends \Symfony\Component\Routing\Attribute\Route implements AttributeNameInterface
{
    public const string GET          = 'GET';

    public const string POST         = 'POST';

    public const string PUT          = 'PUT';

    public const string DELETE       = 'DELETE';

    public const string PATCH        = 'PATCH';

    public const string OPTIONS      = 'OPTIONS';

    public const string HEAD         = 'HEAD';

    #[\Override]
    public function getAttributeName(): string
    {
        return static::class;
    }
}
