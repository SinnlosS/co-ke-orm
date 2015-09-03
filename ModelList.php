<?php
namespace de\coding_keller\ORM;
class ModelList implements \ArrayAccess,\Iterator {
  protected $repository;
  protected $parentList = null;
  protected $position   = 0;
  protected $keyMap     = array();
  protected $models     = array();

  public function __construct(Repository $repository,$parentList=null) {
    $this->repository = $repository;
    $this->parentList = $parentList;
  }
  public function lazyLoad(Property $property) {
    if($this->parentList instanceof ModelList) {
      $this->parentList->lazyLoad($property);
      return $this;
    }
    $clone   = clone $this;
    $pValues = $this->repository->lazyLoad($property,$clone);
    $clone   = null;
    foreach($pValues as $key=>$val) {
      $this->models[$key]->{$property->name} = $val;
    }
    return $this;
  }
  public function sort($property,$direction="asc") {
    $direction = strtolower($direction);
    $switched = false;
    $keyMap   = array();
    $models   = $this->models;
    if($direction==="desc") {
      do {
        foreach($models as $key=>$model) {
          $cur = !isset($cur)||$model->$property>$cur->$property?$model:$cur;
        }
        $keyMap[] = $cur->id;
        unset($models[$cur->id]);
      } while(count($models));
    }
    else {
      do {
        foreach($models as $key=>$model) {
          $cur = !isset($cur)||$model->$property<$cur->$property?$model:$cur;
        }
        $keyMap[] = $cur->id;
        unset($models[$cur->id]);
      } while(count($models));
    }
    $this->keyMap;
  }
  public function avg($column) {
    $i = 0;
    $s = 0;
    foreach($this->models as $model) {
      $s+= is_array($model->$column) || $model->$column instanceof ModelList ? count($model->$column) : $model->$column;
      $i++;
    }
    return $s/$i;
  }
  public function sum($column) {
    $s = 0;
    foreach($this->models as $model) {
      $s+= is_array($model->$column) || $model->$column instanceof ModelList ? count($model->$column) : $model->$column;
    }
    return $s;
  }
  public function max($column,$returnModel=false) {
    $max = null;
    foreach($this->models as $model) {
      if(is_array($model->$column) || $model->$column instanceof ModelList) {
        $cnt = count($model->$column);
        if(!$returnModel) {
          $max = $cnt>$max ? $cnt : $max;
        }
        else {
          $max = $cnt>count($max->$column) ? $model : $max;
        }
      }
      else {
        if(!$returnModel) {
          $max = $model->$column>$max ? $model->$column : $max;
        }
        else {
          $max = $model->$column>$max->$column ? $model : $max;
        }
      }
    }
    return $max;
  }
  public function min($column,$returnModel=false) {
    $min = null;
    foreach($this->models as $model) {
      if(is_array($model->$column) || $model->$column instanceof ModelList) {
        $cnt = count($model->$column);
        if(!$returnModel) {
          $min = $cnt<$min ? $cnt : $min;
        }
        else {
          $min = $cnt<count($min->$column) ? $model : $min;
        }
      }
      else {
        if(!$returnModel) {
          $min = $model->$column<$min ? $model->$column : $min;
        }
        else {
          $min = $model->$column<$min->$column ? $model : $min;
        }
      }
    }
    return $min;
  }
  public function first() {
    return count($this->models) && isset($this->models[$this->keyMap[0]]) ? $this->models[$this->keyMap[0]] : null;
  }
  public function count() {
    return count($this->models);
  }
  public function matching($property,$value) {
    if(!$this->repository->hasProperty($property)) {
      throw new \Exception("Unknown Property '{$property}'");
    }
    $matchResults = array();
    foreach($this->models as $model) {
      if($model->$property!=$value) {
        continue;
      }
      $matchResults[] = $model;
    }
    return $matchResults;
  }
  public function offsetExists($offset) {
    return isset($this->models[$offset]);
  }
  public function offsetGet($offset) {
    return isset($this->models[$offset]) ? $this->models[$offset] : null;
  }
  public function offsetSet($offset,$value) {
    $this->models[$offset] = $value;
    $this->keyMap[]        = $offset;
  }
  public function offsetUnset($offset) {
    unset($this->models[$offset]);
  }
  public function rewind() {
    $this->position = 0;
  }
  public function valid() {
    return $this->position < count($this->keyMap);
  }
  public function key() {
    return $this->keyMap[$this->position];
  }
  public function current() {
    return $this->models[$this->keyMap[$this->position]];
  }
  public function next() {
    $this->position++;
  }
  public function _dump() {
    foreach($this->models as $model) {
      $model->_dump();
    }
  }
}
?>
