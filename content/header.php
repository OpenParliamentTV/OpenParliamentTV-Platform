<header>
	<!--<div class="text-center alert m-0 px-1 py-0 alert-info" style="font-size: 14px;">* <span class="icon-attention mr-1"></span><a href="<?= $config["dir"]["root"] ?>/announcements" style="color: inherit; text-decoration: underline;"><?php echo L::messageAnnouncementCurrentState; ?></a></div>-->
	<nav class="navbar justify-content-between navbar-light">
		<div class="container-fluid px-0">
			<div class="<?=($page != "media") ? "flex-fill" : ""?>">
				<a href="<?= $config["dir"]["root"] ?>/" class="breadcrumb-page navbar-text large brand">
				<?php 
				if ($page != "media") {
					if ($page == "search" || $page == "main") {
						$brandClass = "d-block";
					} else {
						$brandClass = "d-none d-md-inline";
					} 
					
				} else {
					$brandClass = "d-none d-lg-inline";
				}
				?>
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/optv-logo_klein.png"><span class="<?= $brandClass ?>">Open <b>Parliament TV</b></span>
				</a>
				<!-- Beta Version Notice -->
				<!--
				<span style="font-size: 10px;position: relative;top: -2px;line-height: 10px;float: left;"><span class="icon-info-circled"></span><a href="<?= $config["dir"]["root"] ?>/version" style="display: inline-block;vertical-align: top;">Public <br>Beta</a></span>
				-->
				<?php 
					if (isset($pageBreadcrumbs)) {
						foreach ($pageBreadcrumbs as $breadcrumb) {
							if (isset($breadcrumb["path"])) {
								echo '<div class="breadcrumb-page">
									<span class="navbar-text breadcrumb-separator  text-truncate">/</span><a href="'.$config["dir"]["root"].$breadcrumb["path"].'" class="navbar-text ps-0 pe-0 text-truncate">'.$breadcrumb["label"].'</a>
								</div>';
							} else {
								echo '<div class="breadcrumb-page">
									<span class="navbar-text breadcrumb-separator  text-truncate">/</span><span class="navbar-text ps-0 pe-0 text-truncate">'.$breadcrumb["label"].'</span>
								</div>';
							}
						}
					}
				?>
			</div>
			
			<?php
			if ($_REQUEST["a"] == "media" && $isResult) {
				$autoplayResultsClass = (boolval($_REQUEST['playresults'])) ? "active" : "";
				$backParamStr = preg_replace('/(&playresults=[0-1])/', '', ltrim($paramStr, '&'));
				$backParamStr = preg_replace('/(&context=[^&]+)/', '', $backParamStr);
			?>
				<div class="navbarCenterOptions">
					<a href='<?= $config["dir"]["root"]."/search".$backParamStr ?>' class="btn btn-primary btn-sm"><span class="icon-left-open-big"></span><span class="icon-search"></span><span class="visually-hidden"><?php echo L::backToResults; ?></span></a>
					<div id="prevResultSnippetButton" class="btn btn-primary btn-sm"><span class="icon-left-open-big"></span><span class="visually-hidden"><?php echo L::previousSpeech; ?></span></div>
					<div id="nextResultSnippetButton" class="btn btn-primary btn-sm"><span class="icon-right-open-big"></span><span class="visually-hidden"><?php echo L::nextSpeech; ?></span></div>
					<div id="toggleAutoplayResults" class="navbar-text switch-container <?=$autoplayResultsClass?>">
						<span class="switch">
							<span class="slider round"></span>
						</span><span class="d-none d-md-inline"><?php echo L::autoplayResults; ?></span>
					</div>
				</div>
			<?php
			}
			?>
			<div class="navbarRightOptions">
				<div class="dropdown d-inline">
					<button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo L::menu; ?> <span class="icon-menu"></span></button>
					<div class="dropdown-menu dropdown-menu-end" style="width: 200px;">
						<div id="toggleDarkmode" style="padding-left: 1.5rem;" class="navbar-text switch-container <?= ($color_scheme == "dark") ? "active" : "" ?>">
							<span class="switch">
								<span class="slider round"></span>
							</span><span class="d-inline">Dark Mode</span>
						</div>
						<div class="dropdown-divider"></div>
						<div class="py-2 px-4"><?php echo L::chooseLanguage; ?>:</div>
						<div class="btn-group-vertical d-block px-4 mb-3" role="group">
							<?php
							foreach ($acceptLang as $tmpLangK=>$tmpLang) {
								$tmpParams = array_merge(array(),$_REQUEST);
								$tmpParams["lang"] = $tmpLang["short"];
								$linkChangeLanguage = "?".http_build_query($tmpParams);
								echo "<a class='btn btn-sm langswitch".(($lang==$tmpLang["short"])?" active" : "")."' href='".$linkChangeLanguage."' target='_self' data-lang='".$tmpLang["short"]."'>".$tmpLang["name"]."</a>";
							}
							?>
						</div>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item <?= ($page == "manage") ? "active" : "" ?><?= (!$_SESSION["login"]) ? " d-none" : "" ?>" href="<?= $config["dir"]["root"] ?>/manage">Dashboard</a>
						<!--
						<a class="dropdown-item <?= ($page == "login") ? "active" : "" ?><?= ($_SESSION["login"]) ? " d-none" : "" ?>" href="<?= $config["dir"]["root"] ?>/login"><?php echo L::login; ?> <span class="icon-login"></span></a>
						<a class="dropdown-item <?= ($page == "register") ? "active" : "" ?><?= ($_SESSION["login"]) ? " d-none" : "" ?>" href="<?= $config["dir"]["root"] ?>/register"><?php echo L::registerNewAccount; ?></a>
						-->
						<a class="dropdown-item <?= ($page == "logout") ? "active" : "" ?><?= (!$_SESSION["login"]) ? " d-none" : "" ?>" href="<?= $config["dir"]["root"] ?>/logout"><?php echo L::logout; ?> <span class="icon-logout"></span></a>
						<!--<div class="dropdown-divider"></div>-->
						<a class="dropdown-item <?= ($page == "about") ? "active" : "" ?>" href="<?= $config["dir"]["root"] ?>/about"><?php echo L::about; ?></a>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item <?= ($page == "datapolicy") ? "active" : "" ?>" href="<?= $config["dir"]["root"] ?>/datapolicy"><?php echo L::dataPolicy; ?></a>
						<a class="dropdown-item <?= ($page == "imprint") ? "active" : "" ?>" href="<?= $config["dir"]["root"] ?>/imprint"><?php echo L::imprint; ?></a>
					</div>
				</div>
			</div>
		</div>
	</nav>
</header>