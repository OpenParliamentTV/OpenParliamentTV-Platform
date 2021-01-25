<div id="filterbar" class="col-12 nosearch" style="margin-top: 0px;">
	<form id="filterForm" method="get" accept-charset="UTF-8">
		<!--<input type="hidden" name="a" value="search">-->
		<!--<label for="edit-query">Suchbegriff eingeben </label>-->
		<div class="searchContainer input-group">
			<input class="form-control" placeholder="<?php echo L::enterSearchTerm; ?>" id="edit-query" name="q" value="" type="text">
			<div class="input-group-append">
				<button type="button" id="edit-submit" class="btn btn-sm btn-outline-primary"><span class="icon-search"></span><span class="sr-only"><?php echo L::search; ?></span></button>
			</div>
		</div>
		<button class="btn btn-primary btn-sm d-block d-md-none" type="button" data-toggle="collapse" data-target=".filterContainer" aria-expanded="false" aria-controls="">
			<span class="icon-menu-1"></span>Filter <span class="labelShow">anzeigen</span><span class="labelCollapse">ausblenden</span><span class="icon-up-open-big"><span>
		</button>
		<div class="filterContainer collapse show d-md-block">
			<div class="row row-cols-1 row-cols-xl-3">
				<div class="col col-12 col-xl-8">
					<div class="form-group">
						<div class="chartContainer d-none d-md-block">
							<canvas id="partyChart"></canvas>
						</div>
						<div class="checkboxList">
							<label style="display: block;" for="edit-party"><b><?php echo L::party; ?></b></label>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator" data-party="CDU">
								<input id="edit-party-17362" name="party[]" value="CDU" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-17362">CDU</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator" data-party="SPD">
								<input id="edit-party-16118" name="party[]" value="SPD" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16118">SPD</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator" data-party="AfD">
								<input id="edit-party-17364" name="party[]" value="AfD" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-17364">AfD</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator" data-party="FDP">
								<input id="edit-party-17363" name="party[]" value="FDP" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-17363">FDP</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator" data-party="DIE LINKE">
								<input id="edit-party-16124" name="party[]" value="DIE LINKE" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16124">DIE LINKE</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator" data-party="DIE GRÜNEN">
								<input id="edit-party-16122" name="party[]" value="DIE GRÜNEN" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16122">DIE GRÜNEN</label>
							</div>
							<div class="formCheckbox custom-control custom-checkbox partyIndicator" data-party="CSU">
								<input id="edit-party-16120" name="party[]" value="CSU" type="checkbox" class="custom-control-input"> <label class="custom-control-label" for="edit-party-16120">CSU</label>
							</div>
						</div>
						<div style="clear: both;"></div>
					</div>
				</div>
				<div class="col col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
					<div class="form-group">
						<label for="edit-keys"><b><?php echo L::name; ?></b></label>
						<input class="form-control form-control-sm" placeholder="<?php echo L::enterName; ?>" id="edit-keys" name="name" value="" type="text">
					</div>
				</div>
				<div class="col col-12 col-sm-6 col-md-5 col-lg-4 col-xl-2">
					<div class="row row-cols-2">
						<div class="col col-5 form-group">
							<label for="edit-session"><b><?php echo L::session; ?></b></label>
							<select id="edit-session"class="custom-select custom-select-sm" name="sessionNumber">
								<option value="" selected><?php echo L::showAll; ?></option>
								<?php
								for ($i=1; $i <= 192; $i++) { 
								 	echo '<option value="'.$i.'">'.$i.'. Sitzung</option>';
								} 
								?>
							</select>
						</div>
						<div class="col col-7 form-group">
							<label for="edit-electoralPeriod"><b><?php echo L::electoralPeriod; ?></b></label>
							<select id="edit-electoralPeriod"class="custom-select custom-select-sm" name="electoralPeriod">
								<option value="" selected><?php echo L::showAll; ?></option>
								<option value="19">19</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<hr>
			<div class="rangeContainer">
				<label for="timeRange"><b><?php echo L::timePeriod; ?>:</b></label>
				<input type="text" id="timeRange" readonly style="border:0; background: transparent;"/>
				<div id="timelineVizWrapper"></div>
				<div id="sliderRange"></div>
				<input type="hidden" id="timefrom" name="timefrom"/>
				<input type="hidden" id="timeto" name="timeto"/>
			</div>
		</div>
	</form>
</div>