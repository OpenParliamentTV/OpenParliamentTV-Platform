<?php defined('OPTV') or die(); ?>
<?php
/**
 * "Add to playlist" modal. Included once from core-scripts.php for logged-in
 * users; driven by playlist-manager.js. Every add surface (search results, the
 * transcript selection popover, the player title bar) opens this same dialog.
 */
if (empty($_SESSION["login"]) || empty($config["allow"]["playlists"])) {
    return;
}
?>
<div class="modal fade" id="playlistModal" tabindex="-1" aria-labelledby="playlistModalTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="playlistModalTitle"><?= L::playlistAddTo() ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= hAttr(L::cancel()) ?>"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small mb-2" id="playlistModalScope"></p>

				<div class="mb-3">
					<div id="playlistModalList" class="list-group"></div>
				</div>

				<div class="input-group input-group-sm">
					<input type="text" class="form-control" id="playlistModalNewTitle" placeholder="<?= hAttr(L::playlistNew()) ?>">
					<button class="btn btn-outline-primary" type="button" id="playlistModalCreate"><?= L::playlistNew() ?></button>
				</div>
			</div>
			<div class="modal-footer">
				<a href="<?= $config["dir"]["root"] ?>/manage/playlists" class="btn btn-sm btn-outline me-auto"><?= L::playlistManageTitle() ?></a>
				<button type="button" class="btn btn-sm btn-outline" data-bs-dismiss="modal"><?= L::close() ?></button>
			</div>
		</div>
	</div>
</div>
