<?php
namespace de\coding_keller\ORM;
class Property {
  protected $name;
  protected $min = 0;
  protected $max = 0;
  protected $validate = null;
  protected $default = null;
  protected $isLazy = false;
  protected $type = "column";
  protected $validTypes = array("column","belongsTo","hasMany");

  public function __construct($name,array $values=array()) {
    $this->setName($name);
    foreach($values as $property=>$value) {
      if(\property_exists($this, $property)) {
        $this->$property = $value;
      }
    }
  }
  public function __set($key,$value) {
    $method = "set".ucfirst($key);
    if(!method_exists($this,$method)) {
      throw new \Exception("No setter found for property {$key}");
    }
    $this->$method($value);
  }
  public function __get($key) {
    if(!property_exists($this, $key)) {
      throw new Exception("Property {$key} doesnt exist.");
    }
    return $this->$key;
  }
  public function validate($value) {
    if(substr($this->validate,0,2)=="::") {
      $class = substr($this->validate,2);
      if($this->type=="hasMany") {
        $valid = true;
        if(!is_array($value)) {
          return false;
        }
        foreach($value as $object) {
          if(!$object instanceof $class) {
            return false;
          }
        }
        return true;
      }
      return $value instanceof $class;
    }
    $validate = $this->validate;
    return $validate===NULL ? true : Validate::$validate($value,$this->min,$this->max);
  }
  public function setName($name) {
    if(!Validate::string($name,1)) {
      throw new \Exception("Invalid Name For Property");
    }
    $this->name = $name;
    return $this;
  }
  public function setMin($min) {
    $this->min = intval($min);
    return $this;
  }
  public function setMax($max) {
    $this->max = intval($max);
    return $this;
  }
  public function setValidate($method) {
    if($method===null) {
      return $this;
    }
    if(substr($method,0,2)=="::") {
      $class = substr($method,2);
      if(!class_exists($class)) {
        throw new \Exception("Unknown validate-class '{$class}'");
      }
    }
    elseif(!is_callable(array("\de\coding_keller\ORM\Validate",$method))) {
      throw new \Exception("Unknown validate-method '{$method}'");
    }
    $this->validate = $method;
    return $this;
  }
  public function setDefault($defaultValue) {
    $this->default = $defaultValue;
    return $this;
  }
  public function setIsLazy($isLazy) {
    $this->isLazy = (bool)$isLazy;
    return $this;
  }
  public function setType($type) {
    if(!in_array($type,$this->validTypes)) {
      throw new Exception("Unknown Property-Type '{$type}'");
    }
    $this->type = $type;
  }
}
?>
