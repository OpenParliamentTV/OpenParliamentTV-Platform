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

$proceedingsTab = (isset($textContentsHTML)) ? '<li class="nav-item">
        <a class="nav-link active" id="proceedings-tab" data-toggle="tab" href="#proceedings" role="tab" aria-controls="proceedings" aria-selected="true"><span class="tabTitle">'.L::proceedings.'</span><span class="icon-doc-text-1"></span></a>
    </li>' : '';
$relationshipsActiveClass = (isset($textContentsHTML)) ? '' : 'show active';

ob_start();
include "panel.related.php";
$relatedContentsPanel = ob_get_clean();
$relatedContentsHTML = str_replace(array("\r","\n"), " ",$relatedContentsPanel);
$relatedContentsHTML = str_replace("'", "\"",$relatedContentsHTML);

?>
<script type="text/javascript">
    currentMediaID = '<?= $speech["id"] ?>';

    // TODO: Replace URL Quick Fix
    var originMediaID = '<?= $speech["attributes"]['originMediaID'] ?>';
    var videoSource = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/'+ originMediaID +'/'+ originMediaID +'_h264_720_400_2000kb_baseline_de_2192.mp4';
    
    playerData = {
    	'title': '',
    	'documents': [],
        'mediaSource': videoSource,
    	'transcriptHTML': '<?= $relatedContentsHTML ?>',
        'aw_username': null,
        'finds': [
            <?php 
            if (isset($speech['_finds']) && count($speech['_finds']) != 0) {
                $rCnt = 0;
                foreach ($speech['_finds'] as $result) { 
                    if (isset($result['data-start']) && isset($result['data-end'])) {
                        ?>
                            {
                                'start': <?php echo (float)$result['data-start'] ?>,
                                'end': <?php echo (float)$result['data-end']-0.3 ?>
                            },
                        <?php 
                    }
                }
            }
            ?>
        ],
        'annotations': <?= json_encode(getFrametrailAnnotations($speech["annotations"]["data"], $speech["relationships"], $speech["attributes"]["videoFileURI"]))?>
    };

    var isMobile = <?php if ($isMobile) { echo 'true'; } else { echo 'false'; } ?>
    
</script>
<div class="mediaContainer">
    <div class="d-flex flex-column flex-md-row-reverse">
        <div class="playerTitle">
            <div class="speechMeta"><?= $formattedDate ?> | <span class="d-none d-sm-inline"><?= $speech["attributes"]["parliamentLabel"] ?> / </span><a href="../electoralPeriod/<?= $speech["relationships"]["electoralPeriod"]["data"]["id"] ?>"><?= $speech["relationships"]["electoralPeriod"]['data']['attributes']['number'] ?><span class="d-none d-xl-inline">. <?php echo L::electoralPeriodShort; ?></span></a> / <a href="../session/<?= $speech["relationships"]["session"]["data"]["id"] ?>"><span class="d-none d-xl-inline"><?php echo L::session; ?> </span><?= $speech["relationships"]["session"]['data']['attributes']['number'] ?></a> / <a href="../agendaItem/<?= $speech["attributes"]["parliament"]."-".$speech["relationships"]["agendaItem"]["data"]["id"] ?>"><?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["officialTitle"] ?></a></div>
            <h3><a href="../person/<?= $mainSpeaker["id"] ?>"><?= $mainSpeaker['attributes']['label'] ?></a><?php 
                if (isset($mainFaction['attributes']['label'])) {
                ?><a href="../organisation/<?= $mainFaction["id"] ?>"><span class="partyIndicator" data-faction="<?= $mainFaction["id"] ?>"><?= $mainFaction["attributes"]["label"] ?></span></a><?php 
                } ?> - <?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?></h3>
        </div>
        <div class="playerTabs">
            <ul class="nav nav-tabs flex-nowrap" role="tablist">
                <?= $proceedingsTab ?>
                <li class="nav-item">
                    <a class="nav-link <?= $relationshipsActiveClass ?>" id="relationships-tab" data-toggle="tab" href="#relationships" role="tab" aria-controls="relationships" aria-selected="true"><span class="tabTitle"><?php echo L::relationships; ?></span><span class="icon-flow-cascade"></span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="tabTitle"><?php echo L::data; ?></span><span class="icon-download"></span></a>
                </li>
            </ul>
        </div>
    </div>
    <div id="OPTV_Player"></div>
</div>
<div id="shareQuoteModal" class="modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span class="icon-share"></span> <?php echo L::shareQuote; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning"><?php echo L::messageShareQuotePart1; ?> <b><?= $mainSpeaker['attributes']['label'] ?></b>? <?php echo L::messageShareQuotePart2; ?>!</div>
                <label><b>1. <?php echo L::selectTheme; ?></b>:</label>
                <div class="form-row row-cols-2">
                    <div class="col">
                        <div class="card sharePreview active" data-theme="l">
                            <img class="img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/share-image.php">
                            <div class="antialiased text-break cardMeta">
                                <div class="overflow-hidden select-none cardTitleWrapper">
                                    <div class="cardTitle text-truncate"><?= $speechTitleShort ?> | <?php echo L::brand ?></div>
                                    <div class="overflow-hidden text-break text-truncate whitespace-no-wrap select-none cardDescription"><?= L::speech.' '.L::onTheSubject.' '.$speech["relationships"]["agendaItem"]["data"]['attributes']["title"].' '.L::by.' '.$mainSpeaker['attributes']['label'].' ('.$speech["attributes"]["parliamentLabel"].', '.$formattedDate.')' ?></div>
                                </div>
                                <div class="overflow-hidden text-truncate text-nowrap cardWebsite">de.openparliament.tv</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card sharePreview" data-theme="d">
                            <img class="img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/share-image.php">
                            <div class="antialiased text-break cardMeta">
                                <div class="overflow-hidden select-none cardTitleWrapper">
                                    <div class="cardTitle text-truncate"><?= $speechTitleShort ?> | <?php echo L::brand ?></div>
                                    <div class="overflow-hidden text-break text-truncate whitespace-no-wrap select-none cardDescription"><?= L::speech.' '.L::onTheSubject.' '.$speech["relationships"]["agendaItem"]["data"]['attributes']["title"].' '.L::by.' '.$mainSpeaker['attributes']['label'].' ('.$speech["attributes"]["parliamentLabel"].', '.$formattedDate.')' ?></div>
                                </div>
                                <div class="overflow-hidden text-truncate text-nowrap cardWebsite">de.openparliament.tv</div>
                            </div>
                        </div>
                    </div>
                </div>
                <small class="d-block mt-2 text-muted"><?php echo L::shareQuoteMessageTheme; ?></small>
                <div class="form-group mt-3">
                    <label for="shareURL"><b>2. <?php echo L::shareQuoteMessageURL; ?></b>:</label>
                    <textarea id="shareURL" class="form-control" type="text" name="shareURL" rows=3></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal"><?php echo L::close; ?></button>
            </div>
        </div>
    </div>
</div>
<div id="videoAttribution" class="copyrightInfo" style="display: none;"><span class="icon-info-circled"></span><span class="copyrightText"><?php echo L::source; ?>: <?= $speech["attributes"]["creator"] ?>, <?php echo html_entity_decode($speech["attributes"]["license"]); ?></span></div>
<div class="modal fade" id="nerModal" tabindex="-1" role="dialog" aria-labelledby="nerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nerModalTitle"><?php echo L::automaticallyDetected; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body"><?php echo L::messageAutomaticallyDetected; ?></div>
        </div>
    </div>
</div>