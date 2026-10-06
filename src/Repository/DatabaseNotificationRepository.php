<?php

declare(strict_types=1);

namespace Marko\Notification\Database\Repository;

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Entity\DatabaseNotification;
use Psr\Clock\ClockInterface;

/**
 * read_at is written in the database timezone (`database.timezone`, UTC by
 * default), the same zone DatabaseChannel writes created_at in.
 */
class DatabaseNotificationRepository implements NotificationRepositoryInterface
{
    private const string TABLE = 'notifications';

    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private DatabaseTimezoneConfig $databaseTimezoneConfig,
    ) {}

    /**
     * The notifications table name quoted for the connection's SQL dialect.
     */
    private function table(): string
    {
        return $this->connection->quoteIdentifier(self::TABLE);
    }

    /**
     * @return array<DatabaseNotification>
     */
    public function forNotifiable(
        NotifiableInterface $notifiable,
    ): array {
        $rows = $this->connection->query(
            "SELECT * FROM {$this->table()} WHERE notifiable_type = ? AND notifiable_id = ? ORDER BY created_at DESC",
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return array<DatabaseNotification>
     */
    public function unread(
        NotifiableInterface $notifiable,
    ): array {
        $rows = $this->connection->query(
            "SELECT * FROM {$this->table()} WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL ORDER BY created_at DESC",
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function markAsRead(
        string $notificationId,
    ): void {
        $this->connection->execute(
            "UPDATE {$this->table()} SET read_at = ? WHERE id = ?",
            [$this->databaseTimezoneConfig->format($this->clock->now()), $notificationId],
        );
    }

    /**
     * Only an unread row is updated, so the original read_at survives. When nothing was updated the row is
     * looked up, because "already read" and "not yours or missing" both report zero affected rows (MySQL
     * also reports zero when the new value equals the old one).
     */
    public function markAsReadFor(
        NotifiableInterface $notifiable,
        string $notificationId,
    ): bool {
        $owner = [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()];

        $updated = $this->connection->execute(
            "UPDATE {$this->table()} SET read_at = ? WHERE id = ? AND notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL",
            [$this->databaseTimezoneConfig->format($this->clock->now()), $notificationId, ...$owner],
        );

        if ($updated > 0) {
            return true;
        }

        $result = $this->connection->query(
            "SELECT COUNT(*) as count FROM {$this->table()} WHERE id = ? AND notifiable_type = ? AND notifiable_id = ?",
            [$notificationId, ...$owner],
        );

        return (int) ($result[0]['count'] ?? 0) > 0;
    }

    public function markAllAsRead(
        NotifiableInterface $notifiable,
    ): void {
        $this->connection->execute(
            "UPDATE {$this->table()} SET read_at = ? WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL",
            [
                $this->databaseTimezoneConfig->format($this->clock->now()),
                $notifiable->getNotifiableType(),
                (string) $notifiable->getNotifiableId(),
            ],
        );
    }

    public function delete(
        string $notificationId,
    ): void {
        $this->connection->execute(
            "DELETE FROM {$this->table()} WHERE id = ?",
            [$notificationId],
        );
    }

    public function deleteFor(
        NotifiableInterface $notifiable,
        string $notificationId,
    ): bool {
        $deleted = $this->connection->execute(
            "DELETE FROM {$this->table()} WHERE id = ? AND notifiable_type = ? AND notifiable_id = ?",
            [$notificationId, $notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );

        return $deleted > 0;
    }

    public function deleteAll(
        NotifiableInterface $notifiable,
    ): void {
        $this->connection->execute(
            "DELETE FROM {$this->table()} WHERE notifiable_type = ? AND notifiable_id = ?",
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );
    }

    public function unreadCount(
        NotifiableInterface $notifiable,
    ): int {
        $result = $this->connection->query(
            "SELECT COUNT(*) as count FROM {$this->table()} WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL",
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );

        return (int) ($result[0]['count'] ?? 0);
    }

    private function hydrate(
        array $row,
    ): DatabaseNotification {
        $notification = new DatabaseNotification();
        $notification->id = $row['id'];
        $notification->type = $row['type'];
        $notification->notifiableType = $row['notifiable_type'];
        $notification->notifiableId = $row['notifiable_id'];
        $notification->data = $row['data'];
        $notification->readAt = $row['read_at'] ?? null;
        $notification->createdAt = $row['created_at'];

        return $notification;
    }
}
