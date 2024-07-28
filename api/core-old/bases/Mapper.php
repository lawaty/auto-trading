<?php

/**
 * @property string $table
 * @property array $required
 * @property array $optional
 * @property string $entity_type
 */
abstract class Mapper
{
  public static string $entity_type;
  protected Entity $entity;
  public static string $table = "";
  public static array $required = [];
  public static array $optional = [];

  protected static function preProcessStatic()
  {
    static::$entity_type = explode('Mapper', static::class)[0];
  }

  public static function getTableName(): string
  {
    self::preProcessStatic();

    return static::$table;
  }

  public static function getEntityType(): string
  {
    self::preProcessStatic();

    return static::$entity_type;
  }

  public static function getRecordInfo(): array
  {
    /**
     * Method loads required record info from the model instance
     * @return array: required associative record info
     */
    return array_merge(['id'], static::$required, static::$optional);
  }

  /**
   * Function makes the data ready for insertion
   */
  public static function sanitize(array $data): array
  {
    self::preProcessStatic();

    $healthy = [];
    foreach (static::$required as $property) {
      if (!isset($data[$property]))
        throw new RequiredPropertyNotFound($property, static::$entity_type);

      if (is_array($data[$property]))
        $healthy[$property] = json_encode($data[$property]);
      else
        $healthy[$property] = $data[$property];
    }

    foreach (static::$optional as $property) {
      if (!isset($data[$property]) || $data[$property] == null)
        continue;

      if (is_array($data[$property]))
        $healthy[$property] = json_encode($data[$property]);
      else
        $healthy[$property] = $data[$property];
    }

    return $healthy;
  }

  public function __construct(Entity $entity)
  {
    $this->entity = $entity;
    static::$entity_type = explode('Mapper', static::class)[0];
  }

  /**
   * Method creates new record and returns a complete entity instance
   * @param array $entity_data
   * @return Entity: domain object instance
   */
  public static function create(array $entity_data): ?Entity
  {
    self::preProcessStatic();

    foreach ($entity_data as $i => $property) // for json and array
      $entity_data[$i] = is_array($property) ? json_encode($property) : $property;


    $record = static::sanitize($entity_data);

    $id = DB::getDB()->insert(static::$table, $record);
    if ($id) {
      $entity = new static::$entity_type($id);
      $entity->load($entity_data);
      $entity->setSync(true);
      $entity->resetChanges();
      return $entity;
    }

    return null;
  }


  /**
   * Method updates instance record if exists and creates it otherwise
   * @return bool: flag for success
   */
  public function save(): bool
  {
    $db = DB::getDB();

    if ($this->entity->inSync())
      return true;

    if ($result = $db->update(static::$table, $this->entity->getChanges(), $this->entity->getID())) {
      $this->entity->setSync(true);
      $this->entity->resetChanges();
    }

    return (bool) $result;
  }

  /**
   * Method loads record info into a entity instance
   * @param array|int $filters: db search filters
   * @return ?Entity: domain object instance if found
   * this method returns the first found object if many records matched
   */
  public static function get($filters = []): ?Entity
  {
    if (is_numeric($filters))
      $filters = ['id' => $filters];
    else if (!is_array($filters))
      throw new InvalidArguments("Filters must be either numeric or array");

    self::preProcessStatic();
    static::validateFilters($filters);

    /**
     * @var Entity
     */
    if ($items = DB::getDB()->select('*', static::$table, $filters)) {
      $entity = new static::$entity_type($items[0]['id']);
      $entity->load($items[0]);
      $entity->setSync(true);
      return $entity;
    }
    return null;
  }

  /**
   * Method loads records' info into entity instances
   * @param array $filters: db search filters
   * @return Entities: matched records
   */
  public static function  getAll(array $filters = []): Entities
  {
    self::preProcessStatic();
    static::validateFilters($filters);

    return new Entities(
      DB::getDB()->select('*', static::$table, $filters),
      static::$entity_type
    );
  }

  public static function validateFilters(array &$filters, bool $neglect = false)
  {
    self::preProcessStatic();

    foreach ($filters as $key => $value)
      if (!$neglect && !in_array($key, static::$required) && !in_array($key, static::$optional) && !is_numeric($key) && $key != 'id')
        throw new InvalidArguments("Invalid Filter Key $key for entity of type " . static::$entity_type);
  }

  /**
   * Methods deletes the record corresponding to the current entity instance
   * @return bool: success flag
   */
  public function delete(): bool
  {
    $result = DB::getDB()->delete(static::$table, $this->entity->getID());
    if ($result)
      unset($this->entity);

    return (bool) $result;
  }
}
