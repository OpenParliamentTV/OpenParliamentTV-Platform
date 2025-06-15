<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2><?= L::manageUsers(); ?></h2>
                    <div class="card mb-3">
                        <div class="card-body">
                            <a href="<?= $config["dir"]["root"] ?>/register" class="btn btn-primary btn-sm me-1"> <span class="icon-plus"></span> <?= L::manageUsersNewAccount(); ?></a>
                        </div>
                    </div>
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="all-users-tab" data-bs-toggle="tab" data-bs-target="#all-users" role="tab" aria-controls="all-users" aria-selected="true"><span class="icon-users"></span> <?= L::users(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="all-users" role="tabpanel" aria-labelledby="all-users-tab">
                            <table id="usersTable"></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
$(function() {
    // Define formatters and events before table initialization
    var formatters = {
        // Formatter for active status
        activeFormatter: function(value, row, index) {
            return '<div class="form-check form-switch">' +
                   '<input class="form-check-input user-active-switch" type="checkbox" ' +
                   'data-userid="' + row.UserID + '" ' +
                   (value ? 'checked' : '') + '>' +
                   '</div>';
        },

        // Formatter for blocked status
        blockedFormatter: function(value, row, index) {
            return '<div class="form-check form-switch">' +
                   '<input class="form-check-input user-blocked-switch" type="checkbox" ' +
                   'data-userid="' + row.UserID + '" ' +
                   (value ? 'checked' : '') + '>' +
                   '</div>';
        },

        // Formatter for role selection
        roleFormatter: function(value, row, index) {
            return '<select class="form-select form-select-sm user-role-select" ' +
                   'data-userid="' + row.UserID + '">' +
                   '<option value="user" ' + (value === 'user' ? 'selected' : '') + '>User</option>' +
                   '<option value="admin" ' + (value === 'admin' ? 'selected' : '') + '>Admin</option>' +
                   '</select>';
        },

        // Formatter for dates
        dateFormatter: function(value) {
            if (value) {
                return new Date(value).toLocaleString('de');
            }
            return "-";
        },

        // Formatter for action buttons
        operateFormatter: function(value, row, index) {
            
            const editButton = '<a class="list-group-item list-group-item-action" ' +
                'title="<?= L::edit(); ?>" ' +
                'href="<?= $config["dir"]["root"]; ?>/manage/users/' + row.UserID + '">' +
                '<span class="icon-pencil"></span>' +
                '</a>';
            
            // Combine buttons in a horizontal list group
            return '<div class="list-group list-group-horizontal">' +
                editButton +
                '</div>';
        }
    };

    // Initialize Bootstrap Table
    $('#usersTable').bootstrapTable({
        url: '<?= $config["dir"]["root"] ?>/api/v1/?action=getItemsFromDB&itemType=user',
        classes: "table table-striped",
        locale: "<?= $lang; ?>",
        search: true,
        searchAlign: "left",
        minimumCountColumns: 2,
        pagination: true,
        pageSize: 25,
        pageList: [10, 25, 50, 100, 'all'],
        sidePagination: 'server',
        sortName: 'UserRegisterDate',
        sortOrder: 'desc',
        showFooter: false,
        maintainSelected: true,
        clickToSelect: true,
        uniqueId: 'UserID',
        columns: [
            {field: 'UserID', visible: false},
            {field: 'UserName', sortable: true, title: '<?= L::name(); ?>'},
            {field: 'UserMail', sortable: true, title: '<?= L::mailAddress(); ?>'},
            {field: 'UserRole', sortable: true, title: '<?= L::role(); ?>', formatter: formatters.roleFormatter},
            {field: 'UserActive', sortable: true, title: '<?= L::active(); ?>', formatter: formatters.activeFormatter},
            {field: 'UserBlocked', sortable: true, title: '<?= L::blocked(); ?>', formatter: formatters.blockedFormatter},
            {field: 'UserLastLogin', sortable: true, title: '<?= L::lastLogin(); ?>', formatter: formatters.dateFormatter},
            {field: 'UserRegisterDate', sortable: true, title: '<?= L::registerDate(); ?>', formatter: formatters.dateFormatter},
            {field: 'operate', title: '', formatter: formatters.operateFormatter, class: 'minWidthColumn'}
        ],
        queryParams: function(params) {
            return {
                limit: params.limit,
                offset: params.offset,
                sort: params.sort,
                order: params.order,
                search: params.search
            };
        },
        responseHandler: function(res) {
            if (!res || !res.data) {
                console.error('Invalid response format:', res);
                return {
                    total: 0,
                    rows: []
                };
            }
            return {
                total: res.total || 0,
                rows: res.data
            };
        }
    });

    // Handle active status changes
    $(document).on('change', '.user-active-switch', function() {
        const userId = $(this).data('userid');
        const isActive = $(this).prop('checked');
        
        $.ajax({
            url: '<?= $config["dir"]["root"] ?>/api/v1/',
            method: 'POST',
            data: {
                action: 'changeItem',
                itemType: 'user',
                id: userId,
                UserActive: isActive ? 1 : 0
            },
            success: function(response) {
                if (response.meta.requestStatus === 'success') {
                    // Refresh the table after 500ms delay
                    setTimeout(function() {
                        $('#usersTable').bootstrapTable('refresh');
                    }, 500);
                } else {
                    // Show error message and revert switch
                    console.error('Failed to update user status:', response);
                    alert(response.errors ? response.errors[0].detail : 'Failed to update user status');
                    $(this).prop('checked', !isActive);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating user status:', error);
                alert('Error updating user status: ' + error);
                $(this).prop('checked', !isActive);
            }
        });
    });

    // Handle blocked status changes
    $(document).on('change', '.user-blocked-switch', function() {
        const userId = $(this).data('userid');
        const isBlocked = $(this).prop('checked');
        
        $.ajax({
            url: '<?= $config["dir"]["root"] ?>/api/v1/',
            method: 'POST',
            data: {
                action: 'changeItem',
                itemType: 'user',
                id: userId,
                UserBlocked: isBlocked ? 1 : 0
            },
            success: function(response) {
                if (response.meta.requestStatus === 'success') {
                    // Refresh the table after 500ms delay
                    setTimeout(function() {
                        $('#usersTable').bootstrapTable('refresh');
                    }, 500);
                } else {
                    // Show error message and revert switch
                    console.error('Failed to update user status:', response);
                    alert(response.errors ? response.errors[0].detail : 'Failed to update user status');
                    $(this).prop('checked', !isBlocked);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating user status:', error);
                alert('Error updating user status: ' + error);
                $(this).prop('checked', !isBlocked);
            }
        });
    });

    // Handle role changes
    $(document).on('change', '.user-role-select', function() {
        const userId = $(this).data('userid');
        const newRole = $(this).val();
        
        $.ajax({
            url: '<?= $config["dir"]["root"] ?>/api/v1/',
            method: 'POST',
            data: {
                action: 'changeItem',
                itemType: 'user',
                id: userId,
                UserRole: newRole
            },
            success: function(response) {
                if (response.meta.requestStatus === 'success') {
                    // Refresh the table after 500ms delay
                    setTimeout(function() {
                        $('#usersTable').bootstrapTable('refresh');
                    }, 500);
                } else {
                    // Show error message and revert selection
                    console.error('Failed to update user role:', response);
                    alert(response.errors ? response.errors[0].detail : 'Failed to update user role');
                    $(this).val($(this).data('original-value'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating user role:', error);
                alert('Error updating user role: ' + error);
                $(this).val($(this).data('original-value'));
            }
        });
    });

    // Store original value when focus
    $(document).on('focus', '.user-role-select', function() {
        $(this).data('original-value', $(this).val());
    });
});
</script>

<?php
include_once(__DIR__ . '/../../../footer.php');
}
?>