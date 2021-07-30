<?php

include_once(__DIR__ . '/../../header.php');
include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);
//$auth["meta"]["requestStatus"] = "success";

if ($auth["meta"]["requestStatus"] != "success") {

    echo "NOT ALLOWED";
    echo "<pre>";
    //print_r($_SESSION);
    print_r($auth);
    echo "</pre>";

} else {

?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::about; ?></h2>
		</div>
	</div>
	<?php
	/*
	<!--
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::about; ?></h2>
			<p><img src="client/images/optv-logo.png" style="float: right; width: 200px; margin-left: 20px;">Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat.</p>
		</div>
		<div class="col-12 col-lg-9 col-xl-8">
			<div>Die Grundlage von Open Parliament TV wurde in einem 6-monatigen Projekt zusammen mit <a href="https://abgeordnetenwatch.de" target="_blank">abgeordnetenwatch.de</a> im Rahmen der <a href="https://demokratie.io" target="_blank">demokratie.io</a> Förderung geschaffen:</div>
			<div class="partnerLogos" style="background: #fff; padding: 30px 10px 0px 10px; margin-bottom: 10px;">
				<img src="client/images/logos/demokratie-io.png" style="height: 49px; margin-top: -9px;">
				<img src="client/images/logos/bmfsfj-dl.png" style="height: 90px; margin-top: -20px; margin-right: 10px;">
				<img src="client/images/logos/robert-bosch-stiftung.png" style="height: 40px; margin-top: -4px;">
				<div class="clearfix"></div>
			</div>
			<div>(siehe hierzu auch <a href="https://www.demokratie.io/category/learning_journey/abgeordnetenwatch-goes-video/index.html" target="_blank">unsere Artikel</a> im demokratie.io Blog)</div>
		</div>
	</div>
	<hr>
	<div class="row">
		<div class="col-12 col-md-8">
			<h3>Offenes Parlamentsfernsehen überall!</h3>
			<p>Aufbauend auf dem demokratie.io Projekt mit ... möchten wir ...</p>
			<a href="https://openparliament.tv/proposal" target="_blank" class="btn btn-primary btn-sm"><span class="icon-doc-text"></span>Open Parliament TV - Open Source Project Proposal (auf Englisch)</a>
		</div>
		<div class="col-12 col-md-4">
			<h3>Kontakt & Anfragen</h3>
			<p>Joscha Jäger, Projektleiter<br>
			Mail: [MAIL]<br>
			Twitter: [TWITTER]</p>
		</div>
	</div>
	<hr>
	<div class="row">
		<div class="col-12 col-md-8">
			<h3>FAQ</h3>
			<p>Vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. </p>
			<p>Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse</p>
		</div>
		<div class="col-12 col-md-4">
			<h3>Lorem Ipsum</h3>
			<p>Vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. </p>
			<p>Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse</p>
		</div>
	</div>
	-->

	*/
	?>

</main>
    <?php

}

include_once(__DIR__ . '/../../footer.php');

?>