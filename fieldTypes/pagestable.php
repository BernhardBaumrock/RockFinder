<?php namespace ProcessWire;
class RockFinderFieldPagestable extends RockFinderField {  
  /**
   * get sql
   */
  public function getSql() {
    return "SELECT ".
      "`id` AS `pageid`, ". // we need the column "pageid" to do the join
      "`$this->name` ".
      "FROM `pages` AS `$this->alias`";
  }
}