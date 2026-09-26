<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use IfCastle\Application\ApplicationAbstract;
use IfCastle\Application\EngineRolesEnum;

final class PipelineApplication extends ApplicationAbstract
{
    #[\Override]
    protected function defineEngineRole(): EngineRolesEnum
    {
        return EngineRolesEnum::SERVER;
    }
}
