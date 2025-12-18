<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');
include_once(__DIR__ . '/../../../../../modules/utilities/security.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {
    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");
} else {
    include_once(include_custom(realpath(__DIR__ . '/../../../../header.php'),false));
    // Ensure API functions and the apiV1 dispatcher are available
    require_once(__DIR__ . '/../../../../../api/v1/utilities.php'); // For createApiErrorResponse, etc.
    require_once(__DIR__ . '/../../../../../api/v1/api.php'); // For apiV1 function

    $conflict = null; // Initialize conflict variable

    if (isset($_REQUEST["id"])) {
        $conflictID = $_REQUEST["id"];
        $apiResponse = apiV1([
            'action' => 'getItemsFromDB',
            'itemType' => 'conflict',
            'id' => $conflictID,
            'limit' => 1, // We only need one record
            'includeResolved' => true // Assuming we want to see details of resolved conflicts too
        ]);

        if (isset($apiResponse['meta']['requestStatus']) && $apiResponse['meta']['requestStatus'] == 'success' && !empty($apiResponse['data'])) {
            $conflict = $apiResponse['data'];
        } else {
            // Handle error or conflict not found
            echo "<div class='alert alert-danger'>Could not retrieve conflict details or conflict not found.</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>No conflict ID provided.</div>";
    }

?>
<main class="container-fluid subpage">
	<div class="row">
		<?php include_once(__DIR__ . '/../../sidebar.php'); ?>
		<div class="sidebar-content">
			<div class="row">
				<div class="col-12">
					<h2><?= L::conflict(); ?> Detail</h2>
                    <?php if ($conflict): ?>
					<dl class="row">
						<dt class="col-sm-3">ConflictID</dt>
						<dd class="col-sm-9"><?= h($conflict['ConflictID'] ?? ''); ?></dd>

						<dt class="col-sm-3">ConflictEntity</dt>
						<dd class="col-sm-9"><?= h($conflict['ConflictEntity'] ?? ''); ?></dd>

						<dt class="col-sm-3">ConflictIdentifier</dt>
						<dd class="col-sm-9"><?= h($conflict['ConflictIdentifier'] ?? ''); ?></dd>

						<dt class="col-sm-3">ConflictRival</dt>
						<dd class="col-sm-9"><?= h($conflict['ConflictRival'] ?? ''); ?></dd>

						<dt class="col-sm-3">ConflictSubject</dt>
						<dd class="col-sm-9"><?= h($conflict['ConflictSubject'] ?? ''); ?></dd>

						<dt class="col-sm-3">ConflictDescription</dt>
						<dd class="col-sm-9"><pre><?= h($conflict['ConflictDescription'] ?? ''); ?></pre></dd>

						<dt class="col-sm-3">ConflictDate</dt>
						<dd class="col-sm-9"><?= h($conflict['ConflictDate'] ?? ''); ?></dd>

						<dt class="col-sm-3">ConflictTimestamp</dt>
						<dd class="col-sm-9"><?= h($conflict['ConflictTimestamp'] ?? ''); ?></dd>

						<dt class="col-sm-3">ConflictResolved</dt>
						<dd class="col-sm-9"><?= ($conflict['ConflictResolved'] ?? '0') == '1' ? 'Yes' : 'No'; ?></dd>
					</dl>
                    <?php else: ?>
                        <p>Conflict data is not available.</p>
                    <?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</main>
<?php
    include_once (include_custom(realpath(__DIR__ . '/../../../../footer.php'),false));
}
?>

<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/manage/conflicts/conflict-detail/client/style.css?v=<?= $config["version"] ?>" media="all" />