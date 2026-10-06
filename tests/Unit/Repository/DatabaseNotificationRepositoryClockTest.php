<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Testing\Fake\FakeClock;

test('it stores read_at in the database timezone when marking one notification read', function (): void {
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedBindings) {
            $capturedBindings = $bindings;

            return 1;
        });

    $repo = new DatabaseNotificationRepository(
        $connection,
        new FakeClock(new DateTimeImmutable('2026-03-14 11:09:26', new DateTimeZone('America/New_York'))),
        DatabaseTimezoneConfig::fromName('UTC'),
    );
    $repo->markAsRead('uuid-123');

    expect($capturedBindings[0])->toBe('2026-03-14 15:09:26');
});

test('it stores read_at in the database timezone when marking all notifications read', function (): void {
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedBindings) {
            $capturedBindings = $bindings;

            return 2;
        });

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(7);

    $repo = new DatabaseNotificationRepository(
        $connection,
        new FakeClock(new DateTimeImmutable('2026-03-14 11:09:26', new DateTimeZone('America/New_York'))),
        DatabaseTimezoneConfig::fromName('UTC'),
    );
    $repo->markAllAsRead($notifiable);

    expect($capturedBindings[0])->toBe('2026-03-14 15:09:26');
});
