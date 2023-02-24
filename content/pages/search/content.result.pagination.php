<?php
$hitsPerPage = 40;
$numberOfPages = ceil($totalResults / 40);
$currentPage = (isset($_REQUEST["page"]) && $_REQUEST["page"] != "") ? $_REQUEST["page"] : 1;
$prevDisabledClass = ($currentPage == 1) ? "disabled" : "";
$nextDisabledClass = ($currentPage == $numberOfPages) ? "disabled" : "";
//$cleanParamStr = preg_replace("/&page=[0-9]+/m", "", ltrim($paramStr, '&'));
//preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($allowedParams))
if ($_REQUEST["a"] == "search" && count($_REQUEST) > 1) {
    $pagePrev = $allowedParams;
    $pagePrev["page"] = $currentPage-1;

    $pageNext = $allowedParams;
    $pageNext["page"] = $currentPage+1;
?>
<nav aria-label="Paginierung" style="margin-top: 30px;">
	<ul class="pagination justify-content-center">
		<li class="page-item <?=$prevDisabledClass?>">
			<a class="page-link" href='search?<?=preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pagePrev)) ?>' aria-label="Vorherige">
				<span aria-hidden="true">&laquo;</span>
				<span class="sr-only"><?php echo L::previousPage; ?></span>
			</a>
		</li>
		<?php
		$lastPageWasGap = false;
		for ($i=1; $i <= $numberOfPages; $i++) { 
			if ($i == 1) {
                $pageNumber = $allowedParams;
                $pageNumber["page"] = $i;
			?>
				<li class="page-item <?php if ($i == $currentPage) {echo "active";}  ?>"><a class="page-link" href='search?<?=preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pageNumber)) ?>'><?=$i?></a></li>
			<?php
			}
			else if ($i < 3 || 
				($i >= $currentPage-2 && $i <= $currentPage+2) || 
				$i > $numberOfPages-2 ) {
                $pageNumber = $allowedParams;
                $pageNumber["page"] = $i;
			?>
				<li class="page-item <?php if ($i == $currentPage) {echo "active";}  ?>"><a class="page-link" href='search?<?=preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pageNumber)) ?>'><?=$i?></a></li>
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
			<a class="page-link" href='search?<?=preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($pageNext)) ?>' aria-label="Nächste">
				<span aria-hidden="true">&raquo;</span>
				<span class="sr-only"><?php echo L::nextPage; ?></span>
			</a>
		</li>
	</ul>
</nav>
<?php
}
?>