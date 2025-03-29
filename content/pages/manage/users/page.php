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
                    <h2><?php echo L::manageUsers; ?></h2>
                    <div class="card mb-3">
                        <div class="card-body">
                            <a href="<?= $config["dir"]["root"] ?>/register" class="btn btn-outline-success btn-sm me-1">Register New User</a>
                            <a href="#" class="btn btn-primary btn-sm me-1">Send Invite</a>
                            <?php
                            if ($config["mode"] == "dev") {
                                echo '<a class="btn btn-primary btn-sm me-1" href="'.$config["dir"]["root"].'/server/ajaxServer.php?a=devAddTestuser" target="_blank">Auto-Add Test Users (admin@admin.com:Admin!!11 test@test.com:User!!11)</a>';
                            }
                            ?>
                        </div>
                    </div>
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="all-users-tab" data-bs-toggle="tab" data-bs-target="#all-users" role="tab" aria-controls="all-users" aria-selected="true">All Users</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="all-users" role="tabpanel" aria-labelledby="all-users-tab">
                            <table id="manageUsersOverviewTable" class="table">
                                <thead>
                                    <tr>
                                        <th>UserName</th>
                                        <th>Mail</th>
                                        <th>Role</th>
                                        <th>Active</th>
                                        <th>Blocked</th>
                                        <th>Password</th>
                                        <th>LastLogin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php

                                    require_once(__DIR__."/../../../../modules/utilities/safemysql.class.php");

                                    if (!$db) {
                                        try {

                                            $db = new SafeMySQL(array(
                                                'host' => $config["platform"]["sql"]["access"]["host"],
                                                'user' => $config["platform"]["sql"]["access"]["user"],
                                                'pass' => $config["platform"]["sql"]["access"]["passwd"],
                                                'db' => $config["platform"]["sql"]["db"]
                                            ));

                                        } catch (exception $e) {

                                            $return["meta"]["requestStatus"] = "error";
                                            $return["errors"] = array();
                                            $errorarray["status"] = "503";
                                            $errorarray["code"] = "1";
                                            $errorarray["title"] = "Database connection error";
                                            $errorarray["detail"] = "Connecting to platform database failed";
                                            array_push($return["errors"], $errorarray);
                                            return $return;

                                        }
                                    }

                                    $users = $db->getAll("SELECT * FROM ?n",$config["platform"]["sql"]["tbl"]["User"]);

                                    foreach ($users as $user) {

                                        echo "<tr>
                                                <td><input type='text' name='UserName' data-userid='".$user["UserID"]."' value='".$user["UserName"]."' class='userform-username form-control'></td>
                                                <td><input type='text' name='UserMail' data-userid='".$user["UserID"]."' value='".$user["UserMail"]."' class='userform-usermail form-control'></td>
                                                <td><input type='text' name='UserRole' data-userid='".$user["UserID"]."' value='".$user["UserRole"]."' class='userform-userrole form-control'></td>
                                                <td><input type='checkbox' name='UserActive' data-userid='".$user["UserID"]."' class='userform-useractive form-control'".(($user["UserActive"]==1)?" checked":"")."></td>
                                                <td><input type='checkbox' name='UserBlocked' data-userid='".$user["UserID"]."' class='userform-userblocked form-control'".(($user["UserBlocked"]==1)?" checked":"")."></td>
                                                <td><input type='input' name='UserPassword' data-userid='".$user["UserID"]."' placeholder='aA-zZ, Special >8 Chars' class='userform-userpassword form-control'> </td>
                                                <td>".$user["UserLastLogin"]."</td>
                                            </tr>";

                                    }

                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/manage/users/client/users.overview.js?v=<?= $config["version"] ?>"></script>
<?php
include_once(__DIR__ . '/../../../footer.php');
}
?>