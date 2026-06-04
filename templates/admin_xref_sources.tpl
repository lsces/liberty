{* Admin: liberty_xref_source entries *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>{tr}Xref Sources{/tr}</h1>
	</div>

	<div class="body">

		{* Package filter *}
		<form method="get" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_sources.php" class="form-inline">
			<div class="form-group">
				<label for="content_type_guid">{tr}Package{/tr}</label>
				<select name="content_type_guid" id="content_type_guid" class="form-control">
					<option value="">{tr}— All packages —{/tr}</option>
					{foreach from=$guidList item=guid}
						<option value="{$guid|escape}" {if $guid eq $activeGuid}selected="selected"{/if}>{$guid|escape}</option>
					{/foreach}
				</select>
				<button type="submit" class="btn btn-default">{tr}Filter{/tr}</button>
				{if $activeGuid}
					<a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_sources.php?content_type_guid=" class="btn btn-link">{tr}Clear{/tr}</a>
				{/if}
			</div>
		</form>

		{if $deleteError}
			<div class="alert alert-danger">{$deleteError|escape}</div>
		{/if}

		{* Add source form — only when a package is selected (need a group to assign to) *}
		{if $activeGuid && $xref_groups}
		{form legend="Add Xref Source" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_sources.php"}
			<input type="hidden" name="new_content_type_guid" value="{$activeGuid|escape}" />
			<input type="hidden" name="content_type_guid" value="{$activeGuid|escape}" />
			<div class="form-group">
				{formlabel label="Source key" for="source"}
				{forminput}<input type="text" id="source" name="source" class="form-control" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Group" for="x_group"}
				{forminput}
					<select name="x_group" id="x_group" class="form-control">
						{foreach from=$xref_groups item=grp}
							<option value="{$grp.x_group|escape}">{$grp.title|escape} ({$grp.x_group|escape})</option>
						{/foreach}
					</select>
				{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Title" for="cross_ref_title"}
				{forminput}<input type="text" id="cross_ref_title" name="cross_ref_title" class="form-control" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Template" for="template"}
				{forminput}<input type="text" id="template" name="template" placeholder="text / address / phone …" class="form-control" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Href" for="cross_ref_href"}
				{forminput}<input type="text" id="cross_ref_href" name="cross_ref_href" class="form-control" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Multiple" for="multiple"}
				{forminput}<input type="number" id="multiple" name="multiple" value="0" class="form-control" style="width:5em" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Role ID" for="role_id"}
				{forminput}<input type="number" id="role_id" name="role_id" value="3" class="form-control" style="width:5em" />{/forminput}
			</div>
			<div class="form-group submit">
				<input type="submit" class="btn btn-primary" name="fAddSource" value="{tr}Add Source{/tr}" />
			</div>
		{/form}
		{/if}

		{* Source list *}
		<table class="table table-striped data">
			<caption>{tr}Xref Sources{/tr} {if $activeGuid}— {$activeGuid|escape}{/if}</caption>
			<thead>
				<tr>
					<th>{tr}Package{/tr}</th>
					<th>{tr}Source{/tr}</th>
					<th>{tr}Group{/tr}</th>
					<th>{tr}Title{/tr}</th>
					<th>{tr}Template{/tr}</th>
					<th>{tr}Multiple{/tr}</th>
					<th>{tr}Role{/tr}</th>
					<th>{tr}Entries{/tr}</th>
					<th>{tr}Actions{/tr}</th>
				</tr>
			</thead>
			<tbody>
			{foreach from=$xref_sources item=src}
				<tr>
					<td>{$src.content_type_guid|escape}</td>
					<td><code>{$src.item|escape}</code></td>
					<td><code>{$src.x_group|escape}</code></td>
					<td>{$src.cross_ref_title|escape}</td>
					<td>{$src.template|escape}</td>
					<td>{$src.multiple}</td>
					<td>{$src.role_id}</td>
					<td>{$src.num_entries}</td>
					<td>
						{if $src.num_entries eq 0}
							<a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_sources.php?fDeleteSource=1&amp;source={$src.item|escape}&amp;del_content_type_guid={$src.content_type_guid|escape}&amp;content_type_guid={$activeGuid|escape}"
							   onclick="return confirm('{tr}Delete this source?{/tr}')">{biticon ipackage="icons" iname="edit-delete" ipackage="icons" iforce=icon_text iexplain="Delete"}</a>
						{/if}
					</td>
				</tr>
			{foreachelse}
				<tr class="norecords"><td colspan="9">{tr}No sources found{/tr}</td></tr>
			{/foreach}
			</tbody>
		</table>

	</div><!-- end .body -->
</div><!-- end .admin -->
{/strip}
