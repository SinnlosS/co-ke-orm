<?php
namespace de\coding_keller\ORM;
class Select implements Statement {
  protected $uniqueResult    = false;
  protected $validDirections = array("ASC","DESC");
  protected $validJoinTypes  = array("INNER","LEFT","RIGHT","OUTER");
  protected $repository;
  protected $queryString;
  protected $columns         = array();
  protected $lazyLoaded      = array();
  protected $conditions      = array();
  protected $bindValues      = array();
  protected $orderByParams   = null;
  protected $limitCount      = null;
  protected $limitOffset     = null;
  protected $openParenthesis = 0;
  protected $innerJoins      = array();
  protected $leftJoins       = array();
  protected $type;

  public static function factory(Repository $repository) {
    return new self($repository);
  }
  public function __construct(Repository $repository) {
    $this->repository = $repository;
  }
  public function addColumn($column) {
    $this->columns[] = $column;
    return $this;
  }
  public function addCondition(Condition $condition) {
    $this->conditions[] = $condition;
    return $this;
  }
  public function innerJoin(Repository $repository) {
    $this->innerJoins[] = $repository;
    return $this;
  }
  public function leftJoin(Repository $repository) {
    $this->leftJoins[] = $repository;
    return $this;
  }
  public function __get($property) {
    if(!property_exists($this, $property)) {
      throw new InvalidArgumentException("Invalid property '{$property}'");
    }
    return $this->$property;
  }
  public function __call($name, $arguments) {
    $count = count($arguments);
    if(!$count) {
      return $this->repository->$name();
    }
    elseif($count==1) {
      return $this->repository->$name($arguments[0]);
    }
    return call_user_func_array(array($this->repository,$name),$arguments);
  }
  public function build() {
    $from         = "`{$this->repository->table}`";
    $columns      = implode(",",$this->columns);
    foreach($this->innerJoins as $join) {
      $from.= "INNER JOIN `{$join->table}` ON `{$join->table}`.`{$join->pkField}`=`{$this->repository->table}`.`{$join->table}_{$join->pkField}`";
    }
    foreach($this->leftJoins as $join) {
      $from.= "INNER JOIN `{$join->table}` ON `{$join->table}`.`{$join->pkField}`=`{$this->repository->table}`.`{$join->table}_{$join->pkField}`";
    }
    $this->queryString = "SELECT {$columns} FROM {$from}";
    if(count($this->conditions)) {
      $this->queryString.= " WHERE ";
      foreach($this->conditions as $condition) {
        $this->queryString.= $condition;
        if($condition instanceof Condition) {
          if(!is_array($condition->value)) {
            $this->bindValues[] =& $condition->value;
          }
          else {
            foreach($condition->value as $value) {
              $this->bindValues[] = $value;
            }
          }
        }
      }
    }
    if(count($this->orderByParams)) {
      $this->queryString.= " ORDER BY ".implode(",",$this->orderByParams);
    }
    if($this->limitCount!==null) {
      $this->queryString .= " LIMIT ".(int)$this->limitOffset.",".(int)$this->limitCount;
    }
    return $this;
  }
  public function query() {
    if($this->openParenthesis!=0) {
      $message = $this->openParenthesis>0 ? "Unclosed left parenthesis" : "Unopened right parenthesis";
      throw new Exception("{$message}, can't query {$this->queryString}");
    }
    $res = $this->repository->db->prepare($this->queryString) or die("query: {$this->queryString}\n error: ".$this->repository->db->error);
    if(count($this->bindValues)) {
      $i = 1;
      foreach($this->bindValues as $key=>&$value) {
        $res->bindParam($i,$value);
        $i++;
      }
    }
    $res->execute();
    $return = new ModelList($this->repository);
    $keys   = array();
    while($row=$res->fetch(\PDO::FETCH_ASSOC)) {
      $object = new $this->repository->modelClass($this->repository,$return);
      $object->injectProperties($this->repository->properties);
      foreach($this->innerJoins as $join) {
        if($property->type=="column" && (!$property->isLazy || $complete)) {
          $set       = "set".ucfirst($join->repository->table);
          $setObject = new $join->modelClass($join);
          $setObject->injectProperties($join->properties);
          $object->$set($setObject);
        }
      }
      foreach($this->leftJoins as $join) {
        if($property->type=="column" && (!$property->isLazy || $complete)) {
          $set       = "set".ucfirst($join->table);
          $setObject = new $join->modelClass($join);
          $setObject->injectProperties($join->properties);
          $object->$set($setObject);
        }
      }
      foreach($row as $key=>$value) {
        $object->$key = $value;
      }
      $rpk = $object->{$this->repository->pkField};
      $return[$object->{$this->repository->pkField}] = $object;
      $keys[] = $object->{$this->repository->pkField};
      $this->repository->addToCache($object);
    }
    if($this->repository->loadHavings && count($keys)) {
      foreach($this->repository->hasMany as $name=>$class) {
        $return->lazyLoad($name);
      }
    }
    if($this->uniqueResult) {
      $return = count($return) ? $return->first() : null;
    }
    return $return;
  }
  public function pk($value) {
    if(!isset($this->repository->cache[$value])) {
      $condition = new Condition($this->repository,$this->repository->properties[$this->repository->pkField]);
      $condition->eq($value);
      $this->addCondition($condition);
      $this->uniqueResult = true;
      $object = $this->build()->query();
      if(!$object) {
        return NULL;
      }
    }
    return $this->repository->cache[$value];
  }
  public function unique() {
    $this->uniqueResult = true;
    return $this->build()->query();
  }
  public function all() {
    return $this->build()->query();
  }
  public function filter() {
    return $this->build()->query();
  }
  public function lp() {
    $this->conditions[] = " (";
    $this->openParenthesis++;
    return $this;
  }
  public function rp() {
    $this->conditions[] = ") ";
    $this->openParenthesis--;
    return $this;
  }
  public function _and() {
    $this->conditions[] = " AND ";
    return $this;
  }
  public function _or() {
    $this->conditions[] = " OR ";
    return $this;
  }
  public function _xor() {
    $this->conditions[] = " XOR ";
    return $this;
  }
  public function orderBy($column,$direction="ASC") {
    if($this->orderByParams===null) {
      $this->orderByParams = array();
    }
    if(\is_array($column)) {
      foreach($column as $k=>$v) {
        if(is_string($k)) {
          $this->addOrderByParam($k,$v);
        }
        else {
          $this->addOrderByParam($v,"ASC");
        }
      }
    }
    else {
      $this->addOrderByParam($column,$direction);
    }
    return $this;
  }
  public function limit($count,$offset=0) {
    $this->limitCount  = $count;
    $this->limitOffset = $offset;
    return $this;
  }
  private function addOrderByParam($column,$direction="ASC") {
    if(!isset($this->repository->properties[$column])) {
      throw new \InvalidArgumentException("Can't order by non-existing column '{$column}'");
    }
    if(!in_array(strtoupper($direction),$this->validDirections)) {
      throw new \InvalidArgumentException("Invalid direction '{$direction}'");
    }
    $this->orderByParams[] = "`{$this->repository->table}`.`{$column}` {$direction}";
  }
}
?>