# idct/adminata-admin-mongodb-bundle

Doctrine MongoDB ODM persistence backend for **[Adminata][adminata]** — full
CRUD, filtering, sorting, pagination, exports and ACL management for MongoDB
documents, the same way
[`idct/adminata-doctrine-orm-admin-bundle`][orm] provides it for relational
databases.

[adminata]: https://github.com/ideaconnect/adminata
[orm]: https://github.com/ideaconnect/adminata-doctrine-orm-admin-bundle

> **7.0: adminata's own names.** Since 2026-09-12 this package is
> `idct/adminata-admin-mongodb-bundle` (repository
> [ideaconnect/adminata-admin-mongodb-bundle](https://github.com/ideaconnect/adminata-admin-mongodb-bundle);
> the old address redirects) under the `IDCT\Adminata\DoctrineMongoDB\` namespace, built on
> adminata's `IDCT\Adminata\`. The `6.x` line keeps the old package name and the Sonata-named
> adminata; `5.x` is the last line built on `sonata-project/admin-bundle`. Upgrading:
> [UPGRADE-7.0.md](UPGRADE-7.0.md) and
> [adminata's UPGRADE.md](https://github.com/ideaconnect/adminata/blob/main/UPGRADE.md).

[![Latest Stable Version](https://img.shields.io/packagist/v/idct/adminata-admin-mongodb-bundle.svg?label=stable)](https://packagist.org/packages/idct/adminata-admin-mongodb-bundle)
[![License](https://img.shields.io/packagist/l/idct/adminata-admin-mongodb-bundle.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://www.php.net/supported-versions.php)
[![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0-000000?logo=symfony&logoColor=white)](https://symfony.com/releases)
[![Doctrine MongoDB ODM](https://img.shields.io/badge/Doctrine%20MongoDB%20ODM-%5E2.6-orange)](https://www.doctrine-project.org/projects/mongodb-odm.html)
[![adminata](https://img.shields.io/badge/adminata-dev--main-blue)](https://github.com/ideaconnect/adminata)

[![codecov](https://codecov.io/gh/ideaconnect/adminata-admin-mongodb-bundle/branch/7.x/graph/badge.svg?token=yUdY2iB1AV)](https://codecov.io/gh/ideaconnect/adminata-admin-mongodb-bundle)
[![Test](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/test.yaml/badge.svg)](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/test.yaml)
[![Quality assurance](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/qa.yaml/badge.svg)](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/qa.yaml)
[![Lint](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/lint.yaml/badge.svg)](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/lint.yaml)
[![Symfony Lint](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/symfony-lint.yaml/badge.svg)](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/symfony-lint.yaml)
[![Documentation](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/documentation.yaml/badge.svg)](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/actions/workflows/documentation.yaml)

---

## 🚨 6.0 moved to adminata, 7.0 to adminata's names

**6.0 is built against [`idct/adminata`][adminata], not
`sonata-project/admin-bundle`.** adminata is our hard fork of the Sonata Admin
stack with the Twig templates, CSS and JavaScript replaced by a Tailwind CSS v4
/ TailAdmin interface: **Bootstrap, AdminLTE and jQuery are gone**, and there is
no compatibility layer for them. If your project styles admin screens with
Bootstrap class names or scripts them with jQuery, that markup stops working
and has to be ported once.

**7.0 takes adminata's own names.** The namespace is
`IDCT\Adminata\DoctrineMongoDB\`, the bundle class `AdminataDoctrineMongoDBBundle`,
the configuration root `adminata_doctrine_mongodb`, the Twig namespace
`@AdminataDoctrineMongoDB`, and every service id starts with `adminata.`; the
package is `idct/adminata-admin-mongodb-bundle` and it **conflicts** with
`sonata-project/doctrine-mongodb-admin-bundle` rather than living beside it.
The map, and the tool that applies it to an application:
[adminata's UPGRADE.md](https://github.com/ideaconnect/adminata/blob/main/UPGRADE.md);
this package's rows: [UPGRADE-7.0.md](UPGRADE-7.0.md).

Adminata is still in development: [`idct/adminata`](https://packagist.org/packages/idct/adminata)
is on Packagist as `dev-main` only, and that is the constraint this release
requires it at. Both packages install from Packagist; no `repositories` entry
is needed.

**Staying on Sonata Admin 4.x?** Use the `5.x` line. It is the last release
series built against `sonata-project/admin-bundle` and it keeps working.

---

## 🚨 This is a HARD FORK

`idct/adminata-admin-mongodb-bundle` is a **hard fork** of
[`sonata-project/doctrine-mongodb-admin-bundle`](https://github.com/sonata-project/SonataDoctrineMongoDBAdminBundle),
not a soft fork or a temporary patch:

- The vendor name and Composer package id are different
  (`idct/...` vs. `sonata-project/...`) — the two **cannot** be installed
  side by side: 7.0 declares a `conflict` with the upstream package, and no
  `replace`.
- The `5.x` line already breaks BC in places upstream has not:
  `ModelManager::getDocumentManager()` is private, `ProxyQuery::__call()`
  is gone, `ProxyQuery::setOptions()` is removed, `ModelFilter::fixIdentifier()`
  rejects malformed input, `Pager::countResults()` throws when uninitialized,
  and more. See [UPGRADE-5.0.md](UPGRADE-5.0.md) for the full break list.
- Future releases **will keep diverging** — extending the public API,
  replacing parts that aren't worth keeping, dropping things upstream still
  ships. Upstream changes are pulled in selectively, not merged.
- We do **not** sync release numbers with upstream. Our `5.0.0` is the
  fork's first release; the upstream lineage we forked from is 4.12.0. `6.0.0`
  is the move to Adminata, described above.

**If you need exact upstream behaviour**, stay on
`sonata-project/doctrine-mongodb-admin-bundle`. **If you want a modernised
base on PHP 8.4+ / Symfony 7.4+ and don't mind moving with us**, this is
the right place.

The MIT license, Thomas Rabaix's original copyright, and every upstream
contributor's attribution are preserved — see [LICENSE](LICENSE) and the
74-entry author roster in [composer.json](composer.json).

---

## What this bundle does

Installs next to `idct/adminata` and provides every persistence-layer concern
it needs in order to drive an admin UI against a MongoDB collection:

- **CRUD** — `ModelManager` implements every CRUD path Sonata calls
  (`create`, `update`, `delete`, `find`, `findBy`, `findOneBy`,
  `batchDelete`, `reverseTransform`).
- **Datagrids** — `Pager` + `ProxyQuery` wrap the ODM `QueryBuilder` to
  give Sonata's listing screens pagination, sorting and a count query
  routed through `Collection::countDocuments`.
- **Filtering** — twelve ready-to-use filter classes you can declare on an
  admin: `String`, `Number`, `Boolean`, `Choice`, `Date`, `DateRange`,
  `DateTime`, `DateTimeRange`, `Id`, `Model` (relations),
  `Callback` (escape hatch), `Empty` (null / missing field). String filter
  understands `EQUAL`, `NOT_EQUAL`, `CONTAINS`, `NOT_CONTAINS`,
  `STARTS_WITH`, `ENDS_WITH` with an optional `case_sensitive` switch and
  full regex-input escaping.
- **Type guessing** — two `TypeGuesser`s map ODM mapping types
  (`Type::STRING`, `Type::INT`, `Type::DATE`, …) to Sonata column types
  and filter types automatically.
- **Builders** — `DatagridBuilder`, `ListBuilder`, `ShowBuilder` and
  `FormContractor` materialise admin screens from `FieldDescription`s.
- **Exports** — `DataSource` produces a streaming `\Iterator` for Sonata's
  exporter; pass `hydrate: false` for raw-array CSV/XML/JSON exports
  bypassing ODM hydration on wide collections.
- **ACLs** — `ObjectAclManipulator` bulk-applies Symfony Security ACE
  entries to every document of an admin class, batched at 20 docs per
  flush with progress output.
- **Dotted paths** — `FieldDescriptionFactory` resolves nested paths
  (`author.publisher.name`) against ODM `ClassMetadata` and surfaces
  clear errors when a segment isn't actually an association.

If Adminata can do it for SQL via
[`idct/adminata-doctrine-orm-admin-bundle`][orm], this bundle is the piece that
lets you do the same for MongoDB.

---

## Requirements

| | Floor | Tested up to |
|---|---|---|
| PHP | 8.4 | 8.5 |
| Symfony | 7.4 | 8.0 |
| Adminata | 1.0@dev | `dev-main` |
| Doctrine MongoDB ODM | 2.6 | latest 2.x |
| Doctrine MongoDB ODM Bundle | 5.0 | latest 5.x |
| Doctrine Persistence | 4.0 | latest 4.x |
| MongoDB server | 4.0+ | 7.x |

Both [PHP 8.4](https://www.php.net/releases/8.4/en.php) and
[PHP 8.5](https://www.php.net/releases/8.5/en.php) are supported and
exercised in CI. Symfony 7.4 (the current LTS) and 8.0 are the only
supported Symfony lines.

---

## Installation

### Prerequisites

You should already have a Symfony 7.4+ application with **Adminata** and
**Doctrine MongoDB ODM Bundle** installed — this bundle is the glue between
them, not a replacement for either.

```bash
composer require idct/adminata:dev-main doctrine/mongodb-odm-bundle
```

Both come from Packagist. Adminata has no tagged release yet, so `dev-main`
is its only version there, and naming that constraint is what lets a project
with `minimum-stability: stable` take it.

### Install

```bash
composer require idct/adminata-admin-mongodb-bundle
```

Symfony Flex registers the bundle automatically. If you're not using
Flex, add it to `config/bundles.php` manually:

```php
return [
    // ...
    IDCT\Adminata\DoctrineMongoDB\AdminataDoctrineMongoDBBundle::class => ['all' => true],
];
```

### Declare an admin

Tag any admin service with `manager_type: doctrine_mongodb` and Adminata will
resolve it through this bundle's implementations:

```php
// config/services.php (Symfony 7+ PHP config)
$services->set(App\Admin\BookAdmin::class)
    ->tag('adminata.admin', [
        'manager_type' => 'doctrine_mongodb',
        'model_class'  => App\Document\Book::class,
        'label'        => 'Book',
    ]);
```

### Optional bundle config

The bundle ships sane defaults; the only config tree it owns is per-type
template overrides for list and show columns:

```yaml
# config/packages/adminata_doctrine_mongodb.yaml
adminata_doctrine_mongodb:
    templates:
        types:
            list:
                custom_type: '@App/admin/list_custom.html.twig'
            show:
                custom_type: '@App/admin/show_custom.html.twig'
```

---

## Testing

The test suite has three layers:

1. **Pure unit tests** — fast, no external services. Mock Sonata
   interfaces and exercise our classes in isolation. Most of
   `tests/Builder/`, `tests/Filter/`, `tests/FieldDescription/`.
2. **Component tests** — build an in-memory `DocumentManager` against a
   real MongoDB server, persist fixtures, exercise full flows. `PagerTest`,
   `ProxyQueryTest`, `ModelManagerTest`'s integration cases,
   `ObjectAclManipulatorTest`.
3. **Functional tests** — boot the test kernel
   (`tests/App/AppKernel.php`) and drive a real Sonata admin in a real
   browser via Symfony Panther. `tests/Functional/`.

Layers 2 and 3 need a MongoDB server. Layer 3 additionally needs a
Firefox WebDriver.

The `legacy-ui` group is excluded by default. Those scenarios click through the
Bootstrap markup Adminata replaced, and they pass again once its milestones M3
and M4 rewrite the templates; `vendor/bin/phpunit --group legacy-ui` shows where
that stands.

### Quick start (recommended)

```bash
docker compose up -d
PANTHER_SELENIUM_HOST=http://127.0.0.1:4444/wd/hub make test
```

[docker-compose.yml](docker-compose.yml) brings up:

- a `mongo:latest` container on port `27017`
- a `selenium/standalone-firefox:latest` Selenium Grid on port `4444`

Selenium also exposes noVNC at `http://127.0.0.1:7900` (password `secret`)
if you want to watch the browser drive the suite.

### Running individual layers

```bash
make test                            # full suite (needs MongoDB + Firefox)
vendor/bin/phpunit tests/Builder     # unit tests only
vendor/bin/phpunit tests/Functional  # functional tests only
make coverage                        # produces build/logs/clover.xml
```

### Local without docker

If you already have MongoDB and a non-snap Firefox + `geckodriver` on
your `PATH`, leave `PANTHER_SELENIUM_HOST` unset:

```bash
make test
```

Panther will start its own Firefox process. This is the path GitHub
Actions uses — see [`.github/workflows/test.yaml`](.github/workflows/test.yaml).

### Quality gates

The CI workflow runs four gates; matching commands run locally as:

```bash
make test                                                # PHPUnit
vendor/bin/phpstan --no-progress --memory-limit=1G analyse
vendor/bin/rector --no-progress-bar --dry-run
make lint-php                                            # PHP-CS-Fixer
```

All four must be green before a change can land — see
[AGENTS.md §7](AGENTS.md) for the definition of done.

---

## Documentation

For the public API and configuration shape, the `docs/` tree in this repository
(`make docs`); upstream Sonata's documentation for the package this one forked
describes the same design under the old names:
[docs.sonata-project.org/projects/SonataDoctrineMongoDBAdminBundle](https://docs.sonata-project.org/projects/SonataDoctrineMongoDBAdminBundle).

Fork-specific material:

- [AGENTS.md](AGENTS.md) — architectural overview, where each piece lives,
  how it fits next to adminata, contribution rules.
- [UPGRADE-7.0.md](UPGRADE-7.0.md) — 6.x → 7.0: adminata's own names.
- [UPGRADE-6.0.md](UPGRADE-6.0.md) — 5.x → 6.0: the move to adminata.
- [UPGRADE-5.0.md](UPGRADE-5.0.md) — 4.x → 5.0 break list (per-class).
- [CHANGELOG.md](CHANGELOG.md) — per-release notes.

---

## Support

For bugs or feature ideas in this fork, open an issue on
[the fork's repository](https://github.com/ideaconnect/adminata-admin-mongodb-bundle/issues).

For questions about the admin bundle itself, adminata's repository is the place:
<https://github.com/ideaconnect/adminata>.
