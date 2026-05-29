	{assign var=xrefcnt value=$gContent->mInfo.$source|default:[]|@count}
	{assign var=xrefAllowEdit value=$allow_edit|default:true}
	{jstab title="$source_title ($xrefcnt)"}
	{legend legend=$source_title}
	<div class="form-group table-responsive">
		<table>
			<thead>
				<tr>
					<th>{tr}Type{/tr}</th>
					<th>{tr}Link{/tr}</th>
					<th>{tr}Key{/tr}</th>
					<th>{tr}Value{/tr}</th>
					{if $source ne 'history'}
						<th>{tr}Started{/tr}</th>
					{else}
						<th>{tr}Ended{/tr}</th>
					{/if}
					<th>{tr}Updated{/tr}</th>
					{if $xrefAllowEdit}<th>{tr}Edit{/tr}</th>{/if}
				</tr>
			</thead>
			<tbody>
				{section name=xref loop=$gContent->mInfo.$source}
					{assign var=_rowTpl value=$gContent->mInfo.$source[xref].template}
					<tr class="{cycle values="even,odd"}">
						{include file=$gContent->getXrefRecordTemplate($_rowTpl)}
					</tr>
				{sectionelse}
					<tr class="norecords">
						<td colspan="7">{tr}No {$source_title} records found{/tr}</td>
					</tr>
				{/section}
			</tbody>
		</table>
	</div>
	{if $allow_add && $gContent->isValid() && $gContent->hasUpdatePermission() && $source ne 'history'}
		<div>
			{smartlink ititle="Add record" ipackage="liberty" ifile="add_xref.php" booticon="icon-note-add" content_id=$gContent->mInfo.content_id group=$group}
		</div>
	{/if}
	{/legend}
	{/jstab}
