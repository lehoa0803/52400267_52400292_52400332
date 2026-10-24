<?php

// Dùng chung cho form tạo/sửa; không kết nối database khi nạp file.
function trip_metadata_load_tags(PDO $conn): array
{
    $tags = [];
    foreach ($conn->query('SELECT id, name, type, status FROM tags ORDER BY type, name, id')->fetchAll(PDO::FETCH_ASSOC) as $tag) {
        $tags[(int) $tag['id']] = $tag;
    }
    return $tags;
}

function trip_metadata_normalize(array $input, array $availableTags, array $existingTagIds = [], string $existingDescription = ''): array
{
    $errors = [];
    $description = $existingDescription;
    if (array_key_exists('tripDescription', $input)) {
        if (!is_string($input['tripDescription'])) {
            $errors[] = 'Mô tả chuyến đi không hợp lệ.';
        } else {
            $description = trim($input['tripDescription']);
            // TEXT của MySQL giới hạn theo byte, kể cả nội dung tiếng Việt.
            if (!mb_check_encoding($description, 'UTF-8') || strlen($description) > 65535) {
                $errors[] = 'Mô tả chuyến đi phải là văn bản UTF-8 và không vượt quá 65.535 byte.';
            }
        }
    }

    $tagIds = array_values(array_unique(array_map('intval', $existingTagIds)));
    // Form cũ/thiếu trường không được làm mất liên kết tags đang có.
    if (array_key_exists('trip_tags_present', $input)) {
        $rawTags = $input['tripTags'] ?? [];
        if ($input['trip_tags_present'] !== '1' || !is_array($rawTags)) {
            $errors[] = 'Danh sách chủ đề chuyến đi không hợp lệ.';
        } else {
            $tagIds = [];
            foreach ($rawTags as $rawId) {
                $id = is_int($rawId) || is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
                if (!$id || $id < 1) {
                    $errors[] = 'Mã chủ đề chuyến đi không hợp lệ.';
                    continue;
                }
                $tag = $availableTags[$id] ?? null;
                if (!$tag || (($tag['status'] ?? '') !== 'active' && !in_array($id, $existingTagIds, true))) {
                    $errors[] = 'Chủ đề đã chọn không tồn tại hoặc không còn được sử dụng cho chuyến đi mới.';
                    continue;
                }
                $tagIds[] = (int) $id;
            }
            $tagIds = array_values(array_unique($tagIds));
        }
    }

    return ['description' => $description, 'tag_ids' => $tagIds, 'errors' => array_values(array_unique($errors))];
}

function trip_metadata_save_tags(PDO $conn, int $tripId, array $tagIds): void
{
    if (!$conn->inTransaction()) {
        throw new LogicException('Tags phải được lưu trong transaction của chuyến đi.');
    }
    $query = $conn->prepare('SELECT tag_id FROM trip_tags WHERE trip_id = ?');
    $query->execute([$tripId]);
    $currentIds = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
    // Kiểm tra lại vì Admin có thể đã ẩn/xóa tag sau khi form được mở.
    $selection = trip_metadata_normalize(['trip_tags_present' => '1', 'tripTags' => $tagIds], trip_metadata_load_tags($conn), $currentIds);
    if ($selection['errors']) throw new InvalidArgumentException(implode(' ', $selection['errors']));

    $remove = $conn->prepare('DELETE FROM trip_tags WHERE trip_id = ? AND tag_id = ?');
    foreach (array_diff($currentIds, $selection['tag_ids']) as $id) $remove->execute([$tripId, $id]);
    $insert = $conn->prepare('INSERT INTO trip_tags (trip_id, tag_id) VALUES (?, ?)');
    foreach (array_diff($selection['tag_ids'], $currentIds) as $id) $insert->execute([$tripId, $id]);
}
