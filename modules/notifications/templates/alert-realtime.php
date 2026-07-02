<?php
/**
 * Real-time alert email template (Plan B).
 * renderAlertRealtimeEmail(array $n, string $userName, string $manageUrl, string $unsubUrl): string
 * $n is a notification row (NotificationTitle, NotificationBody, NotificationLink).
 */
function renderAlertRealtimeEmail($n, $userName, $manageUrl, $unsubUrl) {
    $title = htmlspecialchars($n["NotificationTitle"] ?? "", ENT_QUOTES, "UTF-8");
    $body  = htmlspecialchars($n["NotificationBody"] ?? "", ENT_QUOTES, "UTF-8");
    $link  = htmlspecialchars($n["NotificationLink"] ?? "", ENT_QUOTES, "UTF-8");
    $name  = htmlspecialchars($userName ?: "", ENT_QUOTES, "UTF-8");

    $html  = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">';
    $html .= '<p>' . L::hello() . ' ' . $name . ',</p>';
    $html .= '<p>' . L::feedSpeechesBy() . ' &mdash; <strong>' . $title . '</strong></p>';
    $html .= '<p style="margin:0 0 4px 0;">' . $body . '</p>';
    if ($link) {
        $html .= '<p><a href="' . $link . '">' . $link . '</a></p>';
    }
    $html .= '<hr style="border:none;border-top:1px solid #ddd;">';
    $html .= '<p style="font-size:12px;color:#888;">';
    $html .= '<a href="' . htmlspecialchars($manageUrl, ENT_QUOTES, "UTF-8") . '">' . L::alertManageTitle() . '</a> &middot; ';
    $html .= '<a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES, "UTF-8") . '">' . L::notificationEmailEnabled() . '</a>';
    $html .= '</p></div>';
    return $html;
}
