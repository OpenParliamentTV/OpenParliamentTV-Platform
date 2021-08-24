<div id="filterbar" class="col-12 nosearch">
	<form id="filterForm" method="get" accept-charset="UTF-8">
		<input type="hidden" name="q" value="">
		<div class="searchContainer">
			<div class="parliamentFilterContainer">
				<?php include_once('content.filter.parliaments.php'); ?>
			</div>
			<div class="position-relative">
				<div>
					<div class="searchInputContainer clearfix">
						<input id="edit-query" placeholder="<?php echo L::enterSearchTerm; ?>" name="edit-query" value="" type="text">
					</div>
					<button type="button" id="edit-submit" class="btn btn-sm btn-outline-primary"><span class="icon-search"></span><span class="sr-only"><?php echo L::search; ?></span></button>
				</div>
				<div class="searchSuggestionContainer">
					<div class="row">
						<div class="col col-12 col-sm-6 col-lg-5">
							<div style="font-weight: bolder;">Suggestions</div>
							<hr class="my-1">
							<div id="suggestionContainerText"></div>
						</div>
						<div class="col col-12 col-sm-6 col-lg-7">
							<div style="font-weight: bolder;"><span class="icon-torso"></span><?php echo L::personPlural; ?></div>
							<hr class="my-1">
							<div id="suggestionContainerPeople"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<button id="toggleFilterContainer" class="btn btn-primary btn-sm d-block d-md-none" type="button" data-toggle="collapse" data-target=".filterContainer" aria-expanded="false" aria-controls="">
			<span class="icon-menu-1"></span><span class="labelShow"><?php echo L::filtersShow; ?></span><span class="labelCollapse"><?php echo L::filtersHide; ?></span><span class="icon-up-open-big"><span>
		</button>
		<div class="filterContainer collapse show d-md-block">
			<div class="row row-cols-1 row-cols-xl-3">
				<div class="col col-12 col-xl-9">
					<div class="form-group">
						<div class="chartContainer d-none d-lg-block">
							<canvas id="factionChart"></canvas>
						</div>
						<div class="checkboxList">
							<label style="display: block;" for="edit-party"><b><?php echo L::faction; ?></b></label>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator mb-2 mb-lg-1" data-faction="Q1023134">
								<input id="edit-party-17362" name="factionID[]" value="Q1023134" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-17362">CDU/CSU</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator mb-2 mb-lg-1" data-faction="Q2207512">
								<input id="edit-party-16118" name="factionID[]" value="Q2207512" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16118">SPD</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator mb-2 mb-lg-1" data-faction="Q42575708">
								<input id="edit-party-17364" name="factionID[]" value="Q42575708" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-17364">AfD</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator mb-2 mb-lg-1" data-faction="Q1387991">
								<input id="edit-party-17363" name="factionID[]" value="Q1387991" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-17363">FDP</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator mb-2 mb-lg-1" data-faction="Q1826856">
								<input id="edit-party-16124" name="factionID[]" value="Q1826856" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16124">DIE LINKE</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator mb-2 mb-lg-1" data-faction="Q1007353">
								<input id="edit-party-16122" name="factionID[]" value="Q1007353" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16122">DIE GRÜNEN</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator mb-2 mb-lg-1" data-faction="Q4316268">
								<input id="edit-party-16123" name="factionID[]" value="Q4316268" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16123">fraktionslos</label>
							</div>
						</div>
						<div style="clear: both;"></div>
					</div>
				</div>
				<!--
				<div class="col col-12 col-sm-6 col-md-5 col-lg-4 col-xl-3">
					<div class="form-group">
						<label for="edit-keys"><b><?php echo L::name; ?></b></label>
						<input class="form-control form-control-sm" placeholder="<?php echo L::enterName; ?>" id="edit-keys" name="person" value="" type="text">
					</div>
				</div>
				-->
			</div>
			<hr>
			<div class="rangeContainer">
				<label for="timeRange"><b><?php echo L::timePeriod; ?>:</b></label>
				<input type="text" id="timeRange" readonly style="border:0; background: transparent;"/>
				<div id="timelineVizWrapper"></div>
				<div id="sliderRange"></div>
				<input type="hidden" id="dateFrom" name="dateFrom"/>
				<input type="hidden" id="dateTo" name="dateTo"/>
			</div>
		</div>
	</form>
</div>