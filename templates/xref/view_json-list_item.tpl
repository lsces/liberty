{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	{assign var="jsonData" value=$xrefInfo.data|json_decode:true}
	{if $jsonData}
		<table class="table-condensed table-borderless" style="margin:0">
			{foreach $jsonData as $jkey => $jval}
				<tr><th style="padding-right:.5em">{$jkey|replace:'_':' '|capitalize}</th><td>{$jval|escape}</td></tr>
			{/foreach}
		</table>
	{else}
		&nbsp;
	{/if}
</td>
<td>&nbsp;</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
