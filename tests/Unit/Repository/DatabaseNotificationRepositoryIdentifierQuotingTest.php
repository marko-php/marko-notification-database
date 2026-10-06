<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Testing\Fake\FakeClock;

/*
 * DatabaseNotificationRepository quotes the notifications table through ConnectionInterface::quoteIdentifier()
 * (#338). The connection quotes with backticks, so SQL that names the table bare or picks its own delimiter shows up.
 */

it('quotes the notifications table in every repository statement', function (): void {
    $statements = [];
    $record = function (string $sql) use (&$statements): void {
        $statements[] = $sql;
    };
    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('quoteIdentifier')->willReturnCallback(fn (string $name): string => "`$name`");
    $connection->method('query')->willReturnCallback(function (string $sql) use ($record): array {
        $record($sql);

        return [];
    });
    $connection->method('execute')->willReturnCallback(function (string $sql) use ($record): int {
        $record($sql);

        return 1;
    });
    $repository = new DatabaseNotificationRepository(
        $connection,
        new FakeClock(),
        DatabaseTimezoneConfig::fromName('UTC'),
    );
    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(42);

    $repository->forNotifiable($notifiable);
    $repository->unread($notifiable);
    $repository->markAsRead('notification-1');
    $repository->markAllAsRead($notifiable);
    $repository->delete('notification-1');
    $repository->deleteAll($notifiable);
    $repository->unreadCount($notifiable);

    expect($statements)->toHaveCount(7)
        ->and(array_filter($statements, fn (string $sql): bool => !str_contains($sql, '`notifications`')))->toBe([])
        ->and($statements[2])->toBe('UPDATE `notifications` SET read_at = ? WHERE id = ?');
})->issue(338);
