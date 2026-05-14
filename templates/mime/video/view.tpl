{strip}
{if $attachment.media_url}
	<div class="form-group aligncenter">
		{include file="bitpackage:liberty/mime/video/player.tpl"}
	</div>
{elseif $attachment.status.processing}
	<div class="form-group aligncenter">
		<a href="{$attachment.download_url}">
			{assign var=size value=$gBitSystem->getConfig('treasury_item_view_thumb')}
			<img src="{$attachment.thumbnail_url.$size}{$refresh}" alt="{$gContent->getTitle()}" title="{$gContent->getTitle()}" />
			<br />{$gContent->getTitle()|escape}
		</a>
	</div>
	{formfeedback warning="{tr}The video is being processed. please try to reload in a couple of minutes.{/tr}"}
{elseif $attachment.status.error}
	<div class="form-group aligncenter">
		<a href="{$attachment.download_url}">
			{assign var=size value=$gBitSystem->getConfig('treasury_item_view_thumb')}
			<img src="{$attachment.thumbnail_url.$size}{$refresh}" alt="{$gContent->getTitle()}" title="{$gContent->getTitle()}" />
			<br />{$gContent->getTitle()|escape}
		</a>
	</div>
	{formfeedback error="{tr}The Video could not be processed. You can upload a different version of the film or simply leave as is.{/tr}"}
{/if}

{if !empty( $attachment.meta.duration )}
	<div class="form-group">
		{formlabel label="Duration" for=""}
		{forminput}
			{$attachment.meta.duration|display_duration}
		{/forminput}
	</div>
{/if}

{include file="bitpackage:liberty/mime_meta_inc.tpl"}
{/strip}
