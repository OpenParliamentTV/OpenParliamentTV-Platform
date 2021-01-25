<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

if (!function_exists("L")) {
    require_once(__DIR__."/../../../i18n.class.php");
    $i18n = new i18n(__DIR__.'/../../../lang/lang_{LANGUAGE}.json', __DIR__.'/../../../langcache/', 'de');
    $i18n->init();
}

require_once(__DIR__."/../../../modules/search/functions.php");
require_once(__DIR__."/../../../modules/media/functions.php");
require_once(__DIR__."/../../../modules/media/include.media.php");

?>
<script type="text/javascript">
    playerData = {
    	'title': '',
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
            echo "'media/".$prevResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    nextResultURL = <?php if ($nextResult) { 
            echo "'media/".$nextResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
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
<div class="playerTitle">
    <div class="speechMeta"><?= $formattedDate ?> | Wahlperiode <?= $speech["_source"]["meta"]['electoralPeriod'] ?> | Sitzung <?= $speech["_source"]["meta"]['sessionNumber'] ?> | <?= $speech["_source"]["meta"]['agendaItemTitle'] ?></div>
    <div><?= $speech["_source"]["meta"]['speakerDegree'] ?> <?= $speech["_source"]["meta"]['speakerFirstName'] ?> <?= $speech["_source"]["meta"]['speakerLastName'] ?><span class="partyIndicator" data-party="<?= $speech["_source"]["meta"]['speakerParty'] ?>"><?= $speech["_source"]["meta"]['speakerParty'] ?></span></div>
    <div class="speechTOPs"><?= $speechTOPTitle ?></div>
</div>