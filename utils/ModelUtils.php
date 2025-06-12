<?php

function buildUpdateQuery($table, $idField, $idValue, $data) {
    $fields = [];
    $params = [":$idField" => $idValue];

    foreach ($data as $key => $value) {
        if ($key !== $idField && $value !== null) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }

    if (empty($fields)) return false;

    $sql = "UPDATE $table SET " . implode(", ", $fields) . " WHERE $idField = :$idField";
    return ['sql' => $sql, 'params' => $params];
}

function buildInsertQuery($table, $data) {
    $fields = [];
    $placeholders = [];
    $params = [];

    foreach ($data as $key => $value) {
        if ($value !== null) {
            $fields[] = $key;
            $placeholders[] = ":$key";
            $params[":$key"] = $value;
        }
    }

    if (empty($fields)) return false;

    $sql = "INSERT INTO $table (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
    return ['sql' => $sql, 'params' => $params];
}

function transformToNested(array $flat): array {
    $nested = [];

    foreach ($flat as $key => $value) {
        if (strpos($key, '__') !== false) {
            [$parent, $child] = explode('__', $key, 2);
            if (!isset($nested[$parent])) {
                $nested[$parent] = [];
            }
            $nested[$parent][$child] = $value;
        } else {
            $nested[$key] = $value;
        }
    }

    return $nested;
}

function formatReactions(array $rows): array {
    $countMap = [
        'like' => 0, 'love' => 0, 'applause' => 0,
        'birthday-cake' => 0, 'smile' => 0, 'trophy' => 0,
        'idea' => 0, 'appreciation' => 0, 'sympathy' => 0, 'star' => 0
    ];

    $items = [];

    foreach ($rows as $row) {
        $type = $row['type'];
        if (isset($countMap[$type])) {
            $countMap[$type]++;
        }

        $items[] = [
            // 'id' => $row['id'],
            'created_at' => $row['created_at'],
            'type' => $type,
            'created_by' => [
                'id' => $row['created_by__id'],
                'display_name' => $row['created_by__display_name'],
                'profile_picture' => $row['created_by__profile_picture']
            ]
        ];
    }

    return [
        'total' => count($items),
        'count' => $countMap,
        'items' => $items
    ];
}
