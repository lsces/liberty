{* Row view for an 'ext' item - see edit_ext_item.tpl: the value is xkey_ext (xkey for a row typed on the generic form), the note xkey. *}
{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>{if $xrefInfo.xkey_ext ne ''}{$xrefInfo.xkey_ext|escape}{else}{$xrefInfo.xkey|escape}{/if}</td>
<td>{if $xrefInfo.xkey_ext ne ''}{$xrefInfo.xkey|escape}{/if}</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
