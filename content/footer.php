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
    $factionCSS = [];
    foreach ($factions["data"] as $faction) {
        $factionColors[$faction["attributes"]["label"]] = $faction["attributes"]["color"];
        $factionIDColors[$faction["id"]] = $faction["attributes"]["color"];

        $tmpFactionCSS = "
        .partyIndicator[data-party='".$faction["attributes"]["label"]."'],
        .partyIndicator[data-faction='".$faction["attributes"]["label"]."'],
        .partyIndicator[data-party='".$faction["id"]."'],
        .partyIndicator[data-faction='".$faction["id"]."']";
        foreach ($faction["attributes"]["labelAlternative"] as $labelAlternative) {
            $tmpFactionCSS .= ", .partyIndicator[data-faction='".$labelAlternative."'], .partyIndicator[data-faction='".$labelAlternative."']";
        }
        $tmpFactionCSS .= " { background-color:".$faction["attributes"]["color"]."; border-color:".$faction["attributes"]["color"]."; background: var(--primary-bg-color); }";
        $factionsCSS[] = $tmpFactionCSS;
    }

    echo "var factionColors=".json_encode($factionColors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_FORCE_OBJECT).";\n";
    echo "var factionIDColors=".json_encode($factionIDColors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_FORCE_OBJECT);

    ?>

</script>
<style type="text/css">
    <?php
    foreach ($factionsCSS as $factionCSS) {
        echo $factionCSS;
    }
    ?>
</style>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/generic.js?v=<?= $config["version"] ?>"></script>
