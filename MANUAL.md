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
`xrefType()`/`mXrefInfo`/`storeXref()`/`stepXref()` instead. This section exists to keep it that
way and to give prose/comments elsewhere in other packages a correct term (`Xref` /
`$gContent->mXrefInfo`) to use instead of naming the raw table.

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
  (rarely used — a default/hint, not the row's actual value) + `sort_order` (display position
  among the items in a group and among items in schema-listing picker queries; defaults to `0`,
  which sorts first — populate explicitly on a new item if display order matters, otherwise it
  will jump ahead of items that already have a real value set).
- **`liberty_xref`** — the actual data, one row per (content_id, item) pair (or several if
  `multiple=1`). Columns: `xref_id` (PK), `content_id` (whose xref this is), `item`, `xkey`
  (`C(32)` — short values only), `xkey_ext` (`C(250)` — longer text: UUIDs, prices, URLs), `data`
  (CLOB — free text/notes), `xref` (FK to *another* `liberty_content.content_id` — this is what
  makes an xref a real link to another piece of content, not just a text field), `xorder`
  (position within a `multiple=1` group), `entry_date`/`last_update_date`/`start_date`/`end_date`
  (see below).

`liberty_xref_item.sort_order` and `liberty_xref.xorder` are different axes — `sort_order` orders
the several *item types* within a group against each other (e.g. which order `CAL`/`FAT`/`FIBR`
display in on a Nutrition tab); `xorder` orders the several *rows* of one `multiple=1` item against
each other. Both `LibertyXrefType::loadContent()`'s row query and the schema-listing queries that
build type/item pickers (`getTypeMarkers()`, `getAvailableItems()`, `getContentTypeMarkers()`)
order by `sort_order` first, falling back to item/title alphabetically as a tie-break.

**Negative `multiple` values**: `multiple` is a plain `I2` (signed smallint, no `CHECK`
constraint). Its only real runtime consumer anywhere in liberty/stock/food/contact is
`LibertyXref::verify()`'s xorder-auto-increment gate — no template or PHP anywhere else branches
on `multiple` by truthiness, so negative values can't be misread as `1`-like by any existing code
path. Two distinct meanings, not a sign+magnitude cardinality scheme — each value below is a flat,
separate flag:

- **`-1` = read-only.** Used for device-reported sensor/time-series data (see `health/MANUAL.md`)
  where editing genuinely doesn't make sense, unlike every other consumer (Stock/Contact/Food),
  whose xref data is all human-curated.
- **`-2` = mutually exclusive within the same `x_group`.** Scoped to the *items actually flagged
  `-2`*, not the whole group — other ordinary `0`/`1` items in the same group are unaffected, so no
  group split is needed to use this.

**Enforced for both flags**:
- `LibertyXrefType::getAvailableItems()` (the query behind `add_xref.php`'s item-type dropdown,
  and every other "what items can this content type have" caller) excludes only `multiple = -1` —
  a `-2` item is a completely normal, still-addable choice, it just has a side effect on store.
- `LibertyXref::verify()` rejects a write only for `multiple = -1` (both the `fAddXref` case and a
  plain in-place edit) — `-2` items are freely addable/editable. Neither flag touches `fStepXref`
  (archive/restore/hard-delete via `stepXref()`) directly — see "Expunge and history" below for how
  a `-1` item's lifecycle operations are handled.
- `edit_xref.php` refuses to even render the edit form for a `multiple = -1` row (redirects back
  to `getEditUrl()`), not just reject the save.
- `LibertyXref::store()` — the `-2` mechanism's actual point: after a successful store of an item
  whose own `multiple = -2`, hard-deletes any *other* item in the same `x_group` (respecting the
  usual dual-guid scoping) that's also flagged `-2`, for that `content_id`. Runs inside the same
  transaction as the store itself (atomic — a failed eviction rolls back the store too), and on
  every store of a `-2` item (add or in-place edit), not just `fAddXref`, so it's self-healing
  against any stale sibling left over from before an item was flagged `-2`. **Hard delete, not
  `stepXref`'s archive path** — mirrors `stepXref`'s own `expunge=3` case, no history trace kept
  for the choice that lost out.

`action_icons.tpl` (see "Item templates" below) checks `$xrefInfo.multiple` to hide the Edit icon
on a read-only (`multiple=-1`) row — `LibertyXrefType::loadContent()`'s row query selects
`s.multiple` for exactly this purpose.

`liberty_xref_group.multiple` exists as a column but is completely dead — zero PHP consumers, no
package's `schema_inc.php` sets it. Left in place as a possible future extension point for a
genuine group-wide (not item-scoped) exclusivity case, but every real case so far has been covered
by the item-scoped `-2` flag instead; don't expect to need it.

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
the same content type, not just a style nit.

Pass `$packageGuid` when constructing `LibertyXrefType`/`LibertyXrefInfo` to enable dual-guid
matching; `LibertyContent::$mPackageGuid` is set automatically by `registerContentType()` from
whatever `handler_package` was passed in the type registration hash (Stock's three classes each
pass `'handler_package' => 'stock'`, for example — no per-class manual wiring needed, it's a base
`LibertyContent` behaviour).

**Two different join styles, for two different situations — don't apply one where the other
belongs:**

- **Resolving items/groups generically for one content type** (`getTypeMarkers()`,
  `getAvailableItems()`, `getContentTypeMarkers()`, `getDisplayGroups()`) — put the guid filter
  (`IN(contentTypeGuid, packageGuid)`) directly in the item↔group JOIN condition, not just `WHERE`.
  Safe here because the filter only ever admits this one content type's own two guid levels, never
  a sibling type's guid — a `contactperson` instance's filter can't accidentally match a
  `contactbusiness`-only group.
- **`loadContent()`** loads several groups first (via that same IN-filter), then joins each
  group's own items using that specific row's own real `content_type_guid` — exact-match there is
  what stops two different guids sharing an `x_group` name (e.g. `quantity` on both
  `stockcomponent` and `stockassembly`) from cross-attributing rows once multiple groups are loaded
  side by side. This is the situation "filter only in WHERE, exact-match the JOIN" actually applies
  to, not the single-type generic queries above.

**Known gap**: `getTypeMarkers()`, `getAvailableItems()`, `getContentTypeMarkers()`, and
`getDisplayGroups()` all correctly use the dual-guid JOIN filter above. `getTemplateFormats()`
still doesn't — flat single-guid `WHERE`, no package-level fallback — but it's low-impact: its
only consumer is Contact's own legacy `add_xref.tpl`/`_fields.tpl` Add flow, so nothing outside
Contact can even reach it. Not worth fixing pre-emptively — would surface more formats needing
dead `_fields.tpl` partials for no concrete live need.

The dual-guid gap only bites when a group and its item are registered at *different* levels
(group package-level, item type-specific, or vice versa). Stock's `supplier` group (shared across
StockComponent/StockAssembly/StockMovement) isn't at risk despite being shared across several
classes — both the group and its `#SUP` item are registered at the same package-level guid
(`'stock'`), so every query that resolves them does a plain exact match, no cross-level fallback
ever needed. Check which level a group is at and which level its item is at, and whether they
match, before assuming any "shared across several classes" case is at risk the same way.

## Type-marker convention (`sort_order = 0`)

A group with `sort_order = 0` is deliberately excluded from the normal tabbed display
(`LibertyXrefType::loadContent()` filters it out) — it's meant to be loaded via a separate,
purpose-built code path instead of the generic per-group tab loop. Used when a group's items are
mutually-exclusive *classifiers* rather than independent fields — e.g. Contact's person/business
type toggle, Food's `FoodAssembly` meal-type group (`BREAKFAST`/`LUNCH`/`DINNER`/`MSNK`/`ESNK` —
which single item code is populated *is* the classification, not a field to display in a tab).

## Group templates vs the generic tabbed display

`$gContent->getXrefListTemplate($xrefGroup->mTemplate)` resolves, in order:
1. `<package>/templates/xref/<content_type_guid>/view_<template>_group.tpl`
2. `<package>/templates/xref/view_<template>_group.tpl`
3. `bitpackage:liberty/list_xref.tpl` (generic fallback)

(Paths live under `templates/xref/`, not bare `templates/`, and filenames have no `_xref_`
infix — e.g. `view_sup_group.tpl`, not `view_xref_sup_group.tpl`.)

An empty/null `liberty_xref_group.template` skips straight to the generic fallback. **The generic
`list_xref.tpl` imposes a fixed 3-column contract** — Type / Value / Notes (+ Started/Updated/Edit
when `allow_edit`) — and wraps every row in its own `<tr>`, calling
`getXrefRecordTemplate($xrefInfo.template)` *inside* that `<tr>` for each row. Any item template
resolving through the generic group path must fit that shape (no own `<tr>`, exactly 3 `<td>`s
+ optional edit columns) — copying a *custom* group's item template (which owns its own `<tr>`
and can have any number of columns) into a generically-dispatched group breaks the layout.

A **custom group template** (non-empty `template`, e.g. Stock's/Food's `'sup'`) opts a group out
of the generic 3-column shape entirely — it can define whatever columns make sense (Stock's/
Food's supplier group: Supplier / Product Code / Price / Note), and its own "Add" link can point
at a package-specific add page instead of the generic `add_xref.php` (see next section for why
that's usually necessary anyway).

## Item templates (view and edit)

Same 3-tier resolution, separately for view and edit:

- `getXrefRecordTemplate($template)` → `<package>/templates/xref/<content_type_guid>/view_<t>_item.tpl` →
  `<package>/templates/xref/view_<t>_item.tpl` → `bitpackage:liberty/xref/view_<t>_item.tpl` →
  hardcoded fallback `bitpackage:liberty/xref/view_text_item.tpl`.
- `getXrefEditTemplate($template)` → same shape, `edit_<t>_item.tpl`, falling back to
  `bitpackage:liberty/edit_xref.tpl` (page-level, not in `xref/`) if no matching template exists
  anywhere.

**Generic templates that exist today** (`liberty/templates/xref/`), all fitting the 3-column
contract:

| `template` | View | Edit | Use |
|---|---|---|---|
| `text` | ✓ | ✓ | Any scalar `xkey`/`xkey_ext`/`data` field. Ultimate fallback. |
| `value` | ✓ | ✓ | Same shape, different column emphasis (`xkey`+`xkey_ext` as two separate values rather than one combined). |
| `link` | ✓ | ✓ (read-only target) | An item whose `xref` points at another piece of content — renders a real link via the linked title, generic across every package. |
| `json-text` | ✓ | ✓ | `data` holds a JSON object — renders as one tidy `Key: value, Key: value` line. Edit shares `json-list`'s form (`{include}` forward) — editing doesn't need to differ by view style. |
| `json-list` | ✓ | ✓ | Same JSON case, but view renders each key on its own line — a nested table *inside* the cell, not separate outer rows (`list_xref.tpl` owns the one `<tr>` per xref row; an item template can't legitimately add more). Edit renders one input per key (`name="json_field[key]"`, PHP's array-POST convention) — `edit_xref.php` reassembles `$_REQUEST['json_field']` back into a JSON string (numeric strings cast back to int/float; blank/zero entries dropped so the saved blob stays sparse, same convention every importer already uses) before the normal `storeXref()` path runs. Only triggers when that field is actually present, so it can't affect any other item type's save. |
| `sod` (Food-local, `food/templates/xref/foodcomponent/`) | — (falls back to `text`) | ✓ | Worked example of an **edit-only override**: a package needing a custom *edit* form for what's otherwise a completely ordinary scalar display doesn't need a matching view template at all — `getXrefRecordTemplate()`'s final fallback (`view_text_item.tpl`) covers it for free as long as no `view_sod_item.tpl` exists anywhere in the resolution chain. Food's `SOD` (sodium) uses this to offer Salt (g)/Sodium (mg) inputs, converting salt→sodium at save time — `edit_xref.php` has a small `sod_salt`/`sod_sodium` hook (same shape as `json_field`'s, gated purely on field presence) alongside the JSON one. **Worth reusing this pattern** for any future item that needs a nonstandard edit *input* but a perfectly standard scalar *display* — cheaper than building both halves of a new template pair. |

**Full field list for `json-list`/`json-text` editing** — a component's stored JSON blob is
normally *sparse* (an importer only writes keys it had real values for), so the edit form can't
know about a currently-missing key from the stored data alone; there'd be no way to add it.
Solved by repurposing `liberty_xref_item.data` (documented above as an unused "default/hint"
column) to hold the item's **complete** set of possible keys as a JSON array — `LibertyXref::load()`
selects it (aliased `item_data`, distinct from the row's own `data`), and the edit template
uses that as the authoritative field list, falling back to whatever's actually in the stored blob
if no hint was registered (so any other package's `json-list` item works with zero extra setup,
just without the "add a missing field" capability until it registers one). Food's `FAT`/`VIT`/`MIN`
register theirs, e.g. `'["total_mg","saturated_mg","mono_mg","poly_mg","trans_mg","cholesterol_mg"]'`
for `FAT`.

`address`/`bank`/`date`/`locate`/`phone`/`sig` also exist as view-only files in `liberty/templates/xref/`,
but only `address`/`phone` are actually registered anywhere (both exclusively by Contact's own
items) — treat the rest as unconfirmed/likely-dead rather than assuming they're safe to reuse.
Contact's *own* `templates/view_xref_contact_item.tpl` is a different, older, separate thing, part
of Contact's own pre-`_item`-convention xref system, deliberately left untouched.

**Using a raw PHP function as a Smarty modifier** (needed for `json-text`/`json-list`'s
`|json_decode:true` and the edit form's `|array_keys` fallback) **requires an explicit allowlist
entry** — `themes/includes/classes/BitweaverExtension.php`'s `getModifierCallback()` has a
`switch` statement of permitted native functions (`basename`, `strpos`, `ucwords`, `json_decode`,
`array_keys`, etc.); anything not listed there fails at Smarty compile time with "unknown
modifier", not a runtime error. Add new entries there, not by trying to register a plugin file for
something that's really just a bare PHP function call.

**Shared date/action-icon columns** (`liberty/templates/xref/dates_cell.tpl`,
`liberty/templates/xref/action_icons.tpl`): every 3-column-contract item template ends with the
same two columns — Added/Updated dates, then Edit/Archive-Restore/Delete icons — so both are
factored into standalone includes rather than hand-rolled per item template. `{include
file="bitpackage:liberty/xref/dates_cell.tpl"}` renders `start_date`/`end_date` (whichever applies
given `$isHistory`) plus `last_update_date`, self-gated on `$xrefAllowEdit|default:true`.
`{include file="bitpackage:liberty/xref/action_icons.tpl"}` renders Edit (gated on update
permission, hidden for `multiple=-1` read-only items and on history rows), Archive/Restore (update
permission — swaps label/icon/`expunge` value based on `$isHistory`), and Delete (gated on the
stricter expunge permission, `expunge=3`, the real hard-delete from the section below). Accepts an
optional `xrefProtected` param (truthy) to hide Archive/Restore and Delete entirely, for items that
must never be touched via this path. **Any new item template should `{include}` both** rather than
reimplementing these columns — copy an already-converted template (e.g.
`liberty/templates/xref/view_text_item.tpl`) as the reference shape.

**`linked_title`/`linked_data`**: `LibertyXrefType::loadContent()` LEFT JOINs
`liberty_content lc_linked ON lc_linked.content_id = x.xref` and exposes `lc_linked.title AS
linked_title` / `lc_linked.data AS linked_data` on every loaded xref row for the **view** path —
no extra query needed there. **The edit path (`edit_xref.php`) doesn't go through that query** —
it loads a single row via `loadXref()`. `LibertyContent::enrichXrefDisplay(array &$pXrefInfo): void`
is the hook for this (called by `edit_xref.php` right before assigning `$xrefInfo` to Smarty,
row passed by reference) — its base implementation populates `linked_title` generically via one
lookup whenever `xref` is set, so `link`'s edit form works with zero package code. Override and
call `parent::enrichXrefDisplay($pXrefInfo)` first to add further package-specific computed fields
on top (e.g. a component's pack size) — the base behaviour isn't lost by overriding.

## Add and edit — the real gap

- **`list_xref.tpl`'s "Add record" link** (`{smartlink ... ifile="add_xref.php" ... group=
  $xrefGroup->mSortOrder}`, gated on `$allow_add && $gContent->hasUpdatePermission() &&
  !$isHistory`) — one link per *group* (tab), not per item, sending the user to `add_xref.php`'s
  flat item picker for that whole group. This is the only add entry point the generic display
  offers today — see the next bullet for what that destination can't actually do.
- **`edit_xref.php`** (generic) — edits *one existing row*, addressed by `xref_id`. Handles save
  (`fSaveXref` → `storeXref($_REQUEST)`), cancel, and expunge (see below). Renders via
  `getXrefEditTemplate()`.
- **`add_xref.php`** (generic) — **has no `xref` field at all.** Its form
  (`liberty/templates/add_xref.tpl`) is a flat `item` picker (any item in the target group) +
  `xkey`/`xkey_ext`/`data` text inputs, nothing else. **There is no way to set a linked-content
  `xref` through the generic Add flow, for any package.** This is a real architectural gap, not a
  not-yet-discovered feature.

**Consequence — every package needing "add a row that links to another piece of content" has
built its own bespoke add page, bypassing `add_xref.php` entirely:**

| Package | Add controller | Search/lookup endpoint |
|---|---|---|
| Stock | `add_movement_component.php`, `add_supplier.php`, `add_component.php`, `add_order.php`, `add_prebuild.php`, `add_requisition.php` | `includes/lookup_component.php`, `includes/component_lookup_inc.php`, `includes/assembly_lookup_inc.php`, `includes/movement_lookup_inc.php` |
| Food | `add_assembly_item.php`, `add_supplier.php` | `includes/lookup_component.php` |
| Contact | (n/a — Contact *is* the thing being looked up) | `includes/lookup_contact.php`, `includes/lookup_contact_inc.php` |

`food/includes/lookup_component.php` and `stock/includes/lookup_component.php` are **byte-for-byte
identical** except namespace, permission string, and `content_type_guid` literal — a deliberate
copy at build time, not an accident. Every one of these add-controllers shares the same shape:
resolve or create a target `content_id` (via typeahead search or, for Food's small supplier list,
a plain `<select>`), then call `storeXref()`/`LibertyXref::store()` directly with `xref` set, then
redirect — exactly what `add_xref.php` structurally can't do.

**Deliberately not generalized.** A generic `add_xref.php` extension could in principle be
template-aware (mirroring `getXrefEditTemplate()`'s resolution chain, so a `link`-templated item
would get an `xref`-picker step the generic form doesn't show for every other item type), but
whenever a package actually wants an `xref`-linking item, the *validation* is inherently
package-specific — which content type, which subset (Food's suppliers filtered to `B04`, Stock's
typeahead across every contact), what counts as a valid pick. A generic mechanism would still need
that bespoke logic supplied per package underneath it; it would mostly just move where the
special-casing lives, not remove it. Each package building its own small `add_X.php` when it
genuinely needs one is the *correct* shape here, not a duplication defect.

## Expunge and history — archive, step, or real hard-delete

`LibertyContent::storeXref()`/`stepXref()` are the two write paths; `stepXref()`'s `expunge`
parameter controls what "delete" actually does:

| `expunge` value | Effect |
|---|---|
| *(unset/default)* | Restore — sets `ignore_end_date='on'` |
| `1` | Soft-close (Archive) — sets `end_date = now()`. Row stays in the table, moves to the "history" view (`isHistory` in templates checks `end_date IS NOT NULL`). Gated behind ordinary update permission. |
| `2` | Step — closes the current row (`end_date = now()`) *and* opens a new one (`fStepXref`), preserving the old value in history while starting a fresh one. Used for e.g. a rename that should keep the old value visible in history. |
| `3` | **Real hard delete** — a genuine `DELETE FROM liberty_xref`, no history trace left. Gated behind the stricter expunge permission (`verifyExpungePermission()`), not the update permission the other three use. All item view templates across liberty/stock/food/contact share this: Archive/Restore icons under update permission, a separate always-available Delete icon under expunge permission (see "Shared date/action-icon columns" above). |

**`multiple=-1` (read-only) items can still be archived/restored** — `LibertyXref::verify()`
exempts a call from its read-only rejection when `expunge` is set **and** none of
`xkey`/`xkey_ext`/`edit` are present, i.e. a genuinely pure lifecycle op (archive/restore/step)
touching no actual value. This is deliberately narrow rather than a blanket "any expunge call is
exempt" rule — `edit_xref.php`'s own `expunge` branch passes the whole `$_REQUEST` into
`stepXref()`, so a looser exemption would let a crafted request sneak a real value edit through the
read-only guard on a `-1` item.

## Known by-reference footguns

Several liberty methods take their param hash **by reference** — passing a literal array (e.g.
`->store( [ 'foo' => 'bar' ] )`) is a PHP fatal error ("could not be passed by reference"), not a
warning. Always assign to a named variable first. Confirmed affected: `storeXref()`,
`parseDataHash()`, `LibertyXref::store()`, `FoodComponent::getDisplayUrlFromHash()` (this one
package-level, but the same base-class contract — any `getDisplayUrlFromHash()` override should
assume the same). Worth grepping `->store( [` / `->getDisplayUrlFromHash( [` etc. for a
literal-array argument before considering xref-touching code finished.

## Double-`store()` version collision

`LibertyContent::store()` computes the next history version as `$this->mInfo['version'] + 1` and
never writes the new value back onto `$this->mInfo['version']` after a successful save. Calling
`->store()` twice on the *same already-loaded object* within one request — even for genuinely
different field changes — computes the identical "next version" both times and collides on
`liberty_content_history`'s unique `(content_id, version)` key (Firebird SQLSTATE 23000, an
uncaught `PDOException` that kills the whole request). Never call `->store()` more than once on
one loaded object per request if either call might have a real field change — merge into one
`$pParamHash` and one call, or reload the object between calls.

## Optional per-content-type calendar/grid rendering — `getDayCellHtml()`

`LibertyContent::getContentList()` — the single generic content-listing method used by search,
Calendar, and anywhere else a list of mixed content types gets built — already dispatches per
content type for `title`/`display_link`/`display_url` (calls that type's own static
`getTitleFromHash()`/`getDisplayLinkFromHash()`/`getDisplayUrlFromHash()`). Same spot: if the
content type's handler class also defines a static `getDayCellHtml( array $pHash ): string`, it
gets called and the result stashed as `$aux['cell_html']`. Purely additive — a content type that
doesn't implement it is completely unaffected, `cell_html` just never gets set.

Built for Calendar specifically (`calendar/templates/calendar.tpl`'s three "Cell Content" blocks —
day/weeklist/month views — each check `{if $item.cell_html}{$item.cell_html}{else}` before falling
back to the plain title/link markup every type got before), but the hook itself lives here, not in
`calendar`, since `getContentList()` is what every consumer shares — any future content-list
consumer gets the same override capability for free. `Health\HealthDay::getDayCellHtml()` (health
package) is the reference implementation to copy — see its own `MANUAL.md` for what it actually
renders.

## Package registration

Every package needs its own `includes/bit_setup_inc.php`, which:
1. Builds a `$pRegisterHash` (`package_name`, `package_path`, plus optional flags like
   `required_package`/`homeable`) and calls `$gBitSystem->registerPackage( $pRegisterHash )`.
2. `define()`s six `<PKG>_PKG_*` constants from that hash before registering — `<PKG>_PKG_NAME`,
   `_URL`, `_PATH`, `_INCLUDE_PATH`, `_CLASS_PATH`, `_ADMIN_PATH` (see any existing package's
   `includes/bit_setup_inc.php`, e.g. `stock/includes/bit_setup_inc.php`, for the exact pattern to
   copy). These must be defined explicitly (not left to dynamic lookup) — some IDEs/static analysis
   can't otherwise see them as real constants.
3. Registers anything else the package needs at boot — an app menu entry
   (`$gBitSystem->registerAppMenu()`), a Liberty service (`$gLibertySystem->registerService()`) if
   the package is content-bearing, etc.
