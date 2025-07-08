<?php

namespace Hakam\MultiTenancyBundle\Config;

class TenantConnectionConfigDTO
{
    private function __construct(
       public string $driver,
        public  string $host,
        public  int    $port,
        public  string $dbname,
        public  string $user,
        public  string $password
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['driver'],
            $data['host'],
            $data['port'],
            $data['dbname'],
            $data['user'],
            $data['password']
        );
    }
}