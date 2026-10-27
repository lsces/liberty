{* Fields for a 'value' item: Value (xkey) and Notes (xkey_ext) as two short values. *}
{strip}
{assign var=xid value=$xid|default:''}
<div class="form-group">
	{formlabel label="Type"}
	{forminput}
		<p class="form-control-static">{$xrefInfo.template_title|escape}</p>
	{/forminput}
</div>

<div class="form-group">
	{formlabel label="Value" for="`$xid`xkey"}
	{forminput}
		<input type="text" class="form-control input-small" name="xkey" id="{$xid}xkey" value="{$xrefInfo.xkey|escape}" />
	{/forminput}
</div>

<div class="form-group">
	{formlabel label="Notes" for="`$xid`xkey_ext"}
	{forminput}
		<input type="text" class="form-control input-small" name="xkey_ext" id="{$xid}xkey_ext" value="{$xrefInfo.xkey_ext|escape}" />
	{/forminput}
</div>
{/strip}
