<?php namespace ProcessWire;
class RockFinderFieldPage extends RockFinderField {
  public $separator = ',';
  
  /**
   * get sql
   */
  public function getSql() {
    $sql = "SELECT";
    $sql .= "\n  `$this->alias`.`pages_id` AS `pageid`";
    $sql .= ",\n  GROUP_CONCAT(`$this->alias`.`data` ORDER BY `$this->alias`.`sort` SEPARATOR '$this->separator') AS `$this->alias`";
    foreach($this->columns as $column) {
      if($column == 'data') continue;
      $sql .= ",\n  GROUP_CONCAT({$this->dataColumn($column)} ORDER BY `$this->alias`.`sort` SEPARATOR '$this->separator') AS `$column`";
    }
    $sql .= "\nFROM `field_{$this->name}` AS `$this->alias`";

    // join all fields
    foreach($this->columns as $i=>$column) {
      if($i==0) continue; // skip data column
      else {
        // join all following fields based on the pages_id column
        $sql .= "\nLEFT JOIN `field_$column` AS `$column` ON `$column`.`pages_id` = `$this->alias`.`data`";
      }
    }
    $sql .= "\nGROUP BY `$this->alias`.`pages_id`";
    return $sql;
  }
  
  /**
   * select all columns of this fieldtype
   */
  public function getJoinSelect() {
    $sql = '';
    foreach($this->columns as $i=>$column) {
      if($i==0)
        $sql .= ",\n  `$this->alias`.`{$this->fieldAlias($column)}` AS `{$this->fieldAlias($column)}`";
      else
        $sql .= ",\n  `$this->alias`.`$column` AS `{$this->fieldAlias($column)}`";
    }
    return $sql;
  }
}