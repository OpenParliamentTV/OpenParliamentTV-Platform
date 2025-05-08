<?php

include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

$quotes1 = [
"/media/DE-0190075036?t=313,324.28&f=WirBrauEine,GeldZuTun&c=l",
"/media/DE-0190017097?t=405.56,412.28&f=SieSollImme,DieVerfWerd&c=l",
"/media/DE-0190042120?t=124.36,130.84&f=AberEineDrfe,DemBadeAuss&c=l",
"/media/DE-0190098154?t=142.48,147.16&f=AberPoliIst,IstKeinWuns&c=l",
"/media/DE-0200034106?t=42.74,55.60&f=InEineFrem,AlleKeinZuck&c=l",
"/media/DE-0190146110?t=117.52,121.7&f=DennOftGilt,BeieDieHund&c=l",
"/media/DE-0190190019?t=34.16,42.32&f=AberLiebKoll,PaarSchlDane&c=l",
"/media/DE-0190161113?t=127.84,136&f=LiebKollUnd,NichZuSehe&c=l",
"/media/DE-0190169064?t=39.48,48.3&f=AberNunJa,SeisDrum&c=l",
"/media/DE-0190237081?t=226.16,235.58&f=BeiDerBafi,WirNirgGese&c=l",
"/media/DE-0190126060?t=89.2,92.52&f=InBadeSage,MachAuchMist&c=l",
"/media/DE-0190164121?t=14.8,24.96&f=LastButNot,ZeitKommRat&c=l",
"/media/DE-0200032061?t=186.92,191.48&f=DennNichViel,DateHelfViel&c=l",
"/media/DE-0190068031?t=83,94.68&f=EineVielVon,KcheDenBrei&c=l",
"/media/DE-0190188069?t=22.42,27.74&f=WasLangWhrt,LeidNichImme&c=l",
"/media/DE-0190074161?t=23.6,38.2&f=InEineErst,KeinKonsHabe&c=l",
"/media/DE-0190062100?t=55.32,66.56&f=AberAnscSind,PopuImmeGera&c=l",
"/media/DE-0190086053?t=307.6,322.92&f=WennIchHre,AlleAtomBern&c=l",
"/media/DE-0190231041?t=117.02,127.24&f=IchSageGanz,MussAuchNich&c=l",
"/media/DE-0190127042?t=189.12,194&f=ZusaAlleRich,RostHerrMini&c=l",
"/media/DE-0190235074?t=119.48,122.72&f=DasZeigWo,AuchEinWeg&c=l",
"/media/DE-0200010071?t=104.2,111.28&f=UndDiesKlar,FrVerbBede&c=l",
"/media/DE-0190237081?t=226.16,235.58&f=BeiDerBafi,WirNirgGese&c=l",
"/media/DE-0190230133?t=51.96,59.82&f=AberWoViel,IstAuchScha&c=l",
"/media/DE-0190061201?t=215.92,227.16&f=WennSieHier,WerdNichReic&c=l",
"/media/DE-0190043097?t=48.68,58.32&f=GermNextTrum,DerPatrAg&c=l",
"/media/DE-0190124168?t=9.8,16.98&f=ZumEinmDer,DieSpraAn&c=l",
"/media/DE-0190118196?t=247,249.76&f=NehmSieDas,AufsLandHina&c=l",
"/media/DE-0190067012?t=9.56,20.72&f=MeinGromHat,MeinAufgErma&c=l",
"/media/DE-0190115227?t=181.6,190&f=DennNichJede,PraxEhrlKauf&c=l",
"/media/DE-0200038038?t=9.36,16.28&f=TgliGrtDas,DenDcheTrll&c=l",
"/media/DE-0190115195?t=113.32,116.6&f=IchSageIhne,ImSackKauf&c=l",
"/media/DE-0190028046?t=281.32,289.52&f=InOstfWrde,SieFedeHabe&c=l",
"/media/DE-0190093066?t=369.98,378.72&f=BeiDerErhh,JahrEineSchn&c=l",
"/media/DE-0190226041?t=121.52,127&f=AberSieHabe,KannNichSage&c=l",
"/media/DE-0190042075?t=14.88,20.2&f=NachKohlFein,DurcDorfGetr&c=l",
"/media/DE-0200044061?t=137.6,145.76&f=MussManDesw,HeutImHufe&c=l",
"/media/DE-0190029156?t=10.48,19.2&f=ErstScheMein,TaraGestReag&c=l",
"/media/DE-0190192006?t=443.28,449.32&f=UndCeteCens,SpecFrauKarl&c=l"
];

function getImageURLfromQuoteURL($quoteURL = "") {
	
	global $config;

	$imageURL = $config["dir"]["root"]."/content/client/images/share-image.php?id=";

	$re = '/\/media\/([a-zA-Z0-9-]+)\?(t=.+)/m';

	preg_match_all($re, $quoteURL, $matches, PREG_SET_ORDER, 0);

	/*
	echo "<pre>";
	print_r($matches[0]);
	echo "</pre>";
	*/

	$imageURL .= $matches[0][1];
	$imageURL .= "&".$matches[0][2];

	return $imageURL;
}

$quoteImages1 = array();

foreach ($quotes1 as $quote) { 
	$quoteImages1[] = getImageURLfromQuoteURL($quote);
}

include_once(__DIR__ . '/../../../header.php');
?>
<main>
	<section id="header" class="pb-5 bg-color-image">
		<div class="container">
			<div class="row justify-content-center" style="position: relative; z-index: 1">
				<div class="col-12 col-md-11 col-lg-9 col-xl-7" style="margin-top: 5%;">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/optv-logo.png" class="d-block d-md-inline" style="width: 200px; vertical-align: top; margin: 0 auto;">
					<h1 class="d-block d-md-inline-block" style="color: #73747c; font-size: 3.2em; vertical-align: middle;margin-top: 75px;margin-left: 30px;">WORTlaut</b></h1>
				</div>
			</div>
		</div>
	</section>
	<hr class="mt-0">
	<section class="mb-4">
		<div class="container">
			<div class="row">
				<div class="col-12 my-4 alert alert-info" role="alert">Diese Seite ist ein Entwurf und nur durch den Direktlink abrufbar. Bitte vorerst nicht weiter teilen / veröffentlichen. </div>
				<div class="col-12">
					<h2>Zitierbare Parlamentsdebatten</h2>
				</div>
			</div>
			<div class="row">
				<div class="col-12 col-lg-5">
					<p>Auf der Open <b>Parliament TV</b> Plattform sind Videoaufzeichnungen der Redebeiträge durch eine Synchronisation mit den Plenarprotokollen <b>Wort für Wort durchsuchbar</b>. </p>
					<p><b>Aber da geht noch mehr:</b> <br>Wir machen die Debatten nicht nur besser durchsuchbar, sondern ermöglichen auch das <b>einfache und schnelle Zitieren</b> von Aussagen. </p>
					<p>Mithilfe der Zitier-Funktion können <b>Ausschnitte</b> aus Parlamentsreden auf anderen Plattformen <b>geteilt werden</b>. Der geteilte Link führt dann direkt zurück zu der Stelle im Video, in der das Zitat auftaucht. </p>
					<p>Hierdurch machen wir die Inhalte der Plenarprotokolle <b>besser nutzbar</b> und öffnen sie für einen <b>neuen Blick auf politische Sprache in den Parlamenten</b>. </p>
				</div>
				<div class="col-12 col-lg-7">
					<video class="w-100" controls poster="<?= $config["dir"]["root"] ?>/content/client/videos/functionality-citation.png">
    					<source src="<?= $config["dir"]["root"] ?>/content/client/videos/functionality-citation.mp4" type="video/mp4">
    				</video>
				</div>
			</div>
		</div>
	</section>
	<hr>
	<section class="mb-4">
		<div class="container">
			<div class="row justify-content-md-center text-center">
				<div class="col-6 col-lg-4 col-xl-3">
					<div class="icon-link me-1" style="height: 70px; font-size: 40px;"></div>
					<p><b>Direkte Links</b> <br>zu Zitaten</p>
				</div>
				<div class="col-6 col-lg-4 col-xl-3">
					<div class="icon-share me-1" style="height: 70px; font-size: 40px;"></div>
					<p><b>Teilen und Kommentieren</b> <br>von Aussagen</p>
				</div>
				<div class="col-6 col-lg-4 col-xl-3">
					<div class="icon-eye me-1" style="height: 70px; font-size: 40px;"></div>
					<p><b>Mehr Sichtbarkeit und Transparenz</b> <br>für Parlamentsdebatten</p>
				</div>
				<div class="col-6 col-lg-4 col-xl-3">
					<div class="icon-chat-1 me-1" style="height: 70px; font-size: 40px;"></div>
					<p><b>Mehr Partizipation</b> <br>durch bessere Nutzbarkeit</p>
				</div>
			</div>
		</div>
	</section>
	<hr class="mb-0">
	<section class="py-4" style="background: var(--primary-bg-color);">
		<div class="container-fluid">
			<div class="row mx-2 text-center">
				<div class="col-12">
					<h2>Zitate-Galerie</h2>
				</div>
			</div>
			<div class="row justify-content-md-center mx-2 mb-4">
				<?php for ($i=0; $i < count($quoteImages1); $i++) { ?>
					<div class="col-12 col-md-6 col-lg-4 col-xl-3 justify-content-center text-center my-3">
						<a href="<?= $config["dir"]["root"] ?><?= $quotes1[$i] ?>">
							<img class="w-100" src="<?= $quoteImages1[$i] ?>">
						</a>
					</div>
				<?php } ?>
			</div>
		</div>
	</section>
	<hr class="mt-0">
	<section class="mb-4 pb-4">
		<div class="container-fluid">
			<div class="row mx-3 text-center">
				<div class="col-12">
					<h2>Mehr zu: Sprache im Parlament</h2>
				</div>
			</div>
			<div class="row justify-content-md-center text-center mx-3">
				<div class="col-12 col-md-6 col-lg-4 col-xl-3 my-4">
					<a class="font-weight-bolder" target="_blank" href="https://www.zeit.de/politik/deutschland/2019-09/bundestagsdebatten-kirschkuchen-steuerreform-gerhard-schroeder-angela-merkel" class="d-block">Drei Tage Kirschkuchen</a>
					<div class="less-opacity">ZEIT ONLINE</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4 col-xl-3 my-4">
					<a class="font-weight-bolder" target="_blank" href="https://www.zeit.de/politik/deutschland/2019-09/frauen-bundestag-70-jahre-parlament-gleichberechtigung" class="d-block">Vom Fräulein zur Bundeskanzlerin</a>
					<div class="less-opacity">ZEIT ONLINE</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4 col-xl-3 my-4">
					<a class="font-weight-bolder" target="_blank" href="https://www.bpb.de/themen/parteien/sprache-und-politik/42720/schlagwoerter/" class="d-block">Schlagwörter - Sprache und Politik</a>
					<div class="less-opacity">Bundeszentrale für politische Bildung</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4 col-xl-3 my-4">
					<a class="font-weight-bolder" target="_blank" href="https://www.sueddeutsche.de/projekte/artikel/politik/wie-der-bundestag-ueber-klimapolitik-spricht-e704090/" class="d-block"> Wie der Bundestag den Klimawandel verdrängte</a>
					<div class="less-opacity">Süddeutsche Zeitung</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4 col-xl-3 my-4">
					<a class="font-weight-bolder" target="_blank" href="https://www.sueddeutsche.de/projekte/artikel/politik/bundestag-das-gehetzte-parlament-e953507/" class="d-block">Das gehetzte Parlament</a>
					<div class="less-opacity">Süddeutsche Zeitung</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4 col-xl-3 my-4">
					<a class="font-weight-bolder" target="_blank" href="https://www.spiegel.de/politik/deutschland/sprache-im-bundestag-frauen-in-afd-reden-nahezu-unsichtbar-a-625aaddc-fdf7-4860-a700-2f635a542fb4" class="d-block">  Frauen in AfD-Reden nahezu unsichtbar</a>
					<div class="less-opacity">DER SPIEGEL</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4 col-xl-3 my-4">
					<a class="font-weight-bolder" target="_blank" href="https://www.t-online.de/nachrichten/deutschland/id_100077148/-dummschwaetzer-neuer-rekord-im-bundestag-wird-so-viel-gepoebelt-wie-nie.html" class="d-block">"Lügner, Dummschwätzer": Im Bundestag wird mehr gepöbelt</a>
					<div class="less-opacity">t-online</div>
				</div>
				
			</div>
		</div>
	</section>
</main>
    <?php

}

include_once(__DIR__ . '/../../../footer.php');

?>