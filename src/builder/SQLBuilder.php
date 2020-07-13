<?php
use Helper\CArray;

class SQLBuilder
{
  private $osuql = null;
  private $sql = [];

  const SELECT_TEMPLATE = "#select##from##join##where##group##having##order##limit#";
  const REGEX_SUB_QUERY = '/{:v:}(?<name>\w+)/msi';

  function __construct($osuql)
  {
    $this->osuql = $osuql;
  }

  public function getSql($queryList)
  {
    if (empty($this->sql)) return null;

    $sqlList = CArray::slice_by_keys($this->sql, $queryList);

    return count($queryList) === 1 && count($sqlList) === 1
            ? reset($sqlList)
            : $sqlList;
  }

  public function run($queryList)
  {
    if (!$this->osuql->get())
      return;

    $allQueryList = $this->osuql->getAllTheQueryList();

    foreach ($allQueryList as $query) {
      $this->sql[$query] = trim($this->buildQuery($query));
    }

    foreach ($queryList as $query) {
      $this->sql[$query] = $this->composeQuery($query);
    }
  }

  private function buildQuery($query)
  {
    $queryType = $this->osuql->getQueryType($query);
    $handler = 'build'.ucfirst($queryType).'Query';
    return method_exists($this, $handler)
            ? $this->$handler($query)
            : null;
  }

  private function buildSelectQuery($query) {
    $this->prepareQuery($query);

    $selectTemplate = self::SELECT_TEMPLATE;

    $selectTemplate = str_replace('#select#', $this->buildSelect($query), $selectTemplate);
    $selectTemplate = str_replace('#from#'  , $this->buildFrom($query),   $selectTemplate);
    $selectTemplate = str_replace('#join#'  , $this->buildJoin($query),   $selectTemplate);
    $selectTemplate = str_replace('#group#' , $this->buildGroup($query),  $selectTemplate);
    $selectTemplate = str_replace('#where#' , $this->buildWhere($query),  $selectTemplate);
    $selectTemplate = str_replace('#having#', $this->buildHaving($query), $selectTemplate);
    $selectTemplate = str_replace('#order#' , $this->buildOrder($query),  $selectTemplate);
    $selectTemplate = str_replace('#limit#' , $this->buildLimit($query),  $selectTemplate);

    return $selectTemplate;
  }

  private function buildUnionQuery($query) {
    $suqlString = $this->osuql->getQuerySuqlString($query);
    return $suqlString;
  }

  private function composeQuery($query) {
    if (!isset($this->sql[$query]))
      return '';
    $suql = $this->sql[$query];

    $subQueries = (new SuQLRegExp(self::REGEX_SUB_QUERY))->match_all($suql);
    if (empty($subQueries['name']))
      return $suql;
    else {
      foreach ($subQueries['name'] as $subQuery)
        $suql = str_replace(SuQLSpecialSymbols::$prefix_declare_variable . $subQuery, '('.$this->composeQuery($subQuery).')', $suql);

      return $suql;
    }
  }

  private function prepareQuery($query) {
    $queryObject = &$this->osuql->getQuery($query);
    $fieldModifiers = $this->osuql->getFieldModifiers($queryObject);

    foreach ($fieldModifiers as $field => $modifiers) {
      foreach ($modifiers as $modifier => $params) {
        $modifier_handler = "mod_$modifier";
        if (method_exists(SQLModifier::class, $modifier_handler))
          SQLModifier::$modifier_handler($queryObject, $field);
      }
    }
  }

  protected function buildSelect($query) {
    $queryObject = &$this->osuql->getQuery($query);

    $fields = $queryObject['select'];
    $select = !is_null($queryObject['modifier'])
                ? "select {$queryObject['modifier']} "
                : 'select ';

    if (empty($fields))
      return '';

    $selectList = [];
    foreach ($fields as $fieldOptions) {
      if (isset($fieldOptions['visible']) && $fieldOptions['visible'] === false) continue;
      $selectList[] = $fieldOptions['field'] . ($fieldOptions['alias'] ? " as {$fieldOptions['alias']}" : '');
    }

    return $select . implode(', ', $selectList);
  }

  protected function buildFrom($query) {
    $queryObject = &$this->osuql->getQuery($query);

    $from = $queryObject['from'];

    if (empty($from))
      return '';

    $fromQuery = &$this->osuql->getQuery($from);

    return $fromQuery
            ? ' from ' . SuQLSpecialSymbols::$prefix_declare_variable . "{$from} {$from}"
            : " from $from";
  }

  protected function buildJoin($query) {
    $queryObject = &$this->osuql->getQuery($query);

    $join = $queryObject['join'];
    $select = $queryObject['select'];

    foreach ($join as &$_join) {
      $_join['on'] = str_replace(array_column($select, 'alias'), array_column($select, 'field'), $_join['on']);
    }
    unset($_join);

    if (empty($join))
      return '';

    $s = [];
    foreach ($join as $_join) {
      $table = $_join['table'];
      $joinQuery = &$this->osuql->getQuery($table);
      $table = $joinQuery
                ? SuQLSpecialSymbols::$prefix_declare_variable . "$table $table"
                : $table;
      $s[] = "{$_join['type']} join $table on {$_join['on']}";
    }

    return ' ' . implode(' ', $s);
  }

  protected function buildGroup($query) {
    $queryObject = &$this->osuql->getQuery($query);

    $group = $queryObject['group'];
    return !empty($group) ? ' group by ' . implode(', ', $group) : '';
  }

  protected function buildWhere($query) {
    $queryObject = &$this->osuql->getQuery($query);

    $where = implode(' and ', $queryObject['where']);
    if (!$where) return '';

    $select = $queryObject['select'];
    $where = str_replace(array_column($select, 'alias'), array_column($select, 'field'), $where);

    return " where $where";
  }

  protected function buildHaving($query) {
    $queryObject = &$this->osuql->getQuery($query);

    $having = $queryObject['having'];
    return !empty($having) ? ' having ' . implode(' and ', $having) : '';
  }

  protected function buildOrder($query) {
    $queryObject = &$this->osuql->getQuery($query);

    $order = $queryObject['order'];

    if (empty($order))
      return '';

    $s = [];
    foreach ($order as $_order) {
      $s[] = "{$_order['field']} {$_order['direction']}";
    }

    return ' order by ' . implode(', ', $s);
  }

  protected function buildLimit($query) {
    $bound = [];
    $queryObject = &$this->osuql->getQuery($query);

    if (!is_null($queryObject['offset'])) $bound[] = $queryObject['offset'];
    if (!is_null($queryObject['limit'])) $bound[] = $queryObject['limit'];

    $bound = implode(', ', $bound);

    return $bound ? " limit $bound" : '';
  }
}
