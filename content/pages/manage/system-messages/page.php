<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

// pageType 'admin' => auth() only succeeds for admins.
$auth = auth($_SESSION["userdata"]["id"] ?? null, "requestPage", $pageType);

if (empty($_SESSION["login"]) || $auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"] ?? "";
    include_once (__DIR__."/../../login/page.php");

} else {

    require_once(__DIR__ . '/../../../../api/v1/modules/systemMessage.php');
    $messagesResp = systemMessageList([]);
    $messages = ($messagesResp["meta"]["requestStatus"] === "success") ? $messagesResp["data"] : [];

    include_once(__DIR__ . '/../../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12 col-lg-8">
                    <h2 class="mb-4"><?= L::notifications(); ?></h2>

                    <div class="bg-white border rounded p-3 mb-3">
                        <h5><?= L::notificationRunMatch(); ?></h5>
                        <p class="small text-muted">Run alert matching over the most recent media of a parliament to generate notifications for testing (no full import needed).</p>
                        <div class="d-flex align-items-end gap-2 flex-wrap">
                            <div>
                                <label class="form-label small mb-0" for="runMatchParliament">Parliament</label>
                                <input type="text" class="form-control form-control-sm" id="runMatchParliament" value="DE" style="width:90px;">
                            </div>
                            <div>
                                <label class="form-label small mb-0" for="runMatchLast">Last N</label>
                                <input type="number" class="form-control form-control-sm" id="runMatchLast" value="50" min="1" max="500" style="width:90px;">
                            </div>
                            <button type="button" id="runMatchBtn" class="btn btn-sm btn-primary"><?= L::notificationRunMatch(); ?></button>
                            <span id="runMatchResult" class="small text-muted"></span>
                        </div>
                    </div>

                    <div class="bg-white border rounded p-3 mb-3">
                        <h5>New broadcast</h5>
                        <p class="small text-muted">Sends an in-app notification to every targeted active user. Optionally also queues an email.</p>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="bcTitle">Title</label>
                            <input type="text" class="form-control form-control-sm" id="bcTitle" maxlength="500">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="bcBody">Body</label>
                            <textarea class="form-control form-control-sm" id="bcBody" rows="3"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="bcLink">Link (optional)</label>
                            <input type="text" class="form-control form-control-sm" id="bcLink">
                        </div>
                        <div class="d-flex align-items-end gap-3 flex-wrap mb-2">
                            <div>
                                <label class="form-label small mb-0" for="bcTarget">Target</label>
                                <select class="form-select form-select-sm" id="bcTarget" style="width:160px;">
                                    <option value="">All users</option>
                                    <option value="admin">Admins only</option>
                                    <option value="user">Regular users</option>
                                </select>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="bcSendEmail">
                                <label class="form-check-label small" for="bcSendEmail">Also send email</label>
                            </div>
                            <button type="button" id="bcSend" class="btn btn-sm btn-primary">Send broadcast</button>
                            <span id="bcResult" class="small text-muted"></span>
                        </div>
                    </div>

                    <div class="bg-white border rounded p-3">
                        <h5>Recent system messages</h5>
                        <?php if (empty($messages)): ?>
                            <div class="text-muted small">None yet.</div>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Type</th><th>Title</th><th>Target</th><th>Email</th><th>Created</th></tr></thead>
                                <tbody>
                                <?php foreach ($messages as $m): $a = $m["attributes"]; ?>
                                    <tr>
                                        <td><?= h($a["messageType"]) ?></td>
                                        <td><?= h($a["title"]) ?></td>
                                        <td><?= h($a["targetRole"] ?: "all") ?></td>
                                        <td><?= $a["sendEmail"] ? "yes" : "no" ?></td>
                                        <td class="small text-muted"><?= h(substr((string)$a["created"], 0, 16)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var api = (config.dir.root || "") + "/api/v1";
    var btn = document.getElementById("runMatchBtn");
    var out = document.getElementById("runMatchResult");
    if (btn) {
        btn.addEventListener("click", function () {
            var parliament = document.getElementById("runMatchParliament").value || "DE";
            var last = document.getElementById("runMatchLast").value || 50;
            btn.disabled = true; out.textContent = "…";
            var body = new URLSearchParams();
            body.append("parliament", parliament);
            body.append("last", last);
            fetch(api + "/notification/runMatch", { method: "POST", credentials: "same-origin", body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    btn.disabled = false;
                    if (res && res.meta && res.meta.requestStatus === "success" && res.data) {
                        out.textContent = "scanned " + res.data.scanned + ", created " + res.data.notificationsCreated + " notification(s)";
                    } else {
                        out.textContent = (res && res.errors && res.errors[0]) ? res.errors[0].detail : "error";
                    }
                })
                .catch(function () { btn.disabled = false; out.textContent = "error"; });
        });
    }

    var bcBtn = document.getElementById("bcSend");
    var bcOut = document.getElementById("bcResult");
    if (bcBtn) {
        bcBtn.addEventListener("click", function () {
            var title = document.getElementById("bcTitle").value.trim();
            if (!title) { bcOut.textContent = "title required"; return; }
            bcBtn.disabled = true; bcOut.textContent = "…";
            var body = new URLSearchParams();
            body.append("title", title);
            body.append("body", document.getElementById("bcBody").value);
            body.append("link", document.getElementById("bcLink").value);
            body.append("targetRole", document.getElementById("bcTarget").value);
            body.append("sendEmail", document.getElementById("bcSendEmail").checked);
            fetch(api + "/systemMessage/create", { method: "POST", credentials: "same-origin", body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    bcBtn.disabled = false;
                    if (res && res.meta && res.meta.requestStatus === "success" && res.data) {
                        bcOut.textContent = "sent to " + res.data.recipients + " user(s)";
                        setTimeout(function () { location.reload(); }, 800);
                    } else {
                        bcOut.textContent = (res && res.errors && res.errors[0]) ? res.errors[0].detail : "error";
                    }
                })
                .catch(function () { bcBtn.disabled = false; bcOut.textContent = "error"; });
        });
    }
})();
</script>
<?php
    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>
