<?php
/**
 * Digest alert email template (Plan B).
 * renderAlertDigestEmail(string $userName, array $groups, string $frequency, string $manageUrl, string $unsubUrl): string
 * $groups: [ ['label' => 'Alert label', 'items' => [ notificationRow, ... ]], ... ]
 */
function renderAlertDigestEmail($userName, $groups, $frequency, $manageUrl, $unsubUrl) {
    $name = htmlspecialchars($userName ?: "", ENT_QUOTES, "UTF-8");

    $html  = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">';
    $html .= '<p>' . L::hello() . ' ' . $name . ',</p>';
    $html .= '<p>' . htmlspecialchars($frequency, ENT_QUOTES, "UTF-8") . ' &mdash; ' . L::notificationInboxTitle() . '</p>';

    foreach ($groups as $group) {
        $label = htmlspecialchars($group["label"], ENT_QUOTES, "UTF-8");
        $count = count($group["items"]);
        $html .= '<h3 style="font-size:15px;margin:16px 0 6px;">' . $label . ' (' . $count . ')</h3><ul style="padding-left:18px;margin:0;">';
        foreach ($group["items"] as $n) {
            $body = htmlspecialchars($n["NotificationBody"] ?? "", ENT_QUOTES, "UTF-8");
            $link = htmlspecialchars($n["NotificationLink"] ?? "", ENT_QUOTES, "UTF-8");
            $html .= '<li style="margin-bottom:4px;">' . ($link ? '<a href="' . $link . '">' . $body . '</a>' : $body) . '</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<hr style="border:none;border-top:1px solid #ddd;">';
    $html .= '<p style="font-size:12px;color:#888;">';
    $html .= '<a href="' . htmlspecialchars($manageUrl, ENT_QUOTES, "UTF-8") . '">' . L::alertManageTitle() . '</a> &middot; ';
    $html .= '<a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES, "UTF-8") . '">' . L::notificationEmailEnabled() . '</a>';
    $html .= '</p></div>';
    return $html;
}
