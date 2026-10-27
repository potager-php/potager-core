<?php

namespace Potager\Limpid;

use Potager\Limpid\Exceptions\InvalidCollectionItemException;
use Potager\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public function __construct(array $items = [])
    {
        foreach ($items as $key => $item) {
            static::assertModel($item, $key);
        }
        return parent::__construct($items);
    }

    public function offsetSet(mixed $o, mixed $v): void
    {
        static::assertModel($v, $o ?? '[]');
        parent::offsetSet($o, $v);
    }

    protected static function assertModel(mixed $item, int|string $key): void
    {
        if (!$item instanceof Model) {
            throw new InvalidCollectionItemException(static::class, $key, get_debug_type($item));
        }
    }

    public function modelKeys(): array
    {
        return array_map(fn(Model $model) => $model->getPrimaryKeyValue(), array_values($this->items));
    }

    public function find(int|string $id): ?Model
    {
        return $this->first(fn(Model $model) => $model->getPrimaryKeyValue() == $id);
    }
}
