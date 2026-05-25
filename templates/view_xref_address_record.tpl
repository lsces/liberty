{strip}
<td>{$gContent->mInfo.$source[xref].source_title|escape}</td>
<td>&nbsp;</td>
<td>{$gContent->mInfo.$source[xref].xkey_ext|escape}, {$gContent->mInfo.$source[xref].xkey|escape}</td>
<td>{$gContent->mInfo.$source[xref].data|escape}</td>
<td>
	{if $source ne 'history'}
		{$gContent->mInfo.$source[xref].start_date|bit_short_date}
	{else}
		{$gContent->mInfo.$source[xref].end_date|bit_short_date}
	{/if}
</td>
<td>{$gContent->mInfo.$source[xref].last_update_date|bit_short_date}</td>
<td>
	<span class="actionicon">
		{if $gContent->hasUpdatePermission() && $source ne 'history'}
			{smartlink ititle="Edit" ipackage="liberty" ifile="edit_xref.php" booticon="icon-note-edit" content_id=$gContent->mInfo.content_id xref_id=$gContent->mInfo.$source[xref].xref_id}
		{/if}
		{if $gContent->hasExpungePermission()}
			{if $source eq 'history'}
				{smartlink ititle="Restore" ipackage="liberty" ifile="edit_xref.php" booticon="icon-note-edit" content_id=$gContent->mInfo.content_id xref_id=$gContent->mInfo.$source[xref].xref_id expunge=-1}
			{else}
				{smartlink ititle="Delete" ipackage="liberty" ifile="edit_xref.php" booticon="icon-note-delete" content_id=$gContent->mInfo.content_id xref_id=$gContent->mInfo.$source[xref].xref_id expunge=1}
			{/if}
		{/if}
	</span>
</td>
{/strip}
