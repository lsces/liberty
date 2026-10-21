{* Shown above a content item's xref tabs when LibertyXrefType::loadContent() found live rows no item definition covers -
   the type's xref scheme was never applied (or a database restore wiped it). Pass the loaded LibertyXrefContent as
   xrefInfo and the content type guid as xrefTypeGuid. *}
{if $xrefInfo && $xrefInfo->mUndefinedItems}
	<div class="alert alert-danger"><p>{$xrefInfo->getSchemeProblem($xrefTypeGuid)|escape}</p></div>
{/if}
