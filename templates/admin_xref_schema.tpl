{* Admin: the xref schema, a package at a time *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>{tr}Xref Schema{/tr}</h1>
	</div>

	<div class="body">
		<div class="feedback-block">{formfeedback hash=$feedback}</div>

		{* 1. Package *}
		<form method="get" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php" class="form-inline">
			<div class="form-group">
				<label for="package">{tr}Package{/tr}</label>
				<select name="package" id="package" class="form-control">
					<option value="">{tr}Choose a package{/tr}</option>
					{foreach from=$packages key=pkg item=pkgGuids}
						<option value="{$pkg|escape}" {if $package !== null && $pkg == $package}selected="selected"{/if}>{if $pkg == ''}{tr}(no registered content type){/tr}{else}{$pkg|escape}{/if} ({$pkgGuids|@count})</option>
					{/foreach}
				</select>
				<button type="submit" class="btn btn-default">{tr}Show{/tr}</button>
			</div>
		</form>

		{if $package === null}
			<table class="table table-striped data">
				<caption>{tr}Who can see each package's groups and items{/tr}</caption>
				<thead>
					<tr>
						<th>{tr}Package{/tr}</th>
						<th>{tr}Content types{/tr}</th>
						{foreach from=$roles item=roleName}<th>{$roleName|escape}</th>{/foreach}
						<th>{tr}Other{/tr}</th>
					</tr>
				</thead>
				<tbody>
				{foreach from=$packageSummary key=pkg item=sum}
					<tr>
						<td><a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php?package={$pkg|escape:'url'}">{if $pkg == ''}{tr}(no registered content type){/tr}{else}{$pkg|escape}{/if}</a></td>
						<td>{$sum.types}</td>
						{foreach from=$roles key=rid item=roleName}<td>{$sum.roles.$rid|default:'-'}</td>{/foreach}
						<td>{$sum.other|default:'-'|escape}</td>
					</tr>
				{/foreach}
				</tbody>
			</table>
			<p class="help-block">{tr}Each figure counts the groups and items a role gates. Registered is the usual setting; Anonymous means a visitor who is not logged in sees it.{/tr}</p>
		{else}

			{if $related}
				<p class="help-block">{tr}Overlaps in use with{/tr}:&nbsp;
					{foreach from=$related item=other name=rel}<a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php?package={$other|escape:'url'}">{$other|escape}</a>{if !$smarty.foreach.rel.last}, {/if}{/foreach}
				</p>
			{/if}

			{* 2. The package's content types and who can see them, with the package switch *}
			<form method="post" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php">
				<input type="hidden" name="tk" value="{$gBitUser->mTicket}" />
				<input type="hidden" name="package" value="{$package|escape}" />
				<table class="table table-striped data">
					<caption>{if $package == ''}{tr}(no registered content type){/tr}{else}{$package|escape}{/if} &mdash; {tr}groups / items gated by each role{/tr}</caption>
					<thead>
						<tr>
							<th></th>
							<th>{tr}Content type{/tr}</th>
							{foreach from=$roles item=roleName}<th>{$roleName|escape}</th>{/foreach}
							<th>{tr}Other{/tr}</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$guids item=g}
						<tr>
							<td><input type="checkbox" name="guids[]" value="{$g|escape}" checked="checked" /></td>
							<td><a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php?package={$package|escape:'url'}&amp;guid={$g|escape:'url'}">{$g|escape}</a></td>
							{foreach from=$roles key=rid item=roleName}
								<td>{if $typeCounts.$g.$rid}{$typeCounts.$g.$rid.groups} / {$typeCounts.$g.$rid.items}{else}-{/if}</td>
							{/foreach}
							<td>{if $typeOther.$g}{$typeOther.$g|escape}{else}-{/if}</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
				<div class="form-group submit">
					<input type="submit" class="btn btn-primary" name="fOpenPackage" value="{tr}Open ticked to anonymous{/tr}"
						onclick="return confirm('{tr}Show the ticked content types to visitors who are not logged in? Every group and item now set to Registered moves to Anonymous. Editors and Administrators rows stay as they are.{/tr}')" />
					<input type="submit" class="btn btn-default" name="fClosePackage" value="{tr}Registered only{/tr}"
						onclick="return confirm('{tr}Hide the ticked content types from visitors who are not logged in? Every group and item now set to Anonymous moves to Registered.{/tr}')" />
				</div>
			</form>

			{* 3. One content type's groups *}
			<form method="get" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php" class="form-inline">
				<input type="hidden" name="package" value="{$package|escape}" />
				<div class="form-group">
					<label for="guid">{tr}Content type{/tr}</label>
					<select name="guid" id="guid" class="form-control">
						<option value="">{tr}Choose a content type{/tr}</option>
						{foreach from=$guids item=g}
							<option value="{$g|escape}" {if $g == $guid}selected="selected"{/if}>{$g|escape}</option>
						{/foreach}
					</select>
					<button type="submit" class="btn btn-default">{tr}Show{/tr}</button>
				</div>
			</form>

			{if $guid !== null}
				<p>
					<a class="btn btn-default btn-sm" href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_groups.php?content_type_guid={$guid|escape:'url'}">{tr}Add group{/tr}</a>
				</p>
				<table class="table table-striped data">
					<caption>{$guid|escape} &mdash; {tr}groups{/tr}</caption>
					<thead>
						<tr>
							<th>{tr}Group{/tr}</th>
							<th>{tr}Title{/tr}</th>
							<th>{tr}Sort{/tr}</th>
							<th>{tr}Items{/tr}</th>
							<th>{tr}Role{/tr}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$groups item=grp}
						<tr{if $grp.x_group == $group} class="active"{/if}>
							<td><a href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php?package={$package|escape:'url'}&amp;guid={$guid|escape:'url'}&amp;x_group={$grp.x_group|escape:'url'}"><code>{$grp.x_group|escape}</code></a></td>
							<td>{$grp.title|escape}</td>
							<td>{$grp.sort_order}</td>
							<td>{$grp.num_sources}</td>
							<td>
								<form method="post" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php" class="form-inline">
									<input type="hidden" name="tk" value="{$gBitUser->mTicket}" />
									<input type="hidden" name="package" value="{$package|escape}" />
									<input type="hidden" name="guid" value="{$guid|escape}" />
									<input type="hidden" name="x_group" value="{$group|escape}" />
									<input type="hidden" name="row_group" value="{$grp.x_group|escape}" />
									<select name="role_id" class="form-control input-sm">
										{foreach from=$roles key=rid item=roleName}<option value="{$rid}" {if $rid == $grp.role_id}selected="selected"{/if}>{$roleName|escape}</option>{/foreach}
										{if !isset($roles[$grp.role_id])}<option value="{$grp.role_id}" selected="selected">{$grp.role_id}</option>{/if}
									</select>
									<input type="submit" class="btn btn-default btn-sm" name="fSetGroupRole" value="{tr}Save{/tr}" />
								</form>
							</td>
							<td><a class="btn btn-link btn-sm" href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_groups.php?content_type_guid={$guid|escape:'url'}&amp;show_group={$grp.x_group|escape:'url'}">{tr}Manage{/tr}</a></td>
						</tr>
					{foreachelse}
						<tr class="norecords"><td colspan="6">{tr}No groups at this content type; its items sit in groups at the package level{/tr}</td></tr>
					{/foreach}
					</tbody>
				</table>

				{* 4. One group's items *}
				{if $groups}
					<form method="get" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php" class="form-inline">
						<input type="hidden" name="package" value="{$package|escape}" />
						<input type="hidden" name="guid" value="{$guid|escape}" />
						<div class="form-group">
							<label for="x_group">{tr}Group{/tr}</label>
							<select name="x_group" id="x_group" class="form-control">
								<option value="">{tr}Choose a group{/tr}</option>
								{foreach from=$groups item=grp}
									<option value="{$grp.x_group|escape}" {if $grp.x_group == $group}selected="selected"{/if}>{$grp.x_group|escape} ({$grp.num_sources})</option>
								{/foreach}
							</select>
							<button type="submit" class="btn btn-default">{tr}Show items{/tr}</button>
						</div>
					</form>
				{/if}

				{if $itemsShown}
					<p>
						<a class="btn btn-default btn-sm" href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_sources.php?content_type_guid={$guid|escape:'url'}{if $group !== null}&amp;show_group={$group|escape:'url'}{/if}">{tr}Add item{/tr}</a>
					</p>
					<table class="table table-striped data">
						<caption>{$guid|escape}{if $group !== null} / {$group|escape}{/if} &mdash; {tr}items{/tr}</caption>
						<thead>
							<tr>
								{if $itemsAllGroups}<th>{tr}Group{/tr}</th>{/if}
								<th>{tr}Item{/tr}</th>
								<th>{tr}Title{/tr}</th>
								<th>{tr}Multiple{/tr}</th>
								<th>{tr}Template{/tr}</th>
								<th>{tr}Role{/tr}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
						{foreach from=$items item=itm}
							<tr>
								{if $itemsAllGroups}<td><code>{$itm.x_group|escape}</code></td>{/if}
								<td><code>{$itm.item|escape}</code></td>
								<td>{$itm.cross_ref_title|escape}</td>
								<td>{$itm.multiple}</td>
								<td>{$itm.template|escape}</td>
								<td>
									<form method="post" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_schema.php" class="form-inline">
										<input type="hidden" name="tk" value="{$gBitUser->mTicket}" />
										<input type="hidden" name="package" value="{$package|escape}" />
										<input type="hidden" name="guid" value="{$guid|escape}" />
										<input type="hidden" name="x_group" value="{$group|escape}" />
										<input type="hidden" name="row_group" value="{$itm.x_group|escape}" />
										<input type="hidden" name="item" value="{$itm.item|escape}" />
										<select name="role_id" class="form-control input-sm">
											{foreach from=$roles key=rid item=roleName}<option value="{$rid}" {if $rid == $itm.role_id}selected="selected"{/if}>{$roleName|escape}</option>{/foreach}
											{if !isset($roles[$itm.role_id])}<option value="{$itm.role_id}" selected="selected">{$itm.role_id}</option>{/if}
										</select>
										<input type="submit" class="btn btn-default btn-sm" name="fSetItemRole" value="{tr}Save{/tr}" />
									</form>
								</td>
								<td><a class="btn btn-link btn-sm" href="{$smarty.const.LIBERTY_PKG_URL}admin/admin_xref_sources.php?content_type_guid={$guid|escape:'url'}&amp;show_group={$itm.x_group|escape:'url'}&amp;show_item={$itm.item|escape:'url'}">{tr}Manage{/tr}</a></td>
							</tr>
						{foreachelse}
							<tr class="norecords"><td colspan="7">{tr}No items{/tr}</td></tr>
						{/foreach}
						</tbody>
					</table>
				{/if}
			{/if}
		{/if}
	</div><!-- end .body -->
</div><!-- end .admin -->
{/strip}
