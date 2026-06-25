<?php defined('OPTV') or die(); ?>
<?php
/**
 * Notification bell. Rendered in the header for logged-in users only.
 * The dropdown list is populated client-side by notificationBell.js, which also
 * polls the unread count.
 */
if (empty($_SESSION["login"]) || empty($config["allow"]["notifications"])) {
    return;
}
?>
<style>
#notificationBell .dropdown-toggle::after { display: none; }
#notificationBell .notificationBellBadge { position: absolute; top: -5px; right: 0; font-size: 10px; }
#notificationBell .notificationBellMenu { max-width: 90vw; }
#notificationBell .notificationBellList { max-height: 50vh; overflow-y: auto; }
#notificationBell .notificationItem { display: block; padding: .5rem .85rem; border-bottom: 1px solid rgba(0,0,0,.06); color: inherit; text-decoration: none; }
#notificationBell .notificationItem:hover { background: rgba(0,0,0,.04); }
#notificationBell .notificationItem.unread { background: rgba(13,110,253,.06); }
#notificationBell .notificationItem .nTitle { font-weight: 600; font-size: .85rem; }
#notificationBell .notificationItem .nBody { font-size: .8rem; opacity: .8; }
#notificationBell .notificationItem .nTime { font-size: .7rem; opacity: .6; }
</style>
<div class="dropdown d-inline notificationBell me-1" id="notificationBell">
	<button class="btn btn-sm dropdown-toggle position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="<?= hAttr(L::notifications()) ?>">
		<span class="icon-attention" aria-hidden="true" style="font-size:18px;"></span>
		<span class="notificationBellBadge badge rounded-pill border d-none" id="notificationBellBadge">0</span>
		<span class="visually-hidden"><?= L::notifications() ?></span>
	</button>
	<div class="dropdown-menu dropdown-menu-end notificationBellMenu p-0" style="width: 340px;">
		<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
			<strong><?= L::notifications() ?></strong>
			<a href="#" class="small" id="notificationMarkAllRead"><?= L::notificationMarkAllRead() ?></a>
		</div>
		<div id="notificationBellList" class="notificationBellList">
			<div class="text-center text-muted py-4 small">…</div>
		</div>
		<div class="border-top text-center py-2">
			<a href="<?= $config["dir"]["root"] ?>/notifications" class="small"><?= L::notificationViewAll() ?></a>
		</div>
	</div>
</div>
