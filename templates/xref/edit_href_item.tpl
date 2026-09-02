{strip}
<div class="edit liberty">
	<div class="header">
		<h1>{tr}Edit{/tr}: {$gContent->getTitle()|escape}</h1>
	</div>
	<div class="body">
		{formfeedback error=$errors}
		{form id="editXrefForm"}
			<input type="hidden" name="content_id" value="{$xrefInfo.content_id|escape}" />
			<input type="hidden" name="xref_id"    value="{$xrefInfo.xref_id|escape}" />
			<input type="hidden" name="item"       value="{$xrefInfo.item|escape}" />

			{if $xrefInfo.cross_ref_href}
			<div class="form-group">
				{formlabel label="Link prefix"}
				{forminput}
					<p class="form-control-static">{$xrefInfo.cross_ref_href|escape}</p>
				{/forminput}
			</div>
			{/if}

			<div class="form-group">
				{formlabel label="External ID" for="xkey"}
				{forminput}
					<input type="text" class="form-control input-small" name="xkey" id="xkey" value="{$xrefInfo.xkey|escape}" />
					{formhelp note="Appended to the link prefix above to build the full URL."}
				{/forminput}
			</div>

			<div class="form-group">
				{formlabel label="Display Label" for="xkey_ext"}
				{forminput}
					<input type="text" class="form-control" name="xkey_ext" id="xkey_ext" value="{$xrefInfo.xkey_ext|escape}" />
				{/forminput}
			</div>

			<div class="form-group">
				{formlabel label="Note" for="edit"}
				{forminput}
					<textarea class="form-control" name="edit" id="edit" rows="4">{$xrefInfo.data|escape}</textarea>
				{/forminput}
			</div>

			<div class="form-group submit">
				<input type="submit" class="btn btn-default" name="fCancel"   value="{tr}Cancel{/tr}" />
				<input type="submit" class="btn btn-primary" name="fSaveXref" value="{tr}Save{/tr}" />
			</div>
		{/form}
	</div>
</div>
{/strip}
