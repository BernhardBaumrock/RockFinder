<?php namespace ProcessWire;
abstract class RockFinderField extends WireData {
  public $name;
  protected $columns;
  public $type;
  public $alias;
  public $siblingseparator = ':';

  // if set to true this will query the current user's language in strict mode
  // if set to false, it will return the value of the current language and fallback
  // to the default language's value if the current language's value is NULL or empty
  public $strictLanguage = false;
  public $multiLang = true;
  
  // todo: change parameters to one options array
  public function __construct($name, $columns, $type) {
    $this->name = $this->alias = $name;
    $this->columns = array_merge(['data'], $columns ?: []);
    $this->type = $type;
  }

  /**
   * get sql
   */
  public function getSql() {
    $sql = "SELECT";
    $sql .= "\n  `pages_id` AS `pageid`";
    foreach($this->columns as $column) {
      $sql .= ",\n  {$this->dataColumn($this->alias)} AS `{$this->fieldAlias($column)}`";
    }
    $sql .= "\nFROM `field_".strtolower($this->name)."` AS `$this->alias`";
    return $sql;
  }

  /**
   * get array of objects
   */
  public function getObjects($limit = 0) {
    $limit = $limit ? " LIMIT $limit" : '';
    $results = $this->database->query($this->getSQL() . $limit);
    return $results->fetchAll(\PDO::FETCH_OBJ);
  }

  /**
   * return the field alias for given column
   */
  protected function fieldAlias($column) {

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
    $alias = $column == 'data'
      ? "{$this->alias}"
      : "{$this->alias}{$this->siblingseparator}$column"
      ;

    return $alias;
  }

  /**
   * select all columns of this fieldtype
   */
  public function getJoinSelect() {
    $sql = '';
    foreach($this->columns as $column) {
      $sql .= ",\n  `$this->alias`.`{$this->fieldAlias($column)}` AS `{$this->fieldAlias($column)}`";
    }
    return $sql;
  }

  /**
   * return the select statement for the data column
   */
  public function dataColumn($column) {
    // if multilang is switched off query "data" column directly
    if($this->multiLang == false) return "`$column`.`data`";

    // if the field does not support multilang return the data column
    $field = $this->fields->get($column);
    if(!$field) throw new WireException("Field $column does not exist");
    if(!$field->type instanceof FieldtypeLanguageInterface) return "`$column`.`data`";
    
    // multilang is ON, check for the user's language
    $lang = $this->wire->user->language;
    if($lang != $this->wire->languages->getDefault()) {
      // in strict mode we return the language value
      if($this->strictLanguage) return "`$column`.`data{$lang->id}`";

      // otherwise we return the first non-empty value
      else return "COALESCE(NULLIF(`$column`.`data{$lang->id}`, ''), `$column`.`data`)";
    }

    // user has default language active, return the value
    return "`$column`.`data`";
  }

  /**
   * get sql for joining this field's table
   */
  public function getJoin() {
    return "\n\n/* --- join $this->alias --- */\n".
      "LEFT JOIN (" . $this->getSql() . ") AS `$this->alias` ".
      "ON `$this->alias`.`pageid` = `pages`.`id`".
      "\n/* --- end $this->alias --- */\n";
  }

  /**
   * Add columns to this field.
   *
   * @param array $columns
   * @return void
   */
  public function addColumns($columns) {
    $this->columns = array_merge($this->columns, $columns);
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
    $info['name'] = $this->name;
    $info['columns'] = $this->columns;
    $info['type'] = $this->type;
    $info['alias'] = $this->alias;
    $info['siblingseparator'] = $this->siblingseparator;
    return $info; 
  }
}