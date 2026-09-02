{* Admin: gateway for a site's private local scheme(s) under config/local/xref_schemes/ *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>{tr}Local Scheme{/tr}</h1>
	</div>

	<div class="body">

		<p>{tr}Directory:{/tr} <code>{$schemeDir|escape}</code></p>

		{if $schemeFiles}
			<p>{tr}Scheme files found:{/tr}</p>
			<ul>
				{foreach from=$schemeFiles item=file}
					<li><code>{$file|escape}</code></li>
				{/foreach}
			</ul>

			{form legend="" action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_local_scheme.php"}
				<input type="submit" class="btn btn-primary" name="fApply" value="{tr}Apply{/tr}" />
			{/form}
		{else}
			<p>{tr}No scheme files found for this site — nothing to apply.{/tr}</p>
		{/if}

		{if $results}
			<div class="alert alert-success">
				<p>
					{tr}Groups:{/tr}
					{$results.counts.groups_inserted} {tr}inserted{/tr},
					{$results.counts.groups_updated} {tr}updated{/tr},
					{$results.counts.groups_unchanged} {tr}unchanged{/tr}
				</p>
				<p>
					{tr}Items:{/tr}
					{$results.counts.items_inserted} {tr}inserted{/tr},
					{$results.counts.items_updated} {tr}updated{/tr},
					{$results.counts.items_unchanged} {tr}unchanged{/tr}
				</p>
				{if $results.gallery_results}
					<p>{tr}Galleries:{/tr}</p>
					<ul>
						{foreach from=$results.gallery_results item=line}
							<li>{$line|escape}</li>
						{/foreach}
					</ul>
				{/if}
			</div>
		{/if}

	</div>
</div>
{/strip}
