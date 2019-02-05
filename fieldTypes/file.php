<?php namespace ProcessWire;
class RockFinderFieldFile extends RockFinderField {
  public $separator = ',';
  
  /**
   * get sql
   */
  public function getSql() {
    $sql = "SELECT";
    $sql .= "\n  `pages_id` AS `pageid`";
    foreach($this->columns as $column) {
      $sql .= ",\n  GROUP_CONCAT(`$column` ORDER BY `sort` SEPARATOR '$this->separator') AS `{$this->fieldAlias($column)}`";
    }
    $sql .= "\nFROM `field_".strtolower($this->name)."` AS `$this->alias`";
    $sql .= "\nGROUP BY `pageid`";
    return $sql;
  }
}