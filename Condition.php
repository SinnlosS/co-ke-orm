<?php
namespace de\coding_keller\ORM;
class Condition {
  protected $repository;
  protected $property;
  protected $condition;
  protected $value;
  protected $__callWhitelist = array("_and","_or","_xor","rp","orderby","limit","lp");
  public static function factory(Repository $repository,Property $property) {
    return new self($repository,$property);
  }
  public function __construct(Repository $repository,Property $property) {
    $this->repository = $repository;
    $this->property   = $property;
    $this->condition  = "`{$repository->table}`.`{$property->name}`";
  }
  public function &__get($property) {
    if(!property_exists($this,$property)) {
      throw new \Exception("Property '$property' doesnt exist.");
    }
    return $this->$property;
  }
  public function  __toString() {
    return $this->condition;
  }
  public function  __call($name, $arguments) {
    if(!in_array($name,$this->__callWhitelist)) {
      throw new \InvalidArgumentException("Invalid call to nonexisting method '{$name}'");
    }
    $count = count($arguments);
    if($count>1) {
      return call_user_func_array(array($this->repository,$name),$arguments);
    }
    elseif($count==1) {
      return $this->repository->$name($arguments[0]);
    }
    else {
      return $this->repository->$name();
    }
  }
  public function eq($value) {
    $this->value     = $value;
    $this->condition.= "=?";
    return $this;
  }
  public function neq($value) {
    $this->value     = $value;
    $this->condition.= "!=?";
    return $this;
  }
  public function gt($value) {
    $this->value     = $value;
    $this->condition.= ">?";
    return $this;
  }
  public function lt($value) {
    $this->value     = $value;
    $this->condition.= "<?";
    return $this;
  }
  public function gte($value) {
    $this->value     = $value;
    $this->condition.= ">=?";
    return $this;
  }
  public function lte($value) {
    $this->value     = $value;
    $this->condition.= "<=?";
    return $this;
  }
  public function like($value) {
    $this->value     = $value;
    $this->condition.= " LIKE ?";
    return $this;
  }
  public function in(array $values=null) {
    $this->condition.= " IN(";
    $this->value     = array();
    $placeHolder     = array();
    $values          = $values===null ? func_get_args() : $values;
    if(!count($values)) {
      throw new InvalidArgumentException("No Parameter passed for IN-Clause");
    }
    foreach($values as $value) {
      $placeHolder[]  = "?";
      $this->value[]  = $value;
    }
    $this->condition.= implode(",",$placeHolder).")";
    return $this;
  }
}
?>
