<?php

namespace Hakam\MultiTenancyBundle\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Hakam\MultiTenancyBundle\Attribute\TenantEntity;
use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;

/**
 * Filters Doctrine entity metadata per active tenant.
 *
 * - Entities marked #[TenantShared] are routed to the 'shared' schema.
 *   If the current tenant is in the excludeTenants list, the entity is hidden
 *   (marked as mapped superclass) for that tenant.
 * - Entities marked #[TenantEntity(tenants: [...])] are only visible to the
 *   listed tenants. For all others the entity is marked as mapped superclass.
 * - Entities with no attribute are routed to the current tenant's own schema.
 *
 * Attribute classification is cached in-process (attributes are static) so
 * reflection only runs once per class per process lifetime, not once per
 * tenant switch.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class TenantMetadataListener
{
    /** @var array<string, array{type: string, instance: TenantShared|TenantEntity|null}> */
    private array $attributeCache = [];

    public function __construct(
        private readonly TenantContextInterface $tenantContext,
        private readonly string $sharedSchemaName = 'shared',
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        $reflection = $metadata->getReflectionClass();

        if (!$reflection || $reflection->isAbstract()) {
            return;
        }

        $tenant = $this->tenantContext->getTenantId();
        if ($tenant === null) {
            return;
        }

        $info = $this->resolveAttributeInfo($reflection->getName(), $reflection);

        match ($info['type']) {
            'shared' => $this->handleShared($metadata, $info['instance'], $tenant),
            'tenant_only' => $this->handleTenantOnly($metadata, $info['instance'], $tenant),
            default => $this->applyTenantSchema($metadata),
        };
    }

    /**
     * Resolve and cache the attribute type for a class.
     * Attribute classification never changes at runtime, so it is safe to cache
     * for the lifetime of the process.
     *
     * @return array{type: string, instance: TenantShared|TenantEntity|null}
     */
    private function resolveAttributeInfo(string $className, \ReflectionClass $reflection): array
    {
        if (array_key_exists($className, $this->attributeCache)) {
            return $this->attributeCache[$className];
        }

        $sharedAttrs = $reflection->getAttributes(TenantShared::class);
        if ($sharedAttrs) {
            return $this->attributeCache[$className] = [
                'type' => 'shared',
                'instance' => $sharedAttrs[0]->newInstance(),
            ];
        }

        $tenantAttrs = $reflection->getAttributes(TenantEntity::class);
        if ($tenantAttrs) {
            return $this->attributeCache[$className] = [
                'type' => 'tenant_only',
                'instance' => $tenantAttrs[0]->newInstance(),
            ];
        }

        return $this->attributeCache[$className] = ['type' => 'all', 'instance' => null];
    }

    private function handleShared(ClassMetadata $metadata, TenantShared $shared, string $tenant): void
    {
        if (!$shared->isAvailableForTenant($tenant)) {
            $metadata->isMappedSuperclass = true;
            return;
        }

        $metadata->setPrimaryTable([
            'name' => $metadata->getTableName(),
            'schema' => $this->sharedSchemaName,
        ]);
    }

    private function handleTenantOnly(ClassMetadata $metadata, TenantEntity $tenantEntity, string $tenant): void
    {
        if (!$tenantEntity->isAvailableForTenant($tenant)) {
            $metadata->isMappedSuperclass = true;
            return;
        }

        $this->applyTenantSchema($metadata);
    }

    private function applyTenantSchema(ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => $metadata->getTableName(),
            'schema' => $this->tenantContext->getSchema(),
        ]);
    }
}
