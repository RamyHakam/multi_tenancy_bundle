<?php

namespace Hakam\MultiTenancyBundle\Message;

class CreateTenantDatabaseMessage
{
    public function __construct(public  readonly  int $tenantId)
    {}
}