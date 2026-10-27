<?php

namespace Potager\Limpid\Attributes\Relation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ManyToMany extends Relation
{
    public function __construct(
        string $target,
        public ?string $pivotTable = null,
        public ?string $pivotForeignKey = null,
        public ?string $pivotRelatedKey = null,
        public ?string $localKey = null,
        public ?string $relatedKey = null,
    ) {
        return parent::__construct($target);
    }
}
