<?php
require_once(__DIR__ . '/../../modules/utilities/security.php');
// Determine the page size based on the limit parameter or default config
$pageSize = $config["display"]["speechesPerPage"];
if (isset($_REQUEST["limit"]) && is_numeric($_REQUEST["limit"]) && intval($_REQUEST["limit"]) > 0) {
    $pageSize = intval($_REQUEST["limit"]);
}

$numberOfPages = ceil($totalResults / $pageSize);
$currentPage = (isset($_REQUEST["page"]) && $_REQUEST["page"] != "") ? $_REQUEST["page"] : 1;
$prevDisabledClass = ($currentPage == 1) ? "disabled" : "";
$nextDisabledClass = ($currentPage == $numberOfPages) ? "disabled" : "";

// Determine pagination context based on request parameters and context
$paginationContext = 'search'; // default

// Check for explicit context from the JavaScript MediaResults manager
if (isset($_REQUEST['paginationMode'])) {
    $paginationContext = $_REQUEST['paginationMode'] === 'embedded' ? 'ajax' : (isset($_REQUEST['baseUrl']) ? trim($_REQUEST['baseUrl'], '/') : 'search');
} elseif (isset($page)) {
    switch ($page) {
        case 'manage-media':
            $paginationContext = 'manage/media';
            break;
        case 'search':
        case 'main':
            $paginationContext = 'search';
            break;
        default:
            // For entity pages, use hash-based navigation
            if (isset($_REQUEST['context'])) {
                $paginationContext = 'ajax';
            } else {
                $paginationContext = 'search';
            }
    }
} else {
    // Fallback: detect from URL if $page is not available
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, '/manage/media') !== false) {
        $paginationContext = 'manage/media';
    } elseif (strpos($requestUri, '/content/components/') !== false && isset($_REQUEST['context'])) {
        $paginationContext = 'ajax';
    }
}

if ($_REQUEST["a"] == "search" && count($_REQUEST) > 1) {
    $pagePrev = $allowedParams;
    $pagePrev["page"] = $currentPage-1;

    $pageNext = $allowedParams;
    $pageNext["page"] = $currentPage+1;
?>
<nav aria-label="Paginierung" style="margin-top: 30px;">
	<ul class="pagination justify-content-center">
		<li class="page-item <?=$prevDisabledClass?>">
			<?php if ($paginationContext === 'ajax'): ?>
				<a class="page-link" href='#page=<?= $currentPage-1 ?>' aria-label="Vorherige">
			<?php else: ?>
				<a class="page-link" href='<?= $paginationContext ?>?<?= hAttr(preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pagePrev))) ?>' aria-label="Vorherige">
			<?php endif; ?>
				<span aria-hidden="true">&laquo;</span>
				<span class="visually-hidden"><?= L::previousPage(); ?></span>
			</a>
		</li>
		<?php
		$lastPageWasGap = false;
		for ($i=1; $i <= $numberOfPages; $i++) { 
			if ($i == 1) {
                $pageNumber = $allowedParams;
                unset($pageNumber["page"]);
			?>
				<li class="page-item <?php if ($i == $currentPage) {echo "active";}  ?>">
					<?php if ($paginationContext === 'ajax'): ?>
						<a class="page-link" href='#page=<?= $i ?>'><?= h($i) ?></a>
					<?php else: ?>
						<a class="page-link" href='<?= $paginationContext ?>?<?= hAttr(preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pageNumber))) ?>'><?= h($i) ?></a>
					<?php endif; ?>
				</li>
			<?php
			}
			else if ($i < 3 || 
				($i >= $currentPage-2 && $i <= $currentPage+2) || 
				$i > $numberOfPages-2 ) {
                $pageNumber = $allowedParams;
                $pageNumber["page"] = $i;
			?>
				<li class="page-item <?php if ($i == $currentPage) {echo "active";}  ?>">
					<?php if ($paginationContext === 'ajax'): ?>
						<a class="page-link" href='#page=<?= $i ?>'><?= h($i) ?></a>
					<?php else: ?>
						<a class="page-link" href='<?= $paginationContext ?>?<?= hAttr(preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pageNumber))) ?>'><?= h($i) ?></a>
					<?php endif; ?>
				</li>
			<?php
				$lastPageWasGap = false;
			} elseif (!$lastPageWasGap) {
			?>
				<li class="page-item disabled"><a class="page-link" href="#">...</a></li>
			<?php
				$lastPageWasGap = true;
			}
			?>
			<?php
		}
		?>
		<li class="page-item <?=$nextDisabledClass?>">
			<?php if ($paginationContext === 'ajax'): ?>
				<a class="page-link" href='#page=<?= $currentPage+1 ?>' aria-label="Nächste">
			<?php else: ?>
				<a class="page-link" href='<?= $paginationContext ?>?<?= hAttr(preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pageNext))) ?>' aria-label="Nächste">
			<?php endif; ?>
				<span aria-hidden="true">&raquo;</span>
				<span class="visually-hidden"><?= L::nextPage(); ?></span>
			</a>
		</li>
	</ul>
</nav>
<?php
}
?>