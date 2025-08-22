<?php
// routes/me_notifications.php
require_once __DIR__ . '/../controllers/MeNotificationsController.php';

function me_notifications($method, $subroutes, $body) {
    $ctl = new MeNotificationsController();

    // /me/notifications
    if (!isset($subroutes[0]) || $subroutes[0] === '') {
        if ($method === 'GET') { $ctl->index(); }
        else { handleError(405, 'Method Not Allowed'); }
        return;
    }

    // /me/notifications/unread_count
    if ($subroutes[0] === 'unread_count') {
        if ($method === 'GET') { $ctl->unreadCount(); }
        else { handleError(405, 'Method Not Allowed'); }
        return;
    }

    // /me/notifications/seen_all  (POST)
    if ($subroutes[0] === 'seen_all') {
        if ($method === 'POST') { $ctl->seenAll(); }
        else { handleError(405, 'Method Not Allowed'); }
        return;
    }

    // /me/notifications/read_all  (POST)
    if ($subroutes[0] === 'read_all') {
        if ($method === 'POST') { $ctl->readAll(); }
        else { handleError(405, 'Method Not Allowed'); }
        return;
    }

    // /me/notifications/{id}/seen  (PATCH)
    if (isset($subroutes[1]) && $subroutes[1] === 'seen') {
        if ($method === 'PATCH') { $ctl->markSeen((int)$subroutes[0]); }
        else { handleError(405, 'Method Not Allowed'); }
        return;
    }

    // /me/notifications/{id}/read  (PATCH)
    if (isset($subroutes[1]) && $subroutes[1] === 'read') {
        if ($method === 'PATCH') { $ctl->markRead((int)$subroutes[0]); }
        else { handleError(405, 'Method Not Allowed'); }
        return;
    }

    handleError(404, 'Not Found');
}
