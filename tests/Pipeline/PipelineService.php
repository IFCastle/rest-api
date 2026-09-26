<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use IfCastle\RestApi\Rest;
use IfCastle\ServiceManager\AsServiceMethod;

use function Async\delay;

#[Rest('/pipeline')]
final class PipelineService
{
    /**
     * Returns the id it was called with after waiting $delay milliseconds,
     * so concurrent calls suspend inside the pipeline and interleave.
     *
     * @return array<string, string>
     */
    #[AsServiceMethod]
    #[Rest('/echo/{id}/{delay}', methods: Rest::GET)]
    public function echo(string $id, int $delay): array
    {
        if ($delay > 0) {
            delay($delay);
        }

        return ['id' => $id];
    }
}
