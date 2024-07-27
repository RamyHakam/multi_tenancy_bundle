<?php

namespace Hakam\MultiTenancyBundle\Enum;

enum DriverTypeEnum: string
{
    case MYSQL = 'mysql';
    case POSTGRES = 'postgresql';
    case SQLITE = 'sqlite';
}
