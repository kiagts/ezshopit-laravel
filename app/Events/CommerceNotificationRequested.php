<?php

namespace App\Events;

class CommerceNotificationRequested
{
    public function __construct(public readonly string $operation, public readonly array $arguments) {}
}
