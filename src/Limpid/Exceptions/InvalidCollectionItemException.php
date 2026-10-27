<?php

namespace Potager\Limpid\Exceptions;

use InvalidArgumentException;

class InvalidCollectionItemException extends InvalidArgumentException
{
    public function __construct(string $collection, int|string $key, string $type)
    {
        parent::__construct("{$collection} can only contain Model instances, {$type} given for key \"{$key}\".");
    }
}
