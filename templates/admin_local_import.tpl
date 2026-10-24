{* Admin: gateway for a site's private one-off imports under config/local/import/ *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>{tr}Local Imports{/tr}</h1>
	</div>

	<div class="body">

		<p>{tr}Directory:{/tr} <code>{$importDir|escape}</code></p>

		{if $result}
			<div class="alert alert-{if $result.apply}warning{else}info{/if}">
				<p><strong>{$result.title|escape}</strong> - {if $result.apply}{tr}applied{/tr}{else}{tr}dry run, nothing written{/tr}{/if}</p>
				<pre>{$result.text|escape}</pre>
			</div>
		{/if}

		{if $imports}
			{foreach from=$imports item=import}
				{form legend=$import.title action="{$smarty.const.LIBERTY_PKG_URL}admin/admin_local_import.php"}
					<input type="hidden" name="fImport" value="{$import.file|escape}" />
					{if $import.description}<p>{$import.description|escape}</p>{/if}
					<p><code>{$import.file|escape}</code></p>
					{foreach from=$import.params key=name item=param}
						<div class="form-group">
							{formlabel label=$param.label for="p-`$import.file`-`$name`"}
							{forminput}
								<input type="text" name="p[{$import.file|escape}][{$name|escape}]" id="p-{$import.file|escape}-{$name|escape}" value="{$param.value|escape}" size="{$param.size}" />
							{/forminput}
						</div>
					{/foreach}
					<div class="form-group submit">
						<input type="submit" class="btn btn-default" name="fDryRun" value="{tr}Dry run{/tr}" />
						<input type="submit" class="btn btn-primary" name="fApply" value="{tr}Apply{/tr}" onclick="return confirm('{tr}Run this import for real?{/tr}');" />
					</div>
				{/form}
			{/foreach}
		{else}
			<p>{tr}No import files found for this site - nothing to run.{/tr}</p>
		{/if}

	</div>
</div>
{/strip}
