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
{if $xrefAllowEdit|default:false}
<td>
	{if !$isHistory}
		{$xrefInfo.start_date|bit_short_datetime}
	{else}
		{$xrefInfo.end_date|bit_short_datetime}
	{/if}
</td>
<td>{$xrefInfo.last_update_date|bit_short_date}</td>
<td>
	<span class="actionicon">
		{if $gContent->hasUpdatePermission() && !$isHistory}
			{smartlink ititle="Edit" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id}
		{/if}
		{if $gContent->hasExpungePermission()}
			{if $isHistory}
				{smartlink ititle="Restore" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=-1}
			{else}
				{smartlink ititle="Delete" ipackage="liberty" ifile="edit_xref.php" biticon="user-trash" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=1}
			{/if}
		{/if}
	</span>
</td>
{/if}
{/strip}
