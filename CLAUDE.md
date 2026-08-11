# Liberty Package — Developer Notes

## LibertyXref / xorder
`liberty_xref.xorder` — used for BOM grouping and sort. Must be explicitly selected
in queries; it is not auto-included in standard SELECT lists.

## LibertyXref / entry_date (added 2026-08-10)
`liberty_xref.entry_date` existed in the schema but was completely dead everywhere (0 of
2369 rows populated, checked across the whole DB) until `LibertyXref::verify()`/`store()`
started stamping it: set once at insert time (`$this->mDb->NOW()`), left untouched on
update, unless the caller explicitly passes `'entry_date'` in `$pParamHash` to override the
default — e.g. to stamp a whole batch of related xrefs (inserted across several `store()`
calls) with one shared value for later grouping. `LibertyXrefType::loadContent()`'s SELECT
now includes `x.entry_date` so it's available on loaded xref rows without an extra query.
First real use: stock's per-assembly quantity-line grouping (see `stock/CLAUDE.md`'s
"Multi-assembly movements" section) — every BOM line `explodeFromAssembly()` inserts for one
assembly-add gets the *same* `entry_date` as that assembly's own `ASSEMBLY` xref, letting
later code scope matches to just that batch. Race condition if two batches are stamped at
exactly the same instant is real but doesn't matter in practice — the web interface can't
submit two separate form actions in the same instant.

## isValid() — deliberately not a real existence check (2026-08-10)
`LibertyContent::isValid()` only checks `verifyId($this->mContentId)` — it does **not**
verify that `load()` actually found a matching row, despite what its own docblock claims
("establish if the object has been loaded with a valid record"). This means a
syntactically-valid-but-nonexistent content_id reads as "valid" through the base class.

**A stricter fix was tried and reverted the same day.** The obvious correct version —
`return verifyId($this->mContentId) && $this->mDb->getOne("SELECT 1 FROM liberty_content
WHERE content_id=? AND content_type_guid=?", ...)` — works fine for view/edit pages, but
`LibertyContent::storePreference()` calls `LibertyContent::isValid()` **explicitly**,
bypassing every subclass override (including `RoleUser`'s own, which uses `mUserId` not
`mContentId`). `RoleUser::mContentId` stays `null` until `load()` succeeds, and
`storePreference()` is exercised by `users/register.php` during account creation — tightening
the base method risked turning a currently-working preference save into a silent no-op
mid-registration. A full codebase audit (all `LibertyContent` subclasses, every `isValid()`
override, every explicit-base-class call site) confirmed this specific risk and found most
real content types (wiki, blogs, fisheye, newsletters, nexus, users) already override
`isValid()` with their own non-content-id key anyway, so a base fix would have bought little.

**How to apply**: if a view/edit page needs to correctly 404 on a bad content_id, add the
same query-based override to *that content type's own class* (see `StockMovement`/
`StockAssembly`/`StockComponent` in `stock/includes/classes/`, and `Contact` in
`contact/includes/classes/Contact.php` — all four use an identical pattern). Do not touch
`LibertyContent::isValid()` itself without re-running that audit first.

**Side effect found 2026-08-11**: giving these four classes a real, DB-querying `isValid()` is
what first exposed a pre-existing, unrelated kernel bug — `LibertyContent::getCacheKey()` calls
`isValid()` to decide the cache key, and `BitBase::__destruct()` was unsetting `$this->mDb`
*before* calling `storeInCache()` (which reaches `getCacheKey()`). Before this fix, `isValid()`
never needed `mDb`, so the ordering never mattered; afterward, any of these four objects being
destroyed with `BIT_CACHE_OBJECTS` active crashed with "Call to a member function getOne() on
null" — live on srv10 since this fix's own deploy window (4032+ occurrences via `Contact` alone).
Fixed in `kernel/includes/classes/BitBase.php` (kernel repo commit `893a876`) — the destructor no
longer pre-emptively unsets `mDb`, since `__sleep()` already does that at the correct point.
Nothing about this `isValid()` fix itself was wrong; the kernel bug was just latent until this
gave it a trigger. See `project_apcu_object_cache_stale_assets` memory for the full chain.

## LibertyXrefType — instance class
`LibertyXrefType` is an **instance class**, not a bag of statics. Construct with
`new LibertyXrefType( $contentTypeGuid, $packageGuid = null )`. In page/class code,
always access it via `LibertyContent::xrefType()` which lazily creates and caches the
instance. The five runtime query methods (`getDisplayGroups`, `getTypeMarkers`,
`getAvailableItems`, `getTemplateFormats`, `getContentTypeMarkers`) are instance methods.
Admin cross-type queries (`getXrefTypeList`, `getContentTypeGuids`, `getGroupList`)
remain static.

## Dual-guid xref schema (package-level + content-type-level)
A package with multiple content types can define xref groups/items at two levels:
- **Package-level** — groups shared across all content types in the package, keyed by the package guid
- **Content-type-level** — groups specific to one content type, keyed by the content type guid

**Stock is the reference implementation:**
- Package-level (`'stock'`): `stgrp`, `supplier`, `kitlocker` — apply to both assemblies and components
- Content-type-level (`'stockcomponent'`): `quantity`, `values`; (`'stockassembly'`): `quantity`

To support this, pass `$packageGuid` when constructing `LibertyXrefType` or `LibertyXrefInfo`
(both accept it as an optional second argument). The `mPackageGuid` property on `LibertyContent`
is set automatically by `registerContentType()` when `handler_package` differs from the content
type guid — so subclasses get it for free.

When writing xref JOIN queries that span both levels, always join item↔group on
`t.content_type_guid = s.content_type_guid` (self-consistent); apply the guid `IN()` filter
only in the WHERE clause on `s`. Putting the filter in the JOIN ON instead causes
cross-matching when two guids share an `x_group` name.

## LibertyXrefGroup display path

**PHP pattern** — display and edit pages:
```php
$gContent->loadXrefInfo();
$gBitSmarty->assign( 'gXrefInfo', $gContent->mXrefInfo );
```

**Template pattern** — view and edit templates:
```smarty
{foreach $gXrefInfo->mGroups as $xrefGroup}
    {include file=$gContent->getXrefListTemplate($xrefGroup->mTemplate)
        xrefGroup=$xrefGroup allow_edit=false}   {* true for edit pages *}
{/foreach}
```

Group templates receive `$xrefGroup` (LibertyXrefGroup object). First two lines must be:
```smarty
{assign var=xrefAllowEdit value=$allow_edit|default:false}
{assign var=isHistory value=($xrefGroup->mXGroup eq 'history')}
```

Fallback for groups with no specific template → `liberty/list_xref.tpl`.

View pages pass `allow_edit=false` (or omit), edit pages pass `allow_edit=true`.

**Linked content fields (`linked_title` / `linked_data`)** — `LibertyXrefType::loadContent()`
LEFT JOINs `liberty_content lc_linked ON lc_linked.content_id = x.xref` and exposes
`lc_linked.title AS linked_title` and `lc_linked.data AS linked_data` on every xref row.
These come from the **linked content item's** `liberty_content` row (via the `x.xref` FK),
NOT from the xref row's own `xkey`/`xkey_ext`/`data` columns (which are already available
as `$xrefInfo.xkey`, `$xrefInfo.xkey_ext`, `$xrefInfo.data` without any join).
When `x.xref > 0` these fields hold the title and description of the linked item (contact,
component, assembly, etc.). `liberty_content` has no `xkey_ext` equivalent — if further
fields from the linked item are needed, add them to the SELECT in `loadContent()` as
additional `lc_linked.*` aliases, or use a correlated subquery for linked xref data.

- **View templates**: use `$xrefInfo.linked_title` and `$xrefInfo.linked_data` directly — no
  separate enrichment query needed.
- **Edit templates** (`edit_xref.php` path): `enrichXrefDisplay()` is called on the single row
  before display. Override this in the content class (e.g. `StockBase::enrichXrefDisplay()`)
  to set `xref_title` for the edit form. The two paths use different field names by design.
- **Extra fields** (e.g. `part_size` from a second xref): override `loadXrefInfo()` in the
  content class, call `parent::loadXrefInfo()` first, then enrich the group rows. Use
  `array_map( fn($r) => $r['xref'], $group->mXrefs )` — NOT `array_column()` — to extract
  xref values from `LibertyXref` objects (ArrayAccess; `array_column` ignores offsetGet on
  some PHP builds).

## Firebird GROUP BY strictness
Firebird requires every non-aggregate column in SELECT to appear in GROUP BY — including
`lc.data`, `lc.title` etc. Correlated scalar subqueries in SELECT (e.g. `SELECT FIRST 1 ...`)
are exempt. MySQL is more lenient; Firebird is not.

## parseDataHash
`LibertyContent::parseDataHash( &$pParamHash )` takes its argument **by reference** — always
assign to a named variable before calling, never pass a literal array.
```php
$parseHash = [ 'data' => $row['data'], 'format_guid' => $row['format_guid'] ?? 'bithtml' ];
$row['parsed_data'] = LibertyContent::parseDataHash( $parseHash );
```

## storeXref
`storeXref()` takes `&$pParamHash` by reference — always assign hash to a named variable
before calling. Passing a literal array is a fatal error.

## Content owner change
`edit_content_owner_inc.tpl` provides an Owner dropdown gated on:
- Feature `liberty_allow_change_owner` active
- Permission `p_liberty_edit_content_owner`

Include inside any edit form to allow reassigning `user_id`. `LibertyContent::store()`
handles `owner_id` + `current_owner_id` → updates `lc.user_id`.
