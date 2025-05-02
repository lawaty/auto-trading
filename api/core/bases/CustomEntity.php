<?php

class CustomEntity implements JsonSerializable, ArrayAccess
{
  protected array $entities;
  protected array $foreigns;
  protected array $data;
  protected CustomMapper $mapper;

  /**
   * Initialize Custom Entity Fields
   */
  public function __construct(...$mappers)
  {
    if (empty($mappers))
      throw new InvalidArguments("At least one mapper is needed for an object of type CustomEntity");

    $this->data = [];
    $this->foreigns = [];
    $this->entities = [];

    foreach ($mappers as $mapper) {
      if (!isset($this->mapper))
        $this->mapper = new CustomMapper($mapper);
      else
        $this->mapper->join($mapper);

      foreach (array_merge($mapper::$required, $mapper::$optional) as $info)
        if (!in_array($info, $mapper::$entity_type::$protected))
          $this->data[$info] = '';

      foreach ($mapper::$entity_type::$foreigns as $foreign => $entity)
        $this->foreigns[$foreign] = $entity;

      $this->entities[] = $mapper::$entity_type;
    }
  }

  /**
   * Load data into the entity.
   *
   * @param array $data The data to be loaded.
   */
  public function load(array $data): void
  {
    foreach ($data as $property => $value) {
      if (isset($this->data[$property]))
        $this->set($property, $value);
    }
  }

  /**
   * Setter for entity properties.
   *
   * @param string $property The property to set.
   * @param mixed $value The value to set for the property.
   * @throws PropertyNotExisting if the property is not found.
   */
  public function set(string $property, $value): void
  {
    if (!isset($this->data[$property]))
      throw new PropertyNotExisting($property, implode(', ', $this->entities));

    if (is_float($value))
      $value = floatval($value);

    else if (is_numeric($value) && isset($value[0]) && $value[0] != '0')
      $value = intval($value);

    else if (isJson($value))
      $value = json_decode($value, true);

    // Foreign Linking
    if (isset($this->foreigns[$property]) && is_numeric($value))
      $value = new $this->foreigns[$property]($value);

    $this->data[$property] = $value;
  }

  /**
   * Retrieve all possible data
   */
  public function getData(): array
  {
    return $this->data;
  }

  /**
   * Naive getter for entity properties.
   *
   * @param string $property The property to retrieve.
   * @return mixed|null The property value if found, null otherwise.
   * @throws PropertyNotExisting if the property is not found.
   */
  public function naiveGet(string $property): mixed
  {
    if (!isset($this->data[$property]))
      throw new PropertyNotExisting($property, implode(', ', $this->entities));

    return $this->data[$property];
  }

  /**
   * Recommended getter for entity properties.
   *
   * @param string ...$properties The properties to retrieve.
   * @return mixed The property value(s) if found, null otherwise.
   */
  public function get(...$properties)
  {
    $result = [];

    foreach ($properties as $property)
      $result[$property] = $this->naiveGet($property);

    if (count($result) == 1)
      return array_values($result)[0];
    else if (count($result) == 0)
      return null;

    return $result;
  }

  /**
   * Get the mapper instance for the entity.
   *
   * @return CustomMapper The mapper instance.
   */
  public function getMapper(): CustomMapper
  {
    return $this->mapper;
  }

  // Overriding Comparison Operators
  public function __equals(CustomEntity $obj): bool
  {
    foreach ($this->getData() as $key => $value) {
      if ($value != $obj->get($key))
        return false;
    }

    return true;
  }

  public function __notEquals(CustomEntity $obj): bool
  {
    return !$this->__equals($obj);
  }

  // JsonSerialize
  public function jsonSerialize(): mixed
  {
    return $this->getData();
  }


  // ArrayAccess
  public function offsetExists(mixed $offset): bool
  {
    return isset($this->data[$offset]);
  }

  public function offsetGet($offset): mixed
  {
    return $this->get($offset);
  }

  public function offsetSet($offset, $value): void
  {
    $this->set($offset, $value);
  }

  public function offsetUnset($offset): void
  {
    $this->set($offset, null);
  }
}
