<?php namespace ProcessWire;

/**
 * Highly Efficient and Flexible SQL Finder Module
 * 
 * Bernhard Baumrock, baumrock.com
 * MIT
 */

class RockSqlFinder extends WireData implements Module {

  private $closures = []; // array for uniqe column name check
  private $pagesTableColumns; // holds all columns of the "pages" table

  private $fields = []; // array of fields to query
  private $joins = []; // array of joined tables

  private $fieldname; // holding the field's name, eg mycustomfield
  private $field; // simple field query
  private $fieldDot = []; // dot-syntax query
  private $tablename; // table name to query for this field
  private $tablealias; // table alias

  // this setting defines how multilanguage field values are queried
  // if the setting is true, the query will return the field value in the current language
  // if the setting is false, the query will return the default language if the current language is empty
  private $strictLanguage;

  /**
   * Initialize the module
   */
  public function init() {
    $this->addHook('Pages::findObjects', $this, 'findObjects');

    // populate pagesTableColumns
    $this->pagesTableColumns = array_keys(
      $this->database->query("SELECT * FROM pages LIMIT 0,1")->fetchAll(\PDO::FETCH_ASSOC)[0]
    );
  }

  /**
   * method that returns an array of objects or an array of arrays
   */
  public function findObjects($event) {
    
    // get the query
    $query = $this->buildQuery(
      $event->arguments(0), // selector
      $event->arguments(1) ?: [], // columns
      $event->arguments(2) // strictLanguage
    );

    // early exit if we got no query
    // this is the case when the find operation returned an empty set of pages
    if(!$query) {
      $event->return = [];
      return;
    }

    // return the array of objects
    $objects = $this->getObjectsFromQuery($query);
    
    // execute closures and return the result
    $event->return = $this->executeClosures($objects);
  }

  /**
   * get object or array from sql query
   */
  public function getObjectsFromQuery($query) {
    $results = $this->database->query($query);
    return $results->fetchAll(\PDO::FETCH_OBJ);
  }
  
  /**
   * execute all closures
   */
  private function executeClosures($objects) {
    if(!count($this->closures)) return $objects;
    // loop objects
    foreach($objects as $object) {
      $page = $this->pages->get($object->id);
      foreach($this->closures as $key => $closure) $object->{$key} = $closure($page);
    }
    return $objects;
  }

  /**
   * build an sql query for selector+columns combination
   * 
   * @param selector $selector
   * @param array $columns
   * @param bool $strictLanguage
   * 
   * @return string
   */
  public function buildQuery($selector, $columns, $strictLanguage = false) {
    $sanitizer = $this->wire->sanitizer;
    $this->strictLanguage = $strictLanguage;
    
    // data checks
    if(!$sanitizer->selectorValue($selector)) throw new WireException("First argument needs to be a valid PW selector");
    if(!is_array($columns)) throw new WireException("Second argument needs to be an array");

    // find page-ids from selector
    $ids = $this->pages->findIDs($selector);

    // erly exit if the find operation did not return any ids
    if(!count($ids)) return;
    $pageIDs = implode(',', $ids);

    // loop all columns and populate $fields and $joins
    $this->executeColumns($columns);

    // build query
    $sql = "SELECT";

    // add field selection statements
    foreach($this->fields as $i=>$field) {
      $sql .= "\n  " . ($i?',':'') . $field;
    }
    if(!count($this->fields)) $sql .= "\n  *";
    //$sql .= $this->buildColumnQuery($columns);
    $sql .= "\nFROM pages";
    foreach($this->joins as $join) $sql .= $join;
    $sql .= "\nWHERE\n  pages.id IN ($pageIDs)";

    // make sure we keep the sort order of the pages->find() operation
    $sql .= "\nORDER BY\n  FIELD (pages.id,$pageIDs)";

    // replace all #data# occurrences by the languages data value
    // todo: add language support
    $sql = str_replace('#data#', 'data', $sql);
    
    d($sql, 'sql', [6,999]);
    return $sql;
  }

  /**
   * execute all columns and build sql strings
   * this populates $this->fields and $this->joins
   */
  private function executeColumns($columns) {
    foreach($columns as $name => $query) {
      if(!is_string($name)) {
        // there was no key/query pair, just a query
        // in that case the column name is identical to the query
        $name = $query;
      }

      d($name, 'name');
      d($query, 'query');
      // build the query for this column
      $this->executeColumn($name, $query);
    }
  }

  /**
   * build a query for the given column
   */
  private function executeColumn($name, $query) {
    $this->fieldname = $name;
    $this->fieldquery = $query;

    // if the query is an SQL statement return it
    if(is_callable($query) AND !is_string($query)) $this->addClosureField();
    elseif(stripos(ltrim($query, "("), "select ") === 0) $this->addSqlField();
    elseif(strpos($query, '.')) $this->addDotField();
    else $this->addField();
  }

  /**
   * get the sql string for the given sql query
   */
  private function addSqlField() {
    if($name == $query) $this->fields[] = $query;
    else $this->fields[] = "($query) as $name";
  }

  private function addDotField() {
    $this->fieldDot = explode(".", $this->fieldquery);
    $this->fields[] = 'addDotField';
  }

  private function addClosureField() {
    $this->fields[] = 'addClosureField';
  }

  /**
   * simple field, no dot notation
   * just join the related table and add the field to the selects
   */
  private function addField() {
    // join the table
    $this->addSqlJoin();
    //$this->addFieldSelect($tablealias);
    // select the field
    //$this->fields[] = '';
  }

  /**
   * add select statement for given field
   */
  private function addFieldSelect($name, $table) {
    $this->fields[] = "`$table`.`#data#` AS `$name`";
  }


  /**
   * add a join
   */
  private function addSqlJoin() {
    // get the table name and alias from field
    $table = $this->getTableName();
    $tablealias = $this->getTableAlias();

    if($table == 'pages') {
      $on1 = "`$tablealias`.`id`";
      $on2 = "`pages`.`id`";
    }
    else {
      $on1 = "`$tablealias`.`pages_id`";
      $on2 = "`pages`.`id`";
    }

    $sql = "\nLEFT JOIN `$table` AS `$tablealias` ON $on1 = $on2";
    $this->joins[] = $sql;
  }

  /**
   * get table alias for given field
   */
  private function getTableAlias() {
    // if name + query are the same its a simple field
    if($this->fieldname == $this->fieldquery) return $this->fieldname;
    //elseif($this->fieldDot) return $this->fieldDot[0].".".$this->field
    else return $this->fieldname;
  }

  /**
   * get table name to query for this field
   */
  private function getTableName() {
    if(in_array($this->fieldquery, $this->pagesTableColumns)) {
      // this field is part of the pages table
      return "pages";
    }
    else {
      // this is a regular field
      return "field_{$this->fieldquery}";
    }
  }

















































  /**
   * build sql query for given columns
   * 
   * @param array $columns
   * @return string part of sql statement
   */
  private function buildColumnQuery($columns) {
    if(!count($columns)) throw new WireException("At least one column must be specified");

    // we always return the id of the page at very first
    $columns = array_merge(['id'], $columns);

    // loop all columns
    $sql = ""; // sql query
    $this->br = "  ";
    $columnsDone = [];
    foreach($columns as $key => $value) {
      // if the column is set as key/value pair the resulting property will have a custom name
      // eg 'title' => 'mycolumn' will show the value of the title field named as 'mycolumn'
      if(is_string($key)) {
        $column = $key;
        $customname = $value;
      }
      else {
        $column = $value;
        $customname = null;
      }


      $column = is_string($key) ? $key : $value;
      $query = $value;

      // check double definition of columns
      if(in_array($column, $columnsDone)) throw new WireException("Column '$column' is defined multiple times!");
      $columnsDone[] = $column;

      ##### check different query options #####
      if(is_callable($query) AND !is_string($query)) {
        // is_string makes sure the query is not a php function name like "date"
        $sql .= $this->getQueryForClosure($column, $query);
      }
      elseif(is_string($query)) {
        // if the query is a string there are two options:
        // 1) it is an sql query
        // 2) it is a field name
        if(stripos($query, "(select") === 0) $sql .= $this->getQueryForSql($query);
        else $sql .= $this->getQueryForField($column, $customname);
      }

      // debug
      // d($column, 'column');
      // d($query, 'query');

      $this->br = ",\n  ";
    }

    return $sql;
  }

  /**
   * request a single field
   * @param string $column, this is the name of the requested column
   * @return string $sql
   */
  private function getQueryForField($column, $customname = null) {
    $customname = $customname ?: $column;

    // check if the field is part of the pages table
    if($this->isInPagesTable($column))  return "{$this->br}pages.`$column` AS `$column`";

    // check if the column is of format pagefield.title
    // in this case we build a dot-query
    // in this case we cannot work with subqueries, we need to do joins
    if(strpos($column, '.')) return $this->getDotQuery($column);

    // usually the field data is in the db-table "field_yourfieldname"
    // sometimes it is necessary to query other db-tables, like the table "pages" for page status, created etc
    // to query another table the field can be specified as table:column syntax
    $columns = explode(":", $column);
    $column = $columns[0];
    
    // check if field exists
    $field = $this->wire->fields->get($column);
    if(!$field) throw new WireException("Field $column does not exist");

    // check for wrong sql query
    if(stripos($column, "select") === 0)
      throw new WireException("SQL query syntax error: use '(select ... from ... where ...) as ...'");

    $fieldtype = $field->type;

    // reset $data variable
    $sql = "";
    $data = "data";
    switch(true) {
      // if it is a multilang field we append the language id to query the correct column
      case $fieldtype instanceof FieldtypeTextLanguage:
      case $fieldtype instanceof FieldtypeTextareaLanguage:
        // the current user's language is not the default language
        // we need to query a different db table column (data1234)
        $data = $this->getDataLanguageString();
        // no break here intended!

      // build sql query
      case $fieldtype instanceof FieldtypePage:
      case $fieldtype instanceof FieldtypeFile:
        $sql .= "{$this->br}(SELECT GROUP_CONCAT(`$data` SEPARATOR '|') FROM `field_$column` WHERE `pages_id` = pages.id ORDER BY sort) AS `$customname`";
        $sql .= $this->getSubQueries($columns, $fieldtype);
        break;
      default:
        $sql .= "{$this->br}(SELECT `$data` FROM `field_$column` WHERE pages_id = pages.id) AS `$customname`";
    }

    
    return $sql;
  }

  /**
   * get data of a referenced page
   * eg. mypagefield.id.title.status
   */
  private function getDotQuery($column) {
    $dots = explode(".", $column);
    // the first item is the fields name
    // this table has to be joined to the query
    foreach($dots as $i=>$dot) {
      if($i == 0) {
        // mypagefield.id.title.status
        // $dot = mypagefield
        // we need to join field_mypagefield in this case
        $this->addJoin($dot);
        continue;
      }

      // all other dots should be fields
      return $this->getDotQueryForField($dot, $dots[0]);
    }
  }

  /**
   * return dot query for given fieldname
   */
  private function getDotQueryForField($field, $parent) {
    // add the join for this field
    $this->addJoin($field, $parent);
    //return "{$this->br}"
  }

  /**
   * join table for fields that where requested by dot-notation
   */
  private function addJoin($field, $parent = null) {
    $table = $this->tableName($field, $parent); // eg dotNotationMyfield
    if($parent) $this->joins[] = "\nLEFT JOIN  `field_$field` AS `$table` ON `$table`.pages_id = `{$this->tableName($parent)}`.data";
    else $this->joins[] = "\nLEFT JOIN  `field_$field` AS `$table` ON `$table`.pages_id = pages.id";
  }

  /**
   * this is to make sure we have no name conflicts
   */
  private function tableName($dot, $parent = '') {
    return "dot".ucfirst($parent).ucfirst($dot);
  }

  private function getSubQueries($columns, $fieldtype) {
    if(count($columns)<2) return;
    $this->br .= "  "; // add indentation

    // return subqueries for file fieldtypes
    if($fieldtype instanceof FieldtypeFile) {
      $sql = "";
      $field = $columns[0];
      foreach($columns as $i=>$column) {
        if($i==0) continue; // skip first element (parent)
        $as = $field."_".$column;
        $sql .= "{$this->br}(SELECT GROUP_CONCAT(`$column` SEPARATOR '|') FROM `field_$field` WHERE pages_id = pages.id ORDER BY sort) AS `$as`";
      }
      return $sql;
    }

    // return subqueries for file fieldtypes
    if($fieldtype instanceof FieldtypePage) {
      $sql = "";
      $field = $columns[0];
      foreach($columns as $i=>$column) {
        if($i==0) continue; // skip first element (parent)

        $as = $field."_".$column;
        $join = "LEFT JOIN pages AS pagestable ON pagestable.id = fieldtable.data";

        $sql .= "{$this->br}(";
        $sql .= "SELECT GROUP_CONCAT(`$column` SEPARATOR '|') FROM `field_$field` AS fieldtable $join WHERE fieldtable.pages_id = pages.id ORDER BY fieldtable.sort";
        $sql .= ") AS `$as`";
      }
      return $sql;
    }
  }

  /**
   * return the sql query string to query the right column for multilanguage fields
   */
  private function getDataLanguageString() {
    $data = "data";
    if($this->user->language->name != 'default') {
      if($this->strictLanguage) $data .= $this->user->language->id;
      else $data = "IF(LENGTH(`$data{$this->user->language->id}`)>0, `$data{$this->user->language->id}`, `$data`)";
    }
    return $data;
  }

  /**
   * request custom SQL
   */
  private function getQueryForSql($query) {
    return "{$this->br}" . str_replace("#data#", $this->getDataLanguageString(), $query);
  }
  
  /**
   * request a callback value
   * CAUTION: this option is only for flexibility and may cause significant performance problems!
   * if the query is a callback we return an empty string
   * the value of this column will be calculated after the database was queried
   * the resultset will be looped via foreach and the values will be calculated
   * note that this can be SIGNIFICANTLY slower than a regular SQL query
   */
  private function getQueryForClosure($column, $closure) {
    // add column to closures array
    // all columns in this array will be calculated after the whole sql resultset was loaded
    $this->closures[$column] = $closure;
    return "{$this->br}'' AS `$column`";
  }

  /**
   * is this column a regular field or is it part of the "pages" table
   * id, status, templates_id are examples of fields in the pages table
   */
  private function isInPagesTable($column) {
    return in_array($column, $this->pagesTableColumns);
  }
}
