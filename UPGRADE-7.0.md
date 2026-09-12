# Upgrading to 7.0

7.0 renames everything in this package that carried the Sonata name, together with adminata's own
rename, and renames the package itself. The complete guide — Composer, `bundles.php`,
configuration, PHP, Twig, markup, the tool that makes the edits for you, and the one edit that is
a data migration — is
[adminata's UPGRADE.md](https://github.com/ideaconnect/adminata/blob/main/UPGRADE.md). The rows
that are this package's:

| Before | After |
|---|---|
| `idct/sonata-admin-mongodb-bundle` `^6.0` | `idct/adminata-admin-mongodb-bundle` `^7.0` — `composer remove` the old name, `composer require` the new one; a `vcs` repository for `https://github.com/ideaconnect/adminata-admin-mongodb-bundle.git` until it is on Packagist |
| `Sonata\DoctrineMongoDBAdminBundle\` | `IDCT\Adminata\DoctrineMongoDB\` |
| `Sonata\DoctrineMongoDBAdminBundle\SonataDoctrineMongoDBAdminBundle` in `bundles.php` | `IDCT\Adminata\DoctrineMongoDB\AdminataDoctrineMongoDBBundle` |
| `config/packages/sonata_doctrine_mongo_db_admin.yaml`, root `sonata_doctrine_mongo_db_admin:` | `adminata_doctrine_mongodb.yaml`, `adminata_doctrine_mongodb:` |
| parameter `sonata_doctrine_mongodb_admin.templates` | `adminata_doctrine_mongodb.templates` |
| `@SonataDoctrineMongoDBAdmin/…` | `@AdminataDoctrineMongoDB/…` |
| `sonata.admin.manager.doctrine_mongodb`, `sonata.admin.doctrine_mongodb.filter.type.*`, every other `sonata.admin.*` id of this package | `adminata.admin.…` |
| `Sonata\DoctrineMongoDBAdminBundle\Filter\StringFilter` and every other class | the same class under the new namespace |
| the form themes' `sonata_type_*` blocks | `adminata_type_*` |

Your own admin services keep their ids unless you decide otherwise: the `ROLE_*` names the
security handlers derive from an admin's code are stored in your users' roles
(UPGRADE.md §6.3).

```console
$ vendor/bin/adminata-rename --app --dry-run .   # what changes, and which names are yours
$ vendor/bin/adminata-rename --app .
```
