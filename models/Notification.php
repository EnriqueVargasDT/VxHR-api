<?php
final class Notification
{
    public function __construct(private \PDO $db) {}

    public function list(int $limit, int $offset): array {
        $sql = "SELECT * FROM dbo.notifications
                ORDER BY created_at DESC, id DESC
                OFFSET :o ROWS FETCH NEXT :l ROWS ONLY";
        $st = $this->db->prepare($sql);
        $st->bindValue(':o', $offset, PDO::PARAM_INT);
        $st->bindValue(':l', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM dbo.notifications WHERE id=:id");
        $st->execute([':id'=>$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $p): int {
    $rk = strtoupper($p['redirect']['kind'] ?? 'NONE');
    $url   = $p['redirect']['url'] ?? null;
    $rname = $p['redirect']['route']['name'] ?? null;
    $rparams = isset($p['redirect']['route']['params']) ? json_encode($p['redirect']['route']['params'], JSON_UNESCAPED_UNICODE) : null;
    $rkind  = $p['redirect']['resource']['kind'] ?? null;
    $rid    = isset($p['redirect']['resource']['id']) ? (int)$p['redirect']['resource']['id'] : null;

    $sql = "INSERT INTO dbo.notifications
            (title, body, [type], category_code, priority,
             redirect_kind, link_url, route_name, route_params_json, resource_kind, resource_id,
             send_inapp, send_email, scheduled_at, payload_json,
             created_by_user_id)
            OUTPUT INSERTED.id
            VALUES
            (:title,:body,:type,:cat,:prio,
             :rk,:url,:rname,:rparams,:rkind,:rid,
             :inapp,:email,:sched,:payload,
             :creator)";

    $st = $this->db->prepare($sql);

    // Campos de texto
    $st->bindValue(':title',   (string)$p['title'], PDO::PARAM_STR);
    $st->bindValue(':body',    (string)$p['body'],  PDO::PARAM_STR);
    $st->bindValue(':type',    (string)$p['type'],  PDO::PARAM_STR);
    $st->bindValue(':cat',     $p['category_code'] ?? null, PDO::PARAM_NULL | PDO::PARAM_STR);
    $st->bindValue(':rk',      $rk, PDO::PARAM_STR);
    $st->bindValue(':url',     $url, $url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->bindValue(':rname',   $rname, $rname === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->bindValue(':rparams', $rparams, $rparams === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $st->bindValue(':rkind',   $rkind, $rkind === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    // Números / flags
    $st->bindValue(':rid',     $rid, $rid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':prio',    (int)($p['priority'] ?? 0), PDO::PARAM_INT);
        $st->bindValue(':inapp',   (int)($p['send_inapp'] ?? 1), PDO::PARAM_INT);
        $st->bindValue(':email',   (int)($p['send_email'] ?? 0), PDO::PARAM_INT);

        // Fecha y JSON
        $sched = $p['scheduled_at'] ?? null;
        $st->bindValue(':sched',   $sched, $sched === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $payload = isset($p['payload']) ? json_encode($p['payload'], JSON_UNESCAPED_UNICODE) : null;
        $st->bindValue(':payload', $payload, $payload === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        // Creador (requerido)
        $st->bindValue(':creator', $_SESSION['pk_user_id'], PDO::PARAM_INT);

        $st->execute();
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('No identity returned for notifications insert');
        }
        return (int)$id;
    }

    public function update(int $id, array $p): void {
        $fields = [];
        $bind = [':id'=>$id];
        foreach ([
            'title','body','type','category_code','priority',
            'send_inapp','send_email','scheduled_at','payload_json'
        ] as $k) {
            if (array_key_exists($k, $p)) {
                $fields[] = "$k = :$k";
                $bind[":$k"] = ($k === 'payload_json') ? json_encode($p[$k]) : $p[$k];
            }
        }
        if (!empty($p['redirect'])) {
            $rk = strtoupper($p['redirect']['kind'] ?? 'NONE');
            $fields[] = "redirect_kind=:rk";
            $bind[':rk'] = $rk;
            $fields[] = "link_url=:url";  $bind[':url'] = $p['redirect']['url'] ?? null;
            $fields[] = "route_name=:rname"; $bind[':rname'] = $p['redirect']['route']['name'] ?? null;
            $fields[] = "route_params_json=:rparams"; $bind[':rparams'] = isset($p['redirect']['route']['params'])?json_encode($p['redirect']['route']['params']):null;
            $fields[] = "resource_kind=:rkind"; $bind[':rkind'] = $p['redirect']['resource']['kind'] ?? null;
            $fields[] = "resource_id=:rid"; $bind[':rid'] = $p['redirect']['resource']['id'] ?? null;
        }
        if (!$fields) return;
        $sql = "UPDATE dbo.notifications SET ".implode(',', $fields).", updated_at=SYSUTCDATETIME() WHERE id=:id";
        $st = $this->db->prepare($sql);
        $st->execute($bind);
    }

    public function delete(int $id): void {
        // Soft delete (opcional) o hard delete:
        $st = $this->db->prepare("DELETE FROM dbo.notifications WHERE id=:id");
        $st->execute([':id'=>$id]);
    }
}
