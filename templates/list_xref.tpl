	{assign var=xrefcnt value=$gContent->mInfo.$source|default:[]|@count}
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
					<th>{tr}Edit{/tr}</th>
				</tr>
			</thead>
			<tbody>
				{section name=xref loop=$gContent->mInfo.$source}
					<tr class="{cycle values="even,odd"}">
						{include file="bitpackage:liberty/view_xref_`$gContent->mInfo.$source[xref].template`_record.tpl"}
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
