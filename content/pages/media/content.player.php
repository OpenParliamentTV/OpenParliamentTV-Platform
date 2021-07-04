<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

if (!function_exists("L")) {
    require_once(__DIR__."/../../../i18n.class.php");
    $i18n = new i18n(__DIR__.'/../../../lang/lang_{LANGUAGE}.json', __DIR__.'/../../../langcache/', 'de');
    $i18n->init();
}

require_once(__DIR__."/../../../modules/media/functions.php");
require_once(__DIR__."/../../../modules/media/include.media.php");
require_once(__DIR__."/../../../modules/utilities/textArrayConverters.php");

?>
<script type="text/javascript">
    playerData = {
    	'title': '',
    	'documents': [],
        'mediaSource': '<?= $speech["attributes"]['videoFileURI'] ?>',
    	'transcriptHTML': '<?= $textContentsHTML ?>',
        'aw_username': null,
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

    prevResultURL = <?php if ($prevResult) { 
            echo "'media/".$prevResult["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    nextResultURL = <?php if ($nextResult) { 
            echo "'media/".$nextResult["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    prevResultID = <?php if ($prevResult) { 
            echo "'".$prevResult["id"]."'";
        } else {
            echo 'null';
        } ?>;
    nextResultID = <?php if ($nextResult) { 
            echo "'".$nextResult["id"]."'";
        } else {
            echo 'null';
        } ?>;
    
    //console.log(nextResultURL);
</script>
<div class="mediaContainer">
    <div class="playerTitle">
        <div class="speechMeta"><?= $formattedDate ?> | <?= $speech["attributes"]["parliamentLabel"] ?> | <a href="../electoralPeriod/<?= $speech["relationships"]["electoralPeriod"]["data"]["id"] ?>"><?= $speech["relationships"]["electoralPeriod"]['data']['attributes']['number'] ?>. Electoral Period</a> – <a href="../session/<?= $speech["relationships"]["session"]["data"]["id"] ?>">Session <?= $speech["relationships"]["session"]['data']['attributes']['number'] ?></a> – <a href="../agendaItem/<?= $speech["attributes"]["parliament"]."-".$speech["relationships"]["agendaItem"]["data"]["id"] ?>"><?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["officialTitle"] ?></a></div>
        <h3><a href="../person/<?= $speech["relationships"]["people"]["data"][0]["id"] ?>"><?= $speech["relationships"]["people"]['data'][0]['attributes']['label'] ?></a><a href="../organisation/<?= $speech["relationships"]["people"]["data"][0]["attributes"]["party"]["id"] ?>"><span class="partyIndicator" data-party="<?= $speech["relationships"]["people"]['data'][0]['attributes']['party']['labelAlternative'] ?>"><?= $speech["relationships"]["people"]['data'][0]['attributes']['party']['labelAlternative'] ?></span></a> - <?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?></h3>
    </div>
    <div id="OPTV_Player"></div>
    <div class="text-center" style="height: 35px;"><span class="icon-angle-double-down" style="font-size: 22px;"></span></div>
</div>
<div class="container mb-5">
    <?php include_once('content.related.php'); ?>
</div>