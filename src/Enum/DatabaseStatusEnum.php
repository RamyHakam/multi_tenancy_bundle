<?phpnamespace Hakam\MultiTenancyBundle\Enum;enum DatabaseStatusEnum: string{    case DATABASE_MIGRATED = 'DATABASE_MIGRATED';    case DATABASE_CREATED = 'DATABASE_CREATED';    case DATABASE_NOT_CREATED = 'DATABASE_NOT_CREATED';}