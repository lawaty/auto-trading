<?php

class CustomMapper
{
  private array $entities;
  private array $mappers;
  private array $on;
  private array $tables;
  private static array $joins;

  private static function getJoins(): array
  {
    if (!isset(self::$joins))
      self::$joins = json_decode(file_get_contents(APP_DIR . '/model/database/joins.json'), true);

    return self::$joins;
  }

  /**
   * @param string $start_mapper: classname of the starting mapper
   */
  public function __construct(string $start_mapper)
  {
    $this->mappers = [$start_mapper];
    $this->tables = [$start_mapper::$table];
  }

  /**
   * @param string $mapper: mapper classname
   * @param ?string $which: which mapper do you need to connect with.
   */
  private function naiveJoin(string $mapper, string $which)
  {
    $table1 = $mapper::$table;
    $joins = self::getJoins();

    if (in_array($which, $this->mappers)) {
      $table2 = $which::$table;
      if (isset($joins[$table1][$table2])) {
        $on_clause = $joins[$table1][$table2];
      } else if ($joins[$table2][$table1]) {
        $on_clause = $joins[$table2][$table1];
      } else throw new JoinImpossible($table1, [$table2]);

      $this->mappers[] = $mapper;
      $this->tables[] = $table1;
      $this->on[] = $on_clause;
      
      return;
    }
    else throw new JoinImpossible($table1, $which::$table);
  }

  private function getTable(): string
  {
    $table = implode(' INNER JOIN ', $this->tables);
    $table .= " ON " . implode(' AND ', $this->on);
    return $table;
  }

  /**
   * @param string $mapper: mapper classname
   * @param ?string $which: which mapper do you need to connect with. If not specified, the first matching connecting table will be used
   */
  public function join(string $mapper, string $which = null): void
  {
    if ($which)
      self::naiveJoin($mapper, $which);

    $connected = false;
    foreach ($this->mappers as $temp) {
      try {
        self::naiveJoin($mapper, $temp);
        $connected = true;
        break;
      } catch (JoinImpossible $e) {
        continue;
      }
    }

    if (!$connected)
      throw new JoinImpossible($mapper::$table, $this->tables);
  }

  /**
   * Method loads record info into a entity instance
   * @param array|int $filters: db search filters
   * @return ?Entity: domain object instance if found
   * this method returns the first found object if many records matched
   */
  public function get($filters = []): ?CustomEntity
  {
    $filters = $this->validateFilters($filters);

    /**
     * @var CustomEntity
     */

    if ($items = DB::getDB()->select('*', $this->getTable(), $filters)) {
      $entity = new CustomEntity(...$this->mappers);
      $entity->load($items[0]);
      return $entity;
    }
    return null;
  }

  /**
   * Method loads records' info into entity instances
   * @param array $filters: db search filters
   * @return array: matched records
   */
  public function getAll(array $filters = []): array
  {
    $filters = $this->validateFilters($filters);

    return DB::getDB()->select('*', $this->getTable(), $filters);
  }

  public function validateFilters(array &$filters)
  {
    $result = [];
    foreach ($this->mappers as $mapper)   {
      $temp = $filters;
      $mapper::validateFilters($temp, true);
      $result = array_merge($result, $temp);
    }

    return array_unique($result);
  }
}
