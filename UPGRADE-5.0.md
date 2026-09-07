UPGRADE FROM 4.x to 5.0
=======================

5.0 is the first release of `idct/sonata-admin-mongodb-bundle` after the fork diverged from
`sonata-project/doctrine-mongodb-admin-bundle`. This guide lists the breaking changes a 4.x
user (or a user migrating from upstream) needs to handle.

For context behind each change, see `BEST_VERSION.md` (the 5.x findings) and `FIX.md` (the
post-release fix plan that landed in this version).

## Requirements bumped

- PHP `^8.4` is now the minimum.
- Symfony `^7.4 || ^8.0`.
- `doctrine/persistence` `^4.0`.
- `doctrine/mongodb-odm` `^2.6`.
- `doctrine/mongodb-odm-bundle` `^5.0`.
- `sonata-project/admin-bundle` `^4.39`.

Tighter than upstream and tighter than 4.x: drop any constraint on persistence 3 or ODM-bundle
4 in the consuming app.

## `Sonata\DoctrineMongoDBAdminBundle\Model\ModelManager`

- `getDocumentManager()` is now `private`. If you were calling it externally, either depend on
  `Symfony\Bridge\Doctrine\ManagerRegistry` directly or wrap the lookup in your own service.
- The class is `final readonly`.

## `Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery`

- `__call()` magic delegation to the underlying `Builder` has been removed. Call
  `$proxyQuery->getQueryBuilder()->yourMethod(...)` explicitly. Filter code in this bundle
  already used that explicit form; only external callers leaning on the magic forwarder are
  affected.
- `setSortBy()` now validates the composed field name against
  `/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/`. Field names containing `$`,
  whitespace, or other operator-like characters are rejected with `InvalidArgumentException`.
  `_id` is allowed.
- `setSortOrder()` now rejects values outside `asc`/`desc` (case-insensitive) with
  `InvalidArgumentException`.
- `setFirstResult()` / `setMaxResults()` no longer mutate the wrapped `Builder`. The
  pagination state is held on the proxy and applied to a clone inside `execute()`. Callers
  that introspected the builder *after* calling these setters used to see `->skip()/->limit()`
  recorded on it; they no longer will.

## `Sonata\DoctrineMongoDBAdminBundle\Datagrid\Pager`

- `countResults()` now throws `\LogicException` if called before `init()`. Previously it
  silently returned `0` from an uninitialized state. Call `init()` before reading the count.

## `Sonata\DoctrineMongoDBAdminBundle\Filter\ModelFilter`

- `handleScalar()` now ignores values that are not objects with a `getId()` method. Previously
  it would crash with `Error: Call to a member function getId() on null/string/...` for
  malformed payloads.
- `fixIdentifier()` now rejects empty strings, arrays, and other non-string/non-int shapes with
  `InvalidArgumentException`. Previously these were silently echoed back to the query and
  yielded "matches nothing" or "matches unrelated documents."

## `Sonata\DoctrineMongoDBAdminBundle\Filter\StringFilter`

- The `CONTAINS` and `NOT_CONTAINS` paths now `preg_quote()` the user value before
  constructing the `MongoDB\BSON\Regex`. Searching for `foo.bar` now matches the literal
  substring `foo.bar` (previously the `.` was a regex wildcard). This is a behavior change
  visible to end users: searches that incidentally relied on regex syntax in user input will
  match fewer documents.
- The filter now also honors `StringOperatorType::TYPE_STARTS_WITH`, `TYPE_ENDS_WITH`, and
  `TYPE_NOT_EQUAL` when callers configure `'operator_type' => StringOperatorType::class`. The
  default operator type is still `ContainsOperatorType` — no migration needed unless you opt in.
- New option `case_sensitive` (bool, default `false`) drops the regex `i` flag when set true.
- `isSearchEnabled()` now coerces the option to bool. Previously `global_search: 'true'`
  (string) would return a truthy non-bool value and crash on PHP's strict return type.

## `Sonata\DoctrineMongoDBAdminBundle\Filter\AbstractDateFilter` (range mode)

- Per-side type validation in `filterRange`. If `value.start` or `value.end` is not a
  `\DateTimeInterface`, that side is dropped from the query instead of being handed to the
  Mongo driver. Previously a mixed payload could crash inside the driver.

## `Sonata\DoctrineMongoDBAdminBundle\Util\ObjectAclManipulator`

- The wrapping `ModelManagerException` now carries the original exception's message and the
  admin code. If you were matching on the previous empty exception message, update your
  assertions / logs.
- The internal `\ArrayIterator` rebuild loop has been removed. No public-API change, but
  memory behavior on large collections has improved materially.

## `Sonata\DoctrineMongoDBAdminBundle\Exporter\DataSource`

- New optional constructor argument `bool $hydrate = true`. Default preserves the historical
  hydrate-everything behavior. Pass `false` for large exports where raw arrays are sufficient.

## `Sonata\DoctrineMongoDBAdminBundle\Builder\DatagridBuilder`

- The previously chained `setOption('x', getOption('x', getter()))` calls are now an explicit
  "set only when unset" loop. The observable behavior is identical to 4.x.

## New filters

- `Sonata\DoctrineMongoDBAdminBundle\Filter\EmptyFilter` is registered as
  `sonata.admin.odm.filter.type.empty`. It accepts the boolean YES/NO output from
  `Sonata\AdminBundle\Form\Type\BooleanType` and maps YES to "field is null or missing"
  (`field == null` in Mongo) and NO to the negation.

## Configuration

- The `show_chain` type-guesser service is now seeded with the show guesser instead of the
  list guesser. Compile-time behavior was unchanged (the `AddGuesserCompilerPass` overwrites
  the seed), but the service argument now matches the chain it serves. No app-side change is
  required.

## See also

- [BEST_VERSION.md](BEST_VERSION.md) — the planning doc with severity-classified findings.
- [FIX.md](FIX.md) — the post-release fix log for the 5.0 cut.
- The [CHANGELOG.md](CHANGELOG.md) `5.0.0` entry for the canonical commit-by-commit summary.
