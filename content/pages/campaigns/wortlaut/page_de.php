<?php

include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

include_once(__DIR__ . '/../../../header.php');
?>
<main>
	<section id="header" class="pb-5 bg-color-image">
		<div class="container">
			<div class="row justify-content-center" style="position: relative; z-index: 1">
				<div class="col-12 col-md-11 col-lg-9 col-xl-7" style="margin-top: 5%;">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/optv-logo.png" class="d-block d-md-inline" style="width: 200px; vertical-align: top; margin: 0 auto;">
					<h1 class="brand d-block d-md-inline-block">WORTlaut</b></h1>
				</div>
			</div>
			<div class="row justify-content-center" style="position: relative; z-index: 1">
				<div class="col-12">
					<h2 class="text-center" style="font-size: 1.7rem;">Lorem Ipsum</h2>
				</div>
			</div>
		</div>
	</section>
	<hr class="mt-0">
	<section id="platform" class="mb-4 py-5" style="">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-12 col-md-10 col-lg-6" style="font-size: 1.3rem;">
					<div class="alert text-center mt-0 mt-xl-3 mb-2 pt-0">Lorem Ipsum
					</div>
					<a class="btn btn-primary d-block py-2 mt-3 text-center mt-xl-4" style="background: #6f8087;color: #fff;border: none;" href="https://de.openparliament.tv" target="_blank" class="text-white"><span class="icon-right-open-big mr-1"></span> <b>https://de.openparliament.tv</b></a>
				</div>
				<div class="col-12 col-md-8 col-lg-6 mt-4 mt-lg-0">
					<a href="https://de.openparliament.tv"><img src="client/images/screenshot.png" title="Visit de.openparliament.tv" class="d-block" style="width: 100%; border: 2px solid #fff; box-shadow: 0 0 6px #999999;" alt="Screenshot of the Open Parliament TV Platform"></a>
				</div>
			</div>
		</div>
	</section>
	<section id="idea" class="mb-4">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<h2><span class="icon-lightbulb-1 mr-1" style="top: -4px;position: relative;"></span>Lorem Ipsum</h2>
				</div>
				<div class="col-12 col-md-6">
					<p>Lorem Ipsum</p>
					<p>Lorem Ipsum</p>
				</div>
				<div class="col-12 col-md-6">
					<p>Lorem Ipsum</p>
					<ul>
						<li>Lorem Ipsum</li>
						<li>Lorem Ipsum</li>
					</ul>
				</div>
				<div class="col-12">
					<a href="./vision-mission-strategy" class="btn btn-primary d-block py-2 mt-3" style="background: #6f8087;color: #fff;border: none;"><span class="icon-right-open-big mr-1"></span>More Info</a>
				</div>
			</div>
		</div>
	</section>
	<hr>
	<section id="testimonials" class="mb-4">
		<div class="container">
			<div class="row">
				<div class="col-12 col-md-6">
					<h2><span class="icon-megaphone mr-1"></span>Lorem Ipsum</h2>
				</div>
			</div>
			<div class="row justify-content-md-center">
				<div class="col-12 col-md-6 col-lg-6 col-xl-4 justify-content-center text-center my-3">
					Lorem Ipsum
				</div>
				<div class="col-12 col-md-6 col-lg-6 col-xl-4 justify-content-center text-center my-3">
					Lorem Ipsum
				</div>
				<div class="col-12 col-md-6 col-lg-6 col-xl-4 justify-content-center text-center my-3">
					Lorem Ipsum
				</div>
				<div class="col-12 col-md-6 col-lg-6 col-xl-4 justify-content-center text-center my-3">
					Lorem Ipsum
				</div>
				<div class="col-12 col-md-6 col-lg-6 col-xl-4 justify-content-center text-center my-3">
					Lorem Ipsum
				</div>
			</div>
		</div>
	</section>
</main>
    <?php

}

include_once(__DIR__ . '/../../../footer.php');

?>