{strip}
{if !empty($attachment.source_file)}
	<iframe src="{$smarty.const.UTIL_PKG_URL}javascript/pdfjs/web/viewer.html?file={$smarty.const.BIT_ROOT_URI}{$attachment.source_url}?zoom=page-width
		{if !empty($highlight)}#search={$highlight|escape}{/if}" width="100%" height="600px"></iframe>
	{include file="bitpackage:liberty/mime_meta_inc.tpl"}
{else}
	{include file=$gLibertySystem->getMimeTemplate('view', $smarty.const.LIBERTY_DEFAULT_MIME_HANDLER)}
{/if}
{/strip}
