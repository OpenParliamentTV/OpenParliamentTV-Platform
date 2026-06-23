<?php
/**
 * System broadcast email template (Plan B).
 * renderSystemBroadcastEmail(array $n, string $userName, string $unsubUrl): string
 */
function renderSystemBroadcastEmail($n, $userName, $unsubUrl) {
    $title = htmlspecialchars($n["NotificationTitle"] ?? "", ENT_QUOTES, "UTF-8");
    $body  = nl2br(htmlspecialchars($n["NotificationBody"] ?? "", ENT_QUOTES, "UTF-8"));
    $link  = htmlspecialchars($n["NotificationLink"] ?? "", ENT_QUOTES, "UTF-8");
    $name  = htmlspecialchars($userName ?: "", ENT_QUOTES, "UTF-8");

    $html  = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">';
    $html .= '<p>' . L::hello() . ' ' . $name . ',</p>';
    $html .= '<h2 style="font-size:16px;">' . $title . '</h2>';
    $html .= '<p>' . $body . '</p>';
    if ($link) {
        $html .= '<p><a href="' . $link . '">' . $link . '</a></p>';
    }
    $html .= '<hr style="border:none;border-top:1px solid #ddd;">';
    $html .= '<p style="font-size:12px;color:#888;"><a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES, "UTF-8") . '">' . L::notificationEmailEnabled() . '</a></p>';
    $html .= '</div>';
    return $html;
}
