# Liberty Package — Reference Manual

How the xref system actually works today. For the history of *why* — decisions, bugs found,
wrong turns — see `CLAUDE.md`'s dated session log instead; this file only tracks current
behaviour.

This manual covers `liberty_xref`/`liberty_xref_group`/`liberty_xref_item` — the generic
attribute/relationship system every content-heavy package (Stock, Contact, Food, and others)
builds its own custom data onto rather than adding schema columns. The rest of Liberty (content
versioning/history, caching, permissions) isn't covered here.

## Data model

Three tables:

- **`liberty_xref_group`** — a tab. `x_group` (short code) + `content_type_guid` (which content
  type this tab belongs to) + `title` (tab label) + `sort_order` (tab position; `0` is a special
  case, see "Type-marker convention" below) + `template` (optional — see "Group templates" below).
- **`liberty_xref_item`** — a field definition within a group. `item` (short code, e.g. `SUP`,
  `WT`, `CAL`) + `x_group` + `content_type_guid` + `cross_ref_title` (field label) + `multiple`
  (`0` = at most one row per content_id, `1` = many) + `template` (which item template to render
  — see "Item templates" below) + `cross_ref_href` (link prefix, used by some templates) + `data`
  (rarely used — a default/hint, not the row's actual value).
- **`liberty_xref`** — the actual data, one row per (content_id, item) pair (or several if
  `multiple=1`). Columns: `xref_id` (PK), `content_id` (whose xref this is), `item`, `xkey`
  (`C(32)` — short values only), `xkey_ext` (`C(250)` — longer text: UUIDs, prices, URLs), `data`
  (CLOB — free text/notes), `xref` (FK to *another* `liberty_content.content_id` — this is what
  makes an xref a real link to another piece of content, not just a text field), `xorder`
  (position within a `multiple=1` group), `entry_date`/`last_update_date`/`start_date`/`end_date`
  (see below).

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
matching; `LibertyContent::$mPackageGuid` is set automatically by `registerContentType()`. When
writing a manual xref JOIN spanning both levels, join item↔group on
`t.content_type_guid = s.content_type_guid` and apply the guid filter only in `WHERE` — filtering
inside the JOIN condition causes cross-matching when two different guids happen to share an
`x_group` name.

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

Liberty ships generic `text`/`value` item templates (view+edit) as the ultimate fallback for
simple scalar fields — these were the actual default for every Food item before custom templates
existed, and still are for anything that doesn't need one.

**`linked_title`/`linked_data` — view path only.** `LibertyXrefType::loadContent()` LEFT JOINs
`liberty_content lc_linked ON lc_linked.content_id = x.xref` and exposes `lc_linked.title AS
linked_title` / `lc_linked.data AS linked_data` on every loaded xref row — this is how a view
template shows the name of whatever `xref` points at, no extra query needed. **The edit path
(`edit_xref.php`) does not go through this query** — it loads a single row via `loadXref()`, so
`linked_title` is never populated there. If an edit template needs to show the linked object's
name, override `enrichXrefDisplay(array &$pXrefInfo): void` on the content class (default
implementation is a no-op) — `edit_xref.php` calls it right before assigning `$xrefInfo` to
Smarty, and it receives the row by reference to enrich in place.

## Add and edit — the real gap

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

**Not yet generalized.** A generic `add_xref.php` extension (accept an optional `xref` field, plus
a generic `liberty/includes/lookup_content.php?content_type_guid=X&q=...` parameterized by content
type instead of hardcoded per package) would collapse this duplication, but nothing has been built
— flagged here as a real, evidenced architecture gap for whenever it's worth tackling, not
actioned.

## Expunge and history — no real hard-delete exists

`LibertyContent::storeXref()`/`stepXref()` are the two write paths; `stepXref()`'s `expunge`
parameter controls what "delete" actually does:

| `expunge` value | Effect |
|---|---|
| *(unset/default)* | Restore — sets `ignore_end_date='on'` |
| `1` | Soft-close — sets `end_date = now()`. Row stays in the table, moves to the "history" view (`isHistory` in templates checks `end_date IS NOT NULL`). This is what every "Delete" icon in every xref template actually does. |
| `2` | Step — closes the current row (`end_date = now()`) *and* opens a new one (`fStepXref`), preserving the old value in history while starting a fresh one. Used for e.g. a rename that should keep the old value visible in history. |

**There is no case that issues a real `DELETE FROM liberty_xref`, anywhere in the class** —
confirmed by grep across `LibertyXref.php`/`LibertyXrefContent.php` (2026-08-17). Every "Delete"
in the UI is actually "soft-close to history." For most content this is exactly right (audit
trail matters); it's the wrong behaviour for a genuine data-entry duplicate (e.g. a component
accidentally carrying both `WT` and `VOL` type markers after a manual correction added the second
without removing the first) — see `project_liberty_hard_delete_xref` memory for the open,
undecided design question (a real `expunge=3` hard-delete case, permission-gated more strictly
than normal expunge, vs. some other approach) — not built yet.

**Related gap, same root cause**: nothing enforces mutual exclusivity between xref items that are
conceptually "pick one of these" (e.g. Food's `SGL`/`WT`/`VOL` — a component's meant to declare
only one). The generic "Add record" flow lets a human add a second, competing marker without any
prompt to remove the old one. Found live 2026-08-17 (a component ended up with both `WT` and `VOL`
simultaneously after a manual correction) — not fixed, same open thread as the hard-delete
question above.

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

## Package registration

Every package needs its own `includes/bit_setup_inc.php` — see this file's own `CLAUDE.md` for the
required shape and the six `<PKG>_PKG_*` constants convention (not covered again here, that section
is already reference-appropriate as written).
