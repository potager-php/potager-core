<?php

namespace Potager\Limpid\Attributes\Relation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class HasMany extends Relation
{
    public const MANY = true;

    public function __construct(
        string $target,
        public ?string $foreignKey = null,
        public ?string $localKey = null
    ) {
        return parent::__construct($target);
    }
}
