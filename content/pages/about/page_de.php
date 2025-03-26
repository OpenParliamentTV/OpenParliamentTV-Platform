<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {

include_once(__DIR__ . '/../../header.php');
?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::about; ?></h2>
		</div>
	</div>
	<div class="row">
		<div class="col-12 col-lg-8">
			<p>Diese Suchmaschine und interaktive Videoplattform für Parlamentsdebatten ist Teil des <a href="https://openparliament.tv">Open Parliament TV Projekts</a>. Unser Ziel ist es, <b>Parlamentsdebatten transparenter und besser zugänglich zu machen</b>. </p>
			<p>Wir entwickeln mit Open Parliament TV die Abläufe und Werkzeuge, welche für einen zeitgemäßen Umgang mit den Inhalten des Parlamentsfernsehens nötig sind. Im Kern <b>synchronisieren </b>wir die<b> Videoaufzeichnungen </b>mit den<b> Plenarprotokollen.</b> Hierüber können wir die Videos zunächst über eine Volltextsuche zugänglich machen.</p>
			<p>Durch die Verknüpfung von Videoaufzeichnung und Protokolltext können wir die Reden zusätzlich</p>
			<ul>
				<li>um <b>interaktive Transkripte</b> erweitern <br>(Klick auf einen Satz &gt; Sprung zu Zeitpunkt im Video)</li>
				<li>mit <b>kontext-basierten Annotationen</b> verknüpfen <br>(Anzeige relevanter Dokumente zu bestimmten Zeitpunkten)</li>
				<li>mit <b>neuen Beteiligungsmöglichkeiten</b> besser zugänglich machen <br>(zitieren, einbinden und teilen ausgewählter Videosegmente im Redekontext)</li>
			</ul>
			<p>Bürger:innen und Journalist:innen erhalten mit Open Parliament TV ein Werkzeug, welches das <b>Auffinden</b>, <b>Teilen</b> und <b>Zitieren</b> von Videoausschnitten aus Parlamentsreden enorm erleichtert. So lassen sich basierend auf einzelnen Schlüsselwörtern oder Satzbausteinen in Sekundenbruchteilen die entsprechenden Ausschnitte finden, abspielen und dann als Zitat in andere Plattformen einbinden.</p>
			<p>Neben der Volltextsuche ermöglicht Open Parliament TV noch viele <b>weitere Einstiege in die Debatten</b>. So lassen sich Redebeiträge beispielsweise von Profilseiten der Fraktionen abrufen oder es ist möglich alle Reden anzusehen, in denen eine bestimmte Drucksache oder ein bestimmtes Gesetz erwähnt wird. Diese Funktionalitäten möchten wir zukünftig noch ausbauen und um semi-automatisierte Analysen der Plenarprotokolle ergänzen. </p>
			<a href="https://openparliament.tv" target="_blank" class="btn btn-primary btn-sm d-block"><span class="icon-right-open-big me-1"></span> Mehr zur Vision, Mission, Strategie und Anwendungsbereichen von Open Parliament TV</a>
			<hr>
			<h3>Open Data</h3>
			<p>Alle Daten auf Open Parliament TV können über unsere <b>Open Data API</b> abgefragt werden: </p>
			<ul>
				<li><a href="api">API Dokumentation</a></li>
			</ul>
			<hr>
			<h3>Open Source</h3>
			<p>Open Parliament TV ist ein <b>nicht-kommerzielles Open Source Projekt</b>. Alle Projektbausteine stehen unter <b>freien Lizenzen</b> und sind auf Github zu finden: </p>
			<ul>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture" target="_blank">OpenParliamentTV-Architecture</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Platform" target="_blank">OpenParliamentTV-Platform</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Parsers" target="_blank">OpenParliamentTV-Parsers</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-NEL" target="_blank">OpenParliamentTV-NEL</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Alignment" target="_blank">OpenParliamentTV-Alignment</a></li>
			</ul>
			<hr>
			<h3>Häufige Fragen</h3>
			<p>Fragen zum Projekt, zu unserer Datengrundlage und zu technischen Einzelheiten beantworten wir unter <a href="https://openparliament.tv/faq">Häufige Fragen</a>.</p>
		</div>
		<div class="col-12 col-lg-4">
			<hr class="d-block d-lg-none">
			<h3>Kontakt & Anfragen</h3>
			<p>Joscha Jäger, Gründer & Projektleiter<br>
			Mail: joscha.jaeger [AT] openparliament.tv<br>
			Twitter: <a href="https://twitter.com/OpenParlTV" target="_blank">@OpenParlTV</a></p>
		</div>
	</div>
</main>
    <?php

}

include_once(__DIR__ . '/../../footer.php');

?>