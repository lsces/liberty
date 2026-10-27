{* Fields for a 'link' item: the picker for the linked content, then Value, Extended Value and Note. *}
{strip}
{assign var=xid value=$xid|default:''}
{include file="bitpackage:liberty/xref/edit_link_picker_inc.tpl"}

<div class="form-group">
	{formlabel label="Value" for="`$xid`xkey"}
	{forminput}
		<input type="text" class="form-control input-small" name="xkey" id="{$xid}xkey" value="{$xrefInfo.xkey|escape}" />
	{/forminput}
</div>

<div class="form-group">
	{formlabel label="Extended Value" for="`$xid`xkey_ext"}
	{forminput}
		<input type="text" class="form-control" name="xkey_ext" id="{$xid}xkey_ext" value="{$xrefInfo.xkey_ext|escape}" />
	{/forminput}
</div>

<div class="form-group">
	{formlabel label="Note" for="`$xid`edit"}
	{forminput}
		<textarea class="form-control" name="edit" id="{$xid}edit" rows="4">{$xrefInfo.data|escape}</textarea>
	{/forminput}
</div>
{/strip}
