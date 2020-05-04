<?php
class SuQL extends SQLSugarSyntax
{
  private $suql;

  function __construct() {
    parent::__construct();
  }

  public function clear() {
    $this->suql = null;
    parent::clear();
  }

  public function rel($leftTable, $rightTable, $on, $temporary = false) {
    parent::rel($leftTable, $rightTable, $on, $temporary);
    return $this;
  }

  public function getSQL() {
    return $this->interpret() ? parent::getSQL() : null;
  }

  public function getSQLObject() {
    return $this->interpret() ? parent::getSQLObject() : null;
  }

  public function query($suql) {
    $this->suql = trim($suql);
    return $this;
  }

  private function interpret() {
    if (!$this->suql) return false;

    // Processing the nested queries
    $nestedQueries = SuQLParser::getNestedQueries($this->suql);
    foreach ($nestedQueries as $name => $query) {
      parent::addQuery($name);

      $handler = SuQLParser::getQueryHandler($query);
      if (!$this->$handler($name, $query))
        return false;
    }

    // Processing the main query
    $query = SuQLParser::getMainQuery($this->suql);
    parent::addQuery('main');

    $handler = SuQLParser::getQueryHandler($query);
    if (!$this->$handler('main', $query))
      return false;

    return true;
  }

  private function SELECT($name, $query)
  {
    $clauses = SuQLParser::parseSelect($query);

    foreach ($clauses['tables'] as $table => $options) {
      if ($options['type'] === 'from')
        parent::addFrom($name, $table);

      else if ($options['type'] === 'join')
        parent::addJoin($name, $options['next'], $table);

      else
        return false;

      if ($options['modifier'] !== '')
        parent::addQueryModifier($name, $options['modifier']);

      if ($options['where'] !== '')
        parent::addWhere($name, $options['where']);

      if ($options['fields'] !== '') {
        $fieldList = SuQLParser::getFieldList($options['fields']);
        for ($i = 0, $n = count($fieldList['name']); $i < $n; $i++) {
          $fieldName = parent::addField(
            $name,
            $table,
            [$fieldList['name'][$i] => $fieldList['alias'][$i]]
          );

          $fieldModifierList = SuQLParser::getFieldModifierList($fieldList['modif'][$i]);
          foreach ($fieldModifierList as $modif => $params) {
            parent::addFieldModifier($name, $fieldName, $modif, $params ? explode(',', $params) : []);
          }
        }
      }
    }

    if (!is_null($clauses['offset'])) parent::addOffset($name, $clauses['offset']);
    if (!is_null($clauses['limit'])) parent::addLimit($name, $clauses['limit']);

    return true;
  }

  private function INSERT($name, $query) {

  }

  private function UPDATE($name, $query) {

  }

  private function DELETE($name, $query) {

  }
}
