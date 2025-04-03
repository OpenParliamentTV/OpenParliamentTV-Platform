<?php
// Optional parameters that can be passed to this component
$showSearchBar = isset($showSearchBar) ? $showSearchBar : false;
$showParliamentFilter = isset($showParliamentFilter) ? $showParliamentFilter : false;
$showToggleButton = isset($showToggleButton) ? $showToggleButton : true;
$showFactionChart = isset($showFactionChart) ? $showFactionChart : true;
$showDateRange = isset($showDateRange) ? $showDateRange : true;
$showSearchSuggestions = isset($showSearchSuggestions) ? $showSearchSuggestions : false;
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
                        <input id="edit-query" placeholder="<?= L::enterSearchTerm; ?>" name="edit-query" value="" type="text">
                    </div>
                    <button type="button" id="edit-submit" class="btn btn-sm btn-outline-primary"><span class="icon-search"></span><span class="visually-hidden"><?= L::search; ?></span></button>
                </div>
                <?php if ($showSearchSuggestions): ?>
                <div class="searchSuggestionContainer">
                    <div class="row">
                        <div class="col col-12 col-sm-6 col-lg-5">
                            <div style="font-weight: bolder;"><?= L::suggestions; ?></div>
                            <hr class="my-1">
                            <div id="suggestionContainerText"></div>
                        </div>
                        <div class="col col-12 col-sm-6 col-lg-7">
                            <div style="font-weight: bolder;"><span class="icon-type-person"></span><?= L::personPlural; ?></div>
                            <hr class="my-1">
                            <div id="suggestionContainerPeople"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($showToggleButton): ?>
        <button id="toggleFilterContainer" class="btn btn-primary btn-sm d-block d-md-none" type="button" data-bs-toggle="collapse" data-bs-target=".filterContainer" aria-expanded="false" aria-controls="">
            <span class="icon-menu-1"></span><span class="labelShow"><?= L::filtersShow; ?></span><span class="labelCollapse"><?= L::filtersHide; ?></span><span class="icon-up-open-big"></span>
        </button>
        <?php endif; ?>
        
        <div class="filterContainer collapse show d-md-block">
            <div class="row">
                <div class="col col-12">
                    <div class="form-group d-flex">
                        <?php if ($showFactionChart): ?>
                        <div class="chartContainer">
                            <canvas id="factionChart"></canvas>
                        </div>
                        <?php endif; ?>
                        <div class="checkboxList flex-grow-1">
                            <label style="display: block;" for="edit-party"><b><?= L::faction; ?></b></label>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q2207512">
                                <input id="edit-party-16118" name="factionID[]" value="Q2207512" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-16118">SPD</label>
                            </div>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q1023134">
                                <input id="edit-party-17362" name="factionID[]" value="Q1023134" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-17362">CDU/CSU</label>
                            </div>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q1007353">
                                <input id="edit-party-16122" name="factionID[]" value="Q1007353" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-16122">DIE GRÜNEN</label>
                            </div>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q1387991">
                                <input id="edit-party-17363" name="factionID[]" value="Q1387991" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-17363">FDP</label>
                            </div>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q42575708">
                                <input id="edit-party-17364" name="factionID[]" value="Q42575708" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-17364">AfD</label>
                            </div>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q1826856">
                                <input id="edit-party-16124" name="factionID[]" value="Q1826856" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-16124">DIE LINKE</label>
                            </div>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q127785176">
                                <input id="edit-party-17365" name="factionID[]" value="Q127785176" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-17365">BSW</label>
                            </div>
                            <div class="formCheckbox form-check partyIndicator mb-2 mb-lg-1" data-faction="Q4316268">
                                <input id="edit-party-16123" name="factionID[]" value="Q4316268" type="checkbox" class="form-check-input"> <label class="form-check-label" for="edit-party-16123">fraktionslos</label>
                            </div>
                        </div>
                        <div style="clear: both;"></div>
                    </div>
                </div>
            </div>
            <?php if ($showDateRange): ?>
            <hr>
            <div class="rangeContainer">
                <label for="timeRange"><b><?= L::timePeriod; ?>:</b></label>
                <input type="text" id="timeRange" readonly style="border:0; background: transparent;"/>
                <div id="timelineVizWrapper"></div>
                <div id="sliderRange"></div>
                <input type="hidden" id="dateFrom" name="dateFrom"/>
                <input type="hidden" id="dateTo" name="dateTo"/>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div> 