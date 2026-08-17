{strip}
{if $xrefAllowEdit|default:true}
<td>
	{if !$isHistory}
		{$xrefInfo.start_date|bit_short_datetime}
	{else}
		{$xrefInfo.end_date|bit_short_datetime}
	{/if}
</td>
<td>{$xrefInfo.last_update_date|bit_short_date}</td>
{/if}
{/strip}
