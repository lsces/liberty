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
		{if $gContent->hasUpdatePermission()}
			{if $isHistory}
				{smartlink ititle="Restore" ipackage="liberty" ifile="edit_xref.php" biticon="edit" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=-1}
			{else}
				{smartlink ititle="Archive" ipackage="liberty" ifile="edit_xref.php" biticon="archive-insert" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=1}
			{/if}
		{/if}
		{if $gContent->hasExpungePermission()}
			{smartlink ititle="Delete" ipackage="liberty" ifile="edit_xref.php" biticon="user-trash" content_id=$gContent->mInfo.content_id xref_id=$xrefInfo.xref_id expunge=3}
		{/if}
	</span>
</td>
{/if}
{/strip}
