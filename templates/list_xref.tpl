{assign var=xrefAllowEdit value=$allow_edit|default:true}
{assign var=tabTitle value=$xrefGroup->mTitle}
{assign var=isHistory value=($xrefGroup->mXGroup eq 'history')}
{jstab title="`$tabTitle` ({$xrefGroup->mXrefs|@count})"}
{legend legend=$tabTitle}
<div class="form-group table-responsive">
	<table class="table table-condensed">
		<thead>
			<tr>
				<th>{tr}Type{/tr}</th>
				<th>{tr}Value{/tr}</th>
				<th>{tr}Notes{/tr}</th>
				{if $xrefAllowEdit}
					{if $isHistory}<th>{tr}Ended{/tr}</th>{else}<th>{tr}Started{/tr}</th>{/if}
					<th>{tr}Updated{/tr}</th>
					<th>{tr}Edit{/tr}</th>
				{/if}
			</tr>
		</thead>
		<tbody>
			{if $xrefGroup->mXrefs}
				{foreach $xrefGroup->mXrefs as $xrefInfo}
					<tr class="{cycle values="even,odd"}">
						{include file=$gContent->getXrefRecordTemplate($xrefInfo.template)}
					</tr>
				{/foreach}
			{else}
				<tr class="norecords">
					<td colspan="{if $xrefAllowEdit}6{else}3{/if}">{tr}No {$tabTitle} records found{/tr}</td>
				</tr>
			{/if}
		</tbody>
	</table>
</div>
{if $isHistory && $xrefGroup->mXrefs && $xrefAllowEdit && $gContent->isValid() && $gContent->hasExpungePermission()}
	{* A link, not a form: this tab is normally inside the page's own edit form, and a form inside a form is dropped by the browser (the click then submitted the outer one) - the row icons are links for the same reason *}
	<div>
		<a class="btn btn-default" href="{$smarty.const.LIBERTY_PKG_URL}edit_xref.php?content_id={$xrefContentId|default:$gContent->mInfo.content_id}&amp;fClearHistory=1" title="{tr}Deletes every record listed here for good (no files are touched){/tr}" onclick="return confirm('{tr}Delete all{/tr} {$xrefGroup->mXrefs|@count} {tr}history records? This cannot be undone.{/tr}');">{tr}Clear history{/tr}</a>
	</div>
{/if}
{if $allow_add && $gContent->isValid() && $gContent->hasUpdatePermission() && !$isHistory}
	<div>
		{smartlink ititle="Add record" ipackage="liberty" ifile="add_xref.php" biticon="list-add" content_id=$xrefContentId|default:$gContent->mInfo.content_id group=$xrefGroup->mSortOrder}
	</div>
{/if}
{/legend}
{/jstab}
