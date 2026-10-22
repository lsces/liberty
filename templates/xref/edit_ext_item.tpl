{* Generic edit form for an item whose value lives in xkey_ext (the longer column), with an optional short note in xkey - the
   convention names, dates and places already follow wherever Wikidata, a credit or an importer wrote them. Set an item's
   template to 'ext' and, to name the fields, its liberty_xref_item.data to {"labels":{"value":"Name","note":"Notes"}} (both
   optional; they default to Value and Notes). The row's own `data` is left alone, so a source marker stored there survives an
   edit. A row typed on the generic text form has its value in xkey: that shows as the Value here and is moved to xkey_ext on
   the next Save. *}
{strip}
{assign var=extLabels value=$xrefInfo.item_data|default:'null'|json_decode:true}
{assign var=valueLabel value='Value'}
{assign var=noteLabel value='Notes'}
{if is_array($extLabels) && is_array($extLabels.labels)}
	{if $extLabels.labels.value ne ''}{assign var=valueLabel value=$extLabels.labels.value}{/if}
	{if $extLabels.labels.note ne ''}{assign var=noteLabel value=$extLabels.labels.note}{/if}
{/if}
{if $xrefInfo.xkey_ext ne ''}{assign var=extValue value=$xrefInfo.xkey_ext}{assign var=extNote value=$xrefInfo.xkey}{else}{assign var=extValue value=$xrefInfo.xkey}{assign var=extNote value=''}{/if}
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

			<div class="form-group">
				{formlabel label="Type"}
				{forminput}
					<p class="form-control-static">{$xrefInfo.template_title|escape}</p>
				{/forminput}
			</div>

			<div class="form-group">
				{formlabel label=$valueLabel for="xkey_ext"}
				{forminput}
					<input type="text" class="form-control input-small" name="xkey_ext" id="xkey_ext" value="{$extValue|escape}" />
				{/forminput}
			</div>

			<div class="form-group">
				{formlabel label=$noteLabel for="xkey"}
				{forminput}
					<input type="text" class="form-control input-small" name="xkey" id="xkey" value="{$extNote|escape}" />
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
