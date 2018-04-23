<?php namespace ProcessWire;
abstract class RockFinderField extends WireData {
  public $name;
  protected $columns;
  public $type;
  public $alias;
  public $siblingseparator = ':';
  
  // todo: change parameters to one options array
  public function __construct($name, $columns, $type) {
    $this->name = $this->alias = $name;
    $this->columns = array_merge(['data'], $columns ?: []);
    $this->type = $type;
  }

  /**
   * get sql
   */
  public function getSql() {
    $sql = "SELECT";
    $sql .= "\n  `pages_id` AS `pageid`";
    foreach($this->columns as $column) {
      $sql .= ",\n  `$this->alias`.`$column` AS `{$this->fieldAlias($column)}`";
    }
    $sql .= "\nFROM `field_{$this->name}` AS `$this->alias`";
    return $sql;
  }

  /**
   * get array of objects
   */
  public function getObjects($limit = 0) {
    $limit = $limit ? " LIMIT $limit" : '';
    $results = $this->database->query($this->getSQL() . $limit);
    return $results->fetchAll(\PDO::FETCH_OBJ);
  }

  /**
   * return the field alias for given column
   */
  protected function fieldAlias($column) {
    // bug: custom fieldnames do not work like this
    $alias = $column == 'data'
      ? "{$this->name}"
      : "{$this->name}{$this->siblingseparator}$column"
      ;
    return $alias;
  }

  /**
   * select all columns of this fieldtype
   */
  public function getJoinSelect() {
    $sql = '';
    foreach($this->columns as $column) {
      $sql .= ",\n  `$this->alias`.`{$this->fieldAlias($column)}` AS `{$this->fieldAlias($column)}`";
    }
    return $sql;
  }

  /**
   * get sql for joining this field's table
   */
  public function getJoin() {
    return "\n\n/* --- join $this->alias --- */\n".
      "LEFT JOIN (" . $this->getSql() . ") AS `$this->alias` ".
      "ON `$this->alias`.`pageid` = `pages`.`id`".
      "\n/* --- end $this->alias --- */\n";
  }

  /**
   * debugInfo PHP 5.6+ magic method
   *
   * This is used when you print_r() an object instance.
   *
   * @return array
   *
   */
  public function __debugInfo() {
    $info = parent::__debugInfo();
    $info['name'] = $this->name;
    $info['columns'] = $this->columns;
    $info['type'] = $this->type;
    $info['alias'] = $this->alias;
    $info['siblingseparator'] = $this->siblingseparator;
    return $info; 
  }
}