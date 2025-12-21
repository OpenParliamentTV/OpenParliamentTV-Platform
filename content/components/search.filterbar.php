<?php
// Optional parameters that can be passed to this component
$showSearchBar = isset($showSearchBar) ? $showSearchBar : false;
$showParliamentFilter = isset($showParliamentFilter) ? $showParliamentFilter : false;
$showToggleButton = isset($showToggleButton) ? $showToggleButton : true;
$showFactionChart = isset($showFactionChart) ? $showFactionChart : true;
$showDateRange = isset($showDateRange) ? $showDateRange : true;
$showSearchSuggestions = isset($showSearchSuggestions) ? $showSearchSuggestions : false;
$showAdvancedFilters = isset($showAdvancedFilters) ? $showAdvancedFilters : false;
?>

<div id="filterbar" class="col-12">
    <form id="filterForm" method="get" accept-charset="UTF-8">
        <input type="hidden" name="q" value="">
        <input type="hidden" name="context" value="">
        <div class="searchContainer">
            <?php if ($showParliamentFilter): ?>
            <div class="parliamentFilterContainer d-none">
                <?php include_once('search.filter.parliaments.php'); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($showSearchBar): ?>
            <div class="position-relative">
                <div>
                    <div class="searchInputContainer clearfix">
                        <input id="edit-query" placeholder="<?= L::enterSearchTerm(); ?>" name="edit-query" value="" type="text">
                    </div>
                    <button type="button" id="edit-submit" class="btn btn-sm btn-outline-primary"><span class="icon-search"></span><span class="visually-hidden"><?= L::search(); ?></span></button>
                </div>
                <?php if ($showSearchSuggestions): ?>
                <div class="searchSuggestionContainer">
                    <div class="row">
                        <div class="col col-12 col-sm-6 col-lg-5">
                            <div style="font-weight: bolder;"><?= L::suggestions(); ?></div>
                            <hr class="my-1">
                            <div id="suggestionContainerText"></div>
                        </div>
                        <div class="col col-12 col-sm-6 col-lg-7 mt-3 mt-sm-0">
                            <div style="font-weight: bolder;"><?= L::entities(); ?><span class="betaWarning"><span class="icon-attention me-1"></span>beta</span></div>
                            <hr class="my-1">
                            <div id="suggestionContainerEntities"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($showToggleButton): ?>
        <button id="toggleFilterContainer" class="btn btn-sm d-block d-md-none" type="button" data-bs-toggle="collapse" data-bs-target=".filterContainer" aria-expanded="false" aria-controls="">
            <span class="icon-menu-1"></span><span class="labelShow"><?= L::filtersShow(); ?></span><span class="labelCollapse"><?= L::filtersHide(); ?></span><span class="icon-up-open-big"></span>
        </button>
        <?php endif; ?>
        
        <div class="filterContainer collapse show d-md-block">
            <div class="row">
                <div class="col col-12">
                    <div class="form-group d-flex">
                        <?php if ($showFactionChart): ?>
                        <div class="factionChartContainer flex-shrink-0">
                            <div id="factionChart"></div>
                        </div>
                        <?php endif; ?>
                        <div class="checkboxList flex-grow-1">
                            <label style="display: block;" for="edit-party"><b><?= L::faction(); ?></b></label>
                            <?php

                            require_once (__DIR__."/../../api/v1/api.php");

                            $factions = apiV1(array("action"=>"search", "itemType"=>"organisations", "type"=>"faction", "filterable"=>1 ));

                            $filterableFactions = [];
                            foreach ($factions["data"] as $faction) {
                                $filterableFactions[] = $faction["id"];
                                echo '
                                <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="'.$faction["id"].'">
                                    <input id="edit-party-'.$faction["id"].'" name="factionID[]" value="'.$faction["id"].'" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-'.$faction["id"].'">'.$faction["attributes"]["label"].'</label>
                                </div>
                                ';

                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($showDateRange): ?>
            <hr>
            <div class="rangeContainer">
                <label for="timeRange"><b><?= L::timePeriod(); ?>:</b></label>
                <input type="text" id="timeRange" readonly style="border:0; background: transparent;"/>
                <div class="position-relative">
                    <div id="timelineVizWrapper" class="resultTimeline"></div>
                    <div id="sliderRange" class="sliderRange"></div>
                </div>
                <input type="hidden" id="dateFrom" name="dateFrom"/>
                <input type="hidden" id="dateTo" name="dateTo"/>
            </div>
            <?php endif; ?>
            <?php if ($showAdvancedFilters): ?>
            <hr>
            <div class="row">
                <div class="col-12 col-lg-6 mb-3">
                    <label for="agendaItemTitle-filter" class="form-label"><?= L::agendaItem(); ?></label>
                    <input type="text" class="form-control form-control-sm" id="agendaItemTitle-filter" name="agendaItemTitle" placeholder="<?= L::enterSearchTerm(); ?>">
                </div>
                <div class="col-6 col-lg-2 mb-3">
                    <label for="public-filter" class="form-label">Public</label>
                    <select class="form-select form-select-sm" id="public-filter" name="public">
                        <option value="">Select...</option>
                        <option value="true">Yes</option>
                        <option value="false">No</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2 mb-3">
                    <label for="aligned-filter" class="form-label">Aligned</label>
                    <select class="form-select form-select-sm" id="aligned-filter" name="aligned">
                        <option value="">Select...</option>
                        <option value="true">Yes</option>
                        <option value="false">No</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2 mb-3">
                    <label for="number-of-texts-filter" class="form-label">Texts</label>
                    <select class="form-select form-select-sm" id="number-of-texts" name="numberOfTexts">
                        <option value="">Select...</option>
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div> 