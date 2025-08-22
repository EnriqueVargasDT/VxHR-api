<?php
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../services/NotificationPublisher.php';
require_once __DIR__ . '/../config/config.php'; // define dbConnection()

final class NotificationController
{
    private \PDO $db;
    private Notification $model;
    private NotificationPublisher $publisher;

    public function __construct() {
        $this->db = dbConnection();
        $this->model = new Notification($this->db);
        $this->publisher = new NotificationPublisher($this->db);
    }

    public function index(): void {
        try {
            $page   = max(1, (int)($_GET['page']  ?? 1));
            $limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $data   = $this->model->list($limit, $offset);
            jsonResponse($data, null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    public function show(int $id): void {
        try {
            $row = $this->model->find($id);
            if (!$row) { handleError(404, 'Notification not found'); return; }
            jsonResponse($row, null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    public function store(array $body): void {
        try {
            $id = $this->model->create($body);

            // Publicación inmediata si no está programada a futuro
            $scheduledAt = $body['scheduled_at'] ?? null;
            if (!$scheduledAt || strtotime($scheduledAt) <= time()) {
                $target  = strtoupper($body['target']['scope'] ?? 'ALL');
                $userIds = $body['target']['user_ids'] ?? [];
                $from = [
                    'email' => $body['from_email']     ?? 'no-reply@miapp.com',
                    'name'  => $body['from_name']      ?? 'Mi App',
                    'reply' => $body['reply_to_email'] ?? null,
                ];
                if ($target === 'ALL') {
                    $this->publisher->publishToAll($id, $from);
                } else {
                    $this->publisher->publishToUsers($id, array_map('intval', $userIds), $from);
                }
            }

            // Usa tu firma: (data, message, status, extras)
            jsonResponse(['id' => $id], null, 201);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    public function update(int $id, array $body): void {
        try {
            if (!$this->model->find($id)) { handleError(404, 'Notification not found'); return; }
            $this->model->update($id, $body);
            jsonResponse(['updated' => true], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    public function destroy(int $id): void {
        try {
            if (!$this->model->find($id)) { handleError(404, 'Notification not found'); return; }
            $this->model->delete($id);
            jsonResponse(['deleted' => true], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }

    public function publish(int $id, array $body): void {
        try {
            if (!$this->model->find($id)) { handleError(404, 'Notification not found'); return; }

            $target  = strtoupper($body['target']['scope'] ?? 'ALL');
            $userIds = $body['target']['user_ids'] ?? [];
            $from = [
                'email' => $body['from_email']     ?? 'no-reply@miapp.com',
                'name'  => $body['from_name']      ?? 'Mi App',
                'reply' => $body['reply_to_email'] ?? null,
            ];

            if ($target === 'ALL') {
                $this->publisher->publishToAll($id, $from);
            } else {
                $this->publisher->publishToUsers($id, array_map('intval', $userIds), $from);
            }

            jsonResponse(['published' => true], null, 200);
        } catch (\Throwable $e) {
            handleExceptionError($e);
        }
    }
}
