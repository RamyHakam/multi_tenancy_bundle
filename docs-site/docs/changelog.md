---
title: Changelog
---

## [2.8.3] – 18 Jun 2025
### Added
- `php bin/console tenant:fixtures:load` command for tenant-specific fixtures
- `#[TenantFixture]` attribute to mark and load only tenant fixtures
- Full option support (`--append`, `--group`, `--purge-with-truncate`, `--dbid`) and fixture dependency handling
- Dynamic targeting of specific tenants with `--dbid` flag
### Changed
- Fixed deprecations by @NikoGrano in #55
- Corrected migration command from `migrate` to `update` by @joosee7 in #54
- Updated README.md by @skarnl in #49
- Added Tenant Fixture commands by @RamyHakam in #56

## [2.8.2] – 03 Aug 2024
### Added
- Bulk migrations: execute migrations across all tenant DBs with one command
- Enhanced per-tenant host, username & password configuration
- Support for different database drivers per tenant, independent of the main DB driver
- Extended Doctrine commands for tenant DB automation
### Changed
- Implemented bulk create & migrate commands by @RamyHakam in #47

## [2.8.1] – 27 Jul 2024
### Fixed
- Wrong directory-existence check when creating migrations/fixtures

## [2.8.0] – 28 Jun 2024
### Added
- Multiple-host support for tenant DBs, with IP-based resolution and Docker compatibility
#### Notes
- Tenant schema must share the same driver across all hosts
- In Docker, prefer `docker.host.internal` or explicit IPs over `localhost`

## [2.7.1] – 05 Jun 2024
### Fixed
- PostgreSQL driver resolution issue (PR #38 by @JensMyParcel)

## [2.7.0] – 02 May 2024
### Added
- Support for Doctrine DBAL 3.8 and 4.0, including:
    - README updates by @dfranco in #34
    - URL-connection-parameter adjustment for DBAL 3.6 by @RamyHakam in #32
    - SchemaCommand updates by @mogilvie in #33
    - Deprecation handling for DBAL 4 by @RamyHakam in #36
### Contributors
- @dfranco (first-time) 

## [2.6.0] – 26 Feb 2024
### Added
- Support for Symfony 7 and 6.4; dropped deprecated 6.1 & 6.3

## [2.5.4] – 30 Nov 2023
### Added
- Custom DQL functions via TenantEntityManager configuration

## [2.5.3] – 29 Nov 2023
### Added
- Two new methods in `TenantDbConfigurationInterface` to fix issue #26

## [2.5.2] – 01 Sep 2023
### Added
- Underscore-number-aware naming strategy for tenant EM via config

## [2.5.1] – 10 Jun 2023
### Added
- Support for PostgreSQL; PHP 8.2; on-the-fly tenant DB creation & migrations; `TimestampTrait`; Doctrine annotation upgrades

## [2.5.0] – 23 May 2023
### Added
- `tenant:database:create` command to provision a new tenant DB and run migrations

## [2.0.1] – 11 May 2023
### Fixed
- GitHub Actions build; fixed issues #11 & #13; auto-create missing directories; added CI status badge

## [2.0.0] – 24 Apr 2023
### Added
- On-the-fly tenant DB creation & preparation; Symfony 6 & PHP 8 support

## [1.0.3] – 21 Mar 2023
### Fixed
- Missing config issues #4 & #8

## [1.0.2] – 11 Sep 2022
### Added
- Support for PHP 8, PHP 8.1, Symfony 5.4

## [1.0.0] – 28 May 2022
### Added
- Upgrade to PHP 7.4; `DbConfigTrait`; custom migration paths; various bug fixes 

## [0.2] – 08 Apr 2022
### Added
- Initial release
