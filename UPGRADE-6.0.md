UPGRADE FROM 5.x to 6.0
=======================

6.0 changes exactly one thing, and it is a big one: this bundle is built against
[`idct/adminata`](https://github.com/ideaconnect/adminata) instead of
`sonata-project/admin-bundle`.

Adminata is our hard fork of the Sonata Admin stack with the Twig templates, the CSS and the
JavaScript replaced by a Tailwind CSS v4 / TailAdmin interface. **Bootstrap, AdminLTE and jQuery
are gone, and nothing is aliased or shimmed to soften that.** If your project styles admin screens
with Bootstrap class names, or scripts them with jQuery, that markup stops working and has to be
ported once. Adminata's own `UPGRADE-1.0.md` and `MIGRATION.md` are the guides for that half; this
one covers only what changes in *this* bundle.

Staying on Sonata Admin 4.x is a supported answer: the `5.x` line is the last series built against
`sonata-project/admin-bundle` and it keeps working.

## What does not change

Nothing about this bundle's own API:

- the `Sonata\DoctrineMongoDBAdminBundle\` namespace,
- the `SonataDoctrineMongoDBAdminBundle` bundle class,
- the `sonata_doctrine_mongo_db_admin` configuration root and every option under it,
- every service id, every filter class, every template path,
- the `manager_type: doctrine_mongodb` tag your admins are declared with.

An application that already uses this bundle changes its `composer.json` and its imports, and
nothing else here.

## Requirements

- `sonata-project/admin-bundle` is replaced by `idct/adminata` at `^1.0@dev`.
- `sonata-project/exporter` and `sonata-project/form-extensions` are **gone** as direct
  requirements: adminata merged both into its admin bundle, and `conflict`s with them.

Adminata was not on Packagist when 6.0 was released, which is what the `@dev` marker says; the
`6.x` line installed it from a `vcs` repository for `https://github.com/ideaconnect/adminata.git`.
It is there now, as `dev-main` — but that branch carries adminata's own names, which the `6.x`
line does not speak: an application staying on `6.x` pins adminata at `dev-main#d76c4818f`, the
last commit under the Sonata names. Moving on to 7.0 ([UPGRADE-7.0.md](UPGRADE-7.0.md)) is the
supported path.

## `config/bundles.php`

Three lines come out. Adminata's `SonataAdminBundle` registers the block, form and Twig stacks
itself, so the bundles that used to carry them no longer exist:

```diff
-    Sonata\BlockBundle\SonataBlockBundle::class => ['all' => true],
-    Sonata\Form\Bridge\Symfony\SonataFormBundle::class => ['all' => true],
-    Sonata\Twig\Bridge\Symfony\SonataTwigBundle::class => ['all' => true],
     Sonata\AdminBundle\SonataAdminBundle::class => ['all' => true],
     Sonata\DoctrineMongoDBAdminBundle\SonataDoctrineMongoDBAdminBundle::class => ['all' => true],
```

Two more come out if you had them: `Sonata\Exporter\Bridge\Symfony\SonataExporterBundle` and
`Sonata\Doctrine\Bridge\Symfony\SonataDoctrineBundle`, merged the same way.

The `sonata_block`, `sonata_form`, `sonata_twig` and `sonata_exporter` configuration roots are
**unchanged** — adminata registers their extensions — so `config/packages/sonata_*.yaml` needs no
edit at all.

## Imports

The merged packages' classes moved into `Sonata\AdminBundle\`. In this bundle's own sources that is
done; in yours, rewrite:

| Was | Is |
|---|---|
| `Sonata\Form\Type\` | `Sonata\AdminBundle\Form\Type\` |
| `Sonata\Exporter\` | `Sonata\AdminBundle\Exporter\` |
| `Sonata\BlockBundle\` | `Sonata\AdminBundle\` |
| `Sonata\Twig\` | `Sonata\AdminBundle\Twig\` |
| `Sonata\Doctrine\` | `Sonata\AdminBundle\Doctrine\` |

**One of these is not a pure rename.** Both stacks shipped a `CollectionType`, so adminata renamed
*its own* — the one behind `sonata_type_native_collection` — to `NativeCollectionType`, and gave
the plain name to the form stack's, which is what `sonata_type_collection` resolves to. A
`use Sonata\AdminBundle\Form\Type\CollectionType;` therefore still compiles after the move and
renders the *other* widget. Check every `CollectionType` you import.

## Templates

Any template of yours that overrode a Sonata one, or that extends the admin layout, is written
against markup that no longer exists. Adminata's `PLAN/03` records which templates were rewritten
and which are still inherited; its `porting-status` documentation page is the list to read before
you start.

## Tests

If you have browser tests that click through the admin, expect the same thing this repository
found: the scenarios that address Bootstrap class names fail. Ours are marked `#[Group('legacy-ui')]`
and excluded by default until adminata's milestones M3 and M4 rewrite those templates.
