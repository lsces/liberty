{* Fields for a plain scalar item: Value (xkey), Notes (xkey_ext), Detail (data). $xid, when given,
   prefixes the element ids so several item forms can sit on one page (the add page). *}
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

<div class="form-group">
	{formlabel label="Detail" for="`$xid`edit"}
	{forminput}
		<textarea class="form-control" name="edit" id="{$xid}edit" rows="4">{$xrefInfo.data|escape}</textarea>
	{/forminput}
</div>
{/strip}
