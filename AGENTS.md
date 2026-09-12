# AGENTS.md

Project-specific instructions for any AI coding agent (or human contributor)
operating on `idct/sonata-admin-mongodb-bundle`. Read this end-to-end before
touching code; after reading it you should be able to navigate, understand,
and extend the bundle without any further onboarding.

---

## 1. What this library is

`idct/sonata-admin-mongodb-bundle` is the **MongoDB persistence backend for
Sonata Admin**. It lets a Symfony application build admin UIs (CRUD listings,
filters, search, exports, ACL management) over Doctrine MongoDB ODM
documents — the same way the canonical `sonata-project/doctrine-orm-admin-bundle`
does for relational SQL via Doctrine ORM.

Concretely, when a user installs this bundle alongside
`sonata-project/admin-bundle` and declares an admin with
`manager_type: doctrine_mongodb`, Sonata wires that admin to *our*
implementations of its abstract interfaces (`ModelManager`, `Pager`,
`DatagridBuilder`, etc.) so the resulting admin reads, writes, paginates,
sorts and filters MongoDB documents instead of relational rows.

We do **not** reimplement Sonata Admin. We implement the contracts Sonata
Admin defines so the same UI/UX/templates work over MongoDB.

---

## 2. Relationship to Sonata Admin

Sonata Admin is an umbrella framework — `sonata-project/admin-bundle`
provides the UI templates, routes, controllers, form glue, filter/datagrid
abstraction, and a per-persistence-layer extension API. There are three
official persistence backends:

- `sonata-project/doctrine-orm-admin-bundle` — relational / Doctrine ORM
- `sonata-project/doctrine-mongodb-admin-bundle` — MongoDB / Doctrine ODM
  *(upstream — this fork's ancestor)*
- `sonata-project/doctrine-phpcr-admin-bundle` — content repositories

Each backend implements roughly the same set of interfaces against its own
persistence stack:

| Sonata Admin contract | Our implementation |
|---|---|
| `IDCT\Adminata\Model\ModelManagerInterface` | `IDCT\Adminata\DoctrineMongoDB\Model\ModelManager` |
| `IDCT\Adminata\Datagrid\PagerInterface` | `IDCT\Adminata\DoctrineMongoDB\Datagrid\Pager` |
| `IDCT\Adminata\Datagrid\ProxyQueryInterface` | `IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery` (and our extended sub-interface of the same name) |
| `IDCT\Adminata\FieldDescription\FieldDescriptionFactoryInterface` | `IDCT\Adminata\DoctrineMongoDB\FieldDescription\FieldDescriptionFactory` |
| `IDCT\Adminata\FieldDescription\TypeGuesserInterface` | `IDCT\Adminata\DoctrineMongoDB\FieldDescription\TypeGuesser` (list/show) + `FilterTypeGuesser` (datagrid filters) |
| `IDCT\Adminata\Builder\{Datagrid,List,Show,Form}BuilderInterface` | `IDCT\Adminata\DoctrineMongoDB\Builder\{Datagrid,List,Show}Builder` + `FormContractor` |
| `IDCT\Adminata\Filter\Filter` (base class) | `IDCT\Adminata\DoctrineMongoDB\Filter\Filter` (intermediate base) and concrete filters |
| `IDCT\Adminata\Util\ObjectAclManipulator` | `IDCT\Adminata\DoctrineMongoDB\Util\ObjectAclManipulator` |
| `IDCT\Adminata\Exporter\DataSourceInterface` | `IDCT\Adminata\DoctrineMongoDB\Exporter\DataSource` |

Every concrete service id starts with `adminata.admin.*` (Sonata's namespace,
because Sonata's compiler passes look them up by id) and ends in
`doctrine_mongodb` (the discriminator that pairs them with admins declared
`manager_type: doctrine_mongodb`). See
[`src/Resources/config/doctrine_mongodb.php`](src/Resources/config/doctrine_mongodb.php),
[`src/Resources/config/doctrine_mongodb_filter_types.php`](src/Resources/config/doctrine_mongodb_filter_types.php),
and [`src/Resources/config/security.php`](src/Resources/config/security.php).

**This bundle must always remain installable alongside the latest
`sonata-project/admin-bundle`.** Changes that would force users to fork
or pin Sonata Admin are off-limits unless explicitly opted into.

---

## 3. Relationship to upstream

This package started life as
[`sonata-project/doctrine-mongodb-admin-bundle`](https://github.com/sonata-project/SonataDoctrineMongoDBAdminBundle).
The fork was created at upstream's 4.12.0 and renamed to
`idct/sonata-admin-mongodb-bundle` on the `5.x` branch.

We **intentionally diverge** from upstream:

- Backwards compatibility with upstream is **not** a goal. A 4.x consumer
  cannot drop in 5.0; see [UPGRADE-5.0.md](UPGRADE-5.0.md) for the
  observable breaks.
- Backwards compatibility *within the 5.x line* **is** a goal. Don't break
  public API mid-5.x; queue BC breaks for 6.x and add them to a future
  `UPGRADE-6.0.md`.
- We pull useful changes from upstream selectively, not via merge. Track
  divergence in [BEST_VERSION.md](BEST_VERSION.md) and
  [FIX.md](FIX.md) — those documents are the source of truth for *why*
  the fork looks like it does.

---

## 4. Current state (5.x line)

| Aspect | Floor |
|---|---|
| PHP | `^8.4` |
| Symfony | `^7.4 \|\| ^8.0` (full bundle: config, dependency-injection, doctrine-bridge, form, http-kernel, property-access; test-only: browser-kit, css-selector, dom-crawler, twig-bridge, panther) |
| Doctrine ODM | `^2.6` |
| Doctrine ODM Bundle | `^5.0` |
| Doctrine Persistence | `^4.0` (3.x dropped on the 5.x cut) |
| Doctrine Collections | `^2.0` |
| Sonata Admin Bundle | `^4.39` |
| PHPUnit | 11 / 12 (suite written for 12's strictness) |

Quality gates currently green:

- **PHPUnit**: 299 tests, ~860 assertions, ~97% line coverage / ~90% method coverage.
- **PHPStan** level 8 + bleeding edge + strict rules.
- **Rector** with `UP_TO_PHP_84` + `PHPUNIT_120` + `PHPUNIT_CODE_QUALITY`.
- **PHP CS Fixer** with `@PHP8x4Migration`, `@PHPUnit9x1Migration:risky`, `@Symfony` + `:risky`, `@PSR12` + `:risky`.

Tagged release: `5.0.0` (annotated tag on `5.x`). See
[CHANGELOG.md](CHANGELOG.md) for what changed since upstream's 4.12.0.

---

## 5. Architectural concepts

### 5.1 ModelManager — the persistence facade

[`src/Model/ModelManager.php`](src/Model/ModelManager.php) implements every
CRUD path Sonata Admin calls: `create`, `update`, `delete`, `find`,
`findBy`, `findOneBy`, `batchDelete`, `getIdentifierFieldNames`,
`getNormalizedIdentifier`, `addIdentifiersToQuery`, `executeQuery`,
`getExportFields`, `reverseTransform`, `getRealClass`. It is the only
class that holds a `Symfony\Bridge\Doctrine\ManagerRegistry` and resolves
the right `DocumentManager` per document class. Anywhere else in the bundle
that needs a `DocumentManager`, expect either an injected `ManagerRegistry`
(ObjectAclManipulator, FieldDescriptionFactory) or already-resolved
metadata threaded through.

Notable invariants:

- `getDocumentManager()` is **private** (was public + deprecated in 4.x;
  privatised on 5.0). Don't reopen it.
- `batchDelete` flushes every `BATCH_SIZE = 20` documents and then calls
  `DocumentManager::clear()`. The clear is deliberate — without it,
  multi-100k batch deletes leak the UnitOfWork. The trade-off (other
  managed entities in scope also get cleared) is documented inline.
- `reverseTransform` uses Symfony's `PropertyAccessor`; it goes through
  the field mapping's `fieldName` so admins can use Mongo-side property
  names that differ from PHP property names.

### 5.2 ProxyQuery — the QueryBuilder wrapper

[`src/Datagrid/ProxyQuery.php`](src/Datagrid/ProxyQuery.php) wraps a
`Doctrine\ODM\MongoDB\Query\Builder` behind Sonata's `ProxyQueryInterface`
(we extend that interface in
[`src/Datagrid/ProxyQueryInterface.php`](src/Datagrid/ProxyQueryInterface.php)
to expose `getQueryBuilder()`). Key design choices:

- **Setters don't mutate the wrapped builder.** `setSortBy`, `setSortOrder`,
  `setFirstResult`, `setMaxResults` store proxy state; `execute()` clones
  the builder and applies pagination + sort to the *clone*. This is the
  B1 fix from BEST_VERSION.md — the caller's `QueryBuilder` reference
  stays untouched.
- **`setSortBy` validates the composed field path** against a strict regex
  (`SORT_FIELD_PATTERN`) that allows leading underscore (so `_id` works)
  but rejects `$where`, dots-followed-by-dollar, etc. This is the B4
  defence-in-depth against operator smuggling.
- **`setSortOrder` is case-normalised** to lower-case `'asc'` / `'desc'`;
  anything else throws.
- `__clone` deep-clones the wrapped builder so two ProxyQuery clones don't
  share state. The property isn't `readonly` for compatibility with
  PHPStan's bleedingEdge readonly-in-clone rule.

### 5.3 Pager — count + slice

[`src/Datagrid/Pager.php`](src/Datagrid/Pager.php) extends Sonata's
abstract `BasePager`. Two responsibilities:

- `init()` computes total count via a clone of the underlying Builder
  (`->count()->getQuery()->execute()`), stores it on `$this->resultsCount`,
  resets the proxy's pagination state to `null`, then sets first/max
  result for the requested page.
- `countResults()` exposes the stored count or throws `LogicException`
  if `init()` hasn't run — the H6 fix that disambiguates "no rows" from
  "count not computed".

`__clone` resets `$resultsCount = null` so cloning an initialized pager
doesn't carry a stale count.

### 5.4 Builders — DatagridBuilder, ListBuilder, ShowBuilder, FormContractor

[`src/Builder/`](src/Builder/). Sonata invokes these when it materialises
an admin's UI:

- **`DatagridBuilder`** builds the filter form + paginator for the list
  page. Iterates `FieldDescription`s, calls `FilterTypeGuesser` when no
  filter type is provided, instantiates the right `Filter` subclass via
  the `FilterFactory`, and attaches them all to a Sonata `Datagrid`.
- **`ListBuilder`** turns `FieldDescription`s into Sonata's list-table
  columns: sets default templates per type, special-cases `_action` /
  `actions` columns, fills in sort defaults when applicable.
- **`ShowBuilder`** is the show-page equivalent — much simpler, just
  template defaulting and label fallback.
- **`FormContractor`** is a no-op subclass of upstream
  `AbstractFormContractor`. It exists as a stable DI service id +
  extension point; future Mongo-specific form behaviour goes here.

All four are `final readonly class` with constructor-promoted dependencies.

### 5.5 Field descriptions

[`src/FieldDescription/`](src/FieldDescription/).

- **`FieldDescription`** extends Sonata's `BaseFieldDescription`. Adds
  Mongo-specific accessors: `getTargetModel()` reads
  `associationMapping['targetDocument']`, `isIdentifier()` reads
  `fieldMapping['id']`, `describesSingleValued/CollectionValuedAssociation()`
  consult `ClassMetadata::ONE` / `MANY`.
- **`FieldDescriptionFactory`** creates `FieldDescription` instances. Its
  important job is resolving dotted paths (`author.publisher.name`)
  against ODM `ClassMetadata` chains; the helper
  `getParentMetadataForProperty` walks each segment, validates it's an
  association on the current class, and produces the parent-association
  mapping list the field description needs. Invalid segments throw
  `InvalidArgumentException` with the bad segment named.

### 5.6 Type guessers

[`src/FieldDescription/TypeGuesser.php`](src/FieldDescription/TypeGuesser.php)
and
[`src/FieldDescription/FilterTypeGuesser.php`](src/FieldDescription/FilterTypeGuesser.php).
They translate ODM mapping types into Sonata column/filter types.
Both match against `Doctrine\ODM\MongoDB\Types\Type::*` constants and
handle both short (`BOOL`, `INT`) and long (`BOOLEAN`, `INTEGER`) alias
forms because ODM 2.x ships both — see the explanatory comment in each
file.

Tagged services:

- `adminata.admin.guesser.doctrine_mongodb_list`
- `adminata.admin.guesser.doctrine_mongodb_show`
- `adminata.admin.guesser.doctrine_mongodb_datagrid`

The `AddGuesserCompilerPass` (`src/DependencyInjection/Compiler/`)
collects each tag and feeds them into the right `TypeGuesserChain`. Add
more guessers by tagging them with the same id; no other change required.

### 5.7 Filters

[`src/Filter/`](src/Filter/). One class per filter type, each extending
our intermediate
[`src/Filter/Filter.php`](src/Filter/Filter.php) (which adds the
`ProxyQueryInterface` type guard around Sonata's abstract `apply()`).

Current set:

| Filter | Operator type from upstream | What it does |
|---|---|---|
| `BooleanFilter` | `BooleanType` | true / false / array of either |
| `CallbackFilter` | n/a — user-supplied callable | escape hatch |
| `ChoiceFilter` | `EqualOperatorType` | `in` / `notIn` over a value list, `equals` / `notEqual` over a scalar |
| `DateFilter` (range / time variants) | `DateOperatorType`, `DateRangeOperatorType` | `gte`, `lte`, etc. against `\DateTimeInterface` |
| `EmptyFilter` *(new in 5.0)* | `BooleanType` (YES = empty/missing, NO = has value) | uses Mongo's null-equality which matches both `null` and absent fields |
| `IdFilter` | `EqualOperatorType` | strict ObjectId equality |
| `ModelFilter` | `EqualOperatorType` | match by document reference (`field._id`, `field.id`, `field.$id` depending on `storeAs`) |
| `NumberFilter` | `NumberOperatorType` | float / int comparisons |
| `StringFilter` | `ContainsOperatorType` + `StringOperatorType` | equals, contains (regex), not contains, starts-with, ends-with, with optional `case_sensitive` |

When adding a filter:

1. Subclass `IDCT\Adminata\DoctrineMongoDB\Filter\Filter` (not
   Sonata's base — ours adds the type guard).
2. Implement `getDefaultOptions()` and `getFormOptions()` and the
   protected `filter()` method.
3. Register it as a `adminata.admin.filter.type`-tagged service in
   [`src/Resources/config/doctrine_mongodb_filter_types.php`](src/Resources/config/doctrine_mongodb_filter_types.php).
4. Add a corresponding case to `FilterTypeGuesser::guess()` if it should
   be auto-selected for some ODM mapping type.
5. Cover it in `tests/Filter/<Name>FilterTest.php`. The shared
   `FilterWithQueryBuilderTestCase` mocks an ODM Builder + Expr stub for
   you.

Filter input always arrives as `IDCT\Adminata\Filter\Model\FilterData`;
always check `$data->hasValue()` before reading and guard against malformed
shapes (non-scalar, wrong type) — silent skip is preferred over throwing
in `filter()` since users hit these via the admin UI.

### 5.8 DataSource — exports

[`src/Exporter/DataSource.php`](src/Exporter/DataSource.php) — implements
`IDCT\Adminata\Exporter\DataSourceInterface`. Hands a Panther-friendly
`\Iterator` (via `DoctrineODMQuerySourceIterator`) to Sonata's exporter
for CSV/XML/JSON export of the current datagrid query.

Constructor flag `bool $hydrate = true` (5.0+) toggles ODM hydration off
for raw-array streaming — materially faster on wide collections, but
field accessors on the FieldDescription side won't resolve (only mapped
field names). Defaults to `true` to preserve historical behaviour.

### 5.9 ObjectAclManipulator

[`src/Util/ObjectAclManipulator.php`](src/Util/ObjectAclManipulator.php)
extends upstream Sonata's `BaseObjectAclManipulator` to bulk-configure
Symfony Security ACLs for every document of a given admin class.
Iterates the collection with the ODM cursor, detaches each row right
after collecting its `ObjectIdentity`, batches the ACE creation in
groups of `BATCH_SIZE = 20`, and emits progress every
`PROGRESS_REPORT_INTERVAL = 200` documents (plus a trailing line for
the partial-batch tail).

### 5.10 DI plumbing

- [`src/AdminataDoctrineMongoDBBundle.php`](src/AdminataDoctrineMongoDBBundle.php)
  registers the two compiler passes.
- [`src/DependencyInjection/AdminataDoctrineMongoDBExtension.php`](src/DependencyInjection/AdminataDoctrineMongoDBExtension.php)
  loads the three service config files (`doctrine_mongodb.php`,
  `doctrine_mongodb_filter_types.php`, `security.php`) and threads the
  user-configured template overrides into the list/show builders.
- [`src/DependencyInjection/Compiler/AddGuesserCompilerPass.php`](src/DependencyInjection/Compiler/AddGuesserCompilerPass.php)
  collects tagged type guessers into the chain.
- [`src/DependencyInjection/Compiler/AddTemplatesCompilerPass.php`](src/DependencyInjection/Compiler/AddTemplatesCompilerPass.php)
  attaches the bundle's form / filter Twig themes to every Mongo-managed
  Admin service.
- [`src/DependencyInjection/Configuration.php`](src/DependencyInjection/Configuration.php)
  declares the `adminata_doctrine_mongodb` config tree (just the
  per-type template overrides).

---

## 6. Testing concepts

Three layers, in increasing realism:

1. **Pure unit tests** — `tests/{Builder,Filter,Datagrid,Model,FieldDescription,Util}/`.
   Mock Sonata interfaces, exercise one of our classes in isolation. Fast,
   no external services. Most filters / builders / guessers live here.
2. **Component / integration tests** — same directories, but the test
   builds a real in-memory `DocumentManager` (no network), persists
   fixtures, and exercises full flows. Examples: `PagerTest`,
   `ProxyQueryTest`, parts of `ModelManagerTest`,
   `ObjectAclManipulatorTest`. These do require a running MongoDB at
   `mongodb://localhost:27017` because ODM resolves the metadata lazily.
3. **Functional tests** — `tests/Functional/`. Boot the test kernel
   (`tests/App/AppKernel.php`) and drive a real Sonata admin via Symfony
   Panther (headless Firefox). Cover end-to-end flows like "create a
   document via the form", "filter the list", "follow a reference".

The functional tests need both a MongoDB server and a Firefox WebDriver.
Locally we use Selenium docker for both (see [docker-compose.yml](docker-compose.yml));
in CI we rely on the runner's preinstalled Firefox + geckodriver. The
switch between the two paths lives in
[`tests/Functional/BasePantherTestCase.php`](tests/Functional/BasePantherTestCase.php):
when `PANTHER_SELENIUM_HOST` is set, we route through Selenium; otherwise
local Firefox.

Test bootstrap loads fixtures via
`doctrine:mongodb:fixtures:load` (see
[`tests/custom_bootstrap.php`](tests/custom_bootstrap.php)).

---

## 7. Definition of done

A task is **not** finished until **all four** of these gates pass:

1. **PHPUnit** — `PANTHER_SELENIUM_HOST=http://127.0.0.1:4444/wd/hub make test`
   (Selenium docker) or `make test` (host Firefox). Every test must pass;
   new behaviour needs a regression test that locks the new behaviour
   from the public API surface, not internal state.
2. **PHPStan** — `vendor/bin/phpstan --no-progress --memory-limit=1G analyse`.
   Must report `[OK] No errors`. Do not silence findings with
   `@phpstan-ignore`, baseline entries, `assert()`, inline `@var`, or type
   casts — fix the underlying cause.
3. **Rector** — `vendor/bin/rector --no-progress-bar --dry-run`. Must
   report `[OK] Rector is done!`. If Rector suggests a change, apply it
   with `vendor/bin/rector --no-progress-bar` (no `--dry-run`); only add
   it to `rector.php`'s skip list when there's a documented reason
   (e.g. `ReadOnlyPropertyRector` collides with our `__clone` pattern).
4. **PHP CS Fixer** — `make lint-php`. Must exit 0 with no pending
   changes. Apply with `make cs-fix-php` if it has suggestions.

Running just one of these and declaring the work done is not enough.
Changes can pass PHPStan and still break PHPUnit (and vice versa), and
CS-Fixer / Rector regularly surface migration-set or ordering issues none
of the other tools catch.

---

## 8. Working on the codebase

- The default branch is `5.x`. There is no living `4.x` branch here; that
  was the upstream branch we forked from, see Section 3.
- See [BEST_VERSION.md](BEST_VERSION.md) for the review-and-roadmap doc
  and [FIX.md](FIX.md) for the post-5.0 punch list (with `R#`-prefixed
  IDs for fresh findings layered on top of `F#` from the original
  review). Most items there ship; deferred items name their reason.
- Use existing `F#` / `R#` IDs in commit messages and CHANGELOG entries
  when fixing items the review identified, so changes trace back to the
  finding that motivated them.
- Functional / UI changes need to go through the functional tests in
  `tests/Functional/`. If you can't run them locally (e.g. no Firefox),
  say so explicitly in the PR description; don't silently skip them.
- New filter / builder / guesser classes are wired in
  `src/Resources/config/*.php`. Forgetting the service tag is the
  most common "everything compiles but admin pages 500" symptom.

---

## 9. Style and conventions

- `declare(strict_types=1);` at the top of every PHP file.
- New classes default to `final`. Stateless services should be
  `final readonly class`. The exception is anything with a `__clone`
  that needs to reassign properties (see `ProxyQuery`).
- PHP 8.4+ features are encouraged where they improve clarity: typed
  class constants, `#[\Override]`, asymmetric visibility, property hooks.
- Header comment on every PHP file (the existing Sonata Project header
  is the canonical form; CS-Fixer enforces it).
- No `@phpstan-ignore-*` comments. No `assert($x instanceof Y)` to
  override the analyser. If you need to narrow a type for PHPUnit, use a
  real `static::assertInstanceOf(...)` — it both narrows the type *and*
  verifies the invariant at runtime, which is what the test should be
  doing anyway.
- Don't generate documentation files unless asked.
- Don't break BC within the 5.x line. Queue BC breaks for 6.x.
