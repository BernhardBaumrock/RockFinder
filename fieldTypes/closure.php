<?php namespace ProcessWire;
class RockFinderFieldClosure extends RockFinderField {
  
  /**
   * select all columns of this fieldtype
   */
  public function getJoinSelect() {
    return ",\n  '' AS `{$this->alias}`";
  }

  /**
   * get sql for joining this field's table
   */
  public function getJoin() {
    // don't join anything
    return;
  }
}