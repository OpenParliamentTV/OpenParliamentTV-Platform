<?php defined('OPTV') or die(); ?>
<?php
/**
 * Checkbox list of the filterable factions.
 *
 * Shared by the search filter bar and the statistics page so both show the same
 * factions, in the same order, with the same colours — the colour comes from the
 * `partyIndicator` class plus `data-faction`, which content/head.php targets with
 * per-faction CSS it generates from the same query.
 *
 * The alternative, writing the faction ids into a page, produces a list that is
 * wrong for every other parliament and quietly stale for this one whenever a new
 * faction enters parliament.
 *
 * Callers may set, before including:
 *   $factionCheckboxName    input name attribute      (default "factionID[]")
 *   $factionCheckboxPrefix  id prefix per checkbox    (default "edit-party-")
 *   $factionCheckboxClass   extra class on the input  (default "")
 *
 * Sets $filterableFactions to the ids rendered, which the search filter bar reads.
 */

require_once(__DIR__ . "/../../api/v1/api.php");

$factionCheckboxName   = $factionCheckboxName   ?? 'factionID[]';
$factionCheckboxPrefix = $factionCheckboxPrefix ?? 'edit-party-';
$factionCheckboxClass  = $factionCheckboxClass  ?? '';

$factions = apiV1(array("action" => "search", "itemType" => "organisations", "type" => "faction", "filterable" => 1));

$filterableFactions = [];
foreach ($factions["data"] ?? [] as $faction) {
    $filterableFactions[] = $faction["id"];
    $inputClass = trim('form-check-input ' . $factionCheckboxClass);
    echo '
                                <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="' . $faction["id"] . '">
                                    <input id="' . $factionCheckboxPrefix . $faction["id"] . '" name="' . $factionCheckboxName . '" value="' . $faction["id"] . '" type="checkbox" class="' . $inputClass . '"> <label class="form-check-label" for="' . $factionCheckboxPrefix . $faction["id"] . '">' . $faction["attributes"]["label"] . '</label>
                                </div>
                                ';
}
