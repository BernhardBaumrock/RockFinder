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
  public $debug;
  public $debuginfo = [];

  // here we store the composed sql query
  // this is to make several $finder->getSQL() calls
  // only create the query once
  public $sql;

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
  public function addField($name, $columns = [], $options = []) {
    $defaults = [
      'type' => null,
      'alias' => null,
    ];
    $options = array_merge($defaults, $options);
    $type = $options['type'];
    $alias = $options['alias'];

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
    if(!class_exists($class)) {
      $this->warning("Class $class not found for field $name, using RockFinderFieldText");
      $class = "\ProcessWire\RockFinderFieldText";
    }
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
      $arr[] = $this->addField($field, null, [
        'type' => $type,
        'alias' => $alias
      ]);
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
    // if the sql statement was already created we return it
    if($this->sql) return $this->sql;

    $sqltimer = $this->timer('getSQL');

    // get sql statement of pages->find
    $selector = new Selectors($this->selector.$this->limit);
    $pf = new PageFinder();
    $query = $pf->find($selector, ['returnQuery' => true]);
    $query['select'] = ['pages.id']; // we only need the id
    $pwfinder = $query->prepare()->queryString;

    // start sql statement
    $sql = "SELECT\n  `pages`.`id` AS `id`";
    foreach($this->fields as $field) $sql .= $field->getJoinSelect();
    $sql .= "\nFROM\n  `pages`";
    foreach($this->fields as $field) $sql .= $field->getJoin();
    $rockfinder = $sql;

    // join both queries
    $sql = "SELECT `rockfinder`.* FROM";
    $sql .= "\n\n /* original pw query */";
    $sql .= "\n($pwfinder) as `pwfinder`";
    
    $sql .= "\n\nLEFT JOIN (";
    $sql .= "\n/* rockfinder */\n";
    $sql .= $rockfinder;
    $sql .= "\n/* end rockfinder */";
    $sql .= "\n) AS `rockfinder` ON `pwfinder`.`id` = `rockfinder`.`id`";

    $this->timer('getSQL', $sqltimer, "<textarea class='noAutosize' rows=5>$sql</textarea>");
    $this->sql = $sql;
    return $sql;
  }

  /**
   * load sql from file
   */
  public function loadSQL($filename) {
    if(!is_file($filename)) {
      $filename = $this->config->paths->assets . 'RockFinder/' . pathinfo($filename, PATHINFO_FILENAME) . '.sql';
    }
    if(!is_file($filename)) throw new WireException("Invalid filename $filename");
    $sql = file_get_contents($filename);
    $finderSql = $this->getSQL();
    $sql = str_replace('@sql', "\n\n($finderSql)\n\n", $sql);
    $this->sql = $sql;
    return $this;
  }

  /**
   * get array of objects for this finder
   */
  public function getObjects($array = null) {
    $timer = $this->timer('getObjects');
    try {
      $results = $this->database->query($this->getSql());
      $objects = $results->fetchAll($array ? \PDO::FETCH_ASSOC : \PDO::FETCH_OBJ);
    }
    catch(\PDOException $e) {
      // if the sql has an error we return an empty resultset
      // this is the case when no pages where found or the sql is not correct
      return [];
    }

    $clstimer = $this->timer('executeClosures');
    $closures = $this->executeClosures($objects);
    $this->timer('executeClosures', $clstimer);

    $ajax = $this->ajax
      ? ', AJAX is turned ON and not tracked'
      : ''
      ;
    
    $this->timer('getObjects', $timer, 'Includes executeClosures' . $ajax);
    return $closures;
  }

  /**
   * return array of arrays
   */
  public function getArrays() {
    return $this->getObjects(true);
  }

  /**
   * return a flat array of values
   */
  public function getValues($field) {
    $arr = [];
    foreach($this->getArrays() as $item) $arr[] = $item[$field];
    return $arr;
  }

  /**
   * return regular pw page objects
   */
  public function getPages($field = 'id') {
    $pages = new PageArray();
    foreach($this->getValues($field) as $id) $pages->add($this->pages->get($id));
    return $pages;
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
   * return an Array from a given sql statement
   * this is used for simple sub-selects that store aggregated data
   * it always returns an array of key/value pars (2 columns)
   * the sql statement MUST select id + val, eg:
   * SELECT fg AS id, count(fg) as val FROM ({$finder->getSQL()}) as fg group by fg
   */
  public function getArrayFromSql($sql) {
    $result = wire('database')->query($sql);
    $arr = [];
    while($row = $result->fetch(\PDO::FETCH_ASSOC)) $arr[$row['id']] = $row['val'];
    return $arr;
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
