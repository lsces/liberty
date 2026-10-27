{* The picker for an item that links to another piece of content: a search box that fills the hidden
   `xref` id. Where to search comes from the content class (enrichXrefDisplay() sets $xrefInfo.lookup:
   url, params, label, help). Built on BitComponentTypeahead and written to work when the form is
   injected into a page after it loaded (it loads the script itself if it is missing), with element ids
   prefixed by $xid. Typing again clears the link until a result is chosen. With no lookup configured the
   linked item is shown, not editable. *}
{strip}
{assign var=xid value=$xid|default:''}
{if $xrefInfo.lookup}
<div class="form-group">
	{formlabel label=$xrefInfo.lookup.label|default:$xrefInfo.template_title for="`$xid`link_search"}
	{forminput}
		<input type="hidden" name="xref" id="{$xid}xref" value="{$xrefInfo.xref|escape}" />
		<div class="dropdown">
			<input type="text" class="form-control" id="{$xid}link_search" autocomplete="off"
				value="{$xrefInfo.linked_title|escape}" placeholder="{tr}Type to search…{/tr}" />
			<ul id="{$xid}link_dropdown" class="dropdown-menu col-xs-12 pre-scrollable"></ul>
		</div>
		{if $xrefInfo.lookup.help}{formhelp note=$xrefInfo.lookup.help}{/if}
	{/forminput}
</div>
<script>
(function($) {
	function start() {
		BitComponentTypeahead({
			input:       '#{$xid}link_search',
			dropdown:    '#{$xid}link_dropdown',
			idField:     '#{$xid}xref',
			lookupUrl:   '{$xrefInfo.lookup.url}',
			extraParams: function() { return {$xrefInfo.lookup.params|default:[]|json_encode}; },
			label:       function(row) { return row.title + (row.scref ? ' (' + row.scref + ')' : ''); }
		});
	}
	if (window.BitComponentTypeahead) { start(); } else { $.getScript('{$smarty.const.KERNEL_PKG_URL}scripts/BitComponentTypeahead.js', start); }
}(jQuery));
</script>
{elseif $xrefInfo.xref}
<div class="form-group">
	{formlabel label="Linked to"}
	{forminput}
		<p class="form-control-static">
			<a href="{$smarty.const.BIT_ROOT_URL}index.php?content_id={$xrefInfo.xref|escape}">{$xrefInfo.linked_title|default:$xrefInfo.xref|escape}</a>
		</p>
		{formhelp note="Not editable here - this item has no picker configured; its package's own page handles changing the link."}
	{/forminput}
</div>
{/if}
{/strip}
