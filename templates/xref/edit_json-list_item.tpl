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

			{* item_data (liberty_xref_item.data, normally an unused 'default/hint' column) —
			   the full set of possible sub-fields, registered by the package. Falls back to
			   whatever's actually stored if no hint was registered — a component's stored
			   blob is normally sparse (importers only write fields they had real values
			   for), so without this hint there's no way to add a currently-missing field. *}
			{assign var="jsonData" value=$xrefInfo.data|default:'null'|json_decode:true}
			{assign var="jsonFields" value=$xrefInfo.item_data|default:'null'|json_decode:true}
			{if !$jsonFields}
				{assign var="jsonFields" value=$jsonData|array_keys}
			{/if}
			{if $jsonFields}
				{foreach $jsonFields as $jkey}
					<div class="form-group">
						{formlabel label="`$jkey|replace:'_':' '|capitalize`" for="json_field_`$jkey`"}
						{forminput}
							{if $jsonData[$jkey]|is_array}
								{* A list (several artists, several contact ids) - shown read-only and NOT
								   submitted: a text box would turn it into the literal "Array" on save.
								   edit_xref.php keeps any stored field the form doesn't send untouched. *}
								<p class="form-control-static">{$jsonData[$jkey]|join:', '|escape}</p>
							{else}
								<input type="text" class="form-control input-small" name="json_field[{$jkey}]" id="json_field_{$jkey}" value="{$jsonData[$jkey]|escape}" />
							{/if}
						{/forminput}
					</div>
				{/foreach}
			{else}
				<p>{tr}No fields found in this record.{/tr}</p>
			{/if}

			<div class="form-group submit">
				<input type="submit" class="btn btn-default" name="fCancel"   value="{tr}Cancel{/tr}" />
				<input type="submit" class="btn btn-primary" name="fSaveXref" value="{tr}Save{/tr}" />
			</div>
		{/form}
	</div>
</div>
{/strip}
