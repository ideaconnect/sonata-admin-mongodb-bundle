# War Against the Mutants

Tickbox plan for killing the 89 escaped mutants reported by Infection on this
project. Baseline (before any work): **674 mutants, 585 killed, 89 escaped,
Covered MSI 86%**.

Run `composer infection` to regenerate the report; raw diffs live in
[build/infection/infection.log](build/infection/infection.log).

## Progress

- **Original target killed:** 74 / 89 from the original list. The other 4 are
  *equivalent mutants* (no observable behavior difference under any input).
- **Covered Code MSI: 97%** (was 86%) — target ≥97% reached.
- **Mutation Code Coverage: 100%**.
- **15 escapes remaining** in total, all in the leftovers section below.

---

## Tier 1 — Array-shape assertions (23 mutants) ✅ DONE

Add a `testGetDefaultOptions` and/or `testGetFormOptionsHasExactShape` that
`assertSame` the *full* returned array (key, value, order). Kills both
`ArrayItem` (`'key' => v` → `'key' > v`) and `ArrayItemRemoval` mutants in one
go.

- [x] `tests/Filter/StringFilterTest.php` — default + form shape *(77, 78, 79)*
- [x] `tests/Filter/BooleanFilterTest.php` — default + form shape *(49, 50)*
- [x] `tests/Filter/CallbackFilterTest.php` — form shape *(52, 53)*
- [x] `tests/Filter/ChoiceFilterTest.php` — form shape *(59, 60, 61)*
- [x] `tests/Filter/EmptyFilterTest.php` — default + form shape *(62, 63, 64)*
- [x] `tests/Filter/IdFilterTest.php` — form shape *(65, 66)*
- [x] `tests/Filter/ModelFilterTest.php` — default (kills `'mapping_type' => false`) + form shape *(69, 70, 71, 72)*
- [x] `tests/Filter/NumberFilterTest.php` — default + form shape *(75, 76)*
- [x] `tests/FieldDescription/FilterTypeGuesserTest.php` — assert `options['field_name']` is populated *(36)*

---

## Tier 2 — Match-arm / switch-case synonyms (8 mutants) ✅ DONE

### `tests/FieldDescription/FilterTypeGuesserTest.php`

- [x] Add `ClassMetadata::MANY` row via new `testGuessTypeWithToManyAssociation` *(37 — SharedCaseRemoval)*
- [x] Add `Type::BOOL` row (synonym of `BOOLEAN`) to `provideGuessTypeNoAssociationCases` *(38)*
- [x] Add `Type::DATE_IMMUTABLE` row (synonym of `DATE`) *(39)*
- [x] Add `Type::INTEGER` row (synonym of `INT`) *(40)*

### `tests/FieldDescription/TypeGuesserTest.php`

- [x] Add `Type::BOOLEAN` row (synonym of `BOOL`) *(41)*
- [x] Add `Type::INTEGER` row (synonym of `INT`) *(42)*
- [x] Add `Type::ID` row (synonym of `STRING` in this map) *(43)*

---

## Tier 3 — Boundary & defensive-coding behavior (18 mutants → 14 killed, 4 equivalent) ✅ DONE

### `tests/Datagrid/PagerTest.php`

- [x] `testInitResetsProxyPaginationToNull` rewritten to pre-seed non-null
      pagination so a removed reset becomes observable *(21, 22)*
- [ ] ~~Page-zero branch~~ — *equivalent mutant*: `BasePager::setPage(0)`
      normalizes `page` to `1` when `maxPerPage > 0`, and the `||
      maxPerPage === 0` short-circuit covers the only other reachable case.
      The `0 === getPage()` arm is dead code. *(23 — defensible to leave)*
- [x] `testInitRoundsUpPartialLastPage` — 11 docs / 10 per page → 2 pages *(24)*

### `tests/Datagrid/ProxyQueryTest.php`

- [x] `testSetSortByAccumulatesNestedParentPath` — two parent mappings ⇒
      dotted `outer.inner.field` path *(25)*

### `tests/Exporter/DataSourceTest.php`

- [x] `testCreateIteratorDoesNotMutateSharedBuilder` — uses a real ODM
      Builder and reflects the `hydrate` private property post-call *(31)*

### `tests/FieldDescription/FieldDescriptionTest.php`

- [x] `testIsIdentifierIsFalseWhenIdKeyMissing` *(32)*
- [x] `testSetAssociationMappingDoesNotOverwriteAlreadyResolvedMappingType` *(33)*
- [x] `testSetFieldMappingDoesNotOverwriteAlreadyResolvedMappingType` — uses
      reflection to drive protected setters in reverse order *(34)*
- [x] `testSetParentAssociationMappingsThrowsOnNonArrayEntry` — locks the
      foreach validation loop *(35)*

### `tests/Filter/DateFilterTest.php` & `tests/Filter/DateRangeFilterTest.php`

- [x] `testFilterRangeBranchDoesNotFallThroughForDateTimeValue` (DateRange)
      — locks the `return;` after `filterRange()` *(44)*
- [x] `testFilterWholeDayDoesNotMutateInputDateTime` — pins the input's
      timestamp post-apply, catches removed `clone $value` *(45)*
- [x] `testFilterRecordsWholeDay` extended with `expects(never())->method('equals')`
      — drops the trailing `applyType(equals)` regression *(46)*
- [x] `testFilterThrowsForUnknownOperatorType` regex now matches the int
      *keys* of the operator map, not the string operator values *(47)*
- [ ] ~~null-start-and-null-end early return~~ — *equivalent mutant*: with
      both bounds null, the trailing branches all guard on `null !== start/end`
      and produce no `applyType` calls regardless. *(48 — defensible to leave)*

### `tests/Filter/IdFilterTest.php`

- [x] `testItTrimsSurroundingWhitespaceOnValidObjectId` — pads a valid id
      with whitespace and asserts the trimmed value reaches the builder *(67)*
- [ ] ~~empty-string early return~~ — *equivalent mutant*: `'' === $value`
      short-circuits to the same final state as falling through to
      `new ObjectId('')` which throws and the catch returns. *(68 — defensible to leave)*

### `tests/Filter/ModelFilter.php`

- [ ] ~~`handleMultiple` empty-collection early return~~ — *equivalent
      mutant*: with an empty value, the loop produces no IDs and the
      downstream `[] === $ids` guard short-circuits regardless. *(73 —
      defensible to leave)*
- [x] `testHandleMultipleSkipsInvalidEntriesAndContinues` — non-object then
      valid object in the same payload; both must be processed *(74)*

### `tests/Filter/BooleanFilterTest.php`

- [x] `testFilterArraySkipsInvalidEntriesAndContinues` — invalid entry in
      the middle so both pre- and post-invalid valid entries survive *(51)*

### `tests/Filter/CallbackFilterTest.php`

- [x] `testFilterThrowsWhenCallbackReturnsNonBoolScalar` extended to assert
      the message contains the literal `"string"` token with both quotes —
      kills five `Concat` / `ConcatOperandRemoval` mutants in one assertion *(54-58)*

---

## Tier 4 — DI extension & bundle wiring (7 mutants) ✅ DONE

### `tests/SonataDoctrineMongoDBAdminBundleTest.php`

- [x] `testAddTemplatesCompilerPassRegistersAtPriorityMinusOne` — reflects
      on `PassConfig::$beforeOptimizationPasses` to pull the priority bucket *(88, 89)*

### `tests/DependencyInjection/SonataDoctrineMongoDBAdminExtensionTest.php`

- [x] `testLoadWiresCustomTemplatesIntoBuildersAndParameter` — asserts the
      `sonata_doctrine_mongodb_admin.templates` parameter is set, and the
      list/show builder definitions get the per-type templates as argument
      index `1` *(26, 27, 28, 29, 30)*

---

## Tier 5 — Builders (20 mutants) ✅ DONE

### `tests/Builder/DatagridBuilderTest.php`

- [x] `testGetBaseDatagridDisablesCsrfWhenEnabledByDefault` *(1)*
- [x] `testFixFieldDescriptionKeepsUserProvidedMappingOption` — locks the
      `&&` short-circuit in the defaults loop *(2)*
- [x] `testAddFilterNoTypeRegistersTypeAndMergesArrayOption` — covers
      `setType`, array-merge right-hand-wins, and `mergeOption('field_options',
      ['required' => false])` in one focused test *(3, 4, 6, 7, 8)*
- [x] `testAddFilterCallsFixFieldDescriptionSettingFieldNameOption` *(5)*
- [x] `testGetBaseDatagridDoesNotInjectCsrfOptionWhenDisabled` *(9)*

### `tests/Builder/ListBuilderTest.php`

- [x] `testFixFieldDescriptionAppliesAllSortDefaultsWhenUnset` *(11, 12, 13, 15, 17, 19)*
- [x] `testFixFieldDescriptionPreservesUserProvidedSortOptions` *(14, 16, 18)*
- [x] `testFixFieldDescriptionSkipsSortDefaultsWhenFieldMappingIsEmpty` *(10)*

### `tests/Builder/ShowBuilderTest.php`

- [x] `testAddFieldAppendsToList` *(20)*

---

## Tier 6 — ModelManager (8 mutants) ✅ DONE

### `tests/Model/ModelManagerTest.php`

- [x] `testCreate/Update/DeleteWrapsOdmMongoDBExceptionInModelManagerException`
      — three focused tests throw `Doctrine\ODM\MongoDB\MongoDBException` so
      the `|MongoDBException` arm of the catch union is what's actually
      caught (the existing `RuntimeException` variants are driver-side and
      could be caught by `Exception` alone) *(80, 81, 82)*
- [x] `provideFailingBatchDeleteCases` extended with an
      `odm-side MongoDBException is wrapped` case *(85)*
- [x] `testGetIdentifierFieldNamesStripsNulls` *(83)*
- [x] `testBatchDeleteClearsDocumentManagerOnEachFullBatch` — 21 docs,
      batch size 20: expects `clear()` exactly twice (loop + final) *(84)*
- [x] `provideFailingBatchDeleteCases` extended with an
      `exactly one batch, fails on first flush` case — locks the
      `$i > $batchSize` boundary *(86)*
- [x] `testReverseTransformPrefersFieldMappingsOverAssociationMappings` —
      same key in both maps, the field-mapping side wins *(87)*

---

## Leftover escapes (15)

The 4 equivalent mutants flagged inline above are defensible to leave —
each one has no input that distinguishes the mutated code from the original.

The other 11 are all in [src/Util/ObjectAclManipulator.php](src/Util/ObjectAclManipulator.php),
a file that *wasn't* in the original 89-escape list. They surfaced after Tier 1
fixed the order-dependent test isolation bug in
[tests/Util/ObjectAclManipulatorTest.php](tests/Util/ObjectAclManipulatorTest.php)
— Infection randomizes order, so before the fix it bailed out before reaching
this file. Worth a follow-up pass:

- [ ] `src/Util/ObjectAclManipulator.php:45` — `MethodCallRemoval`
- [ ] `src/Util/ObjectAclManipulator.php:70, 82` — `DecrementInteger` /
      `Modulus` on the batch-flush counter
- [ ] `src/Util/ObjectAclManipulator.php:108` — five mutants on the
      progress-report condition (`GreaterThan`, `GreaterThanNegotiation`,
      `Modulus`, `LogicalAnd`, `LogicalAndNegation`)
- [ ] `src/Util/ObjectAclManipulator.php:109` — `MethodCallRemoval` on the
      progress-line write
- [ ] `src/Util/ObjectAclManipulator.php:120` — `IncrementInteger` on the
      summary counter

These look like the same pattern that surfaced in `ModelManager::batchDelete`
(unit-counters and batch-flush logic) and should be killable with a couple of
focused tests using a buffered output. Out of scope for the original 89.

---

## Notes & gotchas

- **Infection runs tests in random order.** A pre-existing test isolation bug
  in `ObjectAclManipulatorTest::setUp()` was fixed during Tier 1 by moving DB
  cleanup into `setUp()`. Keep an eye on similar issues if more failures
  surface — Infection will refuse to run if the initial test suite is red.
- **MongoDB-dependent tests are in the `unit` testsuite too** (anything under
  `tests/Util/`). The Infection config excludes `tests/Functional/` but not
  `tests/Util/`, so a live MongoDB is needed to run the suite.
- **Some mutants are intrinsically hard to kill** — log-message wording,
  redundant defensive `null` resets after `clone`, etc. Defensible to leave
  5-10 escaped at the end.

## Useful commands

```bash
# Full project run
composer infection

# Scope to one file while drafting kills
vendor/bin/infection --filter='src/Filter/StringFilter.php' --threads=1

# Only mutate lines changed vs main branch (CI/dev)
vendor/bin/infection --git-diff-filter=AM --git-diff-base=4.x

# Re-run only the tests for a given file
vendor/bin/phpunit --testsuite=unit --filter StringFilterTest
```
