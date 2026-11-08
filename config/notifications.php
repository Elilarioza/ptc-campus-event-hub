<?php

if (!function_exists('createNotification')) {
    function createNotification(PDO $pdo, int $userId, string $title, string $message): bool
    {
        if ($userId <= 0 || trim($title) === '' || trim($message) === '') {
            return false;
        }

        $checkStmt = $pdo->prepare(
            "SELECT id FROM notifications WHERE user_id = ? AND title = ? AND message = ? LIMIT 1"
        );
        $checkStmt->execute([$userId, $title, $message]);

        if ($checkStmt->fetch()) {
            return false;
        }

        $insertStmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, is_read, created_at)
             VALUES (?, ?, ?, 0, NOW())"
        );

        return $insertStmt->execute([$userId, $title, $message]);
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('markNotificationAsRead')) {
    function markNotificationAsRead(PDO $pdo, int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
        );

        return $stmt->execute([$notificationId, $userId]);
    }
}

if (!function_exists('markAllNotificationsAsRead')) {
    function markAllNotificationsAsRead(PDO $pdo, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
        );

        return $stmt->execute([$userId]);
    }
}
