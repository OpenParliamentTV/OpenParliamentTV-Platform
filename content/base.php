<?php defined('OPTV') or die(); ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="ltr" <?= $schemaItemScopeString ?? '' ?>>
<head>
	<?php $this->insert('head') ?>
    <script type="text/javascript">
        const config = <?= json_encode([
            "dir" => [
                "root" => $config["dir"]["root"]
            ],
            "display" => [
                "ner" => $config["display"]["ner"],
                "speechesPerPage" => $config["display"]["speechesPerPage"]
            ],
            "isMobile" => $isMobile
        ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>

        const localizedLabels = <?= $langJSONString ?>;
    </script>
</head>
<body class='<?= hAttr($color_scheme."mode") ?> <?= (!empty($_SESSION["login"]) ? "login" : "") ?>'>
	<div class="mainLoadingIndicator">
		<div class="workingSpinner" style="position: fixed; top: 50%;"></div>
	</div>
	<?= $this->section('content') ?>
</body>
</html>
