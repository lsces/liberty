{* Add a detail row. The type list shows every addable item of the group; each item has a panel with its
   own fields (xref_item_form), and choosing a type shows that panel and disables the others so only the
   chosen item's inputs are submitted. Same show/hide approach as the gallery layout options. *}
{literal}
<script>//<![CDATA[
function updateXrefPanel() {
	var select = document.getElementById('item');
	if( !select ) { return; }
	document.querySelectorAll('div.xref-add-panel').forEach(function(div, i) {
		var on = ( i === select.selectedIndex );
		div.style.display = on ? 'block' : 'none';
		div.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = !on; });
	});
}
document.addEventListener('DOMContentLoaded', function() {
	var select = document.getElementById('item');
	if( select ) { select.addEventListener('change', updateXrefPanel); }
	updateXrefPanel();
});
//]]></script>
{/literal}
{strip}
<div class="edit liberty">
	<div class="header">
		<h1>{tr}Add Detail{/tr}: {$gContent->getTitle()|escape}</h1>
	</div>

	<div class="body">
		{formfeedback error=$errors}

		{form id="addXrefForm"}
			<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
			<input type="hidden" name="group"  value="{$group}" />

			<div class="form-group">
				{formlabel label="Type" for="item"}
				{forminput}
					{html_options name="item" id="item" options=$xrefTypeList.list selected=$smarty.request.item|default:null}
				{/forminput}
			</div>

			{capture assign=panelsHtml}
				{foreach $xrefTypeList.list as $itemCode => $itemTitle}
					{* Element ids are prefixed per panel (p1_, p2_, ...) so no two fields on the page share one. *}
					{assign var=panelId value="p`$itemTitle@iteration`_"}
					<div class="xref-add-panel">
						{if $postedRow && $postedRow.item eq $itemCode}
							{xref_item_form content=$gContent item=$itemCode template=$xrefTypeList.type[$itemCode] row=$postedRow xid=$panelId}
						{else}
							{xref_item_form content=$gContent item=$itemCode template=$xrefTypeList.type[$itemCode] xid=$panelId}
						{/if}
					</div>
				{/foreach}
			{/capture}

			{if $xrefDates}
				{jstabs}
					{jstab title="Details"}
						{$panelsHtml nofilter}
					{/jstab}
					{include file="bitpackage:liberty/edit_xref_dates.tpl"}
				{/jstabs}
			{else}
				{$panelsHtml nofilter}
			{/if}

			<div class="form-group submit">
				<input type="submit" class="btn btn-default" name="fCancel" value="{tr}Cancel{/tr}" />
				<input type="submit" class="btn btn-primary" name="fAddXref" value="{tr}Save{/tr}" />
			</div>
		{/form}
	</div><!-- end .body -->
</div><!-- end .liberty -->
{/strip}
