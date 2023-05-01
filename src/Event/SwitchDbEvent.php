<?php

namespace Hakam\MultiTenancyBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class SwitchDbEvent extends Event
{
    private ?string $dbIndex;

    public function __construct(?string $tenantDbIndex)
    {
        $this->dbIndex = $tenantDbIndex;
    }

    public function getDbIndex(): ?string
    {
        return $this->dbIndex;
    }
}
