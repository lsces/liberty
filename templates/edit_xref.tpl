{strip}
<div class="edit liberty">
	<div class="header">
		<h1>{tr}Edit Detail{/tr}: {$gContent->getTitle()|escape}</h1>
	</div>

	<div class="body">
		{formfeedback error=$errors}

		{form id="editXrefForm"}
			<input type="hidden" name="content_id" value="{$xrefInfo.content_id}" />
			<input type="hidden" name="xref_id"    value="{$xrefInfo.xref_id}" />
			<input type="hidden" name="item"        value="{$xrefInfo.item|escape}" />

			{jstabs}
				{jstab title="{tr}Details{/tr}"}
					{legend legend="Detail"}
						<div class="form-group">
							{formlabel label="Type"}
							{forminput}
								<p class="form-control-static">{$xrefInfo.template_title|escape}</p>
							{/forminput}
						</div>
						<div class="form-group">
							{formlabel label="Value" for="edit"}
							{forminput}
								<input type="text" class="form-control" name="edit" id="edit" value="{$xrefInfo.data|escape}" />
							{/forminput}
						</div>
						<div class="form-group">
							{formlabel label="Linked Content ID" for="xref"}
							{forminput}
								<input type="text" class="form-control input-small" name="xref" id="xref" value="{$xrefInfo.xref|escape}" />
							{/forminput}
						</div>
					{/legend}
				{/jstab}

				{include file="bitpackage:liberty/edit_xref_dates.tpl"}
			{/jstabs}

			<div class="form-group submit">
				<input type="submit" class="btn btn-default" name="fCancel" value="{tr}Cancel{/tr}" />
				<input type="submit" class="btn btn-primary" name="fSaveXref" value="{tr}Save{/tr}" />
			</div>
		{/form}
	</div><!-- end .body -->
</div><!-- end .liberty -->
{/strip}
