<?php

class Entities implements Iterator, JsonSerializable, ArrayAccess, Countable
{
  protected string $entity_name;
  protected array $array;
  protected int $index = 0;

  public function __construct(array $entities_array, string $entity_name)
  {
    $this->array = $entities_array;
    $this->entity_name = $entity_name;
  }

  protected function createEntitiy(array $info)
  {
    $entity = new $this->entity_name($info);
    $entity->load($info);
    return $entity;
  }

  // Iterator
  public function current(): Object
  {
    if (is_array($this->array[$this->key()]))
      $this->array[$this->key()] = $this->createEntitiy($this->array[$this->key()]);

    return $this->array[$this->key()];
  }

  public function next(): void
  {
    $this->index++;
  }

  public function key(): int
  {
    return $this->index;
  }

  public function valid(): bool
  {
    return isset($this->array[$this->index]);
  }

  public function rewind(): void
  {
    $this->index = 0;
  }

  public function getKeys(): array
  {
    return array_keys($this->array);
  }

  // JSON
  public function jsonSerialize(): mixed
  {
    return $this->array;
  }

  // ArrayAccess
  public function offsetExists($offset): bool
  {
    return isset($this->array[$offset]);
  }

  public function offsetGet($offset): mixed
  {
    if (is_array($this->array[$offset]))
      $this->array[$offset] = $this->createEntitiy($this->array[$offset]);

    return $this->array[$offset];
  }

  public function offsetSet($offset, $value): void
  {
    $this->array[$offset] = $value;
  }

  public function offsetUnset($offset): void
  {
    unset($this->array[$offset]);
  }

  // Countable Interface
  public function count(): int
  {
    return count($this->array);
  }

  // More
  public function length(): int
  {
    return count($this->array);
  }

  public function toArray(): array
  {
    return $this->array;
  }

  public function push($to_be_pushed): void
  {
    if (is_array($to_be_pushed))
      array_push($this->array, ...$to_be_pushed);
    else
      array_push($this->array, $to_be_pushed);
  }

  public function reverse(): void
  {
    $this->array = array_reverse($this->array);
  }
}