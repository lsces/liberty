{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	{assign var="jsonData" value=$xrefInfo.data|json_decode:true}
	{if $jsonData}
		{foreach $jsonData as $jkey => $jval}{if !$jval@first}, {/if}{$jkey|replace:'_':' '|capitalize}: {$jval|escape}{/foreach}
	{else}
		&nbsp;
	{/if}
</td>
<td>&nbsp;</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
