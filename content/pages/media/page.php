<?php include_once(__DIR__ . '/../../header.php'); 
require_once(__DIR__."/../../../modules/media/functions.php");
require_once(__DIR__."/../../../modules/media/include.media.php");
?>
<main id="content">
    <div class="mediaContainer">
        <div class="playerTitle">
            <div class="speechMeta"><?= $formattedDate ?> | <?= $speech["attributes"]["parliamentLabel"] ?> | <a href="../electoralPeriod/<?= $speech["relationships"]["electoralPeriod"]["data"]["id"] ?>"><?= $speech["relationships"]["electoralPeriod"]['data']['attributes']['number'] ?>. Electoral Period</a> – <a href="../session/<?= $speech["relationships"]["session"]["data"]["id"] ?>">Session <?= $speech["relationships"]["session"]['data']['attributes']['number'] ?></a> – <a href="../agendaItem/<?= $speech["attributes"]["parliament"]."-".$speech["relationships"]["agendaItem"]["data"]["id"] ?>"><?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["officialTitle"] ?></a></div>
            <h3><a href="../person/<?= $speech["relationships"]["people"]["data"][0]["id"] ?>"><?= $speech["relationships"]["people"]['data'][0]['attributes']['label'] ?></a><a href="../organisation/<?= $speech["relationships"]["people"]["data"][0]["attributes"]["party"]["id"] ?>"><span class="partyIndicator" data-party="<?= $speech["relationships"]["people"]['data'][0]['attributes']['party']['labelAlternative'] ?>"><?= $speech["relationships"]["people"]['data'][0]['attributes']['party']['labelAlternative'] ?></span></a> - <?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?></h3>
        </div>
        <?php include_once('content.player.php'); ?>
        <div class="text-center" style="height: 35px;"><span class="icon-angle-double-down" style="font-size: 22px;"></span></div>
    </div>
    <div class="container mb-5">
        <?php include_once('content.related.php'); ?>
    </div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/FrameTrail.min.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/player.js"></script>
<script type="text/javascript">
	var autoplayResults = <?php if ($autoplayResults) { echo 'true'; } else { echo 'false'; } ?>;

	var prevResultURL = <?php if ($prevResult) { 
            echo "'media/".$prevResult["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    var nextResultURL = <?php if ($nextResult) { 
            echo "'media/".$nextResult["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
</script>