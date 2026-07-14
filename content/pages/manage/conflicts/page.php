<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/admin') ?>
<main class="container-fluid subpage">
	<div class="row">
		<?php include_once(__DIR__ . '/../sidebar.php'); ?>
		<div class="sidebar-content">
			<div class="row" style="position: relative; z-index: 1">
				<div class="col-12 mainContainer">
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="conflicts-tab" data-bs-toggle="tab" data-bs-target="#conflicts" role="tab" aria-controls="conflicts" aria-selected="true"><span class="icon-attention"></span> <?= L::conflicts(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="conflicts" role="tabpanel" aria-labelledby="conflicts-tab">
                            <div id="conflict-messages"></div>
                            <div class="contentContainer">
                                <table class="table table-striped" id="conflictsStats">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?= L::type(); ?></th>
                                            <th scope="col" class="text-end"><?= L::conflictStatusOpen(); ?></th>
                                            <th scope="col" class="text-end"><?= L::conflictStatusIgnored(); ?></th>
                                            <th scope="col" class="text-end"><?= L::conflictStatusResolved(); ?></th>
                                            <th scope="col" class="text-end"><?= L::affectedSpeeches(); ?></th>
                                            <th scope="col" class="minWidthColumn"><?= L::actions(); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="conflictsStatsBody">
                                        <tr><td colspan="6" class="text-center text-muted"><div class="spinner-border spinner-border-sm" role="status"></div></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div id="toolbar">
                                <select id="conflictStatusFilter" class="form-select form-select-sm d-inline-block w-auto me-1">
                                    <option value="open" selected><?= L::conflictStatusOpen(); ?></option>
                                    <option value="ignored"><?= L::conflictStatusIgnored(); ?></option>
                                    <option value="resolved"><?= L::conflictStatusResolved(); ?></option>
                                    <option value="all"><?= L::all(); ?></option>
                                </select>
                                <select id="conflictTypeFilter" class="form-select form-select-sm d-inline-block w-auto me-1">
                                    <option value="" selected><?= L::allTypes(); ?></option>
                                </select>
                                <span id="bulkActions" class="me-1">
                                    <button class="btn bulk-action-btn" id="bulkIgnoreBtn" title="<?= L::ignore(); ?>" disabled><span class="icon-eye-off"></span> <?= L::ignore(); ?></button>
                                    <button class="btn bulk-action-btn" id="bulkReopenBtn" title="<?= L::reopen(); ?>" disabled><span class="icon-eye"></span> <?= L::reopen(); ?></button>
                                    <button class="btn bulk-action-btn" id="bulkResolveBtn" title="<?= L::resolve(); ?>" disabled><span class="icon-ok"></span> <?= L::resolve(); ?></button>
                                    <button class="btn bulk-action-btn" id="bulkDeleteBtn" title="<?= L::delete(); ?>" disabled><span class="icon-trash"></span> <?= L::delete(); ?></button>
                                </span>
                                <button id="cleanupConflictsBtn" class="btn" title="<?= L::cleanup(); ?>"><span class="icon-magic"></span> <?= L::cleanup(); ?></button>
                            </div>
							<div id="conflictsDiv" class="contentContainer">
								<table id="conflictsTable"></table>
							</div>
                        </div>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

<!-- Conflict detail modal -->
<div class="modal fade" id="conflictDetailsModal" tabindex="-1" aria-labelledby="conflictDetailsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h1 class="modal-title fs-5" id="conflictDetailsModalLabel"><?= L::conflict(); ?></h1>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="conflictDetailsDiv" class="contentContainer" style="display: none">
					<div class="alert alert-secondary" id="conflictDetailsExplanation"></div>
					<table class="col-12 table-striped" id="conflictDetails" data-toggle="table">
						<tbody>
							<tr>
								<td><?= L::type(); ?></td>
								<td id="conflictDetailsType"></td>
							</tr>
							<tr>
								<td><?= L::label(); ?></td>
								<td id="conflictDetailsLabel"></td>
							</tr>
							<tr>
								<td>WikidataID</td>
								<td id="conflictDetailsWid"></td>
							</tr>
							<tr>
								<td><?= L::parliament(); ?></td>
								<td id="conflictDetailsParliament"></td>
							</tr>
							<tr>
								<td><?= L::statusLabel(); ?></td>
								<td id="conflictDetailsStatus"></td>
							</tr>
							<tr>
								<td><?= L::firstSeen(); ?></td>
								<td id="conflictDetailsFirstSeen"></td>
							</tr>
							<tr>
								<td><?= L::lastSeen(); ?></td>
								<td id="conflictDetailsLastSeen"></td>
							</tr>
							<tr>
								<td><?= L::additionalInformation(); ?></td>
								<td id="conflictDetailsData"></td>
							</tr>
							<tr>
								<td><?= L::affectedSpeeches(); ?> (<span id="conflictDetailsMediaCount"></span>)</td>
								<td id="conflictDetailsMedia"></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="w-100 btn btn-secondary" data-bs-dismiss="modal"><?= L::close(); ?></button>
			</div>
		</div>
	</div>
</div>

<!-- Modal for Adding New Entity -->
<div class="modal fade" id="addEntityModal" tabindex="-1" aria-labelledby="addEntityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEntityModalLabel"><?= L::manageEntitiesNew(); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded here by AJAX -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row w-100">
					<div class="col-6 ps-0">
                        <button type="button" class="btn btn-primary w-100" id="modalAddEntitySubmitBtn" disabled><span class="icon-plus"></span> <?= L::manageEntitiesNew(); ?></button>
                    </div>
					<div class="col-6 pe-0">
                        <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal"><?= L::cancel(); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.css?v=<?= $config["version"] ?>" media="all">
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.js?v=<?= $config["version"] ?>"></script>

<script type="application/javascript">
	$(function() {

		const conflictTypeMeta = {
			"person-missing-wikidata-id": { label: localizedLabels.conflictTypePersonMissingWikidataId, explanation: localizedLabels.conflictTypePersonMissingWikidataIdExplanation, addable: true },
			"faction-missing-wikidata-id": { label: localizedLabels.conflictTypeFactionMissingWikidataId, explanation: localizedLabels.conflictTypeFactionMissingWikidataIdExplanation, addable: true },
			"person-missing-context": { label: localizedLabels.conflictTypePersonMissingContext, explanation: localizedLabels.conflictTypePersonMissingContextExplanation, addable: false },
			"media-import-error": { label: localizedLabels.conflictTypeMediaImportError, explanation: localizedLabels.conflictTypeMediaImportErrorExplanation, addable: false },
			"media-validation-error": { label: localizedLabels.conflictTypeMediaValidationError, explanation: localizedLabels.conflictTypeMediaValidationErrorExplanation, addable: false },
			"annotation-import-error": { label: localizedLabels.conflictTypeAnnotationImportError, explanation: localizedLabels.conflictTypeAnnotationImportErrorExplanation, addable: false },
			"text-import-error": { label: localizedLabels.conflictTypeTextImportError, explanation: localizedLabels.conflictTypeTextImportErrorExplanation, addable: false },
			"document-missing-source-uri": { label: localizedLabels.conflictTypeDocumentMissingSourceUri, explanation: localizedLabels.conflictTypeDocumentMissingSourceUriExplanation, addable: false },
			"entity-suggestion-missing-id": { label: localizedLabels.conflictTypeEntitySuggestionMissingId, explanation: localizedLabels.conflictTypeEntitySuggestionMissingIdExplanation, addable: false },
			"legacy-unclassified": { label: localizedLabels.conflictTypeLegacyUnclassified, explanation: localizedLabels.conflictTypeLegacyUnclassifiedExplanation, addable: false }
		};

		const statusMeta = {
			"open": { label: localizedLabels.conflictStatusOpen, badge: "text-bg-warning" },
			"ignored": { label: localizedLabels.conflictStatusIgnored, badge: "text-bg-secondary" },
			"resolved": { label: localizedLabels.conflictStatusResolved, badge: "text-bg-success" }
		};

		function typeLabel(type) {
			return (conflictTypeMeta[type] && conflictTypeMeta[type].label) ? conflictTypeMeta[type].label : type;
		}

		function formatDate(ts) {
			if (!ts) { return ""; }
			return new Date(ts * 1000).toLocaleString("<?= $lang; ?>");
		}

		// Also escapes quotes so values are safe in attribute contexts
		function escapeHtml(value) {
			return String(value == null ? "" : value)
				.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
				.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
		}

		function showMessage(type, text) {
			$('#conflict-messages').attr('class', 'alert alert-' + type).html(text);
		}

		// Populate the type filter select
		for (const type in conflictTypeMeta) {
			$('#conflictTypeFilter').append('<option value="' + type + '">' + escapeHtml(typeLabel(type)) + '</option>');
		}

		// ---------------------------------------------------------------
		// Stats matrix (per type x status), refreshed after every mutation
		// ---------------------------------------------------------------
		function refreshStats() {
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/",
				data: { "action": "getItemsFromDB", "itemType": "conflict", "getStats": true },
				dataType: "json",
				success: function(ret) {
					if (!(ret && ret.meta && ret.meta.requestStatus === "success")) { return; }
					const matrix = {};
					(ret.data || []).forEach(function(row) {
						if (!matrix[row.ConflictType]) { matrix[row.ConflictType] = {}; }
						matrix[row.ConflictType][row.ConflictStatus] = row;
					});
					const $body = $('#conflictsStatsBody').empty();
					const totals = { open: 0, ignored: 0, resolved: 0, speeches: 0 };
					const types = Object.keys(matrix).sort();
					if (types.length === 0) {
						$body.append('<tr><td colspan="6" class="text-center text-muted">0</td></tr>');
						return;
					}
					types.forEach(function(type) {
						const cells = matrix[type];
						const count = function(status) { return (cells[status] && cells[status].ConflictCount) || 0; };
						const speeches = function(status) { return (cells[status] && cells[status].ConflictSpeechCount) || 0; };
						const totalForType = count('open') + count('ignored') + count('resolved');
						totals.open += count('open'); totals.ignored += count('ignored'); totals.resolved += count('resolved');
						totals.speeches += speeches('open');
						const statusCell = function(status) {
							const n = count(status);
							if (n === 0) { return '<span class="text-muted">0</span>'; }
							return '<a href="#" class="stats-filter-link" data-type="' + type + '" data-status="' + status + '"><span class="badge ' + statusMeta[status].badge + '">' + n + '</span></a>';
						};
						let actions = '';
						if (count('open') > 0) {
							actions += '<button class="list-group-item list-group-item-action stats-ignore-type" title="' + escapeHtml(localizedLabels.bulkIgnoreByType) + '" data-type="' + type + '" data-count="' + count('open') + '"><span class="icon-eye-off"></span></button>';
						}
						if (totalForType > 0) {
							actions += '<button class="list-group-item list-group-item-action stats-delete-type" title="' + escapeHtml(localizedLabels.bulkDeleteByType) + '" data-type="' + type + '" data-count="' + totalForType + '"><span class="icon-trash"></span></button>';
						}
						$body.append('<tr>' +
							'<td><span title="' + escapeHtml((conflictTypeMeta[type] || {}).explanation || '') + '">' + escapeHtml(typeLabel(type)) + '</span></td>' +
							'<td class="text-end">' + statusCell('open') + '</td>' +
							'<td class="text-end">' + statusCell('ignored') + '</td>' +
							'<td class="text-end">' + statusCell('resolved') + '</td>' +
							'<td class="text-end">' + speeches('open') + '</td>' +
							'<td><div class="list-group list-group-horizontal">' + actions + '</div></td>' +
						'</tr>');
					});
					$body.append('<tr class="fw-bold">' +
						'<td><?= L::total(); ?></td>' +
						'<td class="text-end">' + totals.open + '</td>' +
						'<td class="text-end">' + totals.ignored + '</td>' +
						'<td class="text-end">' + totals.resolved + '</td>' +
						'<td class="text-end">' + totals.speeches + '</td>' +
						'<td></td>' +
					'</tr>');
				}
			});
		}
		refreshStats();

		// ---------------------------------------------------------------
		// Main table
		// ---------------------------------------------------------------
		$('#conflictsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=conflict",
			classes: "table table-striped",
			locale: "<?= $lang; ?>",
			pagination: true,
			sidePagination: "server",
			dataField: "data",
			totalField: "total",
			search: true,
			searchAlign: "left",
			toolbar: "#toolbar",
			toolbarAlign: "right",
			serverSort: true,
			sortName: "ConflictCount",
			sortOrder: "desc",
			uniqueId: "ConflictID",
			clickToSelect: true,
			maintainSelected: true,
			queryParams: function(params) {
				var apiParams = {};
				apiParams.limit = params.limit || params.pageSize;
				apiParams.offset = params.offset || (params.pageNumber - 1) * (params.limit || params.pageSize);
				apiParams.search = params.search || params.searchText;
				apiParams.sort = params.sort || params.sortName;
				apiParams.order = params.order || params.sortOrder;
				apiParams.status = $('#conflictStatusFilter').val();
				if ($('#conflictTypeFilter').val()) {
					apiParams.type = $('#conflictTypeFilter').val();
				}
				return apiParams;
			},
			columns: [
				{
					field: "state",
					checkbox: true
				},
				{
					field: "ConflictType",
					title: localizedLabels.type,
					sortable: true,
					formatter: function(value) {
						const meta = conflictTypeMeta[value] || {};
						return '<span class="badge text-bg-light border" title="' + escapeHtml(meta.explanation || '') + '">' + escapeHtml(typeLabel(value)) + '</span>';
					}
				},
				{
					field: "ConflictEntityLabel",
					title: localizedLabels.entity,
					class: "w-100",
					sortable: true,
					formatter: function(value, row) {
						let output = value ? escapeHtml(value) : '<span class="text-muted">&mdash;</span>';
						if (row.ConflictEntityWid) {
							output += ' <a href="https://www.wikidata.org/wiki/' + encodeURIComponent(row.ConflictEntityWid) + '" target="_blank" class="text-nowrap">' + escapeHtml(row.ConflictEntityWid) + '</a>';
						}
						return output;
					}
				},
				{
					field: "ConflictCount",
					title: localizedLabels.affectedSpeeches,
					sortable: true
				},
				{
					field: "ConflictLastSeen",
					title: localizedLabels.lastSeen,
					sortable: true,
					formatter: formatDate
				},
				{
					field: "ConflictStatus",
					title: localizedLabels.statusLabel,
					sortable: true,
					formatter: function(value) {
						const meta = statusMeta[value] || { label: value, badge: "text-bg-light" };
						return '<span class="badge ' + meta.badge + '">' + escapeHtml(meta.label) + '</span>';
					}
				},
				{
					field: "ConflictID",
					title: localizedLabels.actions,
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {
						const meta = conflictTypeMeta[row.ConflictType] || {};
						let buttons = "<span class='conflictdetails list-group-item list-group-item-action' title='" + escapeHtml(localizedLabels.viewDetails) + "' data-id='" + value + "' data-bs-toggle='modal' data-bs-target='#conflictDetailsModal'><span class='icon-eye'></span></span>";
						if (meta.addable && row.ConflictStatus === "open") {
							buttons += "<button type='button' class='list-group-item list-group-item-action' title='" + escapeHtml(localizedLabels.manageEntitiesNew) + "' data-bs-toggle='modal' data-bs-target='#addEntityModal' data-entity-label='" + escapeHtml(row.ConflictEntityLabel || '') + "'><span class='icon-plus'></span></button>";
						}
						if (row.ConflictStatus === "open") {
							buttons += "<button type='button' class='list-group-item list-group-item-action conflict-set-status' title='" + escapeHtml(localizedLabels.ignore) + "' data-id='" + value + "' data-status='ignored'><span class='icon-eye-off'></span></button>";
						} else {
							buttons += "<button type='button' class='list-group-item list-group-item-action conflict-set-status' title='" + escapeHtml(localizedLabels.reopen) + "' data-id='" + value + "' data-status='open'><span class='icon-eye'></span></button>";
						}
						if (row.ConflictStatus !== "resolved") {
							buttons += "<button type='button' class='list-group-item list-group-item-action conflict-set-status' title='" + escapeHtml(localizedLabels.resolve) + "' data-id='" + value + "' data-status='resolved'><span class='icon-ok'></span></button>";
						}
						buttons += "<button type='button' class='list-group-item list-group-item-action conflict-delete' title='" + escapeHtml(localizedLabels.delete) + "' data-id='" + value + "'><span class='icon-trash'></span></button>";
						return "<div class='list-group list-group-horizontal'>" + buttons + "</div>";
					}
				}
			]
		});

		function refreshAll() {
			$('#conflictsTable').bootstrapTable('uncheckAll');
			$('#conflictsTable').bootstrapTable('refresh');
			refreshStats();
		}

		$('#conflictStatusFilter, #conflictTypeFilter').on('change', function() {
			$('#conflictsTable').bootstrapTable('refresh');
		});

		// Stats badges set the table filters
		$('#conflictsStats').on('click', '.stats-filter-link', function(e) {
			e.preventDefault();
			$('#conflictTypeFilter').val($(this).data('type'));
			$('#conflictStatusFilter').val($(this).data('status'));
			$('#conflictsTable').bootstrapTable('refresh');
		});

		// ---------------------------------------------------------------
		// Status changes / deletion
		// ---------------------------------------------------------------
		function changeStatus(ids, status) {
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/",
				type: "POST",
				data: { action: "changeItem", itemType: "conflict", id: ids.join(","), status: status },
				dataType: "json",
				success: function(response) {
					if (response && response.meta && response.meta.requestStatus === 'success') {
						showMessage('success', localizedLabels.messageConflictUpdated.replace('{count}', response.data.updatedCount));
						const currentFilter = $('#conflictStatusFilter').val();
						const leavesFilter = (currentFilter !== 'all' && currentFilter !== status);
						if (leavesFilter && ids.length <= 20) {
							Promise.all(ids.map(function(id) { return animateBootstrapTableRow('conflictsTable', id, 'delete', 600); })).then(refreshAll);
						} else {
							refreshAll();
						}
					} else {
						showMessage('danger', (response && response.errors && response.errors[0] && response.errors[0].detail) || 'Error');
					}
				},
				error: function() { showMessage('danger', 'Request failed'); }
			});
		}

		function deleteConflicts(params, count, ids) {
			const confirmText = (count === 1 && localizedLabels.conflictConfirmDelete) ? localizedLabels.conflictConfirmDelete : localizedLabels.conflictConfirmBulkDelete.replace('{count}', count);
			if (!confirm(confirmText)) { return; }
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/",
				type: "POST",
				data: Object.assign({ action: "deleteItem", itemType: "conflict" }, params),
				dataType: "json",
				success: function(response) {
					if (response && response.meta && response.meta.requestStatus === 'success') {
						showMessage('success', localizedLabels.messageConflictDeleted.replace('{count}', response.data.deletedCount));
						if (ids && ids.length <= 20) {
							Promise.all(ids.map(function(id) { return animateBootstrapTableRow('conflictsTable', id, 'delete', 600); })).then(refreshAll);
						} else {
							refreshAll();
						}
					} else {
						showMessage('danger', (response && response.errors && response.errors[0] && response.errors[0].detail) || 'Error');
					}
				},
				error: function() { showMessage('danger', 'Request failed'); }
			});
		}

		$('#conflictsTable').on('click', '.conflict-set-status', function() {
			changeStatus([$(this).data('id')], $(this).data('status'));
		});

		$('#conflictsTable').on('click', '.conflict-delete', function() {
			const id = $(this).data('id');
			deleteConflicts({ id: id }, 1, [id]);
		});

		// Bulk actions on selection
		function selectedIds() {
			return $('#conflictsTable').bootstrapTable('getSelections').map(function(row) { return row.ConflictID; });
		}
		$('#conflictsTable').on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table post-body.bs.table', function() {
			$('.bulk-action-btn').prop('disabled', selectedIds().length === 0);
		});
		$('#bulkIgnoreBtn').on('click', function() {
			const ids = selectedIds();
			if (ids.length && confirm(localizedLabels.conflictConfirmBulkIgnore.replace('{count}', ids.length))) { changeStatus(ids, 'ignored'); }
		});
		$('#bulkReopenBtn').on('click', function() {
			const ids = selectedIds();
			if (ids.length) { changeStatus(ids, 'open'); }
		});
		$('#bulkResolveBtn').on('click', function() {
			const ids = selectedIds();
			if (ids.length) { changeStatus(ids, 'resolved'); }
		});
		$('#bulkDeleteBtn').on('click', function() {
			const ids = selectedIds();
			if (ids.length) { deleteConflicts({ id: ids.join(",") }, ids.length, ids); }
		});

		// Per-type bulk actions in the stats matrix
		$('#conflictsStats').on('click', '.stats-ignore-type', function() {
			const type = $(this).data('type');
			if (confirm(localizedLabels.conflictConfirmBulkIgnore.replace('{count}', $(this).data('count')))) {
				$.ajax({
					url: config["dir"]["root"] + "/api/v1/",
					type: "POST",
					data: { action: "changeItem", itemType: "conflict", type: type, status: "ignored" },
					dataType: "json",
					success: function(response) {
						if (response && response.meta && response.meta.requestStatus === 'success') {
							showMessage('success', localizedLabels.messageConflictUpdated.replace('{count}', response.data.updatedCount));
							refreshAll();
						} else {
							showMessage('danger', (response && response.errors && response.errors[0] && response.errors[0].detail) || 'Error');
						}
					}
				});
			}
		});
		$('#conflictsStats').on('click', '.stats-delete-type', function() {
			deleteConflicts({ type: $(this).data('type') }, $(this).data('count'), null);
		});

		// ---------------------------------------------------------------
		// Cleanup (auto-resolve missing-entity conflicts whose entity now exists)
		// ---------------------------------------------------------------
		$('#cleanupConflictsBtn').on('click', function() {
			var $btn = $(this);
			$btn.prop('disabled', true).addClass('working');
			$('#conflict-messages').empty().removeAttr('class');
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/",
				type: "POST",
				data: { action: "cleanup", itemType: "conflicts" },
				dataType: "json",
				success: function(response) {
					$btn.prop('disabled', false).removeClass('working');
					if (response && response.meta && response.meta.requestStatus === 'success') {
						showMessage('success', localizedLabels.messageCleanupSuccess.replace('{count}', response.data.cleanedCount || 0));
						refreshAll();
					} else {
						showMessage('danger', (response && response.errors && response.errors[0] && response.errors[0].detail) || 'Error');
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					$btn.prop('disabled', false).removeClass('working');
					showMessage('danger', 'An AJAX error occurred: ' + textStatus + ' - ' + errorThrown);
				}
			});
		});

		// ---------------------------------------------------------------
		// Detail modal
		// ---------------------------------------------------------------
		$(".mainContainer").on("click", ".conflictdetails", function() {
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/",
				data: { "action": "getItemsFromDB", "itemType": "conflict", "id": $(this).data("id"), "status": "all" },
				success: function(ret) {
					if (ret && ret.data && ret.meta && ret.meta.requestStatus === "success") {
						const conflict = ret.data;
						const meta = conflictTypeMeta[conflict.ConflictType] || {};
						$("#conflictDetailsExplanation").text(meta.explanation || '');
						$("#conflictDetailsType").text(typeLabel(conflict.ConflictType));
						$("#conflictDetailsLabel").text(conflict.ConflictEntityLabel || '');
						$("#conflictDetailsWid").html(conflict.ConflictEntityWid ? '<a href="https://www.wikidata.org/wiki/' + encodeURIComponent(conflict.ConflictEntityWid) + '" target="_blank">' + escapeHtml(conflict.ConflictEntityWid) + '</a>' : '');
						$("#conflictDetailsParliament").text(conflict.ConflictParliament || '');
						$("#conflictDetailsStatus").html('<span class="badge ' + ((statusMeta[conflict.ConflictStatus] || {}).badge || 'text-bg-light') + '">' + escapeHtml((statusMeta[conflict.ConflictStatus] || {}).label || conflict.ConflictStatus) + '</span>');
						$("#conflictDetailsFirstSeen").text(formatDate(conflict.ConflictFirstSeen));
						$("#conflictDetailsLastSeen").text(formatDate(conflict.ConflictLastSeen));
						$("#conflictDetailsData").empty();
						if (conflict.ConflictData) {
							$("#conflictDetailsData").jsonView(conflict.ConflictData);
						}
						$("#conflictDetailsMediaCount").text(conflict.ConflictCount);
						$("#conflictDetailsMedia").empty();
						(conflict.ConflictMedia || []).forEach(function(mediaID) {
							if (mediaID.indexOf('origin:') === 0) {
								$("#conflictDetailsMedia").append(escapeHtml(mediaID) + '<br>');
							} else {
								$("#conflictDetailsMedia").append('<a href="<?= $config["dir"]["root"] ?>/media/' + encodeURIComponent(mediaID) + '" target="_blank">' + escapeHtml(mediaID) + '</a><br>');
							}
						});
						if (conflict.ConflictCount > (conflict.ConflictMedia || []).length) {
							$("#conflictDetailsMedia").append('<span class="text-muted">&hellip;</span>');
						}
						$("#conflictDetailsDiv").show();
					} else {
						console.error("Error fetching conflict details:", ret);
					}
				}
			});
		});

		const conflictDetailsModal = document.getElementById('conflictDetailsModal');
		conflictDetailsModal.addEventListener('hidden.bs.modal', event => {
			$("#conflictDetailsDiv").hide();
		});

		// ---------------------------------------------------------------
		// Add-entity modal (loads the shared entity form; the label is
		// prefilled, the admin finds and pastes the Wikidata ID). The
		// conflict row intentionally stays: it auto-clears once the fixed
		// source data has been re-imported (or via the Cleanup button).
		// ---------------------------------------------------------------
		const addEntityModal = document.getElementById('addEntityModal');
		if (addEntityModal) {
			addEntityModal.addEventListener('show.bs.modal', function (event) {
				const modalBody = addEntityModal.querySelector('.modal-body');
				modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

				const componentUrl = '<?= $config["dir"]["root"] ?>/content/components/entity.form.php';
				const button = event.relatedTarget;
				const entityLabelFromButton = button && button.dataset.entityLabel ? button.dataset.entityLabel : null;

				$(modalBody).load(componentUrl, function(response, status, xhr) {
					if (status == "error") {
						modalBody.innerHTML = "Error loading entity form: " + xhr.status + " " + xhr.statusText;
					} else {
						const $theForm = $(modalBody).find('#entityAddForm');
						if (entityLabelFromButton) {
							$theForm.data('entity-label', entityLabelFromButton);
						}

						const $formInternalSubmitBtn = $theForm.find('#entityAddFormSubmitBtn');
						const $modalSubmitBtn = $('#modalAddEntitySubmitBtn');
						$modalSubmitBtn.prop('disabled', $formInternalSubmitBtn.prop('disabled'));

						const observer = new MutationObserver(function(mutations) {
							mutations.forEach(function(mutation) {
								if (mutation.attributeName === "disabled") {
									$modalSubmitBtn.prop('disabled', $formInternalSubmitBtn.prop('disabled'));
								}
							});
						});
						if ($formInternalSubmitBtn.length) {
							observer.observe($formInternalSubmitBtn[0], { attributes: true });
						}
						$(addEntityModal).data('observer', observer);
					}
				});
			});

			addEntityModal.addEventListener('hidden.bs.modal', function (event) {
				const observer = $(addEntityModal).data('observer');
				if (observer) {
					observer.disconnect();
					$(addEntityModal).removeData('observer');
				}
				addEntityModal.querySelector('.modal-body').innerHTML = '';
				$('#modalAddEntitySubmitBtn').prop('disabled', true);
				refreshAll();
			});

			$('body').on('click', '#modalAddEntitySubmitBtn', function() {
				const $form = $('#addEntityModal .modal-body #entityAddForm');
				if ($form.length) {
					$form.submit();
				}
			});
		}

	})
</script>
<style type="text/css">
	#conflictsStats {
		margin-bottom: 30px;
	}
	#toolbar .btn {
		white-space: nowrap;
	}
	#conflictDetailsData {
		background: #fafafa;
		color: #986801;
		max-height: 300px;
		overflow: auto;
	}
	#conflictDetailsData .b {
		color: #383a42;
	}
	#conflictDetailsData li > span:not(.num):not(.null):not(.q):not(.block),
	#conflictDetailsData .str, #conflictDetailsData a {
		color: #50a14f;
	}
	#conflictDetailsMedia {
		max-height: 200px;
		overflow: auto;
	}
</style>
