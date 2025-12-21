<footer>
	<div class="row">
        <div class="col-12" style="font-size: 12px; text-align: center;">This website is powered by <a href="https://openparliament.tv/" target="_blank">OpenParliament TV</a></div>
	</div>
</footer>

<script type="text/javascript">

    <?php

    require_once (__DIR__."/../api/v1/api.php");

    $factions = apiV1(array("action"=>"search", "itemType"=>"organisations", "type"=>"faction", "filterable"=>1 ));

    $factionColors = [];
    $factionIDColors = [];
    foreach ($factions["data"] as $faction) {
        $factionColors[$faction["attributes"]["label"]] = $faction["attributes"]["color"];
        $factionIDColors[$faction["id"]] = $faction["attributes"]["color"];
    }
    echo "var factionColors=".json_decode($factionColors);
    echo "var factionIDColors=".json_decode($factionIDColors);

    ?>

</script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/generic.js?v=<?= $config["version"] ?>"></script>
