<?php
// Sidebar navigation for manage section
?>
<aside class="sticky-sidebar">
    <!-- Mobile Navigation -->
    <div class="d-lg-none w-100 mb-3">
        <div class="dropdown w-100">
            <button class="btn btn-light w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div>
                    <i class="icon-menu me-2"></i>
                    <?= L::menu; ?>
                </div>
                <i class="icon-down-open-big"></i>
            </button>
            <div class="dropdown-menu w-100">
                <!-- Dashboard -->
                <a href="<?= $config["dir"]["root"] ?>/manage" 
                   class="dropdown-item <?= ($page == "manage") ? "active" : "" ?>">
                    <i class="icon-th-large-1 me-2"></i>
                    <?= L::dashboard; ?>
                </a>

                <!-- Personal Settings -->
                <div class="dropdown-header text-uppercase text-muted"><?= L::personalSettings; ?></div>
                <!--
                <a href="<?= $config["dir"]["root"] ?>/manage/notifications" 
                   class="dropdown-item <?= ($page == "manage-notifications") ? "active" : "" ?>">
                    <i class="icon-megaphone me-2"></i>
                    <?= L::notifications; ?>
                </a>
                -->
                <a href="<?= $config["dir"]["root"] ?>/manage/users/<?= $_SESSION["userdata"]["id"] ?>" 
                   class="dropdown-item <?= ($page == "manage-users" && isset($_REQUEST["id"]) && $_REQUEST["id"] == $_SESSION["userdata"]["id"]) ? "active" : "" ?>">
                    <i class="icon-user me-2"></i>
                    <?= L::userSettings; ?>
                </a>

                <!-- Contents -->
                <div class="dropdown-header text-uppercase text-muted"><?= L::contents; ?></div>
                <a href="<?= $config["dir"]["root"] ?>/manage/media" 
                   class="dropdown-item <?= ($page == "manage-media") ? "active" : "" ?>">
                    <i class="icon-play me-2"></i>
                    <?= L::manageMedia; ?>
                </a>
                <a href="<?= $config["dir"]["root"] ?>/manage/entities" 
                   class="dropdown-item <?= ($page == "manage-entities") ? "active" : "" ?>">
                    <i class="icon-tags me-2"></i>
                    <?= L::manageEntities; ?>
                </a>
                <a href="<?= $config["dir"]["root"] ?>/manage/structure" 
                   class="dropdown-item <?= ($page == "manage-structure") ? "active" : "" ?>">
                    <i class="icon-flow-cascade me-2"></i>
                    <?= L::manageStructure; ?>
                </a>

                <!-- Administration -->
                <div class="dropdown-header text-uppercase text-muted"><?= L::administration; ?></div>
                <a href="<?= $config["dir"]["root"] ?>/manage/users" 
                   class="dropdown-item <?= ($page == "manage-users" && (!isset($_REQUEST["id"]) || $_REQUEST["id"] != $_SESSION["userdata"]["id"])) ? "active" : "" ?>">
                    <i class="icon-users me-2"></i>
                    <?= L::manageUsers; ?>
                </a>
                <a href="<?= $config["dir"]["root"] ?>/manage/import" 
                   class="dropdown-item <?= ($page == "manage-import") ? "active" : "" ?>">
                    <i class="icon-upload me-2"></i>
                    <?= L::manageImport; ?>
                </a>
                <a href="<?= $config["dir"]["root"] ?>/manage/conflicts" 
                   class="dropdown-item <?= ($page == "manage-conflicts") ? "active" : "" ?>">
                    <i class="icon-attention me-2"></i>
                    <?= L::manageConflicts; ?>
                </a>
                <a href="<?= $config["dir"]["root"] ?>/manage/entity-suggestions" 
                   class="dropdown-item <?= ($page == "manage-entity-suggestions") ? "active" : "" ?>">
                    <i class="icon-lightbulb me-2"></i>
                    <?= L::manageEntitySuggestions; ?>
                </a>
                <a href="<?= $config["dir"]["root"] ?>/manage/settings" 
                   class="dropdown-item <?= ($page == "manage-settings") ? "active" : "" ?>">
                    <i class="icon-cogs me-2"></i>
                    <?= L::platformSettings; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Desktop Navigation -->
    <div class="d-none d-lg-block">
        <div class="mb-0">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage" 
                       class="nav-link <?= ($page == "manage") ? "active" : "" ?>">
                        <i class="icon-th-large-1 me-2"></i>
                        <?= L::dashboard; ?>
                    </a>
                </li>
            </ul>
        </div>
        <hr>

        <!-- Personal Settings -->
        <div class="mb-0">
            <div class="mb-2 ps-3 text-uppercase text-muted"><?= L::personalSettings; ?></div>
            <ul class="nav nav-pills flex-column mb-auto">
                <!--
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/notifications" 
                       class="nav-link <?= ($page == "manage-notifications") ? "active" : "" ?>">
                        <i class="icon-megaphone me-2"></i>
                        <?= L::notifications; ?>
                    </a>
                </li>
                -->
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/users/<?= $_SESSION["userdata"]["id"] ?>" 
                       class="nav-link <?= ($page == "manage-users" && isset($_REQUEST["id"]) && $_REQUEST["id"] == $_SESSION["userdata"]["id"]) ? "active" : "" ?>">
                        <i class="icon-user me-2"></i>
                        <?= L::userSettings; ?>
                    </a>
                </li>
            </ul>
        </div>
        <hr>

        <!-- Contents -->
        <div class="mb-0">
            <div class="mb-2 ps-3 text-uppercase text-muted"><?= L::contents; ?></div>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/media" 
                       class="nav-link <?= ($page == "manage-media") ? "active" : "" ?>">
                        <i class="icon-play me-2"></i>
                        <?= L::manageMedia; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/entities" 
                       class="nav-link <?= ($page == "manage-entities") ? "active" : "" ?>">
                        <i class="icon-tags me-2"></i>
                        <?= L::manageEntities; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/structure" 
                       class="nav-link <?= ($page == "manage-structure") ? "active" : "" ?>">
                        <i class="icon-flow-cascade me-2"></i>
                        <?= L::manageStructure; ?>
                    </a>
                </li>
            </ul>
        </div>
        <hr>

        <!-- Administration -->
        <div class="mb-0">
            <div class="mb-2 ps-3 text-uppercase text-muted"><?= L::administration; ?></div>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/users" 
                       class="nav-link <?= ($page == "manage-users" && (!isset($_REQUEST["id"]) || $_REQUEST["id"] != $_SESSION["userdata"]["id"])) ? "active" : "" ?>">
                        <i class="icon-users me-2"></i>
                        <?= L::manageUsers; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/import" 
                       class="nav-link <?= ($page == "manage-import") ? "active" : "" ?>">
                        <i class="icon-upload me-2"></i>
                        <?= L::manageImport; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/conflicts" 
                       class="nav-link <?= ($page == "manage-conflicts") ? "active" : "" ?>">
                        <i class="icon-attention me-2"></i>
                        <?= L::manageConflicts; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/entity-suggestions" 
                       class="nav-link <?= ($page == "manage-entity-suggestions") ? "active" : "" ?>">
                        <i class="icon-lightbulb me-2"></i>
                        <?= L::manageEntitySuggestions; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config["dir"]["root"] ?>/manage/settings" 
                       class="nav-link <?= ($page == "manage-settings") ? "active" : "" ?>">
                        <i class="icon-cogs me-2"></i>
                        <?= L::platformSettings; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>