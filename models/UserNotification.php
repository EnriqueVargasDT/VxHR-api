<?php
// models/UserNotification.php

final class UserNotification
{
    public function __construct(private \PDO $db) {}

    /**
     * @return array [$rows, $total, $unread]
     */
    public function listForUser(int $userId, int $limit, int $offset, bool $onlyUnread=false): array {
        // total y unread
        $st = $this->db->prepare("SELECT COUNT(*) FROM dbo.user_notifications WHERE user_id=:u");
        $st->execute([':u'=>$userId]);
        $total = (int)$st->fetchColumn();

        $st = $this->db->prepare("SELECT COUNT(*) FROM dbo.user_notifications WHERE user_id=:u AND read_at IS NULL");
        $st->execute([':u'=>$userId]);
        $unread = (int)$st->fetchColumn();

        $whereUnread = $onlyUnread ? "AND un.read_at IS NULL" : "";

        $sql = "
        SELECT
          n.id,
          n.title,
          n.body,
          n.[type]            AS [type],
          n.category_code,
          n.created_at,
          n.published_at,
          un.seen_at,
          un.read_at,

          -- redirect
          n.redirect_kind,
          n.link_url,
          n.route_name,
          n.route_params_json,
          n.resource_kind,
          n.resource_id,

          -- created_by
          u.pk_user_id AS created_by__id,
          LTRIM(RTRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name_1,'')))) AS created_by__display_name,
          uf.[file]    AS created_by__profile_picture
        FROM dbo.user_notifications un
        JOIN dbo.notifications n ON n.id = un.notification_id
        LEFT JOIN [user].users u ON u.pk_user_id = n.created_by_user_id
        LEFT JOIN [user].[files] uf ON uf.fk_user_id = u.pk_user_id AND uf.type_file = 1
        WHERE un.user_id = :uid
          $whereUnread
        ORDER BY n.created_at DESC, n.id DESC
        OFFSET :o ROWS FETCH NEXT :l ROWS ONLY;
        ";

        $st = $this->db->prepare($sql);
        $st->bindValue(':uid', $userId, PDO::PARAM_INT);
        $st->bindValue(':o', $offset, PDO::PARAM_INT);
        $st->bindValue(':l', $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return [$rows, $total, $unread];
    }

    public function countUnread(int $userId): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM dbo.user_notifications WHERE user_id=:u AND read_at IS NULL");
        $st->execute([':u'=>$userId]);
        return (int)$st->fetchColumn();
    }

    public function markSeen(int $userId, int $notificationId): bool {
        $st = $this->db->prepare("
            UPDATE dbo.user_notifications
               SET seen_at = COALESCE(seen_at, SYSUTCDATETIME())
             WHERE user_id=:u AND notification_id=:n");
        $st->execute([':u'=>$userId, ':n'=>$notificationId]);
        return $st->rowCount() > 0;
    }

    public function markRead(int $userId, int $notificationId): bool {
        $st = $this->db->prepare("
            UPDATE dbo.user_notifications
               SET read_at = COALESCE(read_at, SYSUTCDATETIME()),
                   seen_at = COALESCE(seen_at, SYSUTCDATETIME())
             WHERE user_id=:u AND notification_id=:n");
        $st->execute([':u'=>$userId, ':n'=>$notificationId]);
        return $st->rowCount() > 0;
    }

    public function markAllSeen(int $userId): int {
        $st = $this->db->prepare("
            UPDATE dbo.user_notifications
               SET seen_at = COALESCE(seen_at, SYSUTCDATETIME())
             WHERE user_id=:u AND seen_at IS NULL");
        $st->execute([':u'=>$userId]);
        return (int)$st->rowCount();
    }

    public function markAllRead(int $userId): int {
        $st = $this->db->prepare("
            UPDATE dbo.user_notifications
               SET read_at = COALESCE(read_at, SYSUTCDATETIME()),
                   seen_at = COALESCE(seen_at, SYSUTCDATETIME())
             WHERE user_id=:u AND read_at IS NULL");
        $st->execute([':u'=>$userId]);
        return (int)$st->rowCount();
    }
}
