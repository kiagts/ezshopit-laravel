<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class CommerceDocumentBuilder extends Builder
{
    public function update(array $values)
    {
        throw new \LogicException('Issued documents are immutable.');
    }

    public function delete()
    {
        throw new \LogicException('Issued documents cannot be deleted.');
    }

    public function forceDelete()
    {
        throw new \LogicException('Issued documents cannot be deleted.');
    }
}
