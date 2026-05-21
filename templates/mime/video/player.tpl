{strip}
{if $attachment.media_url}
<video controls preload="metadata" style="width:100%;max-height:600px;">
  <source src="{$attachment.source_url}" type="{$attachment.mime_type|default:'video/mp4'}">
  <p>{tr}Your browser does not support HTML5 video.{/tr} <a href="{$attachment.download_url}">{tr}Download{/tr}</a></p>
</video>
{/if}
{/strip}
