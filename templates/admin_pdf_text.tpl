{* Admin: convert the stored PDFs to the current text handling - see admin_pdf_text.php and LibertyPdfText *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>{tr}PDF text{/tr}</h1>
	</div>

	<div class="body">
		<p>{tr}PDFs stored before the current text handling kept the whole text in the description, or no text at all. This reads each PDF's text layer into its own row (so search finds it), puts a short summary in the description when that is empty or was the old text, and updates the search index. A PDF with no text layer (a scan) is reported and left.{/tr}</p>
		<p><strong>{$counts.done}</strong> {tr}of{/tr} <strong>{$counts.total}</strong> {tr}PDFs have their text stored{/tr}{if $counts.todo}, {$counts.todo} {tr}left{/tr}{/if}.</p>

		{if $result}
			{if $result.rows}
				<table class="table table-condensed">
					<thead><tr><th>{tr}PDF{/tr}</th><th>{tr}Result{/tr}</th><th>{tr}Characters{/tr}</th></tr></thead>
					<tbody>
						{foreach from=$result.rows item=row}
							<tr>
								<td><a href="{$smarty.const.BIT_ROOT_URL}index.php?content_id={$row.content_id}">{$row.title|escape}</a></td>
								<td>{$row.result|escape}</td>
								<td>{if $row.length}{$row.length}{/if}</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
			{else}
				<p>{tr}Nothing more to convert.{/tr}</p>
			{/if}
		{/if}

		{if $counts.todo && ( !$result || $result.more )}
			<p class="help-block">{tr}At most{/tr} {$batch} {tr}PDFs are done per press.{/tr}</p>
			{form legend="" action="`$smarty.const.LIBERTY_PKG_URL`admin/admin_pdf_text.php"}
				<input type="hidden" name="after" value="{if $result}{$result.last}{else}0{/if}" />
				<input type="submit" class="btn btn-primary" name="convert" value="{tr}Convert the next PDFs{/tr}" />
			{/form}
		{elseif !$counts.todo}
			<p><strong>{tr}Finished{/tr}</strong>: {tr}every PDF has its text stored.{/tr}</p>
		{/if}
		<p class="help-block">{tr}PDFs with no text layer stay in the count; the button skips past them.{/tr}</p>
	</div>
</div>
{/strip}
