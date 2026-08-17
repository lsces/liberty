{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>{$xrefInfo.xkey_ext|escape}{if $xrefInfo.xkey}, {$xrefInfo.xkey|escape}{/if}</td>
<td>{$xrefInfo.data|escape}</td>
<td>
	{if !$isHistory}
		{$xrefInfo.start_date|bit_short_datetime}
	{else}
		{$xrefInfo.end_date|bit_short_datetime}
	{/if}
</td>
{if $gBitSystem->isFeatureActive( 'contact_list_last_modified' )}<td>{$xrefInfo.last_update_date|bit_short_date}</td>{/if}
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
