{* Fields for a json-list/json-text item: one input per key. (Fields only - the page around them is
   liberty/edit_xref_page.tpl; $xid prefixes element ids on the add page.)
   Everything comes prepared by LibertyContent::enrichXrefDisplay(): json_fields (the keys to offer: the
   item's field list, else whatever the row holds), json_values (the row's JSON, or for a row with no JSON
   yet the plain columns the item's "legacy" map names) and item_hint (the item's options, see
   LibertyXrefType::parseItemHint(): labels, wide). A value that is a list (several artists, several
   contact ids) is shown read-only and NOT submitted - a text box would turn it into the literal "Array" -
   and LibertyXrefType::applyJsonFields() keeps any stored key the form does not send. *}
{strip}
{assign var=xid value=$xid|default:''}
{assign var=jsonData value=$xrefInfo.json_values|default:[]}
{if $xrefInfo.json_fields}
	{foreach $xrefInfo.json_fields as $jkey}
		{assign var=jlabel value=$jkey|replace:'_':' '|capitalize}
		{if $xrefInfo.item_hint.labels[$jkey]}{assign var=jlabel value=$xrefInfo.item_hint.labels[$jkey]}{/if}
		<div class="form-group">
			{formlabel label=$jlabel for="`$xid`json_field_`$jkey`"}
			{forminput}
				{if $jsonData[$jkey]|is_array}
					<p class="form-control-static">{$jsonData[$jkey]|join:', '|escape}</p>
				{else}
					<input type="text" class="form-control{if !$xrefInfo.item_hint.wide} input-small{/if}" name="json_field[{$jkey}]" id="{$xid}json_field_{$jkey}" value="{$jsonData[$jkey]|escape}" />
				{/if}
			{/forminput}
		</div>
	{/foreach}
{else}
	<p>{tr}No fields found in this record.{/tr}</p>
{/if}
{/strip}
