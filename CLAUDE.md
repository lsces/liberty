# Liberty Package — Developer Notes

## LibertyXref / xorder
`liberty_xref.xorder` — used for BOM grouping and sort. Must be explicitly selected
in queries; it is not auto-included in standard SELECT lists.

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
