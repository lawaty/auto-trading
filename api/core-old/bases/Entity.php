<?php

/**
 * Entity Class
 * 
 * Represents an entity in the system.
 */

abstract class Entity implements JsonSerializable, ArrayAccess
{
  protected ?Mapper $mapper = null; // mapper instance
  protected bool $synced = false; // db synchronization flag
  protected array $ids = ['id' => null]; // entity IDs
  protected array $changes = []; // changes
  public static array $foreigns = []; // array of foreign keys mappings
  public static array $protected = []; // array of elements that are fetch-protected

  /**
   * Copy properties from another entity.
   *
   * @param Entity $entity The entity to copy from.
   * @throws IncompatibleEntities if the entity classes are incompatible.
   */
  public function copy(Entity $entity): void
  {
    // Check if entity classes are compatible
    if (get_class($entity) != static::class)
      throw new IncompatibleEntities(static::class, get_class($entity));

    // Copy each field from the source entity to the current entity
    $fields = get_object_vars($entity);
    foreach ($fields as $field => $value) {
      if (in_array($field, ['mapper', 'synced', 'ids', 'changes']))
        continue;

      $this->{$field} = $entity->naiveGet($field);
    }
  }

  /**
   * Entity constructor.
   *
   * @param mixed $id The entity ID.
   */
  public function __construct($id)
  {
    $this->setID($id);
  }

  /**
   * Load data into the entity.
   *
   * @param array $data The data to be loaded.
   */
  public function load(array $data): void
  {
    foreach ($data as $property => $value) {
      if (property_exists($this, $property))
        $this->set($property, $value);
    }
  }

  /**
   * Get the entity ID.
   *
   * @return array The entity ID formatted as an associative array.
   */
  public function getID(): array
  {
    return $this->ids;
  }

  /**
   * Set the entity ID.
   *
   * @param mixed $id The entity ID.
   * @throws RequiredPropertyNotFound if a required property is not found in the ID array.
   * @throws InvalidID if the ID value is invalid.
   */
  public function setID($id): void
  {
    if (!is_array($id)) {
      $this->ids['id'] = $id;
      return;
    }

    foreach ($this->ids as $key => $value) {
      if (!key_exists($key, $id))
        throw new RequiredPropertyNotFound($key, static::class);

      if ($id[$key] <= 0)
        throw new InvalidID(static::class, $key);

      $this->ids[$key] = intval($id[$key]);
    }
  }

  // Setters and Getters

  /**
   * Naive getter for entity properties.
   *
   * @param string $property The property to retrieve.
   * @return mixed|null The property value if found, null otherwise.
   * @throws PropertyNotExisting if the property is not found.
   */
  public function naiveGet(string $property): mixed
  {
    if (!property_exists($this, $property) && !isset($this->ids[$property]))
      throw new PropertyNotExisting($property, static::class);

    if (isset($this->ids[$property]))
      return $this->ids[$property];

    if (!isset($this->{$property}))
      return null;

    return $this->{$property};
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

    foreach ($properties as $property) {
      if (in_array($property, $this::$protected))
        continue;

      if ($this->naiveGet($property) === null && !$this->inSync()) {
        if (!$entity = $this->getMapper()->get($this->getID()))
          echo "Couldn't find entity in db";

        $this->copy($entity);
        $this->setSync(true);
      }

      $result[$property] = $this->naiveGet($property);
    }

    if (count($result) == 1)
      return array_values($result)[0];
    else if (count($result) == 0)
      return null;

    return $result;
  }

  public function getData(): array
  {
    return $this->get(...array_keys($this->ids), ...$this->getMapper()::$required, ...$this->getMapper()::$optional);
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
    if (!property_exists($this, $property))
      throw new PropertyNotExisting($property, static::class);

    if (is_float($value))
      $value = floatval($value);

    else if (is_numeric($value) && isset($value[0]) && $value[0] != '0')
      $value = intval($value);

    else if (isJson($value))
      $value = json_decode($value, true);

    // Foreign Linking
    if (isset($this::$foreigns[$property]) && is_numeric($value))
      $value = new $this::$foreigns[$property]($value);

    // Value has changed
    if (!isset($this->{$property}) || $this->{$property} != $value) {
      $this->{$property} = $value;

      // If persistence layer is concerned with the changed value
      if (in_array($property, $this->getMapper()::$required) || in_array($property, $this->getMapper()::$optional)) {
        $this->setSync(false);
        $this->changes[$property] = $value instanceof Entity ? $value->get('id') : $value;
      }
    }
  }

  public function getChanges(): array
  {
    return $this->changes;
  }

  public function resetChanges(): void
  {
    $this->changes = [];
  }

  /**
   * Get the mapper instance for the entity.
   *
   * @return Mapper The mapper instance.
   */
  public function getMapper(): Mapper
  {
    $mapper_type = static::class . "Mapper";
    if (!$this->mapper)
      $this->mapper = new $mapper_type($this);

    return $this->mapper;
  }

  /**
   * Check if the entity is in sync with the persistence layer.
   *
   * @return bool True if the entity is in sync, false otherwise.
   */
  public function inSync(): bool
  {
    return $this->synced;
  }

  /**
   * Set the sync state of the entity.
   *
   * @param bool $state The sync state to be set.
   */
  public function setSync(bool $state): void
  {
    $this->synced = $state;
  }

  // Mapper methods
  public function delete(): bool
  {
    return $this->getMapper()->delete();
  }

  public function save(): bool
  {
    return $this->getMapper()->save();
  }

  // Overriding Comparison Operators
  public function __equals(Entity $obj): bool
  {
    if (get_class($this) != get_class($obj))
      return false;

    foreach ($this->getData() as $key => $value) {
      if ($value != $obj->get($key))
        return false;
    }

    return true;
  }

  public function __notEquals(Entity $obj): bool
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
    return property_exists($this, $offset) && !isset($this->ids[$offset]);
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
