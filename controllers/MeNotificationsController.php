<?php
// controllers/MeNotificationsController.php

require_once __DIR__ . '/../models/UserNotification.php';
require_once __DIR__ . '/../config/config.php';

final class MeNotificationsController
{
    private \PDO $db;
    private UserNotification $model;

    public function __construct() {
        $this->db = dbConnection();
        $this->model = new UserNotification($this->db);
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (!isset($_SESSION['pk_user_id'])) {
            handleError(401, 'No authenticated user'); exit;
        }
    }

    private function me(): int {
        return (int)$_SESSION['pk_user_id'];
    }

    // GET /me/notifications?page=1&limit=20&only_unread=0
    public function index(): void {
        try {
            $page   = max(1, (int)($_GET['page']  ?? 1));
            $limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $onlyUnread = isset($_GET['only_unread']) ? (int)$_GET['only_unread'] : 0;

            [$rows, $total, $unread] = $this->model->listForUser($this->me(), $limit, $offset, $onlyUnread === 1);

            // Optional: anidar llaves con __ si tienes esa función global
            if (function_exists('transformToNested')) {
                $rows = array_map('transformToNested', $rows);
            } else {
                // mini-nesting inline (solo created_by__)
                foreach ($rows as &$r) {
                    $r['created_by'] = [
                        'id' => $r['created_by__id'] ?? null,
                        'display_name' => $r['created_by__display_name'] ?? null,
                        'profile_picture' => $r['created_by__profile_picture'] ?? null,
                    ];
                    unset($r['created_by__id'],$r['created_by__display_name'],$r['created_by__profile_picture']);
                }
            }

            jsonResponse(
                $rows,
                null,
                200,
                ['total' => $total, 'unread' => $unread, 'page' => $page, 'limit' => $limit]
            );
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    // GET /me/notifications/unread_count
    public function unreadCount(): void {
        try {
            $c = $this->model->countUnread($this->me());
            jsonResponse(['unread' => $c], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    // PATCH /me/notifications/{id}/seen
    public function markSeen(int $notificationId): void {
        try {
            $ok = $this->model->markSeen($this->me(), $notificationId);
            jsonResponse(['seen' => $ok], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    // PATCH /me/notifications/{id}/read
    public function markRead(int $notificationId): void {
        try {
            $ok = $this->model->markRead($this->me(), $notificationId);
            jsonResponse(['read' => $ok], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    // POST /me/notifications/seen_all
    public function seenAll(): void {
        try {
            $n = $this->model->markAllSeen($this->me());
            jsonResponse(['updated' => $n], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    // POST /me/notifications/read_all
    public function readAll(): void {
        try {
            $n = $this->model->markAllRead($this->me());
            jsonResponse(['updated' => $n], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }
}
