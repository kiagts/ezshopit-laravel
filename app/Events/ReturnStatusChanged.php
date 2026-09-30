<?php

namespace App\Events;

class ReturnStatusChanged
{
    public function __construct(public readonly int $returnId, public readonly ?string $from, public readonly string $to, public readonly int $actorId, public readonly ?int $version = null) {}
}

