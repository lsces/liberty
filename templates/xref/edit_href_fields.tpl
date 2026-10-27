{* Fields for an 'href' item: an external ID appended to the item's link prefix, with a display label. *}
{strip}
{assign var=xid value=$xid|default:''}
{if $xrefInfo.cross_ref_href}
<div class="form-group">
	{formlabel label="Link prefix"}
	{forminput}
		<p class="form-control-static">{$xrefInfo.cross_ref_href|escape}</p>
	{/forminput}
</div>
{/if}

<div class="form-group">
	{formlabel label="External ID" for="`$xid`xkey"}
	{forminput}
		<input type="text" class="form-control input-small" name="xkey" id="{$xid}xkey" value="{$xrefInfo.xkey|escape}" />
		{formhelp note="Appended to the link prefix above to build the full URL."}
	{/forminput}
</div>

<div class="form-group">
	{formlabel label="Display Label" for="`$xid`xkey_ext"}
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
