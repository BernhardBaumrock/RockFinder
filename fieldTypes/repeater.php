<?php namespace ProcessWire;
class RockFinderFieldRepeater extends RockFinderField {
  public $separator = ',';
  
  /**
   * get sql
   */
  public function getSql() {
    $sql = "SELECT";
    $sql .= "\n  `$this->alias`.`pages_id` AS `pageid`";
    $sql .= ",\n  `$this->alias`.`data` AS `$this->name`";
    foreach($this->columns as $column) {
      if($column == 'data') continue;
      // todo: data is not multilanguage
      $sql .= ",\n  GROUP_CONCAT(`$column`.`data` ORDER BY FIND_IN_SET(`$column`.`pages_id`, `$this->alias`.`data`) separator '$this->separator') AS `$column`";
    }
    $sql .= "\nFROM `field_{$this->name}` AS `$this->alias`";

    // join all fields
    $ref = '';
    foreach($this->columns as $i=>$column) {
      if($i==0) continue; // skip data column
      if($i==1) {
        // the first join needs to be done via find_in_set
        // because the data column holds a comma-separated list of page ids
        // with all the ids of the repeater pages
        $sql .= "\nLEFT JOIN `field_$column` AS `$column` ON FIND_IN_SET(`$column`.`pages_id`, `$this->alias`.`data`)";
        $ref = $column;
      }
      else {
        // join all following fields based on the pages_id column
        $sql .= "\nLEFT JOIN `field_$column` AS `$column` ON `$column`.`pages_id` = `$ref`.`pages_id`";
      }
    }
    $sql .= "\nGROUP BY `$this->alias`.`pages_id`";
    return $sql;
  }

  /**
   * add all fields of this repeater
   */
  public function addAllFields() {
    $field = $this->wire->fields->get($this->name);
    foreach($field->repeaterFields as $id) {
      $this->columns[] = $this->wire->fields->get($id)->name;
    }
  }
  
  /**
   * add all provided fields
   */
  public function addFields($fields) {
    foreach($fields as $field) {
      $this->columns[] = $field;
    }
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
