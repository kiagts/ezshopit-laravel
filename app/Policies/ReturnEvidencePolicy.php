<?php

namespace App\Policies;

use App\Models\ReturnEvidence;
use App\Models\User;

class ReturnEvidencePolicy
{
    public function view(User $user, ReturnEvidence $evidence): bool
    {
        return (new ReturnRequestPolicy)->view($user, $evidence->returnRequest);
    }
}
