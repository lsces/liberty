{* The page around one xref row's edit fields: heading, form, the ids every row needs, Save/Cancel.
   The inputs themselves come from the item's fields template ($fieldsTemplate, see
   LibertyContent::getXrefFieldsTemplate()), so the add page can show the same fields. *}
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
			<input type="hidden" name="xorder"     value="{$xrefInfo.xorder|escape}" />

			{if $xrefDates}
				{* Dates belong to the row, not the item type: one "Time period" tab around whatever fields the item has. *}
				{jstabs}
					{jstab title="Details"}
						{include file=$fieldsTemplate}
					{/jstab}
					{include file="bitpackage:liberty/edit_xref_dates.tpl"}
				{/jstabs}
			{else}
				{include file=$fieldsTemplate}
			{/if}

			<div class="form-group submit">
				<input type="submit" class="btn btn-default" name="fCancel"   value="{tr}Cancel{/tr}" />
				<input type="submit" class="btn btn-primary" name="fSaveXref" value="{tr}Save{/tr}" />
			</div>
		{/form}
	</div>
</div>
{/strip}
