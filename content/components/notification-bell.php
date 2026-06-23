<?php
/**
 * Notification bell (Plan B). Rendered in the header for logged-in users only.
 * The dropdown list is populated client-side by notificationBell.js, which also
 * polls the unread count.
 */
if (empty($_SESSION["login"]) || empty($config["allow"]["notifications"])) {
    return;
}
?>
<style>
#notificationBell .dropdown-toggle::after { display: none; }
#notificationBell .notificationBellBadge { position: absolute; top: 2px; right: 0; font-size: 10px; }
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
	<button class="btn btn-sm dropdown-toggle position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="<?= hAttr(L::notificationInboxTitle()) ?>">
		<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align:text-bottom;"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6.36-6.4V10.5c0-3.07-1.64-5.64-4.5-6.32V3.5a1.86 1.86 0 0 0-3.72 0v.68C7.28 4.86 5.64 7.42 5.64 10.5v5.1L4 17.2v.8h16v-.8l-1.64-1.6z"/></svg>
		<span class="notificationBellBadge badge rounded-pill bg-danger d-none" id="notificationBellBadge">0</span>
		<span class="visually-hidden"><?= L::notificationInboxTitle() ?></span>
	</button>
	<div class="dropdown-menu dropdown-menu-end notificationBellMenu p-0" style="width: 340px;">
		<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
			<strong><?= L::notificationInboxTitle() ?></strong>
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
