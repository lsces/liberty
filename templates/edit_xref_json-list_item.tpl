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

			{assign var="jsonData" value=$xrefInfo.data|json_decode:true}
			{if $jsonData}
				{foreach $jsonData as $jkey => $jval}
					<div class="form-group">
						{formlabel label="`$jkey|replace:'_':' '|capitalize`" for="json_field_`$jkey`"}
						{forminput}
							<input type="text" class="form-control input-small" name="json_field[{$jkey}]" id="json_field_{$jkey}" value="{$jval|escape}" />
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
