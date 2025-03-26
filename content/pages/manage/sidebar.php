<?php
// Sidebar navigation for manage section
?>
<div class="sidebar bg-light border-right">
    <div class="list-group list-group-flush">
        <div class="sidebar-heading p-3 mb-2 border-bottom">
            <i class="icon-cog"></i> <?php echo L::manage; ?>
        </div>
        <!-- Personal Settings -->
        <div class="mb-3">
            <div class="sidebar-heading ps-3 mb-2"><small><?php echo L::personalSettings; ?></small></div>
            <a href="<?= $config["dir"]["root"] ?>/manage/notifications" class="list-group-item list-group-item-action border-0">
                <i class="icon-bell"></i> <?php echo L::notifications; ?>
            </a>
            <a href="<?= $config["dir"]["root"] ?>/manage/users/MYUSERID" class="list-group-item list-group-item-action border-0">
                <i class="icon-user"></i> <?php echo L::userSettings; ?>
            </a>
        </div>
        
        <!-- Administration -->
        <div class="mb-3">
            <div class="sidebar-heading ps-3 mb-2"><small><?php echo L::administration; ?></small></div>
            <a href="<?= $config["dir"]["root"] ?>/manage/conflicts" class="list-group-item list-group-item-action border-0">
                <i class="icon-attention"></i> <?php echo L::manageConflicts; ?>
            </a>
            <a href="<?= $config["dir"]["root"] ?>/manage/data" class="list-group-item list-group-item-action border-0">
                <i class="icon-database"></i> <?php echo L::manageData; ?>
            </a>
            <a href="<?= $config["dir"]["root"] ?>/manage/users" class="list-group-item list-group-item-action border-0">
                <i class="icon-users"></i> <?php echo L::manageUsers; ?>
            </a>
            <a href="<?= $config["dir"]["root"] ?>/manage/import" class="list-group-item list-group-item-action border-0">
                <i class="icon-upload"></i> <?php echo L::data; ?>-Import
            </a>
            <a href="<?= $config["dir"]["root"] ?>/manage/config" class="list-group-item list-group-item-action border-0">
                <i class="icon-wrench"></i> <?php echo L::platformSettings; ?>
            </a>
            <a href="<?= $config["dir"]["root"] ?>/manage/entities" class="list-group-item list-group-item-action border-0">
                <i class="icon-th-list"></i> <?php echo L::manageEntities; ?>
            </a>
            <a href="<?= $config["dir"]["root"] ?>/manage/opensearch" class="list-group-item list-group-item-action border-0">
                <i class="icon-search"></i> Search Index
            </a>
        </div>
    </div>
</div>

<style>
.sidebar {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    height: 100%;
    padding: 20px 0;
}
.sidebar-heading {
    font-weight: 500;
    color: #666;
}
.sidebar .list-group-item {
    padding: .5rem 1rem;
    background: transparent;
}
.sidebar .list-group-item:hover {
    background: rgba(0,0,0,.05);
}
.sidebar .list-group-item i {
    width: 20px;
    text-align: center;
    margin-right: 8px;
}

/* Adjust main content when sidebar is present */
main.has-sidebar {
    margin-left: 250px;
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 20px 0;
    }
    
    main.has-sidebar {
        margin-left: 0;
    }
}
</style> 