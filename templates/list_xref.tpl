{assign var=xrefAllowEdit value=$allow_edit|default:true}
{if isset($xrefGroup)}
	{* New path: called with a LibertyXrefGroup object *}
	{assign var=tabTitle value=$xrefGroup->mTitle}
	{assign var=isHistory value=($xrefGroup->mXGroup eq 'history')}
	{jstab title="`$tabTitle` ({$xrefGroup->mXrefs|@count})"}
	{legend legend=$tabTitle}
	<div class="form-group table-responsive">
		<table class="table">
			<thead>
				<tr>
					<th style="width:30%">{tr}Type{/tr}</th>
					<th style="width:30%">{tr}Value{/tr}</th>
					<th style="width:40%">{tr}Notes{/tr}</th>
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
	{if $allow_add && $gContent->isValid() && $gContent->hasUpdatePermission() && !$isHistory}
		<div>
			{smartlink ititle="Add record" ipackage="liberty" ifile="add_xref.php" biticon="list-add" content_id=$gContent->mInfo.content_id group=$xrefGroup->mSortOrder}
		</div>
	{/if}
	{/legend}
	{/jstab}
{else}
	{* Legacy path: called with $source and $source_title (stock, other packages) *}
	{assign var=xrefcnt value=$gContent->mInfo.$source|default:[]|@count}
	{jstab title="$source_title ($xrefcnt)"}
	{legend legend=$source_title}
	<div class="form-group table-responsive">
		<table class="table">
			<thead>
				<tr>
					<th style="width:30%">{tr}Type{/tr}</th>
					<th style="width:30%">{tr}Key{/tr}</th>
					<th style="width:40%">{tr}Value{/tr}</th>
					{if $xrefAllowEdit}
						{if $source ne 'history'}<th>{tr}Started{/tr}</th>{else}<th>{tr}Ended{/tr}</th>{/if}
						<th>{tr}Updated{/tr}</th>
						<th>{tr}Edit{/tr}</th>
					{/if}
				</tr>
			</thead>
			<tbody>
				{section name=xref loop=$gContent->mInfo.$source}
					{assign var=xrefInfo value=$gContent->mInfo.$source[xref]}
					<tr class="{cycle values="even,odd"}">
						{include file=$gContent->getXrefRecordTemplate($xrefInfo.template)}
					</tr>
				{sectionelse}
					<tr class="norecords">
						<td colspan="{if $xrefAllowEdit}6{else}3{/if}">{tr}No {$source_title} records found{/tr}</td>
					</tr>
				{/section}
			</tbody>
		</table>
	</div>
	{if $allow_add && $gContent->isValid() && $gContent->hasUpdatePermission() && $source ne 'history'}
		<div>
			{smartlink ititle="Add record" ipackage="liberty" ifile="add_xref.php" biticon="list-add" content_id=$gContent->mInfo.content_id group=$group}
		</div>
	{/if}
	{/legend}
	{/jstab}
{/if}
