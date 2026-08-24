{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	{assign var="itemData" value=$xrefInfo.item_data|json_decode:true}
	{assign var="sep" value=""}
	{if $xrefInfo.xkey ne ''}
		{$sep}{if $itemData.0}{$itemData.0|replace:'_':' '|capitalize}{else}Value{/if}: {$xrefInfo.xkey|escape}{assign var="sep" value=", "}
	{/if}
	{if $xrefInfo.xkey_ext ne ''}
		{if $xrefInfo.xkey_ext|substr:0:1 eq '{'}
			{assign var="extData" value=$xrefInfo.xkey_ext|json_decode:true}
			{foreach $extData as $ekey => $eval}{$sep}{$ekey|replace:'_':' '|capitalize}: {$eval|escape}{assign var="sep" value=", "}{/foreach}
		{else}
			{$sep}{if $itemData.1}{$itemData.1|replace:'_':' '|capitalize}{else}Notes{/if}: {$xrefInfo.xkey_ext|escape}{assign var="sep" value=", "}
		{/if}
	{/if}
</td>
<td>
	{if $xrefInfo.data ne '' && $xrefInfo.data ne '[]' && $xrefInfo.data ne '{}'}
		<details>
			<summary>{if $itemData.2}{$itemData.2|replace:'_':' '|capitalize}{else}Detail{/if}</summary>
			<pre style="white-space:pre-wrap;margin:.25em 0 0">{$xrefInfo.data|escape}</pre>
		</details>
	{else}
		&nbsp;
	{/if}
</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
