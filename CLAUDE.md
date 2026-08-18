# Liberty Package — Developer Notes

Session log — decisions, bugs found, and why things are the way they are. For how the xref
system actually works today (schema, template resolution, add/edit/expunge mechanics, known
duplication across packages), see `MANUAL.md` instead; this file doesn't repeat that, only the
history behind it.

## LibertyXref / entry_date (added 2026-08-10)
`liberty_xref.entry_date` existed in the schema but was completely dead everywhere (0 of
2369 rows populated, checked across the whole DB) until `LibertyXref::verify()`/`store()`
started stamping it (see `MANUAL.md`'s "Dates" section for current behaviour). First real use:
stock's per-assembly quantity-line grouping (see `stock/CLAUDE.md`'s "Multi-assembly movements"
section) — every BOM line `explodeFromAssembly()` inserts for one assembly-add gets the *same*
`entry_date` as that assembly's own `ASSEMBLY` xref, letting later code scope matches to just
that batch. Race condition if two batches are stamped at exactly the same instant is real but
doesn't matter in practice — the web interface can't submit two separate form actions in the
same instant.

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

## Firebird GROUP BY strictness
Firebird requires every non-aggregate column in SELECT to appear in GROUP BY — including
`lc.data`, `lc.title` etc. Correlated scalar subqueries in SELECT (e.g. `SELECT FIRST 1 ...`)
are exempt. MySQL is more lenient; Firebird is not.

## Package registration — includes/bit_setup_inc.php (found 2026-08-15, scoping the food package)
Every package needs an `includes/bit_setup_inc.php` — this is the "base" package convention:
the file that makes a directory a package bitweaver actually recognizes, not a schema file or
any central hardcoded list. Minimum required shape (see `stock/includes/bit_setup_inc.php` as
the reference example):

```php
<?php
$pRegisterHash = [
	'package_name' => 'stock',                              // lowercase package dir name
	'package_path' => dirname( dirname( __FILE__ ) ).'/',
	'homeable'     => true,
];
define( 'STOCK_PKG_NAME', $pRegisterHash['package_name'] );
define( 'STOCK_PKG_URL', BIT_ROOT_URL . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'STOCK_PKG_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'STOCK_PKG_INCLUDE_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/');
define( 'STOCK_PKG_CLASS_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/classes/');
define( 'STOCK_PKG_ADMIN_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/admin/');

$gBitSystem->registerPackage( $pRegisterHash );

if( $gBitSystem->isPackageActive( 'stock' ) ) {
	// only runs once the package is actually installed+enabled:
	// app-menu registration ($gBitSystem->registerAppMenu()), service registration
	// ($gLibertySystem->registerService()), package-specific defines, and hook functions
	// (e.g. stock's own *_expunge_user() callback for user-deletion cleanup) all go here.
}
```

The six `<PKG>_PKG_*` constants (`_PKG_NAME`, `_PKG_URL`, `_PKG_PATH`, `_PKG_INCLUDE_PATH`,
`_PKG_CLASS_PATH`, `_PKG_ADMIN_PATH`) are **not defined anywhere centrally** — each package
defines its own copy in its own `bit_setup_inc.php`, by convention, from its own directory name.
Don't go looking for a master registry file; there isn't one — `registerPackage()` is the actual
discovery mechanism, called once per package as bitweaver's bootstrap includes each package's
`bit_setup_inc.php` in turn.

**The six manual `define()` lines above are a bodge, not the real mechanism.**
`registerPackage()` is *itself* capable of defining all six constants automatically from
`$pRegisterHash` — that's the actual, intended, single-source-of-truth mechanism. The manual
defines exist purely because VS Code's static analysis can't follow that dynamic
definition-from-hash and flags every `STOCK_PKG_*` reference elsewhere in the package as an
undefined constant. So each package pre-defines them by hand (matching stock's own inline
comment: `// fix to quieten down VS Code which can't see the dynamic creation of these ...`),
and `registerPackage()` detects they're already defined and skips redefining them — the manual
block and the automatic mechanism don't conflict, the manual one just wins by going first.
Stripping the hardcoding would be the cleaner fix (one definition, not two ways to get the same
six constants) but reintroduces the VS Code false-positive undefined-constant warnings across
every file that references them — a real editor-tooling cost, not a hypothetical one. Left as-is
deliberately; **copy the manual-define pattern for any new package** (including `food`) rather
than relying on the automatic path alone.

**How to apply:** any new package needs this file before anything else — before schema, before
content classes — since nothing else about the package can be reached until `registerPackage()`
has run.

## Content owner change
`edit_content_owner_inc.tpl` provides an Owner dropdown gated on:
- Feature `liberty_allow_change_owner` active
- Permission `p_liberty_edit_content_owner`

Include inside any edit form to allow reassigning `user_id`. `LibertyContent::store()`
handles `owner_id` + `current_owner_id` → updates `lc.user_id`.

## 2026-08-17 — xref system reviewed, MANUAL.md written
Prompted by Lester wanting to step back and review the whole xref system after a run of Food work
(SUP tab correction, hard-delete question, WT/VOL mutual-exclusivity gap) kept surfacing the same
underlying mechanics. Wrote `MANUAL.md` from scratch, consolidating what was scattered across this
file's dated entries plus everything learned building Stock/Contact/Food's xref usage this week —
template resolution chains, the `add_xref.php` no-`xref`-field gap and the resulting duplication
across packages (confirmed `food`/`stock`'s `lookup_component.php` are byte-identical copies),
`expunge`'s three cases and the missing fourth (real hard-delete), the double-`store()` version
bug. This file trimmed down to session-log-only content per the same split already validated on
`mapper` (`CLAUDE.md`=history, `MANUAL.md`=current-state reference).

## 2026-08-18 — read-only xref items: `multiple < 0` convention adopted

Came out of scoping the `health` package (see `health/CLAUDE.md`/`MANUAL.md`) — device-reported
sensor/time-series data has no business being user-editable, and nothing in the xref system
currently has a concept of "this item is read-only." Decided to put it straight on
`liberty_xref_item` rather than build it package-local first (the precedent the JSON templates set
in the 2026-08-17 review) — reasoning: read-only-ness is about the generic *dispatch* path itself
(`list_xref.tpl`'s per-row Edit/Delete icons, `edit_xref.php`'s write path), which lives in
liberty, not in any package; a package-local workaround would mean duplicating that dispatch code,
exactly what the shared mechanism exists to avoid.

**Encoding**: reuse `multiple`'s sign rather than add a column — `multiple < 0` = read-only,
`abs(multiple)` recovers the existing cardinality meaning (`-1`=single, `-2`=multiple, reserved).
Checked the live codebase before adopting this (not assumed safe): `multiple`'s numeric value has
exactly one real consumer anywhere across liberty/stock/food/contact —
`LibertyXref::verify()`'s `$next > 0` xorder-auto-increment gate — and no template or PHP code
anywhere branches on `multiple` by truthiness/equality, so a negative value can't be silently
misread as `1`-like. Column is `I2` with no `CHECK` constraint, so this needed zero schema
migration. Full writeup (including what's *not* yet enforced — item-picker exclusion, server-side
write refusal, per-row icon suppression, none of which are built) is in `MANUAL.md`'s Data model
section now, not just here — this is a settled contract, not an open question.

## 2026-08-18 (same session) — group-level add dispatch, captured brainstorm, not built

Lester's own framing, worth keeping verbatim in shape: the existing group-level "Add record" link
(`list_xref.tpl`, see `MANUAL.md`'s "Add and edit" section) is one flat link to `add_xref.php`'s
generic item picker for the whole group. Idea, prompted by thinking through where the read-only
flag should actually suppress a button: turn this into a real per-group template
(`add_<group>_group.tpl`, mirroring the per-item `getXrefRecordTemplate()`/
`getXrefEditTemplate()`override convention Stock/Food/Contact already use) with three behaviours,
not one:

1. **`add_generic_group`** (the fallback, what today's flat `add_xref.php` link effectively is) —
   becomes a dropdown selector over the group's *available* item types (mirrors
   `LibertyXrefType::getAvailableItems()`, already used elsewhere for exactly this kind of
   per-group item-type enumeration) rather than a single undifferentiated form.
2. **Per-item-type dispatch to a package's own bespoke add page** for anything that's really an
   xref-link (Food's `SUP`, Stock's `#SUP`) — the button morphs into "Add Supplier" and goes
   straight to `add_supplier.php`/`add_component.php`/etc, the already-established
   bespoke-add-page pattern (`MANUAL.md`'s "Add and edit" table). **Doesn't reopen the settled
   2026-08-17 decision** not to teach `add_xref.php` itself about `xref` targets — the dispatch
   lives at the group-template layer, one level up, choosing *which* add mechanism to send the
   user to (generic vs package-specific), not trying to make the generic one handle every case
   itself.
3. **Read-only awareness** — an item (or a whole group, if every item in it is read-only) flagged
   via the `multiple < 0` convention above simply doesn't get an Add affordance offered at all,
   in either of the two paths above.

Explicitly not scoped or built this session — Lester's own words, "will probably link in with my
next nighttime brainstorm." Captured here so it isn't lost, not because it's the next thing to
build. Whenever it is picked up: `add_person.php`/`add_business.php` (Contact's page-per-role
precedent, already noted in `food/CLAUDE.md`'s framework-notes section) is worth checking again as
a second real-world precedent for "same content type, different add experience depending on what's
being added."

**Where Contact's `_fields.tpl` system fits into this — Lester's own follow-up question, answered
by re-reading [[project_xref_template_reorg]] rather than designing fresh**: `_fields.tpl`
(`contact/templates/edit_xref_*_fields.tpl`, still live, driven by `contact/add_xref.tpl`'s
per-format `{include}` loop) is Contact's own older, separate Add-flow solution to the *same*
problem the `_item.tpl` convention already solves generically for View/Edit — this was identified
2026-08-17 during the reorg, not new today. That session already named the cleaner shape and
deliberately parked it ("leave it for now, we can revisit later"): Add should dynamically render
the same `edit_<template>_item.tpl` used for editing, with a blank/default `xrefInfo` instead of a
loaded one, rather than maintaining a second `_fields.tpl` per template.

**The `add_<group>_group.tpl` idea above is exactly where that parked plan would actually land**:
once a group template dispatches per-item-type instead of `add_xref.php` showing one flat form,
`add_generic_group`'s per-item-type step (point 1 above) *is* "render `edit_<template>_item.tpl`
in new-row mode" — at that point `_fields.tpl` has nothing left to do and gets deleted outright,
not migrated forward as a parallel system. Read-only items need zero special handling here beyond
what's already built (see the read-only entry above) — an item excluded from
`getAvailableItems()` never reaches either the generic dropdown or a `_fields.tpl`/`_item.tpl`
render in the first place, so there's no third place to teach about `multiple < 0` once this
lands. Still not scoped or built — same "captured for later" status as the rest of this section,
now with its last open sub-question (what happens to `_fields.tpl`) answered rather than left
dangling.
