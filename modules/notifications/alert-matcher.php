<?php
/**
 * Alert matching engine (Plan B — notifications & alerts).
 *
 * Pure matching logic plus the call wrappers used from three places:
 *   1. cronUpdater (per newly-indexed media item, real-time)
 *   2. data/notificationMatcher.php (standalone CLI)
 *   3. notification/runMatch API (admin on-demand test trigger)
 *
 * Input is the JSON:API media *item* node (the `data` node from getItem):
 * { type, id, attributes:{public, parliament, textContents[]…}, relationships:{people,organisations,terms,documents,electoralPeriod} }
 */

require_once(__DIR__ . "/functions.php");

/**
 * Build a flat index of matchable values from a media item.
 */
function mediaMatchIndex($mediaItem) {
    $attr = $mediaItem["attributes"] ?? [];
    $rel = $mediaItem["relationships"] ?? [];

    // Build, per relationship node, both the plain id set and the role-aware
    // "<id>~<context>" token set so per-entity role filters can be matched.
    $collect = function ($node) {
        $ids = [];
        $roleTokens = [];
        if (isset($node["data"]) && is_array($node["data"])) {
            foreach ($node["data"] as $entry) {
                if (!is_array($entry) || !isset($entry["id"])) { continue; }
                $id = (string)$entry["id"];
                $ids[] = $id;
                $ctx = $entry["attributes"]["context"] ?? null;
                if ($ctx) { $roleTokens[] = $id . "~" . $ctx; }
            }
        }
        return [array_values(array_unique($ids)), array_values(array_unique($roleTokens))];
    };

    list($personIDs, $personRoles) = $collect($rel["people"] ?? []);
    list($orgIDs, $orgRoles)       = $collect($rel["organisations"] ?? []);
    list($termIDs, $termRoles)     = $collect($rel["terms"] ?? []);
    list($docIDs, $docRoles)       = $collect($rel["documents"] ?? []);

    // Organisation/faction match should also cover each speaker's party/faction org
    // IDs, mirroring how a faction/party search scopes to that speaker's affiliation.
    $factionIDs = [];
    if (isset($rel["people"]["data"]) && is_array($rel["people"]["data"])) {
        foreach ($rel["people"]["data"] as $person) {
            $pa = $person["attributes"] ?? [];
            if (!empty($pa["party"]["id"])) {
                $orgIDs[] = (string)$pa["party"]["id"];
                $orgRoles[] = (string)$pa["party"]["id"] . "~main-speaker-party";
                $factionIDs[] = (string)$pa["party"]["id"];
            }
            if (!empty($pa["faction"]["id"])) {
                $orgIDs[] = (string)$pa["faction"]["id"];
                $orgRoles[] = (string)$pa["faction"]["id"] . "~main-speaker-faction";
                $factionIDs[] = (string)$pa["faction"]["id"];
            }
        }
    }

    // Concatenate transcript text (HTML) for keyword matching.
    $text = "";
    if (isset($attr["textContents"]) && is_array($attr["textContents"])) {
        foreach ($attr["textContents"] as $tc) {
            $t = $tc["text"] ?? "";
            $text .= " " . (is_array($t) ? json_encode($t) : (string)$t);
        }
    }

    return [
        "personID" => $personIDs,
        "personID_roles" => $personRoles,
        "organisationID" => array_values(array_unique($orgIDs)),
        "organisationID_roles" => array_values(array_unique($orgRoles)),
        "factionID" => array_values(array_unique($factionIDs)),
        "factionID_roles" => array_values(array_unique($orgRoles)),
        "termID" => $termIDs,
        "termID_roles" => $termRoles,
        "documentID" => $docIDs,
        "documentID_roles" => $docRoles,
        "electoralPeriodID" => isset($rel["electoralPeriod"]["data"]["id"]) ? (string)$rel["electoralPeriod"]["data"]["id"] : null,
        "parliament" => $attr["parliament"] ?? null,
        "text" => strtolower(strip_tags($text)),
    ];
}

/**
 * Does a media index satisfy an alert's (normalized) criteria?
 * AND across filter types; OR within a multi-value entity filter. Per-entity role
 * tokens ("<id>~<role>") match against the index' role-aware token set; plain ids
 * match any context.
 */
function mediaMatchesCriteria($index, $criteria) {
    $criteria = normalizeAlertCriteria($criteria);
    if (empty($criteria)) {
        return false; // never notify on an empty rule
    }

    foreach (alertCriteriaEntityKeys() as $key) {
        if (empty($criteria[$key])) { continue; }
        $plain = $index[$key] ?? [];
        $roleTokens = $index[$key . "_roles"] ?? [];
        // A role-less token means the key's default context (mirrors the search
        // backend); "" default (org/term/document) and the "all"/"any" sentinel both
        // mean "match any context".
        $defaultRole = alertEntityKeyDefaultRole($key);
        $matched = false;
        foreach ($criteria[$key] as $token) {
            list($id, $role) = alertSplitRoleToken($token);
            if ($role === null || $role === "") { $role = $defaultRole; }
            if ($role === "" || $role === "*" || $role === "all" || $role === "any") {
                // Wildcard / empty default → match the id in any context.
                if (in_array($id, $plain, true)) { $matched = true; break; }
            } else {
                if (in_array($id . "~" . $role, $roleTokens, true)) { $matched = true; break; }
            }
        }
        if (!$matched) {
            return false;
        }
    }

    if (!empty($criteria["electoralPeriodID"])) {
        if (($index["electoralPeriodID"] ?? null) !== $criteria["electoralPeriodID"]) {
            return false;
        }
    }

    if (!empty($criteria["q"])) {
        if (stripos($index["text"] ?? "", $criteria["q"]) === false) {
            return false;
        }
    }

    return true;
}

/**
 * Build a notification payload (title/body/link) from a media item.
 */
function notificationPayloadForMedia($mediaItem, $parliament, $alertLabel) {
    global $config;
    $attr = $mediaItem["attributes"] ?? [];
    $rel = $mediaItem["relationships"] ?? [];

    $speaker = $rel["people"]["data"][0]["attributes"]["label"] ?? "";
    $faction = $rel["people"]["data"][0]["attributes"]["faction"]["label"] ?? "";
    $agenda = $rel["agendaItem"]["data"]["attributes"]["title"]
        ?? ($rel["agendaItem"]["data"]["attributes"]["officialTitle"] ?? "");
    $date = $attr["dateStart"] ?? "";

    $bodyParts = array_filter([
        $speaker . ($faction ? " (" . $faction . ")" : ""),
        $agenda,
        $date,
    ]);

    $root = $config["dir"]["root"] ?? "";
    $link = $root . "/media/" . rawurlencode((string)($mediaItem["id"] ?? ""));

    return [
        "title" => $alertLabel,
        "body" => implode(" — ", $bodyParts),
        "link" => $link,
    ];
}

/**
 * Match one media item against all active alerts and create notifications.
 * Skips non-public items. Returns the number of notifications created.
 */
function matchMediaItemAgainstAlerts($mediaItem, $parliament, $db) {
    global $config;

    if (empty($config["allow"]["notifications"])) {
        return 0;
    }
    $attr = $mediaItem["attributes"] ?? [];
    if (empty($attr["public"])) {
        return 0; // never notify on non-public speeches
    }

    $alerts = $db->getAll("SELECT * FROM ?n WHERE AlertActive = 1", $config["platform"]["sql"]["tbl"]["Alert"]);
    if (empty($alerts)) {
        return 0;
    }

    $index = mediaMatchIndex($mediaItem);
    $created = 0;

    foreach ($alerts as $alert) {
        $criteria = json_decode($alert["AlertCriteria"], true);
        if (!is_array($criteria)) { continue; }

        // Parliament scoping: an alert with a parliament only matches that one.
        if (!empty($criteria["parliament"]) && $criteria["parliament"] !== $parliament) {
            continue;
        }
        if (!mediaMatchesCriteria($index, $criteria)) {
            continue;
        }

        $payload = notificationPayloadForMedia($mediaItem, $parliament, $alert["AlertLabel"]);
        $isNew = createNotification($db, [
            "userID" => (int)$alert["AlertUserID"],
            "alertID" => (int)$alert["AlertID"],
            "type" => "alert",
            "title" => $payload["title"],
            "body" => $payload["body"],
            "link" => $payload["link"],
            "mediaID" => (string)($mediaItem["id"] ?? ""),
            "parliament" => $parliament,
        ]);
        if ($isNew) {
            $created++;
            $db->query("UPDATE ?n SET AlertLastTriggered = NOW() WHERE AlertID = ?i",
                $config["platform"]["sql"]["tbl"]["Alert"], (int)$alert["AlertID"]);
        }
    }

    return $created;
}

/**
 * On-demand matcher used by the CLI and the admin "run matching" button.
 * Re-fetches the most recent N public media of a parliament via getItem and runs
 * the matcher over each. Returns a small summary array.
 */
function runAlertMatchingForRecent($parliament, $n = 50) {
    global $config;
    require_once(__DIR__ . "/../../api/v1/api.php");

    if (!isset($config["parliament"][$parliament])) {
        return ["error" => "unknown parliament", "parliament" => $parliament];
    }

    $db = getApiDatabaseConnection('platform');
    $dbp = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db) || !is_object($dbp)) {
        return ["error" => "database connection failed", "parliament" => $parliament];
    }

    $mediaTbl = $config["parliament"][$parliament]["sql"]["tbl"]["Media"];
    $ids = $dbp->getCol(
        "SELECT MediaID FROM ?n WHERE MediaPublic = 1 ORDER BY MediaID DESC LIMIT ?i",
        $mediaTbl, (int)$n
    );

    $scanned = 0;
    $created = 0;
    foreach ($ids ?: [] as $id) {
        $resp = apiV1(["action" => "getItem", "itemType" => "media", "id" => $id], $db, $dbp);
        if (($resp["meta"]["requestStatus"] ?? "") !== "success" || empty($resp["data"])) {
            continue;
        }
        $scanned++;
        $created += matchMediaItemAgainstAlerts($resp["data"], $parliament, $db);
    }

    return [
        "parliament" => $parliament,
        "scanned" => $scanned,
        "notificationsCreated" => $created,
    ];
}
