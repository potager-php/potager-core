<?php

namespace Potager\Limpid\Attributes\Relation;

use Attribute;
use Potager\Limpid\Contracts\LimpidAttribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
abstract class Relation implements LimpidAttribute
{
    public const MANY = false;

    public function __construct(
        public string $target
    ) {}
}
