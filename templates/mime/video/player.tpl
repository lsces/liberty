{strip}
{if $attachment.media_url}
<link href="{$smarty.const.UTIL_PKG_URL}javascript/videojs/src/video-js.css" rel="stylesheet" />
<script src="{$smarty.const.UTIL_PKG_URL}javascript/videojs/src/video.js"></script>

<video id="my-video" class="video-js" controls preload="auto" width="100%" height="600px">
  <source src="{$attachment.source_url}" type="video/mp4">
  <p class="vjs-no-js">To view this video please enable JavaScript, and consider upgrading to a web browser that supports HTML5 video</p>
</video>

<script>
  var player = videojs('my-video');
</script>
{/if}
{/strip}
