{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>{$xrefInfo.xkey|escape} {$xrefInfo.xkey_ext|escape}</td>
<td>{$xrefInfo.data|escape}</td>
{if $xrefAllowEdit|default:false}
<td>
	{if $source ne 'history'}
		{$xrefInfo.start_date|bit_short_date}
	{else}
		{$xrefInfo.end_date|bit_short_date}
	{/if}
</td>
<td>{$xrefInfo.last_update_date|bit_short_date}</td>
<td>
	<span class="actionicon">
		{if $gContent->hasUpdatePermission() && $source ne 'history'}
			{smartlink ititle="Edit" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id}
		{/if}
		{if $gContent->hasExpungePermission()}
			{if $source eq 'history'}
				{smartlink ititle="Restore" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=-1}
			{else}
				{smartlink ititle="Delete" ipackage="liberty" ifile="edit_xref.php" biticon="user-trash" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=1}
			{/if}
		{/if}
	</span>
</td>
{/if}
{/strip}
