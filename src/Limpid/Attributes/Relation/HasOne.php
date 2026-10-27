<?php

namespace Potager\Limpid\Attributes\Relation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class HasOne extends Relation
{
    public function __construct(
        string $target,
        public ?string $foreignKey = null,
        public ?string $localKey = null
    ) {
        return parent::__construct($target);
    }
}
