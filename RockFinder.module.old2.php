<?php namespace ProcessWire;

/**
 * Highly Efficient and Flexible SQL Finder Module
 * 
 * Bernhard Baumrock, baumrock.com
 * MIT
 */

class RockSqlFinder extends WireData implements Module {

  /**
   * Initialize the module
   */
  public function init() {
    $this->addHook('Pages::findObjects', $this, 'findObjects');
  }

  /**
   * method that returns an array of objects or an array of arrays
   */
  public function findObjects($event) {
    $options = $event->arguments(1) ?: [];
    // execute the query
    $finder = new RockFinder($event->arguments(0), $options);
    $sql = $finder->render();

    // if sql option is set to true we return the sql
    if(isset($options['sql']) AND $options['sql'] == true) {
      $event->return = $sql;
      return;
    }

    // don't return the sql, return the array of objects
    d($sql, [6,9999]);
    $event->return = $this->getObjectsFromQuery($sql);
  }

  /**
   * get object or array from sql query
   */
  public function getObjectsFromQuery($query) {
    $results = $this->database->query($query);
    return $results->fetchAll(\PDO::FETCH_OBJ);
  }
}

class RockFinder extends Wire {
  private $selector; // pw selector to find pages
  private $fields; // array of fields to return
  private $options; // options array
  private $name; // name of this finder (used for sql table alias)
  private $level; // recursion level
  private $indent; // indendation
  private $pagesTableColumns;

  public function __construct($query, $options, $parent = null, $name = 'root', $level = 0) {
    if($level) {
      // we are inside a join
      $this->fields = $query;
    }
    else {
      $this->selector  = $query[0];
      $this->fields = $query[1];
    }
    $this->options = $options;
    $this->parent = $parent;
    $this->name = $name;
    $this->joins = [];
    $this->level = $level;
    $this->indent = str_repeat(' ', $level*2);

    // if we are inside a join we make sure we have an id column
    if(!in_array('id', $this->fields)) array_unshift($this->fields, 'id');

    // populate pagesTableColumns
    $this->pagesTableColumns = array_keys(
      $this->wire->database->query("SELECT * FROM pages LIMIT 0,1")->fetchAll(\PDO::FETCH_ASSOC)[0]
    );

    $this->executeJoins();
  }

  /**
   * execute joins
   */
  private function executeJoins() {
    if(!isset($this->options['joins'])) return;

    foreach($this->options['joins'] as $name => $join) {
      if(!is_string($name)) $name = uniqid();
      
      // add the executed join to this finder
      $this->joins[] = new RockFinder($join[0], @$join[1], $this, $name, $this->level+1);;
    }
  }

  /**
   * render the resulting query
   */
  public function render() {
    $sql = 
      "\n{$this->indent}SELECT" .
      rtrim($this->select(), ",").
      "\n{$this->indent}FROM".
      $this->from().
      $this->joins().
      $this->where()
      ;

    return $sql;
  }

  /**
   * ################## name functions ##################
   */

  private function tableAlias($finder = null) {
    $finder = $finder ?: $this;
    $path = [$finder->name];
    $parent = $finder->parent;
    while($parent) {
      $path[] = $parent->name;
      $parent = $parent->parent;
    }
    return implode(".", array_reverse($path));
  }

  /**
   * ################## sql helper methods ##################
   */

  /**
   * get table select name from given origin
   */
  private function getTableSelectName($origin) {
    if($this == $origin) return $this->tableAlias();

    // first move up to the highest join of this finder
    $current = $this;
    while($current->level > $origin->level+1) $current = $current->parent;

    // then add all parents to the selector
    $name = $current->name;
    while($current->level > 0) {
      $current = $current->parent;
      $name = $current->name .".". $name;
    }

    return $name;

    // if($this == $origin) return $this->tableAlias();

    // // go up from current finder until the parent is the origin
    // $current = $this;
    // while($current->parent != $origin) $current = $current->parent;

    // return $origin->name . "." . $current->name;
  }

  /**
   * get field alias for given field from origin
   */
  private function getFieldSelectName($field, $origin) {
    if($this == $origin) return $field;

    // go up from current finder until the parent is the origin
    $name = $field;
    $current = $this;
    while($current->parent != $origin) {
      $name = $current->name . "." . $name;
      $current = $current->parent;
    }

    return $name;
  }

  /**
   * get field alias for given field from origin
   */
  private function getFieldAlias($field, $origin) {

    // go up from current finder until the parent is the origin
    $name = $field;
    $current = $this;
    while($current != $origin) {
      $name = $current->name . "." . $name;
      $current = $current->parent;
    }

    return $name;
  }

  /**
   * build the select query for one field
   */
  private function getSelectForField($field, $origin) {
    $sql = '';

    // if this finder has a join for the current field we skip this field
    // the data of this field will be available from within the join
    foreach($this->joins as $join) {
      if($join->name == $field) return;
    }

    $table = $this->getTableSelectName($origin);
    $fieldSelectName = $this->getFieldSelectName($field, $origin);
    $fieldAlias = $this->getFieldAlias($field, $origin);

    $sql .= "\n{$origin->indent}  `$table`.`$fieldSelectName` AS `$fieldAlias`,";

    return $sql;
  }

  /**
   * create the query for the base table
   * the base table joins the "pages" table with all the field_ tables so that we can select
   * all fields easily from this joined basetable
   */
  private function baseTable() {
    $sql = "\n{$this->indent}  SELECT ";
    
    // add fields to select list
    $joins = '';
    foreach($this->fields as $field) {
      // add the sql query
      if(in_array($field, $this->pagesTableColumns)) $sql .= "`$field`,";
      else {
        $sql .= "`field_$field`.`data` AS `$field`,";
        $joins .= "\n{$this->indent}  LEFT JOIN `field_$field` as `field_$field` on `field_$field`.`pages_id` = `pages`.`id`";
      }
    }
    $sql = rtrim($sql, ",") . " FROM `pages`";
    $sql .= $joins;
    return $sql."\n";
  }

  /**
   * build the select query
   */
  private function select($origin = null) {
    $origin = $origin ?: $this;
    $sql = '';

    // select fields of this finder
    foreach($this->fields as $field) $sql .= $this->getSelectForField($field, $origin);

    // select fields of all joined finders
    foreach($this->joins as $join) $sql .= $join->select($origin);

    return $sql;
  }

  private function from() {
    return " ({$this->baseTable()}) AS `{$this->tableAlias()}`";
  }

  private function joins() {
    $sql = '';

    // join all tables from joins
    foreach($this->joins as $join) {
      $sql .= "\n{$this->indent}LEFT JOIN (".
        $join->render().
        "\n{$this->indent}  ) AS `{$join->tableAlias()}`";
      $sql .= " ON `{$join->tableAlias()}`.`id` = `{$this->tableAlias()}`.`{$join->name}`";
    }

    return $sql;
  }

  /**
   * build the where query
   */
  private function where() {
    // do not add any restrictions for joins
    if($this->level) return;

    // for the initial where clause we create ids based on the selector
    $pageIDs = implode(",", $this->wire->pages->findIDs($this->selector));

    // select rows that match ids from the find
    $sql = "\n{$this->indent}WHERE".
      "\n{$this->indent}  `{$this->tableAlias()}`.`id` IN ($pageIDs)";

    // make sure we keep the sort order of the pages->find() operation
    $sql .= "\nORDER BY\n  FIELD (`{$this->tableAlias()}`.`id`, $pageIDs)";

    return $sql;
  }
}