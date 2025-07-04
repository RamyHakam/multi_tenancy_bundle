---
title: "Suggested Patterns for Use"
---

> 🙏 **Thanks to [@mogilvie](https://github.com/mogilvie) for some of these patterns! and idea!**

### 1. User Pattern

Store the user’s current tenant ID in the session or on the User entity. This lets you retrieve the active tenant at any
time:

```php

namespace App\Entity\Tenant;

use App\Model\OrgActivitySuperclass;
use App\Repository\Tenant\OrgActivityRepository;
use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;

#[ORM\Entity(repositoryClass: OrgActivityRepository::class)]
class OrgActivity extends OrgActivitySuperclass implements TenantEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
           
}
```

### 2. Tenant Interface

Implement `TenantEntityInterface` on all tenant-specific entities to signal the bundle which EM to use:

```php
namespace App\Entity\Tenant;

use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;

#[ORM\Entity]
class OrgActivity implements TenantEntityInterface
{
#[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
private int $id;

public function getId(): int
{
return $this->id;
}
}
```

### 3. Custom Controller

Extend your base controller to override persistence methods for EM switching:

```php
<?php

namespace App\Controller;

use App\Entity\Main\Tenant;
use App\Entity\Tenant\Organisation;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Umbrella\CoreBundle\Controller\BaseController;

abstract class DbSwitcherController extends BaseController
{
    public function __construct(private EventDispatcherInterface $eventDispatcher, private TenantEntityManager $tenantEntityManager)
    {
    }
    
    protected function switchDb(Tenant $tenant): Organisation
    {

        // Switch the TenantEntityManager to the current tenant.
        $event = new SwitchDbEvent($tenant->getId());
        $this->eventDispatcher->dispatch($event);

        // Optional depending on your usage, here we return the top entity whenever we switch to a new Tenant DB.
        $organisation = $this->tenantEntityManager->getRepository(Organisation::class)
            ->findOneIdByXeroOrganisationId($tenant->getXeroOrganisationId());

        return $organisation;
    }

    /**
     * Override parent method to check if the entity is a Tenant entity or main entity. Return which ever is appropriate.
     */  
    protected function findOrNotFound(string $className, $id)
    {

        $em = $this->em();

        $reflection = new \ReflectionClass($className);

        if ($reflection instanceof TenantEntityInterface) {
            $em = $this->tenantEntityManager;
        }

        $e = $em->find($className, $id);
        $this->throwNotFoundExceptionIfNull($e);

        return $e;
    }

    /**
     * Override parent method to check if the entity is a Tenant entity or main entity. Return which ever is appropriate.
     */  
    protected function persistAndFlush($entity): void
    {
        if ($entity instanceof TenantEntityInterface) {
            $this->tenantEntityManager->persist($entity);
            $this->tenantEntityManager->flush();
            return;
        }
        $this->em()->persist($entity);
        $this->em()->flush();
    }

    /**
     * Override parent method to check if the entity is a Tenant entity or main entity. Return which ever is appropriate.
     */  
    protected function removeAndFlush($entity): void
    {
        if ($entity instanceof TenantEntityInterface) {
            $this->tenantEntityManager->remove($entity);
            $this->tenantEntityManager->flush();
            return;
        }
        $this->em()->remove($entity);
        $this->em()->flush();
    }
}
```

### 4. Custom Argument Resolver

Override Symfony’s default `EntityValueResolver` to switch DB for tenant entities:

```php
<?php

namespace App\ValueResolver;

namespace App\ValueResolver;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantEntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
        private TenantEntityManager $tenantEntityManager,
        private MapEntity $defaults = new MapEntity(),
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->getName()))) {
            return [];
        }

        $options = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF);
        $options = ($options[0] ?? $this->defaults)->withDefaults($this->defaults, $argument->getType());

        if (!$options->class || $options->disabled) {
            return [];
        }

        $reflectionClass = new \ReflectionClass($options->class);

        if(!$reflectionClass instanceof TenantEntityInterface){
            return [];
        }

        $currentTenant = $this->security->getUser()->getCurrentTenant();

        $switchEvent = new SwitchDbEvent($currentTenant->getId());
        $this->eventDispatcher->dispatch($switchEvent);

        $manager = $this->tenantEntityManager;

        if (!$manager instanceof TenantEntityManager) {
            return [];
        }

        $message = '';
        if (null !== $options->expr) {
            if (null === $object = $this->findViaExpression($manager, $request, $options)) {
                $message = sprintf(' The expression "%s" returned null.', $options->expr);
            }
            // find by identifier?
        } elseif (false === $object = $this->find($manager, $request, $options, $argument->getName())) {
            // find by criteria
            if (!$criteria = $this->getCriteria($request, $options, $manager)) {
                return [];
            }
            try {
                $object = $manager->getRepository($options->class)->findOneBy($criteria);
            } catch (NoResultException|ConversionException) {
                $object = null;
            }
        }

        if (null === $object && !$argument->isNullable()) {
            throw new NotFoundHttpException(sprintf('"%s" object not found by "%s".', $options->class, self::class).$message);
        }

        return [$object];
    }

    private function getManager(?string $name, string $class): ?ObjectManager
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        try {
            $manager = $this->registry->getManager($name);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $manager->getMetadataFactory()->isTransient($class) ? null : $manager;
    }

    private function find(ObjectManager $manager, Request $request, MapEntity $options, string $name): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);
        if (false === $id || null === $id) {
            return $id;
        }

        if ($options->evictCache && $manager instanceof EntityManagerInterface) {
            $cacheProvider = $manager->getCache();
            if ($cacheProvider && $cacheProvider->containsEntity($options->class, $id)) {
                $cacheProvider->evictEntity($options->class, $id);
            }
        }

        try {
            return $manager->getRepository($options->class)->find($id);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    private function getIdentifier(Request $request, MapEntity $options, string $name): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = sprintf($field, $name);
                }

                $id[$field] = $request->attributes->get($field);
            }

            return $id;
        }

        if (null !== $options->id) {
            $name = $options->id;
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name) ?? ($options->stripNull ? false : null);
        }

        if (!$options->id && $request->attributes->has('id')) {
            return $request->attributes->get('id') ?? ($options->stripNull ? false : null);
        }

        return false;
    }

    private function getCriteria(Request $request, MapEntity $options, ObjectManager $manager): array
    {
        if (null === $mapping = $options->mapping) {
            $mapping = $request->attributes->keys();
        }

        if ($mapping && \is_array($mapping) && array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }

        foreach ($options->exclude as $exclude) {
            unset($mapping[$exclude]);
        }

        if (!$mapping) {
            return [];
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if (\is_string($options->id) && null === $request->attributes->get($options->id)) {
            return [];
        }

        $criteria = [];
        $metadata = $manager->getClassMetadata($options->class);

        foreach ($mapping as $attribute => $field) {
            if (!$metadata->hasField($field) && (!$metadata->hasAssociation($field) || !$metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        return $criteria;
    }

    private function findViaExpression(ObjectManager $manager, Request $request, MapEntity $options): ?object
    {
        if (!$this->expressionLanguage) {
            throw new \LogicException(sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $repository = $manager->getRepository($options->class);
        $variables = array_merge($request->attributes->all(), ['repository' => $repository]);

        try {
            return $this->expressionLanguage->evaluate($options->expr, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }
}
```

*Tag the resolver service with higher priority than Symfony’s default.*

```yaml
services:
  # Priority should fire before the default EntityValueResolver
  App\ValueResolver\TenantEntityValueResolver:
    tags:
      - { name: controller.argument_value_resolver, priority: 150 }
```

### 5. DQL Extensions

Register custom DQL functions scoped to tenant queries:

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
  tenant_entity_manager:
    dql:
      string_functions:
      json_extract: App\\DQL\\JsonExtractFunction
    numeric_functions:
      gaussian: App\\DQL\\GaussianFunction
```

### 6. DB Host & Credentials

Store per-tenant host/port/user/password fields on your `TenantDbConfig` entity. The bundle will apply them on each
switch:

```php
class TenantDbConfig implements TenantEntityConfigurationInterface
{
      private string $host;
     private int $port;
    private string $username;
     private string $password;

     public function getHost(): string { return $this->host; }
    public function getPort(): int { return $this->port; }
    public function getUsername(): string { return $this->username; }
    public function getPassword(): string { return $this->password; }
}
```

With these patterns, your application code remains clean and your multi-tenant logic centralized. Enjoy building!
