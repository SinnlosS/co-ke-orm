<?php
namespace de\coding_keller\ORM;
abstract class Model {
  protected $repository;
  protected $modelList  = null;
  protected $properties = array();

  public function __construct(Repository $repository,ModelList $modelList=null) {
    $this->repository = $repository;
    $this->modelList  = $modelList;
  }
  public function &__get($property) {
    if(!isset($this->properties[$property]) && !property_exists($this, $property)) {
      throw new \Exception("Property {$property} doesn't exist");
    }
    if(!$this->properties[$property]['loaded']) {
      if($this->modelList instanceOf ModelList) {
        $this->modelList->lazyLoad($this->properties[$property]['object']);
      }
      else {
        $this->$property = $this->repository->lazyLoad($this->properties[$property]['object'],$this);
      }
    }
    return $this->$property;
  }
  public function __set($property,$value) {
    if(strpos($property, "__")) {
      list($object,$property) = explode("__",$property);
      if($object!==NULL) {
        $this->$object->$property = $value;
        $this->properties[$object]['loaded'] = true;
      }
      return;
    }
    if(!isset($this->properties[$property]) && !property_exists($this, $property)) {
      throw new \Exception("Property {$property} doesn't exist");
    }
    $method = "set".ucfirst($property);
    if(!isset($this->properties[$property]) && !method_exists($this, $method)) {
      throw new \Exception("No Setter-Method found for Property {$property}");
    }
    elseif(isset($this->properties[$property])) {
      if(!$this->properties[$property]['object']->validate($value)) {
        throw new \InvalidArgumentException("Invalid argument for Property {$property}");
      }
      $this->$property = $value;
      $this->properties[$property]['loaded'] = true;
    }
    else {
      $this->$method($value);
      $this->properties[$property]['loaded'] = true;
    }
  }
  public function injectProperties(array $properties) {
    foreach($properties as $property) {
      $this->properties[$property->name]['object'] = $property;
      $this->properties[$property->name]['loaded'] = false;
    }
  }
  public function asArray($full=true) {
    $data = array();
    if($full) {
      foreach($this->properties as $property) {
        if($property['loaded']) {
          continue;
        }
        $name = $property['object']->name;
        if($this->modelList instanceOf ModelList) {
          $this->modelList->lazyLoad($this->properties[$name]['object']);
        }
        else {
          $this->$name = $this->repository->lazyLoad($this->properties[$name]['object'],$this);
        }
        $property['loaded'] = true;
      }
    }
    foreach(get_object_vars($this) as $k=>$v) {
      if($k!="properties" && $k!="repository" && $k!="modelList") {
        if(is_array($v) || $v instanceof ModelList) {
          $data[$k] = array();
          foreach($v as $k2=>$v2) {
            $data[$k][$k2] = $v2 instanceof Model ? $v2->asArray(false) : $v2;
          }
        }
        else {
          $data[$k] = $v instanceof Model ? $v->asArray(false) : $v;
        }
      }
    }
    return $data;
  }
  public function asJson($full=true) {
    return json_encode($this->asArray($full));
  }
  public function _dump($full=true) {
    echo "<pre>",print_r($this->asArray($full),1),"</pre>";
  }
}
?>
