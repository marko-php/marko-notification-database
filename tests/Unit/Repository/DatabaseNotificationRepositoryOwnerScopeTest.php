<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Notification\Database\Tests\Fixtures\SqliteNotificationConnection;
use Marko\Testing\Fake\FakeClock;

/*
 * markAsReadFor() and deleteFor() only touch a notification that belongs to the given notifiable (#422), so a
 * request handler that takes the id from the URL cannot mark or delete another user's notification. The
 * unscoped markAsRead() and delete() stay as they were, for admin and internal use.
 */

function makeOwner(
    string $type,
    string|int $id,
): NotifiableInterface {
    return new readonly class ($type, $id) implements NotifiableInterface
    {
        public function __construct(
            private string $type,
            private string|int $id,
        ) {}

        public function routeNotificationFor(
            string $channel,
        ): mixed {
            return null;
        }

        public function getNotifiableId(): string|int
        {
            return $this->id;
        }

        public function getNotifiableType(): string
        {
            return $this->type;
        }
    };
}

beforeEach(function (): void {
    $this->connection = new SqliteNotificationConnection();
    $this->connection->addNotification('alice-1', 'App\\Entity\\User', '1');
    $this->connection->addNotification('bob-1', 'App\\Entity\\User', '2');
    $this->connection->addNotification('admin-1', 'App\\Entity\\Admin', '1');
    $this->repository = new DatabaseNotificationRepository(
        $this->connection,
        new FakeClock(new DateTimeImmutable('2026-03-14 15:09:26', new DateTimeZone('UTC'))),
        DatabaseTimezoneConfig::fromName('UTC'),
    );
    $this->alice = makeOwner('App\\Entity\\User', 1);
});

it('marks the owner\'s notification as read and returns true', function (): void {
    expect($this->repository->markAsReadFor($this->alice, 'alice-1'))->toBeTrue()
        ->and($this->connection->notification('alice-1')['read_at'])->toBe('2026-03-14 15:09:26');
})->issue(422);

it('does not mark another owner\'s notification as read and returns false', function (): void {
    expect($this->repository->markAsReadFor($this->alice, 'bob-1'))->toBeFalse()
        ->and($this->connection->notification('bob-1')['read_at'])->toBeNull();
})->issue(422);

it('does not mark a notification of another notifiable type with the same id as read', function (): void {
    expect($this->repository->markAsReadFor($this->alice, 'admin-1'))->toBeFalse()
        ->and($this->connection->notification('admin-1')['read_at'])->toBeNull();
})->issue(422);

it('returns false when marking a notification that does not exist as read', function (): void {
    expect($this->repository->markAsReadFor($this->alice, 'missing'))->toBeFalse();
})->issue(422);

it('returns true and keeps the original read_at when the owner\'s notification is already read', function (): void {
    $this->connection->addNotification('alice-read', 'App\\Entity\\User', '1', '2026-01-02 03:04:05');

    expect($this->repository->markAsReadFor($this->alice, 'alice-read'))->toBeTrue()
        ->and($this->connection->notification('alice-read')['read_at'])->toBe('2026-01-02 03:04:05');
})->issue(422);

it('deletes the owner\'s notification and returns true', function (): void {
    expect($this->repository->deleteFor($this->alice, 'alice-1'))->toBeTrue()
        ->and($this->connection->notification('alice-1'))->toBeNull()
        ->and($this->connection->notification('bob-1'))->not->toBeNull();
})->issue(422);

it('does not delete another owner\'s notification and returns false', function (): void {
    expect($this->repository->deleteFor($this->alice, 'bob-1'))->toBeFalse()
        ->and($this->repository->deleteFor($this->alice, 'admin-1'))->toBeFalse()
        ->and($this->connection->notification('bob-1'))->not->toBeNull()
        ->and($this->connection->notification('admin-1'))->not->toBeNull();
})->issue(422);

it('returns false when deleting a notification that does not exist', function (): void {
    expect($this->repository->deleteFor($this->alice, 'missing'))->toBeFalse();
})->issue(422);

it('keeps the unscoped markAsRead and delete acting on any notification by id', function (): void {
    $this->repository->markAsRead('bob-1');
    $this->repository->delete('admin-1');

    expect($this->connection->notification('bob-1')['read_at'])->toBe('2026-03-14 15:09:26')
        ->and($this->connection->notification('admin-1'))->toBeNull()
        ->and($this->connection->notification('alice-1'))->not->toBeNull();
})->issue(422);
