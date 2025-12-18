<?php
include_custom(realpath(__DIR__ . '/../../header.php'));
?>
<main class="container subpage" style="height: calc(100% - 70px);">
	<div class="h-100">
	<div class="row h-100 align-items-center justify-content-center">
    	<div class="col-10 col-sm-8 col-md-6 col-lg-5 col-xl-4 text-center">
			<h1 style="font-size: 60px; margin-top: -30px;">404</h1>
			<h2 style="font-size: 24px;"><?= L::messageErrorNotFoundQuote(); ?></h2>
			<div class="text-right pr-5">Jakob Maria Mierscheid, SPD</div>
    	</div>
    </div>
	</div>
</main>
<?php
include_once (include_custom(realpath(__DIR__ . '/../../footer.php'),false));
?>