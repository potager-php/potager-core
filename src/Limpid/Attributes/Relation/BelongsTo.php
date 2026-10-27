<?php

namespace Potager\Limpid\Attributes\Relation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class BelongsTo extends Relation
{
    public function __construct(
        string $target,
        public ?string $foreignKey = null,
        public ?string $ownerKey = null
    ) {
        return parent::__construct($target);
    }
}
