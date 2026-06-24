<?php
/**
 * Alert create/edit modal (Plan B). Included once in the footer for logged-in
 * users; driven by alertManager.js. First cut exposes real-time frequency only.
 */
if (empty($_SESSION["login"]) || empty($config["allow"]["notifications"])) {
    return;
}
?>
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="alertModalTitle"><?= L::alertCreateTitle() ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= hAttr(L::cancel()) ?>"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="alertModalId" value="">
				<input type="hidden" id="alertModalCriteria" value="">
				<div class="mb-3">
					<label class="form-label" for="alertModalLabel"><?= L::name() ?></label>
					<input type="text" class="form-control" id="alertModalLabel" maxlength="255">
				</div>
				<div class="mb-3">
					<label class="form-label"><?= L::alertCriteria() ?></label>
					<div id="alertModalCriteriaChips" class="border rounded px-2 pt-2 pb-1"></div>
				</div>
				<div class="mb-3">
					<label class="form-label" for="alertModalFrequency"><?= L::alertFrequency() ?></label>
					<select class="form-select" id="alertModalFrequency">
						<option value="realtime" selected><?= L::alertFrequencyRealtime() ?></option>
						<option value="daily"><?= L::alertFrequencyDaily() ?></option>
						<option value="weekly"><?= L::alertFrequencyWeekly() ?></option>
					</select>
				</div>
				<div class="mb-2">
					<label class="form-label d-block"><?= L::alertChannels() ?></label>
					<div class="form-check form-check-inline">
						<input class="form-check-input" type="checkbox" id="alertModalChannelInApp" checked>
						<label class="form-check-label" for="alertModalChannelInApp"><?= L::alertChannelInApp() ?></label>
					</div>
					<div class="form-check form-check-inline">
						<input class="form-check-input" type="checkbox" id="alertModalChannelEmail" checked>
						<label class="form-check-label" for="alertModalChannelEmail"><?= L::alertChannelEmail() ?></label>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-sm btn-outline" data-bs-dismiss="modal"><?= L::cancel() ?></button>
				<button type="button" class="btn btn-sm btn-primary" id="alertModalSave"><?= L::save() ?></button>
			</div>
		</div>
	</div>
</div>
