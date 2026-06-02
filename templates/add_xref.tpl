{strip}
<div class="edit liberty">
	<div class="header">
		<h1>{tr}Add Detail{/tr}: {$gContent->getTitle()|escape}</h1>
	</div>

	<div class="body">
		{formfeedback error=$errors}

		{form id="addXrefForm"}
			<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
			<input type="hidden" name="group"  value="{$group}" />

			<div class="form-group">
				{formlabel label="Type" for="item"}
				{forminput}
					{html_options name="item" id="item" options=$xrefTypeList.list}
				{/forminput}
			</div>

			<div class="form-group">
				{formlabel label="Value" for="xkey"}
				{forminput}
					<input type="text" class="form-control" name="xkey" id="xkey" value="" />
					{formhelp note="Enter the value for this detail record."}
				{/forminput}
			</div>

			<div class="form-group">
				{formlabel label="Note" for="edit"}
				{forminput}
					<input type="text" class="form-control" name="edit" id="edit" value="" />
				{/forminput}
			</div>

			<div class="form-group submit">
				<input type="submit" class="btn btn-default" name="fCancel" value="{tr}Cancel{/tr}" />
				<input type="submit" class="btn btn-primary" name="fAddXref" value="{tr}Save{/tr}" />
			</div>
		{/form}
	</div><!-- end .body -->
</div><!-- end .liberty -->
{/strip}
