<?php
if (!function_exists("L")) {
    require_once(__DIR__."/../../../i18n.class.php");
    $i18n = new i18n(__DIR__.'/../../../lang/lang_{LANGUAGE}.json', __DIR__.'/../../../langcache/', 'de');
    $i18n->init();
}
require_once(__DIR__."/../../../server/functions.php");
require_once(__DIR__."/../../../server/include.play.php");
?>
<script type="text/javascript">
    playerData = {
    	'title': '<?= $speechTitle; ?>',
    	'documents': ['<?= implode("', '", $documentURLs); ?>'],
        'mediaSource': '<?= $mediaSource ?>',
    	'transcriptHTML': '<?= $escapedHtmlContents; ?>',
        'aw_username': '<?= $speech["_source"]["meta"]['aw_username'] ?>',
        'finds': [<?php if (isset($speech['finds']) && count($speech['finds']) != 0) {
                    $rCnt = 0;
                    foreach ($speech['finds'] as $result) { 
                        if ($result['class'] !== 'kommentar') {
                            ?>
                                {
                                    'start': <?php echo (float)$result['data-start'] ?>,
                                    'end': <?php echo (float)$result['data-end']-0.3 ?>
                                },
                            <?php 
                        }
                    }}
                    ?>
        ]
    };

    var isMobile = <?php if ($isMobile) { echo 'true'; } else { echo 'false'; } ?>

    //console.log("<?= $speechID;?>");

    prevResultURL = <?php if ($prevResult) { 
            echo "'?id=".$prevResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    nextResultURL = <?php if ($nextResult) { 
            echo "'?id=".$nextResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    prevResultID = <?php if ($prevResult) { 
            echo "'".$prevResult["_source"]["meta"]["id"]."'";
        } else {
            echo 'null';
        } ?>;
    nextResultID = <?php if ($nextResult) { 
            echo "'".$nextResult["_source"]["meta"]["id"]."'";
        } else {
            echo 'null';
        } ?>;
    
    //console.log(nextResultURL);
</script>
<div id="awplayer"></div>