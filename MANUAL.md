# Liberty Package — Reference Manual

How the xref system actually works today. For the history of *why* — decisions, bugs found,
wrong turns — see `CLAUDE.md`'s dated session log instead; this file only tracks current
behaviour.

This manual covers `liberty_xref`/`liberty_xref_group`/`liberty_xref_item` — the generic
attribute/relationship system every content-heavy package (Stock, Contact, Food, and others)
builds its own custom data onto rather than adding schema columns. The rest of Liberty (content
versioning/history, caching, permissions) isn't covered here.

## Access boundaries — where each layer lives

Never write raw SQL against any of the three tables below from outside
`liberty/includes/classes/` — every access goes through one of these:

- **Structure** (`liberty_xref_group`, `liberty_xref_item`) — only `LibertyXrefType` queries these.
  Other code calls the current content object's cached instance via
  `LibertyContent::xrefType()->method()`; almost never construct `LibertyXrefType` directly (see
  below).
- **Live data, single row** (`liberty_xref`) — load/verify/store/stepXref for one row lives in
  `LibertyXref` only.
- **Live data, bulk-loaded for a content item** — `LibertyXrefType::loadContent()` builds the
  in-memory `LibertyXrefContent` (one `LibertyXrefGroup` per tab, each holding `LibertyXref` rows),
  cached on the content object as `$this->mXrefInfo` by `loadXrefInfo()`. Once loaded, read via
  `findByItem()`/`allItems()` or a `foreach` over `mGroups` — never re-query `liberty_xref` for
  data already loaded this request. **`LibertyXrefContent` is the read-side cache, not a write
  path** — easy to guess wrong from the name alone.
- **`LibertyContent`'s own xref methods** (`xrefType()`, `loadXrefInfo()`, `loadXref()`,
  `storeXref()`, `stepXref()`, `enrichXrefDisplay()`, `getXrefListTemplate()`/
  `getXrefRecordTemplate()`/`getXrefEditTemplate()`) are the sanctioned facade every other
  package's content class calls through — thin delegation to `LibertyXrefType`/`LibertyXref` only,
  no table SQL of their own. The one exception is `expunge()`'s own
  `DELETE FROM liberty_xref WHERE content_id = ?`, alongside its sibling `liberty_content_*`
  deletes in the same method — a content-lifecycle cascade, not a query against the xref system,
  and correctly stays as raw SQL.

Other packages (Stock, Contact, Food, Health, Mapper, ...) must never contain raw SQL against
`liberty_xref`/`liberty_xref_group`/`liberty_xref_item` — go through the content object's
`xrefType()`/`mXrefInfo`/`storeXref()`/`stepXref()` instead. Confirmed 2026-08-30: none currently
do — this section exists to keep it that way and to give prose/comments elsewhere in other
packages a correct term (`Xref` / `$gContent->mXrefInfo`) to use instead of naming the raw table.

## Data model

Three tables:

- **`liberty_xref_group`** — a tab. `x_group` (short code) + `content_type_guid` (which content
  type this tab belongs to) + `title` (tab label) + `sort_order` (tab position; `0` is a special
  case, see "Type-marker convention" below) + `template` (optional — see "Group templates" below).
- **`liberty_xref_item`** — a field definition within a group. `item` (short code, e.g. `SUP`,
  `WT`, `CAL`) + `x_group` + `content_type_guid` + `cross_ref_title` (field label) + `multiple`
  (`0` = at most one row per content_id, `1` = many; **negative = read-only**, see below) +
  `template` (which item template to render
  — see "Item templates" below) + `cross_ref_href` (link prefix, used by some templates) + `data`
  (rarely used — a default/hint, not the row's actual value).
- **`liberty_xref`** — the actual data, one row per (content_id, item) pair (or several if
  `multiple=1`). Columns: `xref_id` (PK), `content_id` (whose xref this is), `item`, `xkey`
  (`C(32)` — short values only), `xkey_ext` (`C(250)` — longer text: UUIDs, prices, URLs), `data`
  (CLOB — free text/notes), `xref` (FK to *another* `liberty_content.content_id` — this is what
  makes an xref a real link to another piece of content, not just a text field), `xorder`
  (position within a `multiple=1` group), `entry_date`/`last_update_date`/`start_date`/`end_date`
  (see below).

**Confirmed gap, not built (found 2026-08-18): no display-order column exists for items within a
group.** `liberty_xref_group` has its own `sort_order` (tab position), but `liberty_xref_item` has
nothing equivalent — `LibertyXrefType::loadContent()`'s row query is `ORDER BY x.item, x.xorder`,
i.e. **alphabetically by item code**, not any deliberately curated sequence. `xorder` (on
`liberty_xref`, the data table) is a different axis entirely — it orders the several *rows* of one
`multiple=1` item type against each other, not the several *item types* within a group against
each other. Symptom that surfaced this: Food's Nutrition tab renders `5AD`/`CAL`/`FAT`/`FIBR`/
`PROT`/`SOD`/`SUGR` in that alphabetical order, not the UK front-of-pack sequence
(`FoodComponent::NUTRITION_SUMMARY_FIELDS`' own PHP-array order gets this right in the day-report/
component-summary views built the same day, precisely because those bypass this query and build
their own ordered array instead — the generic xref tab has no equivalent). Proposed fix: a
`sort_order`-style column on `liberty_xref_item`, used in this query in place of (or ahead of)
`x.item`. **Not started** — unlike the `multiple` sign trick, this needs a genuine new column on a
table every installed package already has live rows in (liberty itself, stock, food, contact,
across every domain), so it's a real schema migration with an upgrade script, not a hand-push —
bigger blast radius than anything else done this session, flagged for confirmation before starting
rather than just built.

**Negative `multiple` values, added 2026-08-18**: `multiple` is a plain `I2` (signed smallint, no
`CHECK` constraint), so negative values need no schema change, and (confirmed before adopting) the
numeric value has exactly one real runtime consumer anywhere in liberty/stock/food/contact —
`LibertyXref::verify()`'s xorder-auto-increment gate — and no template or PHP anywhere branches on
`multiple` by truthiness, so negative values can't be misread as `1`-like by any existing code
path. Two distinct meanings, not a sign+magnitude cardinality scheme — each value below is a flat,
separate flag:

- **`-1` = read-only.** Added for Health package data (device-reported sensor/time-series
  readings — see `health/MANUAL.md`), where editing genuinely doesn't make sense, unlike every
  existing consumer (Stock/Contact/Food), whose xref data is all human-curated.
- **`-2` = mutually exclusive within the same `x_group`.** Added same day for
  [[project_food_package_scoping]]'s long-standing WT/VOL/SGL gap (`foodcomponent`'s `quantity`
  group has three different "which unit does this component use" items that should be
  one-at-a-time, but nothing enforced it). Scoped to the *items actually flagged `-2`*, not the
  whole group — `PCK`/`REM` stay ordinary `0`/`1` items in the same group unaffected, so this
  needed no group split (see "Considered and deferred" below for why a split was the first idea
  and got dropped).

**Enforced as of 2026-08-18, no add-template redesign needed for either flag**:
- `LibertyXrefType::getAvailableItems()` (the query behind `add_xref.php`'s item-type dropdown,
  and every other "what items can this content type have" caller) excludes only `multiple = -1` —
  a `-2` item is a completely normal, still-addable choice, it just has a side effect on store.
- `LibertyXref::verify()` rejects a write only for `multiple = -1` (both the `fAddXref` case and a
  plain in-place edit) — `-2` items are freely addable/editable. Doesn't touch `fStepXref`
  (archive/restore/hard-delete via `stepXref()`) for either flag — expunge/history semantics
  weren't asked for and are a separate decision, not assumed here.
- `edit_xref.php` refuses to even render the edit form for a `multiple = -1` row (redirects back
  to `getEditUrl()`), not just reject the save.
- `LibertyXref::store()` — the `-2` mechanism's actual point: after a successful store of an item
  whose own `multiple = -2`, hard-deletes any *other* item in the same `x_group` (respecting the
  usual dual-guid scoping) that's also flagged `-2`, for that `content_id`. Runs inside the same
  transaction as the store itself (atomic — a failed eviction rolls back the store too), and on
  every store of a `-2` item (add or in-place edit), not just `fAddXref`, so it's self-healing
  against any stale sibling left over from before an item was flagged `-2`. **Hard delete, not
  `stepXref`'s archive path** — mirrors `stepXref`'s own `expunge=3` case, no history trace kept
  for the choice that lost out. This was an explicit choice, not a default: the author's own framing
  throughout ("the store would delete the other quantity records") used delete specifically: an
  archive-via-`stepXref` alternative was raised and not taken up.

**Considered and settled against — group-level approach, `liberty_xref_group.multiple` stays
dead.** The first design floated a *group*-level flag instead of an item-level one — split
SGL/WT/VOL into their own `x_group` (since `quantity` mixes them with non-exclusive `PCK`/`REM`),
reusing `liberty_xref_group`'s own `multiple` column, which is completely dead (confirmed: zero
PHP consumers, no package's `schema_inc.php` even sets it on any `INSERT`) — clean, unused space,
same `-1`-style idiom, at the level where "these items are mutually exclusive" actually describes
a relationship. the author's own objection: reluctant to add another tab for one split-out group —
noted that this is mitigatable (a custom `quantity`-group template can pull a sibling group's rows
into the same `{jstab}`, since `list_xref.tpl` loads all groups together via one `loadXrefInfo()`
call regardless of how they're split), but settled on the simpler item-scoped `-2` flag instead,
which sidesteps the group-mixing problem entirely (exclusivity applies only to items actually
flagged `-2`, so no split is needed at all). **Revisited and confirmed 2026-08-18 (later)**:
the author's own assessment, having now seen `-2` actually working across a real multi-item case
(Stock's own BOM/multi-item groups already get handled with ad-hoc per-package template tricks
rather than a generic mechanism) — `liberty_xref_group.multiple` probably isn't needed at all, not
just deferred. Left dead deliberately, not removed, in case a genuine group-wide (not item-scoped)
exclusivity case turns up later — but don't expect to reach for it; the item-scoped `-2` flag has
covered every real case seen so far.

**Per-row Edit icon suppression — built 2026-08-18 (later), one file.** Turned out to need far
less than the paragraph above once assumed: `action_icons.tpl` (see "Add and edit" below) is
already the single shared render point for Edit/Archive/Delete across all 23 xref item view
templates in liberty/stock/food/contact — no per-item-template work needed, no need to wait for
the `add_<group>_group.tpl` redesign. Two changes: `LibertyXrefType::loadContent()`'s row query
gained `s.multiple` (wasn't selected at all before, so `$xrefInfo.multiple` didn't exist for
templates to check); `action_icons.tpl`'s Edit `{if}` gained `&& $xrefInfo.multiple neq -1`.
**Scoped to exactly what was asked — Edit only, Archive/Delete deliberately untouched** (expunge/
history semantics for a read-only item remain a separate, not-yet-made decision, same as the
write-rejection work earlier the same day). Live-verified by temporarily flagging a real item
(Food's `DUID`) `-1` on desktop rdmcloud — its row lost the Edit icon, an unaffected sibling row
kept all three — then reverted.

**`xref` vs `xkey`/`xkey_ext`**: easy to conflate. `xkey`/`xkey_ext`/`data` hold this row's own
scalar value(s) — a number, a UUID, a note. `xref` holds a *foreign key* to a different content
object entirely (a Contact, a component, whatever). An item can use both at once — e.g. Stock's
`#SUP`/Food's `SUP`: `xref` = the supplier Contact's `content_id`, `xkey`/`xkey_ext` = that
specific supplier relationship's own part number/price.

**`xorder`** must be explicitly selected in queries — it is not auto-included in standard `SELECT`
lists the way most columns are.

**`LibertyXrefType`** is an *instance* class, not a bag of statics — construct with
`new LibertyXrefType( $contentTypeGuid, $packageGuid = null )`, though in page/class code you
should almost never construct it directly: access the current content object's own instance via
`LibertyContent::xrefType()`, which lazily creates and caches it. The five runtime query methods
(`getDisplayGroups`, `getTypeMarkers`, `getAvailableItems`, `getTemplateFormats`,
`getContentTypeMarkers`) are instance methods on it; admin cross-type queries (`getXrefTypeList`,
`getContentTypeGuids`, `getGroupList`) remain static.

**Dates**: `entry_date` stamped once at insert, untouched on update, unless the caller explicitly
overrides it (e.g. to group a batch of related xrefs under one shared timestamp).
`last_update_date` stamps every write. `start_date`/`end_date` drive the history/expunge
mechanism (see "Expunge and history" below) — `end_date IS NOT NULL` is what "closed"/"history"
means, not a separate status column.

## Usage pattern — loading and rendering a content object's xrefs

PHP (view or edit page):
```php
$gContent->loadXrefInfo();
$gBitSmarty->assign( 'gXrefInfo', $gContent->mXrefInfo );
```

Template:
```smarty
{foreach $gXrefInfo->mGroups as $xrefGroup}
    {include file=$gContent->getXrefListTemplate($xrefGroup->mTemplate)
        xrefGroup=$xrefGroup allow_edit=false}   {* true for edit pages *}
{/foreach}
```

Every group template (custom or the generic `list_xref.tpl`) starts with:
```smarty
{assign var=xrefAllowEdit value=$allow_edit|default:false}
{assign var=isHistory value=($xrefGroup->mXGroup eq 'history')}
```

**Extra per-row fields beyond what `loadContent()` already provides** (e.g. a second xref's value
folded into the same row): override `loadXrefInfo()` in the content class, call
`parent::loadXrefInfo()` first, then enrich `$group->mXrefs` afterward. Use
`array_map( fn($r) => $r['xref'], $group->mXrefs )`, **not** `array_column()`, to pull `xref`
values out — `LibertyXref` rows are `ArrayAccess` objects, and `array_column()` ignores
`offsetGet()` on some PHP builds.

## Dual-guid scoping (package-level vs content-type-level)

A package with multiple content types can register xref groups/items at two different scopes,
both matched at query time via `content_type_guid IN (class_guid, package_guid)`:

- **Package-level** — `content_type_guid` = the bare package name (e.g. `'stock'`, `'food'`).
  Shared across every content type in that package.
- **Content-type-level** — `content_type_guid` = the specific class's own guid (e.g.
  `'stockcomponent'`, `'foodcomponent'`). Scoped to just that one content type.

**Both levels share one `sort_order` numbering space** — a package-level group at the same
`sort_order` as a content-type-level group is a real collision once both get loaded together for
the same content type, not just a style nit (hit for real on Food's `external` group, originally
mis-scoped at package level and colliding with `nutrition`'s `sort_order`).

Pass `$packageGuid` when constructing `LibertyXrefType`/`LibertyXrefInfo` to enable dual-guid
matching; `LibertyContent::$mPackageGuid` is set automatically by `registerContentType()` from
whatever `handler_package` was passed in the type registration hash (Stock's three classes each
pass `'handler_package' => 'stock'`, for example — no per-class manual wiring needed, it's a base
`LibertyContent` behaviour). When writing a manual xref JOIN spanning both levels, join item↔group
on `t.content_type_guid = s.content_type_guid` and apply the guid filter only in `WHERE` —
filtering inside the JOIN condition causes cross-matching when two different guids happen to share
an `x_group` name.

**Two known gaps where a query method doesn't consistently use the guid filter**:
`getAvailableItems()`'s group JOIN was fixed 2026-08-17 (was a bare exact-match instead of the
filter, silently hiding a type-specific item whose group lived at package level — Contact's `CON`
item was the real trigger). `getTemplateFormats()` still isn't fixed — flat single-guid `WHERE`,
no package-level fallback — but it's low-impact: its only consumer is Contact's own legacy
`add_xref.tpl`/`_fields.tpl` Add flow (the one earmarked for retirement once the
`add_<group>_group.tpl` redesign lands, see `CLAUDE.md`), so nothing outside Contact can even
reach it. Not worth fixing pre-emptively — would surface more formats needing dead `_fields.tpl`
partials for no concrete live need.

**Checked 2026-08-18 whether Stock's `supplier` group (shared across StockComponent/StockAssembly/
StockMovement) is exposed to either gap — it isn't.** Both the `supplier` group *and* its `#SUP`
item are registered at the same package-level guid (`'stock'`), so every query that resolves them
does a plain exact match, no cross-level fallback ever needed for this pair specifically. The
dual-guid gap only bites when a group and its item are registered at *different* levels (group
package-level, item type-specific, or vice versa) — Stock's supplier group doesn't do that, so
there's nothing live to fix here. Worth checking this same way (which level is the group at, which
level is the item at, do they match) for any future case, rather than assuming "shared across
several classes" automatically means it's at risk.

## Type-marker convention (`sort_order = 0`)

A group with `sort_order = 0` is deliberately excluded from the normal tabbed display
(`LibertyXrefType::loadContent()` filters it out) — it's meant to be loaded via a separate,
purpose-built code path instead of the generic per-group tab loop. Used when a group's items are
mutually-exclusive *classifiers* rather than independent fields — e.g. Contact's person/business
type toggle, Food's `FoodAssembly` meal-type group (`BREAKFAST`/`LUNCH`/`DINNER`/`MSNK`/`ESNK` —
which single item code is populated *is* the classification, not a field to display in a tab).

## Group templates vs the generic tabbed display

`$gContent->getXrefListTemplate($xrefGroup->mTemplate)` resolves, in order:
1. `<package>/templates/<content_type_guid>/view_xref_<template>_group.tpl`
2. `<package>/templates/view_xref_<template>_group.tpl`
3. `bitpackage:liberty/list_xref.tpl` (generic fallback)

An empty/null `liberty_xref_group.template` skips straight to the generic fallback. **The generic
`list_xref.tpl` imposes a fixed 3-column contract** — Type / Value / Notes (+ Started/Updated/Edit
when `allow_edit`) — and wraps every row in its own `<tr>`, calling
`getXrefRecordTemplate($xrefInfo.template)` *inside* that `<tr>` for each row. Any item template
resolving through the generic group path must fit that shape (no own `<tr>`, exactly 3 `<td>`s
+ optional edit columns) — copying a *custom* group's item template (which owns its own `<tr>`
and can have any number of columns) into a generically-dispatched group breaks the layout. This
is not a hypothetical — it's exactly the mistake made and caught while building Food's `SUP` item
(see `project_food_package_scoping` memory, 2026-08-17).

A **custom group template** (non-empty `template`, e.g. Stock's/Food's `'sup'`) opts a group out
of the generic 3-column shape entirely — it can define whatever columns make sense (Stock's/
Food's supplier group: Supplier / Product Code / Price / Note), and its own "Add" link can point
at a package-specific add page instead of the generic `add_xref.php` (see next section for why
that's usually necessary anyway).

## Item templates (view and edit)

Same 3-tier resolution, separately for view and edit:

- `getXrefRecordTemplate($template)` → `<package>/templates/<guid>/view_xref_<t>_item.tpl` →
  `<package>/templates/view_xref_<t>_item.tpl` → `bitpackage:liberty/view_xref_<t>_item.tpl` →
  hardcoded fallback `bitpackage:liberty/view_xref_text_item.tpl`.
- `getXrefEditTemplate($template)` → same shape, `edit_xref_<t>_item.tpl`.

**Generic templates that exist today** (`liberty/templates/`), all fitting the 3-column contract:

| `template` | View | Edit | Use |
|---|---|---|---|
| `text` | ✓ | ✓ | Any scalar `xkey`/`xkey_ext`/`data` field. Ultimate fallback. |
| `value` | ✓ | ✓ | Same shape, different column emphasis (`xkey`+`xkey_ext` as two separate values rather than one combined). |
| `link` | ✓ | ✓ (read-only target) | An item whose `xref` points at another piece of content — renders a real link via the linked title, generic across every package (added 2026-08-17, see below). |
| `json-text` | ✓ | ✓ | `data` holds a JSON object — renders as one tidy `Key: value, Key: value` line. Edit shares `json-list`'s form (`{include}` forward) — editing doesn't need to differ by view style. Added 2026-08-17. |
| `json-list` | ✓ | ✓ | Same JSON case, but view renders each key on its own line — a nested table *inside* the cell, not separate outer rows (`list_xref.tpl` owns the one `<tr>` per xref row; an item template can't legitimately add more). Edit renders one input per key (`name="json_field[key]"`, PHP's array-POST convention) — `edit_xref.php` reassembles `$_REQUEST['json_field']` back into a JSON string (numeric strings cast back to int/float; blank/zero entries dropped so the saved blob stays sparse, same convention every importer already uses) before the normal `storeXref()` path runs. Only triggers when that field is actually present, so it can't affect any other item type's save. Added 2026-08-17. |
| `sod` (Food-local, `food/templates/xref/foodcomponent/`) | — (falls back to `text`) | ✓ | Worked example of an **edit-only override**: a package needing a custom *edit* form for what's otherwise a completely ordinary scalar display doesn't need a matching view template at all — `getXrefRecordTemplate()`'s final fallback (`view_text_item.tpl`) covers it for free as long as no `view_sod_item.tpl` exists anywhere in the resolution chain. Food's `SOD` (sodium) uses this to offer Salt (g)/Sodium (mg) inputs, converting salt→sodium at save time — `edit_xref.php` gained a small `sod_salt`/`sod_sodium` hook (same shape as `json_field`'s, gated purely on field presence) alongside the JSON one. Added 2026-08-18. **Worth reusing this pattern** for any future item that needs a nonstandard edit *input* but a perfectly standard scalar *display* — cheaper than building both halves of a new template pair. |

**Full field list for `json-list`/`json-text` editing** — a component's stored JSON blob is
normally *sparse* (an importer only writes keys it had real values for), so the edit form can't
know about a currently-missing key from the stored data alone; there'd be no way to add it.
Fixed by repurposing `liberty_xref_item.data` (documented above as an unused "default/hint"
column) to hold the item's **complete** set of possible keys as a JSON array — `LibertyXref::load()`
now selects it (aliased `item_data`, distinct from the row's own `data`), and the edit template
uses that as the authoritative field list, falling back to whatever's actually in the stored blob
if no hint was registered (so any other package's `json-list` item works with zero extra setup,
just without the "add a missing field" capability until it registers one). Food's `FAT`/`VIT`/`MIN`
register theirs, e.g. `'["total_mg","saturated_mg","mono_mg","poly_mg","trans_mg","cholesterol_mg"]'`
for `FAT`. Confirmed live: a component whose `FAT` blob genuinely only had 2 of 6 possible keys
correctly offered all 6 for editing, added one, saved sparse.

`address`/`bank`/`date`/`locate`/`phone`/`sig` also exist as view-only files in `liberty/templates/`,
but only `address`/`phone` are actually registered anywhere (both exclusively by Contact's own
items) — treat the rest as unconfirmed/likely-dead rather than assuming they're safe to reuse.
(`contact`/`image`/`inc_report` **were** in this list too — removed 2026-08-17, confirmed dead:
nothing registered them, and `contact` didn't even work, it printed a raw `xref` number instead of
resolving a link. `link` replaces the *concept* `contact` was reaching for, under a proper generic
name — Contact's *own* `templates/view_xref_contact_item.tpl` is a different, older, separate
thing, part of Contact's own pre-`_item`-convention xref system, deliberately left untouched.)

**Using a raw PHP function as a Smarty modifier** (needed for `json-text`/`json-list`'s
`|json_decode:true` and the edit form's `|array_keys` fallback) **requires an explicit allowlist
entry** — `themes/includes/classes/BitweaverExtension.php`'s `getModifierCallback()` has a
`switch` statement of permitted native functions (`basename`, `strpos`, `ucwords`, `json_decode`,
`array_keys`, etc.); anything not listed there fails at Smarty compile time with "unknown
modifier", not a runtime
error. Add new entries there, not by trying to register a plugin file for something that's really
just a bare PHP function call.

**`linked_title`/`linked_data`**: `LibertyXrefType::loadContent()` LEFT JOINs
`liberty_content lc_linked ON lc_linked.content_id = x.xref` and exposes `lc_linked.title AS
linked_title` / `lc_linked.data AS linked_data` on every loaded xref row for the **view** path —
no extra query needed there. **The edit path (`edit_xref.php`) doesn't go through that query** —
it loads a single row via `loadXref()`. `LibertyContent::enrichXrefDisplay(array &$pXrefInfo): void`
is the hook for this (called by `edit_xref.php` right before assigning `$xrefInfo` to Smarty,
row passed by reference) — **its base implementation now has a real default** (added 2026-08-17,
alongside `link`): populates `linked_title` generically via one lookup whenever `xref` is set, so
`link`'s edit form works with zero package code. Override and call
`parent::enrichXrefDisplay($pXrefInfo)` first to add further package-specific computed fields on
top (e.g. a component's pack size) — the base behaviour isn't lost by overriding.

## Add and edit — the real gap

- **`list_xref.tpl`'s "Add record" link** (`{smartlink ... ifile="add_xref.php" ... group=
  $xrefGroup->mSortOrder}`, gated on `$allow_add && $gContent->hasUpdatePermission() &&
  !$isHistory`) — one link per *group* (tab), not per item, sending the user to `add_xref.php`'s
  flat item picker for that whole group. This is the only add entry point the generic display
  offers today — see the next bullet for what that destination can't actually do, and the
  "Group-level add dispatch" idea in `CLAUDE.md`'s session log for a captured-but-not-built
  redesign of this link.
- **`edit_xref.php`** (generic) — edits *one existing row*, addressed by `xref_id`. Handles save
  (`fSaveXref` → `storeXref($_REQUEST)`), cancel, and expunge (see below). Renders via
  `getXrefEditTemplate()`.
- **`add_xref.php`** (generic) — **has no `xref` field at all.** Its form
  (`liberty/templates/add_xref.tpl`) is a flat `item` picker (any item in the target group) +
  `xkey`/`xkey_ext`/`data` text inputs, nothing else. **There is no way to set a linked-content
  `xref` through the generic Add flow, for any package** — confirmed by reading both the
  controller and its template (2026-08-17). This is a real architectural gap, not a
  not-yet-discovered feature.

**Consequence — every package needing "add a row that links to another piece of content" has
built its own bespoke add page, bypassing `add_xref.php` entirely.** Confirmed duplication as of
2026-08-17:

| Package | Add controller | Search/lookup endpoint |
|---|---|---|
| Stock | `add_movement_component.php`, `add_supplier.php`, `add_component.php`, `add_order.php`, `add_prebuild.php`, `add_requisition.php` | `includes/lookup_component.php`, `includes/component_lookup_inc.php`, `includes/assembly_lookup_inc.php`, `includes/movement_lookup_inc.php` |
| Food | `add_assembly_item.php`, `add_supplier.php` | `includes/lookup_component.php` |
| Contact | (n/a — Contact *is* the thing being looked up) | `includes/lookup_contact.php`, `includes/lookup_contact_inc.php` |

`food/includes/lookup_component.php` and `stock/includes/lookup_component.php` are **byte-for-byte
identical** except namespace, permission string, and `content_type_guid` literal — Food's own
docblock says so outright ("Mirrors stock/includes/lookup_component.php exactly"), i.e. this was
a *known*, deliberate copy at build time, not an accident. Every one of these add-controllers
shares the same shape: resolve or create a target `content_id` (via typeahead search or, for
Food's small supplier list, a plain `<select>`), then call `storeXref()`/`LibertyXref::store()`
directly with `xref` set, then redirect — exactly what `add_xref.php` structurally can't do.

**Deliberately not generalized — considered and settled, not just deferred (2026-08-17).** A
generic `add_xref.php` extension was designed in outline (template-aware dispatch, mirroring
`getXrefEditTemplate()`'s resolution chain, so a `link`-templated item would get an `xref`-picker
step the generic form doesn't show for every other item type) but not built. Reasoning for
stopping there: whenever a package actually wants an `xref`-linking item, the *validation* is
inherently package-specific — which content type, which subset (Food's suppliers filtered to
`B04`, Stock's typeahead across every contact), what counts as a valid pick. A generic mechanism
would still need that bespoke logic supplied per package underneath it; it would mostly just move
where the special-casing lives, not remove it. So each package building its own small `add_X.php`
when it genuinely needs one is treated as the *correct* shape here, not a duplication defect —
don't re-propose generalizing this without a concrete reason the calculus has changed.

## Expunge and history — no real hard-delete exists

`LibertyContent::storeXref()`/`stepXref()` are the two write paths; `stepXref()`'s `expunge`
parameter controls what "delete" actually does:

| `expunge` value | Effect |
|---|---|
| *(unset/default)* | Restore — sets `ignore_end_date='on'` |
| `1` | Soft-close (Archive) — sets `end_date = now()`. Row stays in the table, moves to the "history" view (`isHistory` in templates checks `end_date IS NOT NULL`). Gated behind ordinary update permission. |
| `2` | Step — closes the current row (`end_date = now()`) *and* opens a new one (`fStepXref`), preserving the old value in history while starting a fresh one. Used for e.g. a rename that should keep the old value visible in history. |
| `3` | **Real hard delete** — a genuine `DELETE FROM liberty_xref`, no history trace left. Gated behind the stricter expunge permission (`verifyExpungePermission()`), not the update permission the other three use. Added 2026-08-17 — see `project_liberty_hard_delete_xref` memory; all 22 affected item view templates across liberty/stock/food/contact updated in lockstep (Archive/Restore icons under update permission, a separate always-available Delete icon under expunge permission). |

**`multiple=-1` (read-only) items could never actually be archived/restored until fixed
2026-08-28.** `LibertyXref::verify()`'s read-only rejection only ever exempted `fStepXref`
(`expunge=2`'s own flag) — `expunge=1` (archive) and the default (restore) case both fall through
to the same `store()`/`verify()` call as a normal value edit, so the read-only guard blocked the
*entire* write, `end_date` included, with no error surfaced anywhere (`stepXref()`'s return value
wasn't even checked by its caller). Every Health xref item is `multiple=-1`, so this silently
broke exactly the case `-1` was added for. Fix is narrow: `verify()` now also exempts a call where
`expunge` is set **and** none of `xkey`/`xkey_ext`/`edit` are present — a genuinely pure lifecycle
op. Not looser than that, because `edit_xref.php`'s own `expunge` branch passes the whole
`$_REQUEST` into `stepXref()`, so a blanket "any expunge call" exemption would let a crafted
request sneak a real value edit through the read-only guard on a `-1` item.

**Related gap, same root cause, fixed 2026-08-18**: nothing enforced mutual exclusivity between
xref items that are conceptually "pick one of these" (e.g. Food's `SGL`/`WT`/`VOL` — a component's
meant to declare only one) — the generic "Add record" flow let a human add a second, competing
marker without any prompt to remove the old one. Found live 2026-08-17 (a component ended up with
both `WT` and `VOL` simultaneously after a manual correction), fixed the next day via the
`multiple = -2` mutually-exclusive convention above — see that section for the mechanism
(`LibertyXref::store()` hard-deletes the sibling automatically now, same `expunge=3`-style
`DELETE`, just triggered by the store rather than a manual expunge click).

**Live-verified 2026-08-18 against the two pre-existing violations this surfaced** (content_ids
7420 "Tea with Milk", 7446 "Skimmed Milk", both had carried `WT` and `VOL` simultaneously since
before this mechanism existed) — the author resolved both through the real UI, not isql: resaving
`VOL` on Tea correctly auto-deleted the sibling `WT` row via the new mechanism, no manual step
needed. Milk needed one extra manual step — using the `expunge=3` hard-delete ("dustbin") icon on
a leftover history entry — confirming that icon (built 2026-08-17, see the `expunge` table above)
and the new `-2` eviction compose cleanly rather than conflicting. Both components confirmed clean
afterward (one active `VOL` row each, no `WT`/`SGL` remnant, active or archived).

## Known by-reference footguns

Several liberty methods take their param hash **by reference** — passing a literal array (e.g.
`->store( [ 'foo' => 'bar' ] )`) is a PHP fatal error ("could not be passed by reference"), not a
warning. Always assign to a named variable first. Confirmed affected: `storeXref()`,
`parseDataHash()`, `LibertyXref::store()`, `FoodComponent::getDisplayUrlFromHash()` (this one
package-level, but the same base-class contract — any `getDisplayUrlFromHash()` override should
assume the same). Hit for real at least four separate times across this codebase's history —
worth grepping `->store( [` / `->getDisplayUrlFromHash( [` etc. for a literal-array argument
before considering xref-touching code finished.

## Double-`store()` version collision

`LibertyContent::store()` computes the next history version as `$this->mInfo['version'] + 1` and
never writes the new value back onto `$this->mInfo['version']` after a successful save. Calling
`->store()` twice on the *same already-loaded object* within one request — even for genuinely
different field changes — computes the identical "next version" both times and collides on
`liberty_content_history`'s unique `(content_id, version)` key (Firebird SQLSTATE 23000, an
uncaught `PDOException` that kills the whole request). See `reference_liberty_double_store_version_bug`
memory for the full writeup (found in Food's importer, general risk for any package). Fix: never
call `->store()` more than once on one loaded object per request if either call might have a real
field change — merge into one `$pParamHash` and one call, or reload the object between calls.

## Optional per-content-type calendar/grid rendering — `getDayCellHtml()`

`LibertyContent::getContentList()` — the single generic content-listing method used by search,
Calendar, and anywhere else a list of mixed content types gets built — already dispatches per
content type for `title`/`display_link`/`display_url` (calls that type's own static
`getTitleFromHash()`/`getDisplayLinkFromHash()`/`getDisplayUrlFromHash()`). Added 2026-08-22,
same spot: if the content type's handler class also defines a static `getDayCellHtml( array
$pHash ): string`, it gets called and the result stashed as `$aux['cell_html']`. Purely additive —
a content type that doesn't implement it is completely unaffected, `cell_html` just never gets set.

Built for Calendar specifically (`calendar/templates/calendar.tpl`'s three "Cell Content" blocks —
day/weeklist/month views — each check `{if $item.cell_html}{$item.cell_html}{else}` before falling
back to the plain title/link markup every type got before), but the hook itself lives here, not in
`calendar`, since `getContentList()` is what every consumer shares — any future content-list
consumer gets the same override capability for free. First real implementation:
`Health\HealthDay::getDayCellHtml()` (health package) — see its own `CLAUDE.md`/`MANUAL.md` for
what it actually renders and the two real bugs (missing role-permission grants, missing
`event_time`) found live-testing it.

## Package registration

Every package needs its own `includes/bit_setup_inc.php` — see this file's own `CLAUDE.md` for the
required shape and the six `<PKG>_PKG_*` constants convention (not covered again here, that section
is already reference-appropriate as written).
