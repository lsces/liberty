{strip}
{if $attachment.media_url}
	{assign var=id value="audio_`$caller``$attachment.attachment_id`"}
	{if $uploadTab}{assign var=id value="`$id`_tab"}{/if}
	<div id="{$id}">
		{if !$attachment.thumbnail_is_mime && $attachment.thumbnail_url.medium}
			<img src="{$attachment.thumbnail_url.medium}" alt="{$attachment.filename|escape}" /><br />
		{/if}
		<audio controls preload="none" src="{$attachment.media_url}">
			{tr}Your browser does not support the audio element.{/tr}
		</audio>
	</div>
{/if}
{/strip}
