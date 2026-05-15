{strip}
{if $attachment.media_url}
<script src="{$smarty.const.THEMES_PKG_URL}js/videojs/src/js/video.js"></script>

<video id="my-video" class="video-js" controls preload="auto" width="100%" height="600px">
  <source src="{$attachment.source_url}" type="video/mp4">
  <p class="vjs-no-js">To view this video please enable JavaScript, and consider upgrading to a web browser that supports HTML5 video</p>
</video>

<script>
  var player = videojs('my-video');
</script>
{/if}
{/strip}
