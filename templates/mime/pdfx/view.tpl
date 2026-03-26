{strip}
{if !empty($attachment.source_file)}
    <div id="pdf-container" style="width: 100%; height: 600px;"></div>
        <script>PDFObject.embed("{$smarty.const.BIT_ROOT_URI}{$attachment.source_url}", "#pdf-container");</script>
	</div>

	{include file="bitpackage:liberty/mime_meta_inc.tpl"}
{else}
	{include file=$gLibertySystem->getMimeTemplate('view', $smarty.const.LIBERTY_DEFAULT_MIME_HANDLER)}
{/if}
{/strip}
