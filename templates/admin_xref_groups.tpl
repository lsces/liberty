{* Admin: liberty_xref_type groups *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>{tr}Xref Groups{/tr}</h1>
	</div>

	<div class="body">

		{* Package filter *}
		<form method="get" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_groups.php" class="form-inline">
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
					<a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_groups.php?content_type_guid=" class="btn btn-link">{tr}Clear{/tr}</a>
				{/if}
			</div>
		</form>

		{if $deleteError}
			<div class="alert alert-danger">{$deleteError|escape}</div>
		{/if}

		{* Add group form *}
		{if $activeGuid}
		{form legend="Add Xref Group" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_groups.php"}
			<input type="hidden" name="new_content_type_guid" value="{$activeGuid|escape}" />
			<input type="hidden" name="content_type_guid" value="{$activeGuid|escape}" />
			<div class="form-group">
				{formlabel label="Key (group)" for="x_group"}
				{forminput}<input type="text" id="x_group" name="x_group" class="form-control" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Title" for="title"}
				{forminput}<input type="text" id="title" name="title" class="form-control" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Sort Order" for="sort_order"}
				{forminput}<input type="number" id="sort_order" name="sort_order" value="1" class="form-control" style="width:5em" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Role ID" for="role_id"}
				{forminput}<input type="number" id="role_id" name="role_id" value="3" class="form-control" style="width:5em" />{/forminput}
			</div>
			<div class="form-group submit">
				<input type="submit" class="btn btn-primary" name="fAddGroup" value="{tr}Add Group{/tr}" />
			</div>
		{/form}
		{/if}

		{* Group list *}
		<table class="table table-striped data">
			<caption>{tr}Xref Groups{/tr} {if $activeGuid}— {$activeGuid|escape}{/if}</caption>
			<thead>
				<tr>
					<th>{tr}Package{/tr}</th>
					<th>{tr}Key{/tr}</th>
					<th>{tr}Title{/tr}</th>
					<th>{tr}Sort{/tr}</th>
					<th>{tr}Role{/tr}</th>
					<th>{tr}Sources{/tr}</th>
					<th>{tr}Actions{/tr}</th>
				</tr>
			</thead>
			<tbody>
			{foreach from=$xref_groups item=grp}
				<tr>
					<td>{$grp.content_type_guid|escape}</td>
					<td><code>{$grp.x_group|escape}</code></td>
					<td>{$grp.title|escape}</td>
					<td>{$grp.sort_order}</td>
					<td>{$grp.role_id}</td>
					<td>
						<a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_sources.php?content_type_guid={$grp.content_type_guid|escape}">{$grp.num_sources}</a>
					</td>
					<td>
						{if $grp.num_sources eq 0}
							<a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_groups.php?fDeleteGroup=1&amp;x_group={$grp.x_group|escape}&amp;del_content_type_guid={$grp.content_type_guid|escape}&amp;content_type_guid={$activeGuid|escape}"
							   onclick="return confirm('{tr}Delete this group?{/tr}')">{biticon ipackage="icons" iname="edit-delete" ipackage="icons" iforce=icon_text iexplain="Delete"}</a>

						{/if}
					</td>
				</tr>
			{foreachelse}
				<tr class="norecords"><td colspan="7">{tr}No groups found{/tr}</td></tr>
			{/foreach}
			</tbody>
		</table>

	</div><!-- end .body -->
</div><!-- end .admin -->
{/strip}
