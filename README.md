# idct/sonata-admin-mongodb-bundle

Doctrine MongoDB ODM persistence backend for **Sonata Admin** — full CRUD,
filtering, sorting, pagination, exports and ACL management for MongoDB
documents, the same way `sonata-project/doctrine-orm-admin-bundle` provides
it for relational databases.

[![Latest Stable Version](https://img.shields.io/packagist/v/idct/sonata-admin-mongodb-bundle.svg?label=stable)](https://packagist.org/packages/idct/sonata-admin-mongodb-bundle)
[![License](https://img.shields.io/packagist/l/idct/sonata-admin-mongodb-bundle.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://www.php.net/supported-versions.php)
[![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0-000000?logo=symfony&logoColor=white)](https://symfony.com/releases)
[![Doctrine MongoDB ODM](https://img.shields.io/badge/Doctrine%20MongoDB%20ODM-%5E2.6-orange)](https://www.doctrine-project.org/projects/mongodb-odm.html)
[![Sonata Admin](https://img.shields.io/badge/Sonata%20Admin-%5E4.39-blue)](https://docs.sonata-project.org/projects/admin-bundle)

[![codecov](https://codecov.io/gh/ideaconnect/sonata-admin-mongodb-bundle/branch/5.x/graph/badge.svg?token=yUdY2iB1AV)](https://codecov.io/gh/ideaconnect/sonata-admin-mongodb-bundle)
[![Test](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/test.yaml/badge.svg)](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/test.yaml)
[![Quality assurance](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/qa.yaml/badge.svg)](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/qa.yaml)
[![Lint](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/lint.yaml/badge.svg)](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/lint.yaml)
[![Symfony Lint](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/symfony-lint.yaml/badge.svg)](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/symfony-lint.yaml)
[![Documentation](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/documentation.yaml/badge.svg)](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/documentation.yaml)

---

## 🚨 This is a HARD FORK

`idct/sonata-admin-mongodb-bundle` is a **hard fork** of
[`sonata-project/doctrine-mongodb-admin-bundle`](https://github.com/sonata-project/SonataDoctrineMongoDBAdminBundle),
not a soft fork or a temporary patch:

- The vendor name and Composer package id are different
  (`idct/...` vs. `sonata-project/...`) — the two **cannot** be installed
  side by side and `composer replace` is **not** declared.
- The `5.x` line already breaks BC in places upstream has not:
  `ModelManager::getDocumentManager()` is private, `ProxyQuery::__call()`
  is gone, `ProxyQuery::setOptions()` is removed, `ModelFilter::fixIdentifier()`
  rejects malformed input, `Pager::countResults()` throws when uninitialized,
  and more. See [UPGRADE-5.0.md](UPGRADE-5.0.md) for the full break list.
- Future releases **will keep diverging** — extending the public API,
  replacing parts that aren't worth keeping, dropping things upstream still
  ships. Upstream changes are pulled in selectively, not merged.
- We do **not** sync release numbers with upstream. Our `5.0.0` is the
  fork's first release; the upstream lineage we forked from is 4.12.0.

**If you need exact upstream behaviour**, stay on
`sonata-project/doctrine-mongodb-admin-bundle`. **If you want a modernised
base on PHP 8.4+ / Symfony 7.4+ and don't mind moving with us**, this is
the right place.

The MIT license, Thomas Rabaix's original copyright, and every upstream
contributor's attribution are preserved — see [LICENSE](LICENSE) and the
74-entry author roster in [composer.json](composer.json).

---

## What this bundle does

Installs next to `sonata-project/admin-bundle` and provides every
persistence-layer concern Sonata Admin needs in order to drive an admin UI
against a MongoDB collection:

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

If Sonata Admin can do it for SQL via the ORM bundle, this bundle is the
piece that lets you do the same for MongoDB.

---

## Requirements

| | Floor | Tested up to |
|---|---|---|
| PHP | 8.4 | 8.5 |
| Symfony | 7.4 | 8.0 |
| Sonata Admin Bundle | 4.39 | latest 4.x |
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

You should already have a Symfony 7.4+ application with **Sonata Admin
Bundle** and **Doctrine MongoDB ODM Bundle** installed — this bundle is
the glue between them, not a replacement for either.

```bash
composer require sonata-project/admin-bundle doctrine/mongodb-odm-bundle
```

### Install

```bash
composer require idct/sonata-admin-mongodb-bundle
```

Symfony Flex registers the bundle automatically. If you're not using
Flex, add it to `config/bundles.php` manually:

```php
return [
    // ...
    Sonata\DoctrineMongoDBAdminBundle\SonataDoctrineMongoDBAdminBundle::class => ['all' => true],
];
```

### Declare an admin

Tag any Sonata Admin service with `manager_type: doctrine_mongodb` and
Sonata will resolve it through this bundle's implementations:

```php
// config/services.php (Symfony 7+ PHP config)
$services->set(App\Admin\BookAdmin::class)
    ->tag('sonata.admin', [
        'manager_type' => 'doctrine_mongodb',
        'model_class'  => App\Document\Book::class,
        'label'        => 'Book',
    ]);
```

### Optional bundle config

The bundle ships sane defaults; the only config tree it owns is per-type
template overrides for list and show columns:

```yaml
# config/packages/sonata_doctrine_mongo_db_admin.yaml
sonata_doctrine_mongo_db_admin:
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

For the public API and configuration shape, upstream Sonata's documentation
applies as-is:
[docs.sonata-project.org/projects/SonataDoctrineMongoDBAdminBundle](https://docs.sonata-project.org/projects/SonataDoctrineMongoDBAdminBundle).

Fork-specific material:

- [AGENTS.md](AGENTS.md) — architectural overview, where each piece lives,
  how it fits next to Sonata Admin, contribution rules.
- [UPGRADE-5.0.md](UPGRADE-5.0.md) — 4.x → 5.0 break list (per-class).
- [CHANGELOG.md](CHANGELOG.md) — per-release notes.

---

## Support

For bugs or feature ideas in this fork, open an issue on
[the fork's repository](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/issues).

For general Sonata Admin questions, the upstream
[StackOverflow tag](https://stackoverflow.com/questions/tagged/sonata)
remains the best place.

---

## License

[MIT](LICENSE). Thomas Rabaix's original copyright and every upstream
contributor's attribution are preserved; the full author roster lives in
[composer.json](composer.json).
