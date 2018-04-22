<?php namespace ProcessWire;
/**
 * Highly Efficient and Flexible SQL Finder Module
 * 
 * Bernhard Baumrock, baumrock.com
 * MIT
 */
class RockFinder extends WireData implements Module {
  private $selector;
  private $fields = [];

  // sort the returned array?
  // if this option is set to true ($finder->sort = true;) the returned
  // array will be returned in the same order as the pages from the findIDs() operation
  // this can increase the execution time significantly! especially on large datasets. use with caution!
  // see https://processwire.com/talk/topic/18983-rocksqlfinder-highly-efficient-and-flexible-sql-finder-module/?do=findComment&comment=165703
  public $sort = true;

  public function __construct($selector = '', $fields = []) {
    $this->selector = $selector;

    // load all finderfield classes
    require_once('RockFinderField.class.php');
    foreach($this->files->find($this->config->paths->assets."RockFinder/", ['extensions' => ['php']]) as $file)
      require_once($file);
    foreach($this->files->find($this->config->paths->siteModules."RockFinder/fieldTypes/", ['extensions' => ['php']]) as $file)
      require_once($file);

    // add fields
    $this->addFields($fields);
  }

  /**
   * add a field to the finder
   */
  public function addField($name, $columns = [], $type = null) {
    // create new field
    // if the type is set, use this type
    if(!$type) {
      // type was not set manually, so choose the related fieldtypefinder
      $field = $this->wire->fields->get($name);
      if(!$field) {
        // this is not a regular field, so we set the type to "pagestable"
        $type = 'pagestable';
      }
      else {
        $type = $field->type;
        switch(true) {
          case $type instanceof FieldtypeRepeater: $type = 'repeater'; break;
          case $type instanceof FieldtypeFile: $type = 'file'; break;
          case $type instanceof FieldtypePage: $type = 'page'; break;
          default: $type = 'text'; break;
        }
      }
    }

    $class = "\ProcessWire\RockFinderField".ucfirst($type);
    $field = new $class($name, $columns, $type);
    
    // add it to the fields array
    $this->fields[] = $field;
    return $field;
  }

  /**
   * add multiple fields via array
   */
  public function addFields($fields) {
    $arr = [];
    foreach($fields as $field) $arr[] = $this->addField($field);
    return $arr;
  }

  /**
   * get field by name
   */
  public function getField($name) {
    foreach($this->fields as $field) {
      if($field->name == $name) return $field;
    }
  }

  /**
   * get sql for this finder
   */
  public function getSQL() {
    $pageIDs = implode(",", $this->wire->pages->findIDs($this->selector));
    $sql = "SELECT\n  `pages`.`id` AS `id`";
    foreach($this->fields as $field) $sql .= $field->getJoinSelect();
    $sql .= "\nFROM\n  `pages`";
    foreach($this->fields as $field) $sql .= $field->getJoin();
    $sql .= "\nWHERE\n  `pages`.`id` IN ($pageIDs)";
    if($this->sort) $sql .= "\nORDER BY\n  field(`pages`.`id`, $pageIDs)";
    return $sql;
  }

  /**
   * get array of objects for this finder
   */
  public function getObjects() {
    $results = $this->database->query($this->getSQL());
    return $results->fetchAll(\PDO::FETCH_OBJ);
  }
}
