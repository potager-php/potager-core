<?php

namespace Potager\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * A general-purpose wrapper around a PHP array.
 *
 * Holds any kind of item (scalars, arrays, objects, models) and exposes a fluent,
 * chainable API to transform, filter and index them. Every transforming method
 * returns a new instance: the original collection is never modified, with the
 * exception of the ArrayAccess setters (offsetSet / offsetUnset).
 *
 * Return type convention:
 *  - Methods that keep the same kind of items (filter, values, unique, keyBy)
 *    return `static`, so a subclass (e.g. Potager\Limpid\Collection) keeps its
 *    specialised API.
 *  - Methods that may change the nature of the items (map, pluck, groupBy)
 *    return a plain Support\Collection, because the result might no longer
 *    satisfy the subclass's expectations.
 *
 * Keys passed to pluck(), pluckUnique(), keyBy() and groupBy() may be:
 *  - a key or property name: 'email'
 *  - a dot-notation path:    'author.email'
 *  - a callable:             fn($item) => $item->email
 * A string is always treated as a path, never as a callable ('trim' means "the key trim").
 *
 * @template TKey of array-key
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 * @implements ArrayAccess<TKey, TValue>
 */
class Collection implements IteratorAggregate, Countable, ArrayAccess, JsonSerializable
{
    /**
     * @param array<TKey, TValue> $items The items held by the collection.
     */
    public function __construct(protected array $items = []) {}

    /**
     * Create a new collection from any iterable (array, generator, Traversable).
     *
     * Note: keys are preserved when converting a Traversable, so a generator
     * yielding duplicate keys will keep only the last value for each key.
     *
     * @param iterable<TKey, TValue> $items
     * @return static
     */
    public static function make(iterable $items = []): static
    {
        return new static(is_array($items) ? $items : iterator_to_array($items));
    }

    /**
     * Get all items as a native array, keys included.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Determine whether the collection has no items.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Count the items in the collection (Countable).
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get an iterator over the items, allowing `foreach ($collection as $key => $item)` (IteratorAggregate).
     *
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Get the first item, or the first item passing the given truth test.
     *
     * @param (callable(TValue, TKey): bool)|null $fn Optional predicate, receives the item and its key.
     * @return TValue|null The matching item, or null if none (or if the collection is empty).
     */
    public function first(?callable $fn = null): mixed
    {
        foreach ($this->items as $key => $value) {
            if ($fn === null || $fn($value, $key)) return $value;
        }
        return null;
    }

    /**
     * Apply a callback to every item and return the results, keys preserved.
     *
     * Always returns a plain Support\Collection (never `static`): the mapped values
     * may no longer be the kind of items a subclass expects (e.g. models mapped to emails).
     *
     * @template TMapValue
     * @param (callable(TValue, TKey): TMapValue)|null $fn Receives the item and its key.
     * @return Collection<TKey, TMapValue>
     */
    public function map(?callable $fn = null)
    {
        $keys = array_keys($this->items);
        return new Collection(array_combine($keys, array_map($fn, $this->items, $keys)));
    }

    /**
     * Keep only the items passing the given truth test, keys preserved.
     *
     * Without a callback, falsy items (null, false, 0, '', '0', []) are removed.
     * Keys are preserved like array_filter(): call values() to reindex from 0.
     *
     * @param (callable(TValue, TKey): bool)|null $fn Receives the item and its key.
     * @return static
     */
    public function filter(?callable $fn = null)
    {
        return new static(array_filter($this->items, $fn ?? fn($v) => (bool) $v, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Reset the keys to consecutive integers starting from 0.
     *
     * @return static
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Remove duplicate items, keeping the first occurrence and its key.
     *
     * Uses loose comparison (SORT_REGULAR): "1" and 1 are considered equal,
     * and objects are compared by their properties, not by identity.
     *
     * @return static
     */
    public function unique(): static
    {
        return new static(array_unique($this->items, SORT_REGULAR));
    }

    /**
     * Reduce the collection to a single value.
     *
     * @template TCarry
     * @param callable(TCarry, TValue): TCarry $fn Receives the carried value and the current item.
     * @param TCarry $initial The initial carried value.
     * @return TCarry
     */
    public function reduce(callable $fn, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $fn, $initial);
    }

    /**
     * Extract a single value from every item, reindexed from 0.
     *
     * Missing values are returned as null.
     *
     * @example $users->pluck('email')          // ['a@x.com', 'b@x.com']
     * @example $posts->pluck('author.email')   // dot-notation path
     * @example $users->pluck(fn($u) => strtoupper($u->name))
     *
     * @param string|callable(TValue): mixed $value A key, a dot-notation path or a callable.
     * @return Collection<int, mixed>
     */
    public function pluck(string|callable $value): Collection
    {
        return $this->map(fn($item) => static::valueOf($item, $value))->values();
    }

    /**
     * Extract the distinct, non-null values of a key as a native array.
     *
     * Mainly used to build `WHERE ... IN (...)` clauses for eager loading.
     *
     * @param string|callable(TValue): mixed $value A key, a dot-notation path or a callable.
     * @return list<mixed>
     */
    public function pluckUnique(string|callable $value): array
    {
        return $this->pluck($value)->filter(fn($v) => $v !== null)->unique()->values()->all();
    }

    /**
     * Index the items by the given key.
     *
     * When several items share the same key, the last one wins.
     * Keys must resolve to a scalar (int or string): PHP casts them as array keys,
     * so "1" and 1 end up under the same key.
     *
     * @param string|callable(TValue): array-key $key A key, a dot-notation path or a callable.
     * @return static
     */
    public function keyBy(string|callable $key): static
    {
        $out = [];
        foreach ($this->items as $item) $out[static::valueOf($item, $key)] = $item;
        return new static($out);
    }

    /**
     * Group the items by the given key.
     *
     * Each group is itself a collection of the same type as the current one
     * (e.g. grouping a Limpid\Collection gives Limpid\Collection groups).
     * The same scalar key rules as keyBy() apply.
     *
     * @param string|callable(TValue): array-key $key A key, a dot-notation path or a callable.
     * @return Collection<array-key, static>
     */
    public function groupBy(string|callable $key): Collection
    {
        $groups = [];
        foreach ($this->items as $item) $groups[static::valueOf($item, $key)][] = $item;
        return new Collection(array_map(fn(array $group) => new static($group), $groups));
    }

    /**
     * Resolve a value from an item using a key, a dot-notation path or a callable.
     *
     * Each path segment is read from arrays and ArrayAccess with `[]`, and from objects
     * with `->` (magic properties such as model attributes included). Resolution stops
     * with null as soon as a segment is missing or the current value is a scalar.
     *
     * @param mixed $item The item to read from.
     * @param string|callable(mixed): mixed $key A path ('a.b.c') or a callable receiving the item.
     * @return mixed The resolved value, or null if not found.
     */
    protected static function valueOf(mixed $item, string|callable $key): mixed
    {
        if (!is_string($key)) return $key($item);

        foreach (explode('.', $key) as $segment) {
            $item = match (true) {
                is_array($item), $item instanceof ArrayAccess => $item[$segment] ?? null,
                is_object($item) => $item->{$segment} ?? null,
                default => null,
            };
        }
        return $item;
    }

    /**
     * Get the items as a native array.
     *
     * Items are returned as-is: nested objects and collections are not converted.
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Get the data to serialize with json_encode() (JsonSerializable).
     *
     * Nested JsonSerializable items are serialized by json_encode() itself.
     *
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * Determine whether an item exists at the given key: `isset($collection[$key])` (ArrayAccess).
     *
     * Like isset(), returns false when the key exists but holds null.
     *
     * @param TKey $o
     * @return bool
     */
    public function offsetExists(mixed $o): bool
    {
        return isset($this->items[$o]);
    }

    /**
     * Get the item at the given key: `$collection[$key]` (ArrayAccess).
     *
     * Emits a warning and returns null if the key does not exist; use
     * `$collection[$key] ?? $default` when the key may be missing.
     *
     * @param TKey $o
     * @return TValue
     */
    public function offsetGet(mixed $o): mixed
    {
        return $this->items[$o];
    }

    /**
     * Set an item: `$collection[$key] = $value`, or append with `$collection[] = $value` (ArrayAccess).
     *
     * Unlike the other methods, this mutates the current collection.
     *
     * @param TKey|null $o The key, or null to append.
     * @param TValue $v
     * @return void
     */
    public function offsetSet(mixed $o, mixed $v): void
    {
        $o === null ? $this->items[] = $v : $this->items[$o] = $v;
    }

    /**
     * Remove the item at the given key: `unset($collection[$key])` (ArrayAccess).
     *
     * Unlike the other methods, this mutates the current collection.
     *
     * @param TKey $o
     * @return void
     */
    public function offsetUnset(mixed $o): void
    {
        unset($this->items[$o]);
    }
}
