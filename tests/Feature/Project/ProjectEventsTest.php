<?php

use App\Events\ProjectDeleted;
use App\Events\ProjectRestored;
use Illuminate\Broadcasting\PrivateChannel;

it('ProjectDeleted broadcasts on the project channel and each member user channel', function () {
    $event = new ProjectDeleted(projectId: 7, memberIds: [11, 22]);

    expect($event->broadcastAs())->toBe('project.deleted');
    expect($event->broadcastWith())->toBe(['project_id' => 7]);

    $names = array_map(fn (PrivateChannel $c) => $c->name, $event->broadcastOn());
    expect($names)->toBe(['private-project.7', 'private-user.11', 'private-user.22']);
});

it('ProjectRestored broadcasts with the project.restored alias', function () {
    $event = new ProjectRestored(projectId: 7, memberIds: [11]);

    expect($event->broadcastAs())->toBe('project.restored');
    expect($event->broadcastWith())->toBe(['project_id' => 7]);
    $names = array_map(fn (PrivateChannel $c) => $c->name, $event->broadcastOn());
    expect($names)->toBe(['private-project.7', 'private-user.11']);
});
