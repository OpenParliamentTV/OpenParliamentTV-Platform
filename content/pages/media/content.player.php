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

$proceedingsPanel = (isset($textContentsHTML)) ? '<div class="tab-pane fade show active" id="proceedings" role="tabpanel" aria-labelledby="proceedings-tab">'.$textContentsHTML.'</div>' : '';
$proceedingsTab = (isset($textContentsHTML)) ? '<li class="nav-item">
        <a class="nav-link active" id="proceedings-tab" data-toggle="tab" href="#proceedings" role="tab" aria-controls="proceedings" aria-selected="true"><span class="tabTitle">'.L::proceedings.'</span><span class="icon-doc-text-1"></span></a>
    </li>' : '';
$relatedPeopleActiveClass = (isset($textContentsHTML)) ? '' : 'show active';

$relatedPeopleHTML = '';
foreach ($speech["relationships"]["people"]["data"] as $relationshipItem) {
    $relatedPeopleHTML .= '<div class="entityPreview col" data-type="'.$relationshipItem["type"].'"><a href="'.$config["dir"]["root"].'/'.$relationshipItem["type"].'/'.$relationshipItem["id"].'">'.$relationshipItem["attributes"]["label"].'<br>('.$relationshipItem["attributes"]["context"].')</a></div>';
}

/*
$relatedOrganisationsHTML = '';
foreach ($speech["relationships"]["organisations"]["data"] as $relationshipItem) {
    $relatedOrganisationsHTML .= '<div class="entityPreview col" data-type="'.$relationshipItem["type"].'"><a href="'.$config["dir"]["root"].'/'.$relationshipItem["type"].'/'.$relationshipItem["id"].'">'.$relationshipItem["attributes"]["label"].'</a></div>';
}
*/

$documentsTab = '';
$relatedDocumentsHTML = '';
if (isset($speech["relationships"]["documents"]["data"]) && count($speech["relationships"]["documents"]["data"]) > 0) {
    //TODO: Add all documents
    $documentsTab = '<li class="nav-item">
            <a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents" role="tab" aria-controls="documents" aria-selected="false"><span class="tabTitle">'.L::documents.'</span><span class="icon-doc-text"></span></a>
        </li>';
    $relatedDocumentsHTML .= '<div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">';

    //<iframe src="'.$config["dir"]["root"].'/modules/pdf-viewer/web/viewer.html?file='.$speech["relationships"]["documents"]["data"][0]["attributes"]["sourceURI"].'"></iframe>
    foreach ($speech["relationships"]["documents"]["data"] as $tmpDocument) {

        $relatedDocumentsHTML .= '<div class="relationshipsDocument relationshipsDocument_'.$tmpDocument["attributes"]["type"].'">       <a href="'.$tmpDocument["attributes"]["sourceURI"].'" target="_blank">'.$tmpDocument["attributes"]["label"].'</a>       </div>';

    }


    $relatedDocumentsHTML .= '</div>';
}
/*
foreach ($speech["relationships"]["documents"]["data"] as $relationshipItem) {
    
}
*/

$relatedContentsHTML = 
'<div class="tab-content">'.$proceedingsPanel.'<div class="tab-pane fade show '.$relatedPeopleActiveClass.'" id="people" role="tabpanel" aria-labelledby="people-tab"><div class="relationshipsList row row-cols-1 row-cols-md-2 row-cols-lg-3">'.$relatedPeopleHTML.'</div></div>'.$relatedDocumentsHTML.'<div class="tab-pane fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">[CONTENT]</div></div>';

?>
<script type="text/javascript">
    currentMediaID = '<?= $speech["id"] ?>';
    
    playerData = {
    	'title': '',
    	'documents': [],
        'mediaSource': '<?= $speech["attributes"]['videoFileURI'] ?>',
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
        ]
    };

    var isMobile = <?php if ($isMobile) { echo 'true'; } else { echo 'false'; } ?>
    
</script>
<div class="mediaContainer">
    <div class="d-flex flex-column flex-md-row-reverse">
        <div class="playerTitle">
            <div class="speechMeta"><?= $formattedDate ?> | <span class="d-none d-sm-inline"><?= $speech["attributes"]["parliamentLabel"] ?> / </span><a href="../electoralPeriod/<?= $speech["relationships"]["electoralPeriod"]["data"]["id"] ?>"><?= $speech["relationships"]["electoralPeriod"]['data']['attributes']['number'] ?><span class="d-none d-xl-inline">. <?php echo L::electoralPeriodShort; ?></span></a> / <a href="../session/<?= $speech["relationships"]["session"]["data"]["id"] ?>"><span class="d-none d-xl-inline"><?php echo L::session; ?> </span><?= $speech["relationships"]["session"]['data']['attributes']['number'] ?></a> / <a href="../agendaItem/<?= $speech["attributes"]["parliament"]."-".$speech["relationships"]["agendaItem"]["data"]["id"] ?>"><?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["officialTitle"] ?></a></div>
            <h3><a href="../person/<?= $mainSpeaker["id"] ?>"><?= $mainSpeaker['attributes']['label'] ?></a><?php 
                if (isset($mainFaction['attributes']['labelAlternative'])) {
                ?><a href="../organisation/<?= $mainFaction["id"] ?>"><span class="partyIndicator" data-faction="<?= $mainFaction["id"] ?>"><?= $mainFaction["attributes"]["labelAlternative"] ?></span></a><?php 
                } ?> - <?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?></h3>
        </div>
        <div class="playerTabs">
            <ul class="nav nav-tabs flex-nowrap" role="tablist">
                <?= $proceedingsTab ?>
                <li class="nav-item">
                    <a class="nav-link <?= $relatedPeopleActiveClass ?>" id="people-tab" data-toggle="tab" href="#people" role="tab" aria-controls="people" aria-selected="true"><span class="tabTitle"><?php echo L::personPlural ?></span><span class="icon-group"></span></a>
                </li>
                <!--
                <li class="nav-item">
                    <a class="nav-link" id="organisations-tab" data-toggle="tab" href="#organisations" role="tab" aria-controls="organisations" aria-selected="false"><span class="tabTitle">Organisations</span><span class="icon-bank"></span></a>
                </li>
                -->
                <?= $documentsTab ?>
                <!--
                <li class="nav-item">
                    <a class="nav-link" id="terms-tab" data-toggle="tab" href="#terms" role="tab" aria-controls="terms" aria-selected="false"><span class="tabTitle">Terms</span><span class="icon-tag-1"></span></a>
                </li>
                -->
            </ul>
        </div>
    </div>
    <div id="OPTV_Player"></div>
    <!--
    <div class="text-center" style="height: 35px;"><span class="icon-angle-double-down" style="font-size: 22px;"></span></div>
    -->
</div>
<!--
<div class="container mb-5">
    <?php //include_once('content.related.php'); ?>
</div>
-->