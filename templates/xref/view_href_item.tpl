{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	{* href built from xkey, falling back to xkey_ext - same fallback the display label already
	   used one line down, just not the href itself. xkey is only VARCHAR(32), too short for a
	   MusicBrainz-style UUID or a full URL, so any item storing one of those there instead (this
	   codebase's own established convention for a longer value, e.g. fisheyealbum's own
	   mb_artistid/mb_releasegroupid/mb_discid, or contact's own wikidata/musicbrainz items) has
	   always had a truthy xkey_ext but an empty xkey - never satisfying this condition at all
	   until now, so it never rendered as a clickable link, just plain text via the else branch. *}
	{if $xrefInfo.cross_ref_href && ( $xrefInfo.xkey || $xrefInfo.xkey_ext )}
		<a href="{$xrefInfo.cross_ref_href|escape}{$xrefInfo.xkey|default:$xrefInfo.xkey_ext|escape}" target="_blank" rel="noopener">{$xrefInfo.xkey_ext|default:$xrefInfo.xkey|escape}</a>
	{else}
		{$xrefInfo.xkey|escape} {$xrefInfo.xkey_ext|escape}
	{/if}
</td>
{* truncate, not just escape - most href items leave data empty, but one (contact's own
   'wikidata' item) deliberately caches a whole raw JSON entity there (see
   contact/MANUAL-WIKI.md), which would otherwise dump the entire blob into this one table
   cell. A no-op for every other href item, whose data is short or empty anyway. *}
<td>{$xrefInfo.data|truncate:100:"..."|escape}</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
