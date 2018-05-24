<?php namespace ProcessWire;


    /**
     * bug: this does not work for custom fieldnames but makes joined pages with custom alias work
     * $finder = new RockFinder("template=person, isFN=1, has_parent=7888", [
     *     'lang' => 'bla', // does not work
     *     'forename',
     *     'surname',
     *   ]);
     *   $finder->addField('report', ['pdfs']);
     *   $finder->addField('report', ['charts'], ['alias'=>'test']);
     */
    
class RockFinderFieldPage extends RockFinderField {
  public $separator = ',';
  
  /**
   * get sql
   */
  public function getSql() {
    if(!$this->columns) return $this->getSqlNoColumns();
    else return $this->getSqlWithColumns();
  }

  /**
   * get sql when no additional columns are set
   */
  public function getSqlNoColumns() {
    $sql = "SELECT";
    $sql .= "\n  `$this->alias`.`pages_id` AS `pageid`";
    $sql .= ",\n  GROUP_CONCAT(`$this->alias`.`data` ORDER BY `$this->alias`.`sort` SEPARATOR '$this->separator') AS `$this->alias`";
    $sql .= "\nFROM `field_{$this->name}` AS `$this->alias`";
    $sql .= "\nGROUP BY `$this->alias`.`pages_id`";
    return $sql;
  }

  /**
   * get sql when additional columns are set
   * we need to treat that case differently because a group_concat on the data column (the id of the joined page)
   * would lead to multiple id entries in the resulting column when a joined field has multiple entries
   * for example if we join the page with id 123 having a file field with files 1.jpg and 2.jpg the result would be:
   * 123,123 | 1.jpg,2.jpg
   * 
   * bug: joining a page with 2 fields having multiple items but different counts (eg filefield with 2 files
   * and files field with 4 files) makes the concat result in wrong returns
   */
  public function getSqlWithColumns() {
    $sql = "SELECT";
    $sql .= "\n  `$this->alias`.`pages_id` AS `pageid`";
    $sql .= ",\n `$this->alias`.`data` AS `$this->alias`";
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

    // if we have additional columns set we also group by the data column
    // see description of getSqlWithColumns() method why we need to do this
    if($this->columns) $sql .= ", `$this->alias`.`data`";
    
    return $sql;
  }
  
  /**
   * select all columns of this fieldtype
   */
  public function getJoinSelect() {
    $sql = '';
    foreach($this->columns as $i=>$column) {
      if($i==0)
        $sql .= ",\n  `$this->alias`.`{$this->fieldAlias($column)}` AS `{$this->fieldAlias($column)}` /* hier */";
      else
        $sql .= ",\n  `$this->alias`.`$column` AS `{$this->fieldAlias($column)}` /* hier2 */";
    }
    return $sql;
  }
}