<?php

namespace App\Events;

class FinancialNotificationRequested
{
    public function __construct(public readonly string $entity, public readonly int $id, public readonly string $state) {}
}
