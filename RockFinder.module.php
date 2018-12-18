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
  private $filtersAfter = [];
  private $filtersBefore = [];

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

  // array of custom select statements
  private $selects = [];

  // prefix name that is used for this finder when joining it to another finder
  private $joinedFinders = [];
  public $joinPrefix;

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

          // for options fields we use the "file" type
          // if multiple options are set this will return all options as string
          case $type instanceof FieldtypeOptions: $type = 'file'; break;

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
   * add a select statement to SQL query
   * if you are using this method to aggregate data (such as sum() etc)
   * note that this might not be as efficient as doing a sum() and group by ... on the resulting SQL
   */
  public function addSelects($statements = []) {
    $this->selects = array_merge($this->selects, $statements);
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
    $pwfinder = $this->indent($query->prepare()->queryString, 4);

    // start sql statement
    $sql = "SELECT\n  `pages`.`id` AS `id`";
    foreach($this->fields as $field) $sql .= $field->getJoinSelect();

    // add the join prefix if it is set
    $joinPrefix = '';
    if($this->joinPrefix) {
      $joinPrefix = "{$this->joinPrefix}_";
      $sql = str_replace('AS `', "AS `$joinPrefix", $sql);
    }

    // add all select statements
    foreach($this->selects as $alias=>$statement) {
      $sql .= ",\n  ($statement) AS $alias";
    }

    $sql .= "\nFROM\n  `pages`";
    foreach($this->fields as $field) $sql .= $field->getJoin();
    $rockfinder = $sql;

    // join both queries
    $sql = "SELECT";
    $sql .= "\n    `rockfinder`.*";
    $sql .= $this->joinedFinderSelects();
    $sql .= "\nFROM";
    $sql .= "\n    /* original pw query */";
    $sql .= "\n    ($pwfinder) as `pwfinder`";
    
    $sql .= "\n\n/* rockfinder */";
    $sql .= "\nLEFT JOIN (";
    $sql .= "    " . $this->indent($rockfinder, 4);
    $sql .= "\n) AS `rockfinder` ON `pwfinder`.`id` = `rockfinder`.`{$joinPrefix}id`";
    $sql .= "\n/* end rockfinder */";

    $sql .= $this->joinedFinderJoins();

    $this->timer('getSQL', $sqltimer, "<textarea class='noAutosize' rows=5>$sql</textarea>");
    $this->sql = $sql;
    return $sql;
  }

  /**
   * return select statements for all joined finders
   */
  private function joinedFinderSelects() {
    if(!count($this->joinedFinders)) return;

    $sql = "\n    /* joined finders */";
    foreach($this->joinedFinders as $finder) {
      $fields = $finder[1];
      $finder = $finder[0];
      $sql .= "\n    ,`$finder->joinPrefix`.*";
    }
    return $sql;
  }

  /**
   * return join statements for all joined finders
   */
  private function joinedFinderJoins() {
    if(!count($this->joinedFinders)) return;

    $sql = "\n\n/* joinedFinderJoins */";
    foreach($this->joinedFinders as $finder) {
      foreach($finder[1] as $field1=>$field2) {} // assign key/value

      // create sql statement
      $finder = $finder[0];
      $sql .= "\n\n/* join finder {$finder->joinPrefix} */";
      $sql .= "\nLEFT JOIN (";
      $sql .= "\n    " . $this->indent($finder->getSQL(), 4);
      $sql .= "\n) AS `" . $finder->joinPrefix . "`";

      // create ON clause
      // if $field2 has a dot the join is performed on an already joined table
      // $finder1->join($finder2, 'contact', ['id' => 'client']);
      // $finder1->join($finder3, 'referrer', ['id' => 'contact.contact_camefrom']);
      if(strpos($field2, '.')) {
        $field2 = str_replace('.', '`.`', $field2);
        $sql .= " ON `$finder->joinPrefix`.`{$finder->joinPrefix}_$field1` = `$field2`";
      }
      else {
        $sql .= " ON `$finder->joinPrefix`.`{$finder->joinPrefix}_$field1` = `rockfinder`.`$field2`";
      }
    }
    return $sql;
  }

  /**
   * indent all lines of given string
   */
  private function indent($str, $chars) {
    return str_replace("\n", "\n".str_repeat(" ",$chars), $str);
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
  public function getObjects($type = null) {
    $timer = $this->timer('getObjects');
    try {
      $results = $this->database->query($this->getSql());

      // check for the return type
      if($type == 'WireData') {
        // return a wirearray of wiredata objects
        $objects = $results->fetchAll(\PDO::FETCH_CLASS, '\ProcessWire\WireData');
        $objects = (new WireArray())->import($objects);
      }
      elseif($type == 'array') {
        // return array of plain associative php arrays
        $objects = $results->fetchAll(\PDO::FETCH_ASSOC);
      }
      else {
        // no type set, return stdClass objects
        $objects = $results->fetchAll(\PDO::FETCH_OBJ);
      }
    }
    catch(\PDOException $e) {
      // if the sql has an error we return an empty resultset
      // this is the case when no pages where found or the sql is not correct
      return [];
    }
    $result = $objects;

    // execute filters before executing closures
    // this might be more performant then executing filters after all closures
    $result = $this->executeFilters($result);

    // execute closures
    $clstimer = $this->timer('executeClosures');
    $result = $this->executeClosures($result);
    $this->timer('executeClosures', $clstimer);

    // execute filters after closures have been executed
    // sometimes it might be necessary to filter on values executed by closures
    $result = $this->executeFilters($result, 1);

    $ajax = $this->ajax
      ? ', AJAX is turned ON and not tracked'
      : ''
      ;
    
    $this->timer('getObjects', $timer, 'Includes executeClosures' . $ajax);
    return $result;
  }

  /**
   * return array of arrays
   */
  public function getArrays() {
    return $this->getObjects('array');
  }

  /**
   * return a WireArray of WireData objects
   */
  public function getWireArray() {
    return $this->getObjects('WireData');
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
  private function executeClosures($rows) {
    // if limit is set return the objects
    if($this->limit != '') return $rows;

    // if closures exist execute them
    foreach($this->closures as $column => $closure) {
      foreach($rows as $i=>$row) {
        if(is_array($row)) {
          // initial request was getArrays()
          $page = $this->pages->get($row['id']);
          $rows[$i][$column] = $closure->__invoke($page);
        }
        else {
          // initial request was getObjects()
          $page = $this->pages->get($row->id);
          $row->{$column} = $closure->__invoke($page);
        }
      }
    }

    return $rows;
  }

  /**
   * execute filters
   *
   * @param array $rows
   * @param bool $after
   * @return void
   */
  private function executeFilters($rows, $after = false) {
    $filters = $after ? $this->filtersAfter : $this->filtersBefore;
    foreach($filters as $filter) {
      foreach($rows as $i=>$row) {
        // we always send rows as objects to filter functions
        $rowobject = (object)$row;
        if($filter->__invoke($rowobject) == false) unset($rows[$i]);
      }
    }

    return $rows;
  }

  /**
   * add filter to this finder
   *
   * @param callable $callback
   * @param boolean $after
   * @return void
   */
  public function filter($callback, $after = false) {
    // add callback to filters array
    if($after) $this->filtersAfter[] = $callback;
    else $this->filtersBefore[] = $callback;
  }

  /**
   * shortcut
   *
   * @param callable $callback
   * @return void
   */
  public function filterAfter($callback) {
    $this->filter($callback, true);
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
   * join another finder
   */
  public function join($finder, $prefix, $fields) {
    // parameter checks
    if(!$finder instanceof RockFinder) {
      throw new WireException('First parameter needs to be a RockFinder instance');
    }
    if(!is_string($prefix)) throw new WireException('Second parameter needs to be a string');
    if(!is_array($fields)) throw new WireException('Third parameter needs to be an array');
    if(count($fields)!==1) throw new WireException('Third parameter needs to be an array with one key/value pair');
    foreach($fields as $field1 => $field2) {
      if(!is_string($field1) OR !is_string($field2)) {
        throw new WireException('Third parameter needs to be an array with one key/value pair');
      }
    }

    // set the join prefix for the joined finder
    $finder->joinPrefix = $prefix;
    
    // add join to array
    $this->joinedFinders[] = [$finder, $fields];
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
    $info['selects'] = $this->selects;
    return $info;
  }
}
