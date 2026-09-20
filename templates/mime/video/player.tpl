{strip}
{if $attachment.media_url}
<video id="liberty-video-player" controls preload="metadata" style="width:100%;max-height:600px;">
  <source src="{$attachment.source_url}" type="{$attachment.mime_type|default:'video/mp4'}">
  <p>{tr}Your browser does not support HTML5 video.{/tr} <a href="{$attachment.download_url}">{tr}Download{/tr}</a></p>
</video>
{if $attachment.download_url}
  {* Always-visible, not just the <video> tag's own hidden no-HTML5-support fallback text above
     (which no browser that can actually play the tag ever renders) - a real download is still
     the only way to get full multichannel audio out of this: browsers commonly downmix or just
     go silent on 5.1+ audio through the <video> element itself, even though the same file plays
     its surround track correctly in a real media player once saved locally. The `download`
     attribute asks the browser to save rather than navigate/replay inline, overriding the
     `Content-Disposition: inline` this same URL serves for the player's own use above (see
     mime_film_download()'s own docblock for why that's inline, not attachment). *}
  <p class="video-download-original"><a href="{$attachment.download_url}" download>{tr}Download original file{/tr}</a></p>
{/if}
{/if}
{/strip}
