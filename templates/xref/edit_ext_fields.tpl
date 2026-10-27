{* Generic edit form for an item whose value lives in xkey_ext (the longer column), with an optional short note in xkey - the
   convention names, dates and places already follow wherever Wikidata, a credit or an importer wrote them. Set an item's
   template to 'ext' and, to name the fields, its liberty_xref_item.data to {"labels":{"value":"Name","note":"Notes"}} (both
   optional; they default to Value and Notes), and add "note":false to leave the note box out altogether (a web address, say). The row's own `data` is left alone, so a source marker stored there survives an
   edit. A row typed on the generic text form has its value in xkey: that shows as the Value here and is moved to xkey_ext on
   the next Save.
   (Fields only - the page around them is liberty/edit_xref_page.tpl.) *}
{strip}
{assign var=xid value=$xid|default:''}
{assign var=extLabels value=$xrefInfo.item_data|default:'null'|json_decode:true}
{assign var=valueLabel value='Value'}
{assign var=noteLabel value='Notes'}
{if is_array($extLabels) && is_array($extLabels.labels)}
	{if $extLabels.labels.value ne ''}{assign var=valueLabel value=$extLabels.labels.value}{/if}
	{if $extLabels.labels.note ne ''}{assign var=noteLabel value=$extLabels.labels.note}{/if}
{/if}
{assign var=showNote value=true}
{if is_array($extLabels) && isset($extLabels.note) && !$extLabels.note}{assign var=showNote value=false}{/if}
{if $xrefInfo.xkey_ext ne ''}{assign var=extValue value=$xrefInfo.xkey_ext}{assign var=extNote value=$xrefInfo.xkey}{else}{assign var=extValue value=$xrefInfo.xkey}{assign var=extNote value=''}{/if}
<div class="form-group">
	{formlabel label="Type"}
	{forminput}
		<p class="form-control-static">{$xrefInfo.template_title|escape}</p>
	{/forminput}
</div>

<div class="form-group">
	{formlabel label=$valueLabel for="`$xid`xkey_ext"}
	{forminput}
		<input type="text" class="form-control{if $showNote} input-small{/if}" name="xkey_ext" id="{$xid}xkey_ext" value="{$extValue|escape}" />
	{/forminput}
</div>

{if $showNote}
<div class="form-group">
	{formlabel label=$noteLabel for="`$xid`xkey"}
	{forminput}
		<input type="text" class="form-control input-small" name="xkey" id="{$xid}xkey" value="{$extNote|escape}" />
	{/forminput}
</div>
{elseif $xrefInfo.xkey_ext eq ''}
{* No note box: a row typed on the generic form kept its value in xkey, which moves to xkey_ext on this Save. *}
<input type="hidden" name="xkey" value="" />
{/if}
{/strip}
