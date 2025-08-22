<?php
final class NotificationPublisher
{
    public function __construct(private \PDO $db) {}

    public function publishToAll(int $notificationId, array $from): void {
        $n = $this->getNotification($notificationId);
        if (!$n) throw new \RuntimeException('Notification not found');

        $this->db->beginTransaction();
        // marca publicada si no lo está
        $this->markPublished($notificationId);

        // 1) candidatos: todos activos
        $users = $this->fetchUsers(); // [ [id, name, institutional_email, personal_email], ... ]
        $this->fanout($n, $users, $from);
        $this->db->commit();
    }

    public function publishToUsers(int $notificationId, array $userIds, array $from): void {
        $n = $this->getNotification($notificationId);
        if (!$n) throw new \RuntimeException('Notification not found');

        if (!$userIds) return;

        $this->db->beginTransaction();
        $this->markPublished($notificationId);
        $users = $this->fetchUsers($userIds);
        $this->fanout($n, $users, $from);
        $this->db->commit();
    }

    /* ---------------- internals ---------------- */

    private function getNotification(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM dbo.notifications WHERE id=:id");
        $st->execute([':id'=>$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function markPublished(int $id): void {
        $this->db->prepare("UPDATE dbo.notifications SET published_at = COALESCE(published_at, SYSUTCDATETIME()) WHERE id=:id")
                 ->execute([':id'=>$id]);
    }

    /** Si $ids es null => todos activos */
    private function fetchUsers(?array $ids = null): array {
        if ($ids && count($ids) > 0) {
            // lista parametrizada
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT pk_user_id AS id,
                           LTRIM(RTRIM(CONCAT(COALESCE(first_name,''),' ',
                             COALESCE(last_name_1,''),' ',
                             COALESCE(NULLIF(last_name_2,''),'')))) AS display_name,
                           institutional_email, personal_email
                    FROM [user].users
                    WHERE is_active=1 AND pk_user_id IN ($placeholders)";
            $st = $this->db->prepare($sql);
            $st->execute($ids);
        } else {
            $sql = "SELECT pk_user_id AS id,
                           LTRIM(RTRIM(CONCAT(COALESCE(first_name,''),' ',
                             COALESCE(last_name_1,''),' ',
                             COALESCE(NULLIF(last_name_2,''),'')))) AS display_name,
                           institutional_email, personal_email
                    FROM [user].users
                    WHERE is_active=1";
            $st = $this->db->query($sql);
        }
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Carga catálogo, políticas y prefs para todos los candidatos de una categoría */
    private function loadSettings(string $categoryCode, array $userIds): array {
        // Categoría
        $st = $this->db->prepare("SELECT code, active, inapp_default, email_default
                                  FROM dbo.notification_categories WHERE code=:c");
        $st->execute([':c'=>$categoryCode]);
        $cat = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cat) {
            // si no hay categoría, trátala como activa con defaults (1/0)
            $cat = ['code'=>$categoryCode, 'active'=>1, 'inapp_default'=>1, 'email_default'=>0];
        }

        // Política admin
        $st = $this->db->prepare("SELECT inapp_policy, email_policy
                                  FROM dbo.notification_admin_policies WHERE category_code=:c");
        $st->execute([':c'=>$categoryCode]);
        $pol = $st->fetch(PDO::FETCH_ASSOC) ?: ['inapp_policy'=>'ON','email_policy'=>'ON'];

        // Preferencias por usuario
        $prefs = [];
        if ($userIds) {
            $ph = implode(',', array_fill(0, count($userIds), '?'));
            $sql = "SELECT user_id, inapp_enabled, email_enabled, muted_until
                    FROM dbo.user_notification_prefs
                    WHERE category_code = ? AND user_id IN ($ph)";
            $st = $this->db->prepare($sql);
            $st->execute(array_merge([$categoryCode], $userIds));
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $prefs[(int)$r['user_id']] = $r;
            }
        }

        return [$cat, $pol, $prefs];
    }

    private function effectiveDelivery(array $cat, array $pol, ?array $pref): array {
        // La categoría debe estar activa y el usuario no debe estar silenciado
        $active = (int)$cat['active'] === 1;
        $muted  = $pref && !empty($pref['muted_until']) && (strtotime($pref['muted_until']) > time());
        if (!$active || $muted) {
            return [false, false];
        }

        // Defaults de la categoría
        $inappDefault = (int)$cat['inapp_default'] === 1;
        $emailDefault = (int)$cat['email_default'] === 1;

        // Preferencias del usuario (si existen) o cae a defaults
        $inappUser = ($pref !== null && $pref['inapp_enabled'] !== null) ? (bool)$pref['inapp_enabled'] : $inappDefault;
        $emailUser = ($pref !== null && $pref['email_enabled'] !== null) ? (bool)$pref['email_enabled'] : $emailDefault;

        // Políticas admin
        $inappPolicy = $pol['inapp_policy'] ?? 'ON';   // 'OFF' | 'ON' | 'FORCE'
        $emailPolicy = $pol['email_policy'] ?? 'ON';   // 'OFF' | 'ON' (no forzamos email)

        // Resolver IN-APP (sin ternarios anidados)
        if ($inappPolicy === 'OFF') {
            $inapp = false;
        } elseif ($inappPolicy === 'FORCE') {
            $inapp = true;
        } else { // 'ON'
            $inapp = $inappUser;
        }

        // Resolver EMAIL
        if ($emailPolicy === 'OFF') {
            $email = false;
        } else { // 'ON' (nunca forzamos email)
            $email = $emailUser;
        }

        return [$inapp, $email];
    }


    private function fanout(array $n, array $users, array $from): void {
        $nid = (int)$n['id'];
        $sendInapp = (int)$n['send_inapp'] === 1;
        $sendEmail = (int)$n['send_email'] === 1;
        $catCode   = $n['category_code'] ?? null;

        if (!$users) return;
        $userIds = array_map(fn($u)=>(int)$u['id'], $users);
        [$cat, $pol, $prefs] = $this->loadSettings($catCode ?? '', $userIds);

        $toInapp = [];
        $toEmail = [];

        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $pref = $prefs[$uid] ?? null;
            [$inapp, $email] = $this->effectiveDelivery($cat, $pol, $pref);
            if ($sendInapp && $inapp) $toInapp[] = $uid;
            if ($sendEmail && $email) {
                $best = $this->bestEmail($u);
                if ($best) {
                    $toEmail[] = [
                        'user_id' => $uid,
                        'email'   => $best,
                        'name'    => $u['display_name'] ?: null
                    ];
                }
            }
        }

        if ($toInapp)  $this->bulkInsertUserNotifications($nid, $toInapp);
        if ($toEmail)  $this->bulkInsertMailMessages($n, $toEmail, $from);
    }

    private function bestEmail(array $u): ?string {
        $inst = trim((string)($u['institutional_email'] ?? ''));
        $pers = trim((string)($u['personal_email'] ?? ''));
        if ($inst !== '') return $inst;
        if ($pers !== '') return $pers;
        return null;
    }

    private function bulkInsertUserNotifications(int $nid, array $userIds): void {
        $chunks = array_chunk($userIds, 500);
        foreach ($chunks as $chunk) {
            $vals = [];
            $params = [':nid' => $nid];
            $i = 0;
            foreach ($chunk as $uid) {
                $i++;
                $vals[] = "(:u{$i})";
                $params[":u{$i}"] = (int)$uid;
            }

            $sql = "
            ;WITH users_cte(user_id) AS (
                SELECT v.user_id
                FROM (VALUES " . implode(',', $vals) . ") AS v(user_id)
            ),
            consts AS (
                SELECT :nid AS notification_id
            )
            INSERT INTO dbo.user_notifications (notification_id, user_id)
            SELECT c.notification_id, u.user_id
            FROM users_cte u
            CROSS JOIN consts c
            LEFT JOIN dbo.user_notifications un
            ON un.notification_id = c.notification_id
            AND un.user_id         = u.user_id
            WHERE un.user_id IS NULL;
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
        }
    }

    private function bulkInsertMailMessages(array $n, array $targets, array $from): void {
        $nid = (int)$n['id'];
        $priority = ((int)$n['priority'] > 0) ? 1 : 0;

        $fromEmail = $from['email'];
        $fromName  = $from['name'];
        $reply     = $from['reply'] ?? null;
        $subject   = (string)$n['title'];
        $html      = $this->renderHtml($n);
        $text      = null;
        $status    = 'pending';
        $prio      = $priority;

        $chunks = array_chunk($targets, 250);
        foreach ($chunks as $chunk) {
            $rows = [];
            $params = [
                ':nid'       => $nid,
                ':fromEmail' => $fromEmail,
                ':fromName'  => $fromName,
                ':reply'     => $reply,
                ':subject'   => $subject,
                ':html'      => $html,
                ':text'      => $text,
                ':status'    => $status,
                ':prio'      => $prio,
            ];
            $i = 0;
            foreach ($chunk as $t) {
                $i++;
                $rows[] = "(:uid{$i}, :to{$i}, :name{$i})";
                $params[":uid{$i}"]  = (int)$t['user_id'];
                $params[":to{$i}"]   = (string)$t['email'];
                $params[":name{$i}"] = $t['name'] !== null ? (string)$t['name'] : null;
            }

            $sql = "
            ;WITH recipients(recipient_user_id, to_email, to_name) AS (
                SELECT v.recipient_user_id, v.to_email, v.to_name
                FROM (VALUES " . implode(',', $rows) . ") AS v(recipient_user_id, to_email, to_name)
            ),
            consts AS (
                SELECT
                    :nid       AS notification_id,
                    :fromEmail AS from_email,
                    :fromName  AS from_name,
                    :reply     AS reply_to_email,
                    :subject   AS subject_resolved,
                    :html      AS html_resolved,
                    :text      AS text_resolved,
                    :status    AS status,
                    :prio      AS priority
            )
            INSERT INTO dbo.mail_messages
            (campaign_id, notification_id,
            recipient_user_id, to_email, to_name,
            from_email, from_name, reply_to_email, headers_json,
            template_id, vars_json,
            subject_resolved, html_resolved, text_resolved,
            status, priority, queued_at)
            SELECT
            NULL, c.notification_id,
            r.recipient_user_id, r.to_email, r.to_name,
            c.from_email, c.from_name, c.reply_to_email, NULL,
            NULL, NULL,
            c.subject_resolved, c.html_resolved, c.text_resolved,
            c.status, c.priority, SYSUTCDATETIME()
            FROM recipients r
            CROSS JOIN consts c
            LEFT JOIN dbo.mail_messages mm
            ON mm.notification_id   = c.notification_id
            AND mm.recipient_user_id = r.recipient_user_id
            WHERE mm.id IS NULL;
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
        }
    }

    private function renderHtml(array $n): string {
        $cta = '';
        if ($n['redirect_kind'] === 'URL' && !empty($n['link_url'])) {
            $u = htmlspecialchars($n['link_url'], ENT_QUOTES, 'UTF-8');
            $cta = '<p><a href="'.$u.'">Ver más</a></p>';
        }
        return '<h3>'.htmlspecialchars($n['title'] ?? '', ENT_QUOTES, 'UTF-8').'</h3>'
             . '<div>'.($n['body'] ?? '').'</div>' . $cta;
    }
}
