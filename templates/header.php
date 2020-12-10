<header>
	<nav class="navbar justify-content-between navbar-light">
		<div>
			<a href="./" class="navbar-text large brand"><img src="client/images/optv-logo_klein.png"><span class="<?=($_REQUEST["a"] != "play") ? "d-none d-sm-inline" : "d-none d-lg-inline"?>"><?php echo L::brand; ?></span></a><a href="./" class="navbar-text large"><span class="<?=($_REQUEST["a"] != "play") ? "" : "d-none d-md-inline"?>"><span class="mr-2">/</span><span>Deutscher Bundestag</span></span></a>
		</div>
		<div class="navbarCenterOptions">
			<?php
			if ($_REQUEST["a"] == "play" && $isResult) {
				$autoplayResultsClass = ($autoplayResults) ? "active" : "";
				$backParamStr = preg_replace('/(&playresults=[0-1])/', '', ltrim($paramStr, '&'));
			?>
				<a href='<?= "search?".$backParamStr ?>' class="btn btn-primary btn-sm"><span class="icon-left-open-big"></span><span class="icon-search"></span><span class="sr-only"><?php echo L::backToResults; ?></span></a>
				<div id="prevResultSnippetButton" class="btn btn-primary btn-sm"><span class="icon-left-open-big"></span><span class="sr-only"><?php echo L::previousSpeech; ?></span></div>
				<div id="nextResultSnippetButton" class="btn btn-primary btn-sm"><span class="icon-right-open-big"></span><span class="sr-only"><?php echo L::nextSpeech; ?></span></div>
				<div id="toggleAutoplayResults" class="navbar-text switch-container <?=$autoplayResultsClass?>">
					<span class="switch">
						<span class="slider round"></span>
					</span><span class="d-none d-md-inline"><?php echo L::autoplayResults; ?></span>
				</div>
			<?php
			}
			?>
		</div>
		<div class="navbarRightOptions">
			<button class="btn btn-primary btn-sm d-inline" type="button">
				<span class="icon-share"></span>
				<span class="sr-only"><?php echo L::share; ?></span>
			</button>
			<button class="btn btn-primary btn-sm d-inline" type="button" data-toggle="collapse" data-target="#navbarNavSettings" aria-controls="navbarNavSettings" aria-expanded="false" aria-label="Toggle settings">
				<span class="icon-cog-3"></span>
				<span class="sr-only"><?php echo L::settings; ?></span>
			</button>
			<a class="btn btn-primary btn-sm d-inline <?= ($page == "login") ? "active" : "" ?>" href="./login"><span class="icon-torso"></span><span class="sr-only">LABEL</span></a>
			<button class="btn btn-primary btn-sm d-inline" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
				<span class="icon-menu"></span>
			</button>
		</div>
		<div class="collapse navbar-collapse" id="navbarNavSettings" data-parent=".navbar">
			<ul class="navbar-nav">
				<li class="nav-item">SETTING</li>
			</ul>
		</div>
		<div class="collapse navbar-collapse" id="navbarNavDropdown" data-parent=".navbar">
			<ul class="navbar-nav">
				<li class="nav-item text-sm-right">
					<a class="nav-link <?= ($page == "about") ? "active" : "" ?>" href="./about"><?php echo L::about; ?></a>
				</li>
				<li class="nav-item text-sm-right">
					<a class="nav-link <?= ($page == "datapolicy") ? "active" : "" ?>" href="./datapolicy"><?php echo L::dataPolicy; ?></a>
				</li>
				<li class="nav-item text-sm-right">
					<a class="nav-link <?= ($page == "imprint") ? "active" : "" ?>" href="./imprint"><?php echo L::imprint; ?></a>
				</li>
			</ul>
		</div>
	</nav>
</header>