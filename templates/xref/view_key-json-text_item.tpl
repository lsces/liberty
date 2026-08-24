{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	{assign var="itemData" value=$xrefInfo.item_data|json_decode:true}
	{assign var="jsonData" value=$xrefInfo.data|json_decode:true}
	{assign var="sep" value=""}
	{if $xrefInfo.xkey ne ''}
		{$sep}{if $itemData.0}{$itemData.0|replace:'_':' '|capitalize}{else}Value{/if}: {$xrefInfo.xkey|escape}{assign var="sep" value=", "}
	{/if}
	{if $xrefInfo.xkey_ext ne ''}
		{$sep}{if $itemData.1}{$itemData.1|replace:'_':' '|capitalize}{else}Notes{/if}: {$xrefInfo.xkey_ext|escape}{assign var="sep" value=", "}
	{/if}
	{if $jsonData}
		{foreach $jsonData as $jkey => $jval}{$sep}{$jkey|replace:'_':' '|capitalize}: {$jval|escape}{assign var="sep" value=", "}{/foreach}
	{/if}
</td>
<td>&nbsp;</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
