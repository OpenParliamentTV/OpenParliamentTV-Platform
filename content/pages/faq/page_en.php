<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?= L::faq; ?></h2>
			<div class="accordion my-3" id="accordion">
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q1">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a1" aria-expanded="false" aria-controls="a1" itemprop="name">Who is behind Open Parliament TV?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a1" class="collapse" aria-labelledby="q1" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">You can find more information on the people behind the project at <a href="about#team">About the Project > Team</a>. We are in the process of founding a non-profit, which will operate and develop Open Parliament TV as a non-commercial project. <br><br>More at: <i>How are you financed?</i></div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q2">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a2" aria-expanded="false" aria-controls="a2" itemprop="name">Do you work with parliaments?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a2" class="collapse" aria-labelledby="q2" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Open Parliament TV is an independent project and is not operated by a parliament. We are however in contact with several parliament administrations and plan more direct partnerships with parliaments concerning the publication of video recordings in the future. Especially small parliaments often don't have the infrastructure and resources to operate media libraries like the German Bundestag. Open Parliament TV can offer these parliaments better visibility and accessibility. The idea is that parliaments only provide data interfaces and the publication / the operation of a respective platform can run via Open Parliament TV. <br><br>We are not a replacement for existing media libraries. What we offer is a meta platform, which makes contents searchable and connectable beyond the boundaries of existing platforms. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q3">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a3" aria-expanded="false" aria-controls="a3" itemprop="name">Where do you get the videos from?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a3" class="collapse" aria-labelledby="q3" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">In the case of the German Bundestag we retrieve the video recordings of speeches via the RSS interfaces which the media library of the German Bundestag offers. Depending on the parliament we will always get this information via openly accessible interfaces and - when that is not possible - work on the policy level to demand making these contents available as open data (possibly as a legal requirement). This then allows us but at the same time anyone else (civil society, journalists) to work with these contents. <br><br>For videos by the German Bundestag, the respective <a target="_blank" href="https://www.bundestag.de/nutzungsbedingungen">Terms of Use</a> apply. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q4">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a4" aria-expanded="false" aria-controls="a4" itemprop="name">Where do you obtain the proceedings and data?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a4" class="collapse" aria-labelledby="q4" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Since the 19th electoral period (2017) the proceedings of the German Bundestag are available in a <a href="https://www.bundestag.de/services/opendata" target="_blank">machine-readable „Open Data“ Format</a>. For other parliaments we work on extracting this information automatically from PDF files. In a perfect world all parliaments would publish their documents in machine-readable, internationally standardised formats (these standards exists, they are just not used). <br><br>The proceedings by the German Bundestag are in the public domain. <br><br>We retrieve the data on people, factions and parties from <a href="https://www.wikidata.org/" target="_blank">Wikidata</a> (a collaboratively edited multilingual knowledge graph). Additional data on documents is being requested via the "<a href="https://dip.bundestag.de/%C3%BCber-dip/hilfe/api" target="_blank">DIP API</a>" of the German Bundestag. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q5">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a5" aria-expanded="false" aria-controls="a5" itemprop="name">How do you synchronize the videos with the proceedings?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a5" class="collapse" aria-labelledby="q5" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">The synchronisation itself (the "alignment") builds upen the open source "forced alignment" software <a href="https://www.readbeyond.it/aeneas/" target="_blank">"Aeneas"</a> by Alberto Pettarin. We use this component to generate a time-based transcript (text fragments are linked to start -and end times in the video). <br><br> The method we use is based on the visual comparison of audio waves. Instead of using "speech-to-text" based on the original audio signal, we use "text-to-speech" to generate an audio version of the text proceedings. The wave form of this generated audio version is then visually compared with the wave form of the original audio. This makes the method almost language independent, as the pauses between sentences are more important for the synchronisation than the actual spoken word. <br><br> Before starting the alignment process we implemented many small steps that help us to find out which video matches which text fragment in the proceedings. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q6">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a6" aria-expanded="false" aria-controls="a6" itemprop="name">Why are some texts not synchronised?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a6" class="collapse" aria-labelledby="q6" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">We automatically extract the contents from the original proceedings. In rare cases this data contains human errors which irritate the alignment components. In this case we still show the text, but without an interactive transcript. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q7">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a7" aria-expanded="false" aria-controls="a7" itemprop="name">Why is there no text for some speeches?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a7" class="collapse" aria-labelledby="q7" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">In some special cases we are not able to determine 100% which text fragment in the proceedings matches the video recording. To avoid displaying false texts, we temporarily hide these parts and only show the video of the speech. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q8">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a8" aria-expanded="false" aria-controls="a8" itemprop="name">Why does the text differ from the spoken word?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a8" class="collapse" aria-labelledby="q8" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">We use the original proceedings as documented by the stenographers. These documents are created based on the spoken word while the session is running. The documents are however not a word-by-word transcript but are instead optimised for readability (removal of word repetitions etc.). Additionally, members of parliament have some time after the end of a session to implement corrections into the documentation of their speech. Baecause of all this, the proceedings differ from the exact spoken words. In rare cases this is also the reason for a slighly less accurate synchronisation. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q9">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a9" aria-expanded="false" aria-controls="a9" itemprop="name">Why is this not part of the media libraries of parliaments?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a9" class="collapse" aria-labelledby="q9" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Many parliaments don't operate media libraries. Additionally we are convinced that the kind of data enhancement we provide is not the duty of parliaments. It is much more important that parliaments make their raw data openly available to the public in a standardised form via standardised interfaces. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q10">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a10" aria-expanded="false" aria-controls="a10" itemprop="name">How are you financed?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a10" class="collapse" aria-labelledby="q10" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">From June 2020 until September 2021 Open Parliament TV was funded by Media Innovation Center Babelsberg (MIZ). In the future we plan to finance further developments via trial projects with parliament administrations, R&D projects and public funding for democracy-building. The day to day operations shall be financed by institutional users (parliaments, parties, media partners, NGOs) as well as foundations. <br><br>All contents on Open Parliament TV will always be publicly accessible (as well as the source code of all software components is openly licensed). Our mission is inherently public and it has to be supported by public means. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q11">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a11" aria-expanded="false" aria-controls="a11" itemprop="name">Does Open Parliament TV provide accessibility?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a11" class="collapse" aria-labelledby="q11" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">No, unfortunately our contents are not fully accessible yet for people with disabilities. We are currently working on this. But it will still take a while until we have tested and enhanced all parts of the platform with regards to accessibility features. <br><br>It was important for us to make the platform publicly accessible early. Unfortunately this also means we have to deliver this part at a later stage. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q12">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a12" aria-expanded="false" aria-controls="a12" itemprop="name">When can I find my parliament here?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a12" class="collapse" aria-labelledby="q12" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">We are in talks with several parliament administrations, "Parliamentary Monitoring Organisations" and NGOs in multiple countries. Our goal is to integrate more parliaments (from city councils to the EU Parliament and additional national parliaments) step by step with the help of local partners. As soon as we have news on this we'll communicate that via Twitter (@OpenParlTV). <br><br>Furthermore, Open Parliament TV is a decentralised concept: besides the integration into our platform, people can use our openly licensed technical components to start operating their own Open Parliament TV platforms. Via Open Data APIs the different platforms would still be connected and searchable beyond the boundaries of specific instances. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q13">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a13" aria-expanded="false" aria-controls="a13" itemprop="name">If your project is open source, are you not afraid someone steals it?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a13" class="collapse" aria-labelledby="q13" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Previous experiences with larger open source projects shows that the people who originally developed the project are mostly not left out in major new developments and enhancements (for example by a parliament). Before someone gets familiar with the code base, it makes much more sense to work with the original creators, who carry a lot of knowledge in building and operating such a project. <br><br>If nonetheless someone decides to "steal" our project, this is exactly how open source works and we will be happy that our work was obviously useful (see also our - also openly licensed - <a href="https://openparliament.tv/proposal" target="_blank">Project Proposal</a>). There are many parliaments in the world. There is no way we can cover all of them by ourselves anyway. <br><br>On top of that, the decision to open source all components of the project allows re-using the concept far beyond single parliaments or regions. Theoretically a parliament in India could instantly implement Open Parliament TV. We chose a license which requires additional developments to be licensed under the same open conditions. So all new developments can be freely used by anyone else. </div>
					</div>
				</div>
			</div>
			<div class="alert alert-info mt-4">Your question is not answered here? Contact us any time via joscha.jaeger [at] openparliament.tv or Twitter: @OpenParlTV.</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>