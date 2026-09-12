# BEST_VERSION.md — Production-grade modernization plan

This document captures findings from a full read-through of `src/` (≈2,555 LoC, 30 files), classifies them by severity, and proposes a phased path to a production-grade `5.x` release. It complements — not replaces — the work already merged on `4.x`.

## What's already been done on `4.x`

- Dropped Symfony 6 in favor of `^7.3 || ^8.0`.
- Added doctrine/persistence `^4.0` (kept `^3.2` for compat).
- Bumped `doctrine/data-fixtures` to `^1.8 || ^2.0` so persistence 4 can actually resolve.
- Removed dead `IsGranted` `class_exists` fallback and the `config_symfony_v5.yaml` file (unreachable under the new floors).
- Killed all 85 PHPUnit "no-expectations on mock" notices by switching the right doubles to `createStub()` and pulling shared mocks out of `setUp()`.
- Added a dockerized test stack: `mongo` service + `selenium/standalone-firefox` ([docker-compose.yml](docker-compose.yml)), with `BasePantherTestCase` switching to the Selenium grid when `PANTHER_SELENIUM_HOST` is set.
- Applied `readonly` to constructor-promoted dependencies on all `final` service classes that have no `__clone` rewiring:
  [ModelManager](src/Model/ModelManager.php), [DatagridBuilder](src/Builder/DatagridBuilder.php), [ListBuilder](src/Builder/ListBuilder.php), [ShowBuilder](src/Builder/ShowBuilder.php), [FieldDescriptionFactory](src/FieldDescription/FieldDescriptionFactory.php), [ObjectAclManipulator](src/Util/ObjectAclManipulator.php).
- Replaced docblock-typed `protected $range` / `protected $time` in `AbstractDateFilter` + its concrete subclasses with native `protected bool` properties.

Net result on `4.x`: same 173 tests, zero notices, zero PHPUnit deprecations; only the two pre-existing PHP-8.5 deprecations remain — both inside vendored `sonata-project/admin-bundle`, not us.

---

## Severity legend

- **🔴 Bug / correctness** — wrong behavior under realistic input, or latent BC trap.
- **🟠 Hack / smell** — works today, but fragile, hard to test, blocks static analysis, or rots.
- **🟡 Performance** — measurable on production-sized data.
- **🟢 Modernization** — cleaner, faster, or safer with newer language features. No correctness issue.

---

## Findings

### 🔴 B1. `ProxyQuery` mutates the shared `QueryBuilder` through every setter

[src/Datagrid/ProxyQuery.php:112-133](src/Datagrid/ProxyQuery.php#L112-L133)

```php
public function setFirstResult(?int $firstResult): BaseProxyQueryInterface
{
    $this->firstResult = $firstResult;
    $this->queryBuilder->skip($firstResult ?? 0);   // <-- mutates the shared builder
    ...
}
public function setMaxResults(?int $maxResults): BaseProxyQueryInterface
{
    $this->maxResults = $maxResults;
    $this->queryBuilder->limit($maxResults ?? 0);   // <-- mutates the shared builder
    ...
}
```

`execute()` (line 60) then clones the builder *after* it has already been mutated, so the limit/skip leak into the original. `Pager::init()` exploits this — it calls `setFirstResult/setMaxResults` to drive the pager — but anything outside Sonata's own happy path (e.g. exporters, custom pre-processing) sees a query that silently inherits earlier pagination. The intent (per the inline comment on line 62: *"always clone the original queryBuilder"*) was clearly to keep the builder pristine; the setters violate it.

**Fix**: store `firstResult`/`maxResults` only on `ProxyQuery` itself; apply them inside `execute()` on the local clone, like `sort` already is. This also makes `ProxyQuery` truly clone-safe.

### 🔴 B2. `ModelFilter::handleScalar` calls `getId()` on potentially non-object value

[src/Filter/ModelFilter.php:99-110](src/Filter/ModelFilter.php#L99-L110)

```php
protected function handleScalar(ProxyQueryInterface $query, string $field, FilterData $data): void
{
    $id = self::fixIdentifier($data->getValue()->getId());
    ...
}
```

`$data->getValue()` is `mixed`. `handleScalar` is reached when the value is *not* an array, but it can still be `null`, a primitive, or anything else. A user submitting a malformed filter payload trips a fatal `Error: Call to a member function getId() on null/string/...`.

**Fix**: guard with `!\is_object($value) || !method_exists($value, 'getId')` → return; or — better — express the contract as a typed interface and ignore values that don't implement it.

### 🔴 B3. `ObjectAclManipulator::batchConfigureAcls` re-builds `\ArrayIterator` on every row

[src/Util/ObjectAclManipulator.php:56-75](src/Util/ObjectAclManipulator.php#L56-L75)

```php
$objectIds = [];
$objectIdIterator = new \ArrayIterator();
foreach ($qb->getQuery()->getIterator() as $row) {
    $objectIds[] = ObjectIdentity::fromDomainObject($row);
    $objectIdIterator = new \ArrayIterator($objectIds);   // <-- every row
    ...
}
```

Allocating a new iterator on every document — quadratic memory pressure when the source collection is large, and the iterator is overwritten without ever being consumed (`configureAcls` only runs every `$batchSize` iterations). On a 1M-document collection this is a million dead `\ArrayIterator` instances.

**Fix**: build the iterator *once* per batch, right before `configureAcls()` is called, from the accumulated `$objectIds` slice. Also `$batchSize = 20` is a hard-coded magic number; expose it as a constructor argument or constant.

### 🔴 B4. `ProxyQuery::execute()` carries an unfinished injection comment

[src/Datagrid/ProxyQuery.php:65](src/Datagrid/ProxyQuery.php#L65)

```php
// todo : check how doctrine behave, potential SQL injection here ...
$sortBy = $this->getSortBy();
if (null !== $sortBy) {
    $queryBuilder->sort($sortBy, $this->getSortOrder() ?? 'asc');
}
```

In practice `sortBy` is composed from `setSortBy(parentAssociationMappings, fieldMapping)` — Sonata feeds it from admin-configured field mappings, not raw request data. So the immediate risk is low. **But the TODO has been sitting here for years**: it's either a real concern or it isn't. Either:

- Add a unit test asserting that no field name containing `$` / `.` / unexpected operators reaches MongoDB without escaping, and remove the comment, or
- Tighten `setSortBy` to validate `$fieldMapping['fieldName']` against `/^[A-Za-z_][A-Za-z0-9_.]*$/`.

Don't keep the doubt in the public source.

### 🟠 H1. `ProxyQuery::__call` magic delegation

[src/Datagrid/ProxyQuery.php:50-53](src/Datagrid/ProxyQuery.php#L50-L53)

```php
public function __call(string $name, array $args)
{
    return $this->queryBuilder->$name(...$args);
}
```

Used by filters (`$query->getQueryBuilder()->field(...)` is the explicit form they actually use, so `__call` may be largely dead code). It blocks static analysis (PHPStan can't see what methods the proxy exposes), invites accidental coupling, and silently breaks if `Builder` changes its API.

**Fix**: grep usages — if `__call` is unused outside the proxy itself, drop it. If it is used, replace with explicit forwarders for the small known set or expose the builder directly (`getQueryBuilder()`) and remove the magic.

### 🟠 H2. `ModelManager::getDocumentManager` still has the `NEXT_MAJOR: Change visibility to private` note

[src/Model/ModelManager.php:127](src/Model/ModelManager.php#L127)

```php
/**
 * NEXT_MAJOR: Change visibility to private.
 * ...
 */
public function getDocumentManager($class): DocumentManager
```

Its argument is even untyped (`$class` instead of `string|object`). A `5.x` release is the moment to do both — make it `private` and type the parameter.

### 🟠 H3. `ProxyQuery::setMaxResults(null)` ↔ `limit(0)` mapping is documented but type-misleading

[src/Datagrid/ProxyQuery.php:125-130](src/Datagrid/ProxyQuery.php#L125-L130)

`setMaxResults(null)` is "no limit" in Sonata's contract, but it stores `null` in `$maxResults` while pushing `0` into the builder, which MongoDB happens to also interpret as "no limit". The two states drift the moment anyone reads `getMaxResults()` and uses the value arithmetically. Folded into the **B1** fix, this disappears: store `null`, apply `null → don't call ->limit()` inside `execute()`.

### 🟠 H4. `ModelFilter::fixIdentifier` swallows `InvalidArgumentException`

[src/Filter/ModelFilter.php:115-122](src/Filter/ModelFilter.php#L115-L122)

```php
protected static function fixIdentifier(mixed $id): string|ObjectId
{
    try {
        return new ObjectId($id);
    } catch (InvalidArgumentException) {
        return $id;
    }
}
```

The fallback returns the original `$id` regardless of type — including `null` or arrays — and the caller then passes that to `->equals()/->in()` which produces a Mongo query that silently matches nothing or, worse, matches by coincidence. The catch should narrow what's accepted; everything else should be filtered out earlier (B2 covers part of this).

### 🟠 H5. `StringFilter::isSearchEnabled()` returns `getOption('global_search')` typed as `mixed`

[src/Filter/StringFilter.php:47-50](src/Filter/StringFilter.php#L47-L50)

Returns whatever the option holds. If a user sets `global_search: 'true'` (string) in YAML, this returns truthy but with the wrong type — and PHP's strict return type catches it and crashes at runtime. Easy fix: `(bool) $this->getOption('global_search')` or `return true === $this->getOption('global_search');`.

### 🟠 H6. `Pager::countResults()` silently returns 0 when `init()` wasn't called

[src/Datagrid/Pager.php:28-33](src/Datagrid/Pager.php#L28-L33)

Default `$resultsCount = 0` means "count is 0" and "count never computed" look identical. `init()` already throws on unset query — `countResults()` should mirror that, or `$resultsCount` should be `?int = null` with a throw on read.

### 🟠 H7. `ProxyQuery::setOptions` is a write-only setter

[src/Datagrid/ProxyQuery.php:142-146](src/Datagrid/ProxyQuery.php#L142-L146)

Set in some callsites (none in this repo's tests), consumed once inside `execute()`. No getter, no docblock for what shape `$options` should have, no validation. Either remove it (if unused) or convert to a typed value object.

### 🟠 H8. `DatagridBuilder::fixFieldDescription` chains `setOption('x', getOption('x', getter()))`

[src/Builder/DatagridBuilder.php:44-58](src/Builder/DatagridBuilder.php#L44-L58)

The pattern `setOption('field_mapping', $fd->getOption('field_mapping', $fd->getFieldMapping()))` is "set X to its current value, defaulting to a derived value." It's a no-op when the option was already set — the `setOption` is only meaningful in the unset branch. Replace with:

```php
if (null === $fieldDescription->getOption('field_mapping')) {
    $fieldDescription->setOption('field_mapping', $fieldDescription->getFieldMapping());
}
```

Same shape repeats four times in that method. The cleanup is purely readability but it pays off the next time someone needs to reason about precedence.

### 🟠 H9. `tests/App/AppKernel::getBaseDir` uses `sys_get_temp_dir()` directly

[tests/App/AppKernel.php:95-98](tests/App/AppKernel.php#L95-L98)

```php
return sys_get_temp_dir().'/adminata-doctrine-mongodb-admin-bundle/var/';
```

Predictable shared path — parallel CI workers (e.g. ParaTest) trample each other's caches. Include the PID or test token, or move the directory to `bin/.cache/{env}`.

### 🟡 P1. `Pager::computeResultsCount` clones the entire `ProxyQuery` (and the underlying `Builder`) on every count

[src/Datagrid/Pager.php:76-85](src/Datagrid/Pager.php#L76-L85)

Cloning the proxy and the query builder is correct for isolation but expensive on large pipelines (think `lookup`/`unwind`). MongoDB exposes `countDocuments()` directly on the collection with the same filter — significantly cheaper when the query has no aggregation stages that affect cardinality. Most admin grids fall in this category.

**Fix**: when the builder has no aggregation stages, route count through `Collection::countDocuments($filter)`; otherwise fall back to the current clone+count.

### 🟡 P2. `ModelManager::batchDelete` clears the entire `DocumentManager` per batch

[src/Model/ModelManager.php:249, 254](src/Model/ModelManager.php#L249)

`clear()` flushes *all* tracked entities, not just the deleted batch. Fine in a CLI command, but problematic if `batchDelete` is invoked inside a request that loaded unrelated documents. ODM 2.x supports scoped clears via `clear($className)`.

### 🟡 P3. `ProxyQuery::execute()` always clones, even for one-shot queries

[src/Datagrid/ProxyQuery.php:60-75](src/Datagrid/ProxyQuery.php#L60-L75)

If B1 is fixed (mutation pulled out of setters), the clone is the only thing guaranteeing isolation. That's correct. Keep it. Mentioned here only so the trade-off is on the record after the refactor.

### 🟢 M1. Apply `readonly` to remaining promoted properties

Done where safe; `ProxyQuery::$queryBuilder` is intentionally **not** readonly because `__clone` rewires it. PHP 8.3 added support for reassigning readonly props inside `__clone`, so bumping the minimum to `^8.3` unlocks marking that property readonly too. Decision deferred — see "5.x readiness" below.

### 🟢 M2. Convert `ProxyQuery::__clone` to a deep-clone helper

Same target as M1. Today's `__clone` works because the property isn't readonly. If we bump to PHP 8.3, we can wire it through the new clone-readonly mechanism cleanly:

```php
public function __clone(): void
{
    $this->queryBuilder = clone $this->queryBuilder;   // requires PHP 8.3 if readonly
}
```

### 🟢 M3. Replace constants with `enum` where the constant set is closed

`Pager::TYPE_DEFAULT` / `TYPE_SIMPLE` are imported in [DatagridBuilder::getPager](src/Builder/DatagridBuilder.php#L129-L143) and dispatched in a `switch`. A native enum would let `getPager`'s parameter type-narrow the input and make the `default => throw` unreachable for the compiler.

Same shape in `AbstractDateFilter::getOperator` and `NumberFilter::CHOICES` — both are `int → string` maps. PHP 8.1 enums with `int` backing and a `->toMongoOp(): string` method express the intent better than a constant array. Cost: BC break on the type signature.

### 🟢 M4. PHP 8.4 features (deferred)

- **Property hooks** would simplify `Pager::countResults` (lazy/throwing accessor) and `ProxyQuery`'s `getSort*` (read-through getters).
- **Asymmetric visibility** (`public(set) private`) is a cleaner expression of "this is set by Sonata internals, read by everyone" — e.g. `setSortBy` on `ProxyQuery`.

Requires `php: ^8.4` minimum. Not justified for `4.x`; possibly for `5.x`.

### 🟢 M5. Drop the dev comment in [doctrine_mongodb.php](src/Resources/config/doctrine_mongodb.php)

The "drop support for Symfony 4.4" / "drop support for Symfony 5.1" hints at lines 27-28 are dead — both have been dropped for several major versions.

### 🟢 M6. `doctrine/collections` constraint is unusually wide

`^1.6 || ^2.0` — collection 1.x reached end-of-life. The only `use` is `Collection` as a type hint in `ModelFilter`. Tightening to `^2.0` is a one-line change.

### 🟢 M7. `doctrine/mongodb-odm-bundle` still allows v4

`^4.4 || ^5.0`. Bundle v4 hasn't been the recommended version since 2023. For `5.x`, narrow to `^5.0`.

---

## Test gaps (worth filling on `4.x`)

- No test exercises `ProxyQuery::__call`. If we drop it (H1), we want a regression test first; if we keep it, we want one anyway.
- No test for `ProxyQuery` reuse across multiple `execute()` calls — the contract B1 exposes.
- `ModelFilter::handleScalar` has no coverage for non-object scalar values — exactly the bug in B2.
- `Pager::countResults()` has no "called-before-init" test — H6.
- `ObjectAclManipulator::batchConfigureAcls` has one happy-path test ([tests/Util/ObjectAclManipulatorTest.php:55](tests/Util/ObjectAclManipulatorTest.php#L55)); the batching boundaries (exactly `batchSize`, `batchSize + 1`, `batchSizeOutput`) and the trailing-partial-batch branch (line 88) are uncovered, which is the area B3 touches.

---

## Proposed roadmap

### `4.x.next` (this branch, no BC break)

Pure-fix work. No public signatures change.

1. **B1** — pull builder mutation out of `setFirstResult`/`setMaxResults`, apply in `execute()`.
2. **B2** — type-guard `ModelFilter::handleScalar`.
3. **B3** — fix the `\ArrayIterator` rebuild loop in `ObjectAclManipulator`.
4. **B4** — resolve the SQL-injection TODO (test + tighten or remove).
5. **H5, H6, H9** — small correctness/test-isolation fixes.
6. **M5** — drop the stale comment.
7. Fill the test gaps above (especially the B1 reuse case).

### `5.x` (next major — opt-in BC break)

Anything here is a public-API change, kept out of `4.x.next` deliberately.

1. **H2** — make `ModelManager::getDocumentManager` private, type its argument.
2. **H1** — remove `ProxyQuery::__call` magic; provide explicit accessors or rely on `getQueryBuilder()`.
3. **H3, H7** — clean up `ProxyQuery`'s public surface. Strongly consider making it immutable (with-style: `withSortBy()` returns a new instance).
4. **M3** — enum dispatch for `Pager` types and date operators.
5. **M6, M7** — tighten composer constraints (`doctrine/collections: ^2`, `doctrine/mongodb-odm-bundle: ^5`).
6. PHP minimum bump to `^8.3` → unlocks **M1/M2** for `ProxyQuery::$queryBuilder`, plus readonly classes outright on the stateless services.
7. **P1** — switch counts to `countDocuments()` when the pipeline is filter-only.
8. **P2** — scope `DocumentManager::clear()` to the class being batch-deleted.

### `6.x` (when Symfony 7.3 reaches EOL)

PHP `^8.4` minimum, **M4** (property hooks + asymmetric visibility) becomes viable. Likely also a chance to revisit the `BaseProxyQueryInterface` integration in upstream Sonata Admin — the current `setMaxResults` returning the interface (`BaseProxyQueryInterface`) instead of `self` is a long-standing typing wart inherited from upstream.

---

## Out-of-scope but worth noting

- **Translations**: the bundle has translation files in `src/Resources/translations/` (not reviewed in depth here). A separate pass should check for stale message keys and missing locales.
- **Twig templates** under `src/Resources/views/` similarly skipped — they're a separate review.
- **PHPStan baseline**: [phpstan-baseline.neon](phpstan-baseline.neon) is short, which is good. If any of the changes above re-introduce baseline entries, fix them instead of growing the baseline.
- **Coverage**: not currently tracked beyond Codecov uploads. If we're serious about "production grade," set a floor (say 85%) and fail the PR build below it.

---

## Summary

`4.x` after the modernization in this branch is healthy: tests green on PHP 8.5, Symfony 7+/8, doctrine/persistence 3 & 4, no PHPUnit warnings. The next big wins are not language features — they're the three correctness bugs in `ProxyQuery` (B1), `ModelFilter` (B2), and `ObjectAclManipulator` (B3), all addressable without a major bump. The architectural cleanup (immutable `ProxyQuery`, dead `__call`, private `getDocumentManager`) is what `5.x` should be for.
