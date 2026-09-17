{strip}
{if $xrefAllowEdit|default:true}
{* xrefContentId: optional override for a group whose rows actually belong to a different
   content_id than the page's own $gContent (e.g. a show's page merging in its one season's own
   Episodes group) - defaults to $gContent's own id, unchanged for every other caller. *}
{assign var=actionContentId value=$xrefContentId|default:$gContent->mInfo.content_id}
<td>
	<span class="actionicon">
		{if $gContent->hasUpdatePermission() && !$isHistory && $xrefInfo.multiple neq -1}
			{smartlink ititle="Edit" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$actionContentId xref_id=$xrefInfo.xref_id}
		{/if}
		{if $gContent->hasUpdatePermission() && !$xrefProtected|default:false}
			{if $isHistory}
				{smartlink ititle="Restore" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$actionContentId xref_id=$xrefInfo.xref_id expunge=-1}
			{else}
				{smartlink ititle="Archive" ipackage="liberty" ifile="edit_xref.php" biticon="archive-insert" content_id=$actionContentId xref_id=$xrefInfo.xref_id expunge=1}
			{/if}
		{/if}
		{if $gContent->hasExpungePermission() && !$xrefProtected|default:false}
			{smartlink ititle="Delete" ipackage="liberty" ifile="edit_xref.php" biticon="user-trash" content_id=$actionContentId xref_id=$xrefInfo.xref_id expunge=3}
		{/if}
	</span>
</td>
{/if}
{/strip}
