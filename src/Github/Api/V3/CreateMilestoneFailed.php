<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use RuntimeException;

class CreateMilestoneFailed extends RuntimeException
{
    public static function forVersion(string $version, string $error): self
    {
        return new self(sprintf('Milestone "%s" creation failed: %s', $version, $error));
    }
}
