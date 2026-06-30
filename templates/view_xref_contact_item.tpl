{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	{if isset($xrefInfo.xref) && $xrefInfo.xref > 100}
		{$xrefInfo.xref|escape}
	{else}
		&nbsp;
	{/if}
</td>
<td>{$xrefInfo.xkey|escape} {$xrefInfo.xkey_ext|escape}</td>
<td>{$xrefInfo.data|escape}</td>
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
		{if $xrefAllowEdit|default:true && $gContent->hasUpdatePermission() && !$isHistory}
			{smartlink ititle="Edit" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id}
		{/if}
		{if $xrefAllowEdit|default:true && $gContent->hasExpungePermission()}
			{if $isHistory}
				{smartlink ititle="Restore" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=-1}
			{else}
				{smartlink ititle="Delete" ipackage="liberty" ifile="edit_xref.php" biticon="user-trash" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=1}
			{/if}
		{/if}
	</span>
</td>
{/strip}
