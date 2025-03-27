<?php
// Sidebar navigation for manage section
?>
<div class="d-flex flex-shrink-0 flex-column pe-3 me-3 d-none d-md-flex">
    <div class="mb-0">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage" 
                   class="nav-link <?= ($page == "manage") ? "active" : "" ?>">
                    <i class="icon-th-large-1 me-2"></i>
                    <?php echo L::dashboard; ?>
                </a>
            </li>
        </ul>
    </div>
    <hr>

    <!-- Personal Settings -->
    <div class="mb-0">
        <div class="mb-2 ps-3 text-uppercase"><?php echo L::personalSettings; ?></div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/notifications" 
                   class="nav-link <?= ($page == "manage-notifications") ? "active" : "" ?>">
                    <i class="icon-megaphone me-2"></i>
                    <?php echo L::notifications; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/users/MYUSERID" 
                   class="nav-link <?= ($page == "manage-users" && isset($_REQUEST["id"])) ? "active" : "" ?>">
                    <i class="icon-user me-2"></i>
                    <?php echo L::userSettings; ?>
                </a>
            </li>
        </ul>
    </div>
    <hr>

    <!-- Administration -->
    <div class="mb-0">
        <div class="mb-2 ps-3 text-uppercase"><?php echo L::administration; ?></div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/conflicts" 
                   class="nav-link <?= ($page == "manage-conflicts") ? "active" : "" ?>">
                    <i class="icon-attention me-2"></i>
                    <?php echo L::manageConflicts; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/data" 
                   class="nav-link <?= ($page == "manage-data") ? "active" : "" ?>">
                    <i class="icon-database me-2"></i>
                    <?php echo L::manageData; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/users" 
                   class="nav-link <?= ($page == "manage-users" && !isset($_REQUEST["id"])) ? "active" : "" ?>">
                    <i class="icon-users me-2"></i>
                    <?php echo L::manageUsers; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/import" 
                   class="nav-link <?= ($page == "manage-import") ? "active" : "" ?>">
                    <i class="icon-upload me-2"></i>
                    <?php echo L::data; ?>-Import
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/config" 
                   class="nav-link <?= ($page == "manage-config") ? "active" : "" ?>">
                    <i class="icon-cog me-2"></i>
                    <?php echo L::platformSettings; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/entities" 
                   class="nav-link <?= ($page == "manage-entities") ? "active" : "" ?>">
                    <i class="icon-th-list me-2"></i>
                    <?php echo L::manageEntities; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $config["dir"]["root"] ?>/manage/opensearch" 
                   class="nav-link <?= ($page == "manage-opensearch") ? "active" : "" ?>">
                    <i class="icon-search me-2"></i>
                    Search Index
                </a>
            </li>
        </ul>
    </div>
</div>