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
  private $closures = [];

  // here we can set a limit to use for the selector
  // this is needed for getDataColumns to only query one row for better performance
  public $limit = '';

  // array holding debug info
  public $debug = false;
  public $debuginfo = [];

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
  public function addField($name, $columns = [], $type = null, $alias = null) {
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
    if($alias) $field->alias = $alias;
    
    // add it to the fields array
    $this->fields[] = $field;
    return $field;
  }

  /**
   * add multiple fields via array
   */
  public function addFields($fields) {
    $arr = [];
    foreach($fields as $k=>$field) {
      $alias = is_string($k) ? $k : null;
      $type = null;

      // check if the field has a closure
      if(is_callable($field) AND !is_string($field)) {
        $type = 'closure';

        // save this closure to the array
        $this->closures[$alias] = $field;

        // set the fieldname to the alias
        $field = $alias;
      }

      // add thid field to the array
      $arr[] = $this->addField(
        $field,
        null,
        $type,
        $alias
      );
    }
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
    $sqltimer = $this->timer('getSQL');
    
    $timer = $this->timer('findIDs');
    $selector = $this->selector.$this->limit;
    $pageIDs = implode(",", $this->wire->pages->findIDs($selector));
    $this->timer('findIDs', $timer, $selector);

    $sql = "SELECT\n  `pages`.`id` AS `id`";
    foreach($this->fields as $field) $sql .= $field->getJoinSelect();
    $sql .= "\nFROM\n  `pages`";
    foreach($this->fields as $field) $sql .= $field->getJoin();
    $sql .= "\nWHERE\n  `pages`.`id` IN ($pageIDs)";
    if($this->sort) $sql .= "\nORDER BY\n  field(`pages`.`id`, $pageIDs)";

    $this->timer('getSQL', $sqltimer);
    return $sql;
  }

  /**
   * get array of objects for this finder
   */
  public function getObjects() {
    $timer = $this->timer('getObjects');
    $results = $this->database->query($this->getSQL());
    $objects = $results->fetchAll(\PDO::FETCH_OBJ);

    $clstimer = $this->timer('executeClosures');
    $closures = $this->executeClosures($objects);
    $this->timer('executeClosures', $clstimer);
    
    $this->timer('getObjects', $timer, 'Includes executeClosures');
    return $closures;
  }

  /**
   * execute closures
   */
  private function executeClosures($objects) {

    // if limit is set return the objects
    if($this->limit != '') return $objects;

    // if no closures exist return the objects
    if(!count($this->closures)) return $objects;

    // otherwise loop all objects and execute closures
    foreach($this->closures as $column => $closure) {
      foreach($objects as $row) {
        // find the column and execute closure
        $page = $this->pages->get($row->id);
        $row->{$column} = $closure->__invoke($page);
      }
    }
    return $objects;
  }

  /**
   * start timer or add it to debuginfo
   */
  private function timer($name, $timer = null, $desc = '') {
    if(!$this->debug) return;

    if(!$timer) return Debug::timer();
    else {
      $this->debuginfo[] = [
        'name' => $name,
        'value' => Debug::timer($timer)*1000,
        'desc' => $desc,
      ];
    }
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
    $info['selector'] = $this->selector;
    $info['fields'] = $this->fields;
    $info['closures'] = $this->closures;
    $info['debuginfo'] = $this->debuginfo;
    return $info;
  }
}
