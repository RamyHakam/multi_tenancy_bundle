# Symfony Multi-Tenancy Bundle

![Multi-Tenancy Bundle (Desktop Wallpaper)](https://github.com/RamyHakam/multi_tenancy_bundle/assets/17661342/eef23e6a-881c-4817-b7b8-8b7cec913154)

![Action Status](https://github.com/RamyHakam/multi_tenancy_bundle/workflows/action_status/badge.svg?style=flat-square)
[![Total Downloads](https://img.shields.io/packagist/dt/hakam/multi-tenancy-bundle?style=flat-square)](https://packagist.org/packages/hakam/multi-tenancy-bundle)
[![Symfony Flex](https://img.shields.io/badge/Symfony%20Flex-Recipe%20Available-brightgreen.svg?style=flat-square)](https://github.com/symfony/recipes-contrib)

> 📚 **Full Documentation**: [Documentation](https://ramyhakam.github.io/multi_tenancy_bundle/intro/overview)

---

## 🧩 Overview

The **Symfony Multi-Tenancy Bundle** enables scalable, production-ready multi-tenancy for Symfony applications.

Ideal for **SaaS platforms**, **region-based services**, and **multi-vendor e-commerce systems**, this bundle is built around a **database-per-tenant architecture**, giving each tenant:

* A fully isolated database
* Independent schema and migrations
* Configurable connection parameters (host, driver, credentials)

It integrates seamlessly with Doctrine and Symfony’s service container, offering:

* Automatic tenant database switching at runtime via `SwitchDbEvent`
* Separate migration and fixture paths for main vs. tenant databases
* Dedicated `TenantEntityManager` service for runtime isolation

For full usage examples and advanced configuration, see the [documentation](https://ramyhakam.github.io/multi_tenancy_bundle/).

---

## 🚀 Quick Installation

### 1. Via Symfony Flex (Recommended)

```bash
composer require hakam/multi-tenancy-bundle
```

Symfony Flex will automatically scaffold config, register the bundle, and create:

```
src/Entity/Main/
src/Entity/Tenant/
migrations/Main/
migrations/Tenant/
```

### 2. Manual Installation

```bash
composer require hakam/multi-tenancy-bundle
```

Then register in `config/bundles.php`, copy the example `hakam_multi_tenancy.yaml` from docs, and create the required directories.

---

## 🔗 Useful Links

* **Full Documentation:** \[[https://ramyhakam.github.io/multi\_tenancy\_bundle/](https://ramyhakam.github.io/multi_tenancy_bundle/)]
* **GitHub:** [https://github.com/RamyHakam/multi\_tenancy\_bundle](https://github.com/RamyHakam/multi_tenancy_bundle)
* **Packagist:** [https://packagist.org/packages/hakam/multi-tenancy-bundle](https://packagist.org/packages/hakam/multi-tenancy-bundle)
* **Example Project:** [https://github.com/RamyHakam/multi-tenancy-project-example](https://github.com/RamyHakam/multi-tenancy-project-example)

---

## 📄 License

MIT © Ramy Hakam
