<?php

namespace App\Events;

class DisputeActivity
{
    public function __construct(public readonly int $disputeId, public readonly string $type, public readonly int $actorId, public readonly ?int $version = null, public readonly ?string $state = null) {}
}

