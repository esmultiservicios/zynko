<?php
// Scoped assets are only loaded in the commercial views, never in Inbox or the dashboard.
$commercialAssetRoot=dirname(__DIR__,3).'/public/assets';
?>
<link rel="stylesheet" href="assets/css/commercial.css?v=<?=filemtime($commercialAssetRoot.'/css/commercial.css')?>">
<script defer src="assets/js/commercial.js?v=<?=filemtime($commercialAssetRoot.'/js/commercial.js')?>"></script>
