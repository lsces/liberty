{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	{if $xrefInfo.cross_ref_href && $xrefInfo.xkey}
		<a href="{$xrefInfo.cross_ref_href|escape}{$xrefInfo.xkey|escape}" target="_blank" rel="noopener">{$xrefInfo.xkey_ext|default:$xrefInfo.xkey|escape}</a>
	{else}
		{$xrefInfo.xkey|escape} {$xrefInfo.xkey_ext|escape}
	{/if}
</td>
<td>{$xrefInfo.data|escape}</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
