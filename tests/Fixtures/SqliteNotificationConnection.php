<?php

declare(strict_types=1);

namespace Marko\Notification\Database\Tests\Fixtures;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use PDO;
use RuntimeException;

/**
 * An in-memory SQLite connection with the notifications table, so the repository tests run their real SQL
 * against real rows and can prove which rows a statement touched.
 */
class SqliteNotificationConnection implements ConnectionInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT NOT NULL, notifiable_type TEXT NOT NULL, '
            . 'notifiable_id TEXT NOT NULL, data TEXT NOT NULL, read_at TEXT NULL, created_at TEXT NOT NULL)',
        );
    }

    /**
     * Insert a notification row directly.
     */
    public function addNotification(
        string $id,
        string $notifiableType,
        string $notifiableId,
        ?string $readAt = null,
    ): void {
        $this->pdo->prepare(
            'INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        )->execute(
            [$id, 'App\\Notification\\Test', $notifiableType, $notifiableId, '{}', $readAt, '2026-01-01 00:00:00'],
        );
    }

    /**
     * @return array<string, mixed>|null The row with the given id, or null when it does not exist
     */
    public function notification(
        string $id,
    ): ?array {
        $statement = $this->pdo->prepare('SELECT * FROM notifications WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function driverName(): string
    {
        return 'sqlite';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
