<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Context;

interface TenantContextInterface
{
    public function getTenantId(): ?string;

    public function getSchema(): string;
}
