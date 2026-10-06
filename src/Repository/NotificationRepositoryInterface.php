<?php

declare(strict_types=1);

namespace Marko\Notification\Database\Repository;

use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Entity\DatabaseNotification;

interface NotificationRepositoryInterface
{
    /**
     * Get all notifications for a notifiable.
     *
     * @return array<DatabaseNotification>
     */
    public function forNotifiable(
        NotifiableInterface $notifiable,
    ): array;

    /**
     * Get all unread notifications for a notifiable.
     *
     * @return array<DatabaseNotification>
     */
    public function unread(
        NotifiableInterface $notifiable,
    ): array;

    /**
     * Mark a single notification as read, by ID alone, whoever owns it.
     *
     * For admin and internal use only. Code that takes the ID from a request must use markAsReadFor(),
     * otherwise any user who learns another user's notification ID can mark it as read.
     */
    public function markAsRead(
        string $notificationId,
    ): void;

    /**
     * Mark a single notification as read, only if it belongs to the notifiable.
     *
     * The default for request-driven code. A notification that is already read keeps its original read_at.
     *
     * @return bool True when the notification exists and belongs to the notifiable, false otherwise
     */
    public function markAsReadFor(
        NotifiableInterface $notifiable,
        string $notificationId,
    ): bool;

    /**
     * Mark all notifications as read for a notifiable.
     */
    public function markAllAsRead(
        NotifiableInterface $notifiable,
    ): void;

    /**
     * Delete a single notification by ID alone, whoever owns it.
     *
     * For admin and internal use only. Code that takes the ID from a request must use deleteFor(),
     * otherwise any user who learns another user's notification ID can delete it.
     */
    public function delete(
        string $notificationId,
    ): void;

    /**
     * Delete a single notification, only if it belongs to the notifiable.
     *
     * The default for request-driven code.
     *
     * @return bool True when the notification existed, belonged to the notifiable and was deleted, false otherwise
     */
    public function deleteFor(
        NotifiableInterface $notifiable,
        string $notificationId,
    ): bool;

    /**
     * Delete all notifications for a notifiable.
     */
    public function deleteAll(
        NotifiableInterface $notifiable,
    ): void;

    /**
     * Count unread notifications for a notifiable.
     */
    public function unreadCount(
        NotifiableInterface $notifiable,
    ): int;
}
