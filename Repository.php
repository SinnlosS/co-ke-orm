<?php
namespace de\coding_keller\ORM;
abstract class Repository {
  protected $db;
  protected $name;
  protected $table;
  protected $pkField;
  protected $modelClass;
  protected $statement;
  protected $properties      = array();
  protected $belongsTo       = array();
  protected $hasMany         = array();
  protected $cachedAll       = false;
  protected $loadHavings     = false;
  protected $__callStatement = array("limit","orderby","_and","_or","rp","lp");
  protected $cache           = array();

  public function belongsTo($class,$name=null,$isLazy=true) {
    $name = !$name ? $class : $name;
    if(!$this->hasProperty($name)) {
      $this->belongsTo[$name] = $class;
      $this->setProperty(new Property($name,array("isLazy"=>$isLazy,"type"=>"belongsTo")));
    }
  }
  public function hasMany($class,$name=null) {
    $name = !$name ? $class : $name;
    if(!$this->hasProperty($name)) {
      $className = $class."_Repository";
      if(!class_exists($className)) {
        throw new \InvalidArgumentException("Can't have many nonexisting classes '{$class}'");
      }
      //$this->hasMany[$class] = $className;
      $this->hasMany[$name] = $class;
      $this->setProperty(new Property($name,array("isLazy"=>true,"type"=>"hasMany")));
    }
  }
  public function __construct(\PDO $db,$table,$pkField,$modelClass) {
    $repository = get_class($this);
    $parts      = explode("_",$repository);
    $trash      = array_pop($parts);
    $this->name = implode("_",$parts);
    $this->db   = $db;
    $this->setTable($table)->setPkField($pkField)->setModelClass($modelClass);
  }
  public function __get($property) {
    if(!property_exists($this,$property)) {
      throw new \Exception("Property $property doesnt exist.");
    }
    return $this->$property;
  }
  public function __call($name, $arguments) {
    if($this->statement===null) {
      throw new \Exception("No Statement defined to build conditions on");
    }
    if(!isset($this->properties[$name]) && !in_array($name, $this->__callStatement)) {
      throw new \Exception("Invalid method-call '{$name}'");
    }
    if(in_array($name,$this->__callStatement)) {
      $count = count($arguments);
      if($count>1) {
        return call_user_func_array(array($this->statement,$name),$arguments);
      }
      elseif($count==1) {
        return $this->statement->$name($arguments[0]);
      }
      else {
        return $this->statement->$name();
      }
    }
    $condition = new Condition($this,$this->properties[$name]);
    $this->statement->addCondition($condition);
    return $condition;
  }
  public function setTable($table) {
    $this->table = $table;
    return $this;
  }
  public function setPkField($pkField) {
    $this->pkField = $pkField;
    return $this;
  }
  public function setModelClass($modelClass) {
    $this->modelClass = $modelClass;
    return $this;
  }
  public function setProperty(Property $property) {
    $this->properties[$property->name] = $property;
    return $this;
  }
  public function setProperties(array $properties) {
    foreach($properties as $property) {
      if(!$property instanceof Property) {
        throw new InvalidArgumentException("Invalid Array-Element for setProperties");
      }
      $this->properties[$property->name] = $property;
    }
    return $this;
  }
  public function getProperty($property) {
    if(!array_key_exists($property,$this->properties)) {
      throw new Exception("Property {$property} doesnt exist");
    }
    return $this->properties[$property];
  }
  public function getProperties() {
    return $this->properties;
  }
  public function hasProperty($property) {
    return isset($this->properties[$property]);
  }
  public function getColumns($complete=false,$asJoin=false) {
    $columns = array();
    foreach($this->properties as $property) {
      if($property->type=="column" && (!$property->isLazy || $complete)) {
        if($asJoin) {
          $columns[] = "`{$this->table}`.`{$property->name}` as `{$this->name}__{$property->name}`";
        }
        else {
          $columns[] = "`{$this->table}`.`{$property->name}`";
        }
      }
    }
    return $columns;
  }
  public function addToCache(Model $object) {
    $this->cache[$object->{$this->pkField}] = $object;
  }
  public function getFromCache($pkValue) {
    if(!isset($this->cache[$pkValue])) {
      return false;
    }
    return $this->cache[$pkValue];
  }
  public function create(array $data) {
    $object = new $this->modelClass($this);
    $object->injectProperties($this->properties);
    $fieldList = array();
    foreach($this->properties as $key=>$value) {
      if($value->type=="column") {
        $object->$key = isset($data[$key]) ? $data[$key] : $value->default;
        $fieldList[] = "`{$value->name}`=:{$value->name}";
      }
    }
    $set = implode(",",$fieldList);
    $sql = "INSERT INTO `{$this->table}` SET {$set}";
    $res = $this->db->prepare($sql);
    foreach($this->properties as $key=>$value) {
      if($value->type=="column") {
        $res->bindParam(":{$value->name}", $object->{$value->name});
      }
    }
    $res->execute();
    if(!$this->db->lastInsertId()) {
      var_dump($res->errorInfo());
      throw new \Exception("Could not create new entry in {$this->table}: ");
    }
    $object->{$this->pkField} = $this->db->lastInsertId();
    unset($res);
    $this->cache[$object->{$this->pkField}] = $object;
    return $object;
  }
  public function save($object) {
    if(!$object instanceof $this->modelClass) {
      throw new \InvalidArgumentException("Argument has to be an instance of {$this->modelClass}");
    }
    $fieldList = array();
    foreach($this->properties as $property) {
      if($property->type=="column") {
        $fieldList[] = "`{$property->name}`=:{$property->name}";
      }
    }
    $set = implode(",",$fieldList);
    $sql = "UPDATE `{$this->table}` SET {$set} WHERE `{$this->pkField}`=:{$this->pkField}";
    $res = $this->db->prepare($sql) or die($this->db->error);
    foreach($this->properties as $property) {
      if($property->type=="column") {
        $res->bindParam(":{$property->name}", $object->{$property->name});
      }
    }
    $res->bindParam(":{$this->pkField}", $object->{$this->pkField});
    $res->execute();
    if($this->db->lastInsertId()) {
      $object->{$this->pkField} = $this->db->lastInsertId();
    }
    unset($res);
    return $this;
  }
  public function find($complete=false) {
    $this->statement = new Select($this);
    $columns = $this->getColumns($complete);
    if($complete) {
      foreach($this->belongsTo as $name=>$class) {
        $repository = $this->db->repository($class);
        $columns = array_merge($columns,$repository->getColumns(true,true));
        $this->statement->leftJoin($repository);
      }
      $this->loadHavings = true;
    }
    else {
      foreach($this->belongsTo as $name=>$class) {
        if(!$this->properties[$name]->isLazy) {
          $repository = $this->db->repository($class);
          $columns = array_merge($columns,$repository->getColumns(true,true));
          $this->statement->leftJoin($repository);
        }
      }
    }
    foreach($columns as $column) {
      $this->statement->addColumn($column);
    }
    return $this->statement;
  }
  public function lazyLoad(Property $property,$object) {
    $result = null;
    if($object instanceof ModelList) {
      $objectIDs = array();
      foreach($object as $k=>$v) {
        $objectIDs[] = $k;
      }
    }
    switch($property->type) {
      case "column":
        if($object instanceof Model) {
          $statement = Select::factory($this)
                                        ->addColumn("`{$property->name}`")
                                        ->addCondition(Condition::factory($this,$this->properties[$this->pkField])->eq($object->{$this->pkField}))
                                        ->build();
          $res = $this->db->prepare($statement->queryString);
          $res->bindParam(1,$object->{$this->pkField});
          $res->execute();
          $row    = $res->fetch(\PDO::FETCH_ASSOC);
          $result = $row[$property->name];
        }
        elseif($object instanceof ModelList) {
          $field = $this->pkField;
          $temp  = Select::factory($this)
                    ->addColumn("`{$this->pkField}`")->addColumn("`{$property->name}`")
                    ->addCondition(Condition::factory($this,$this->properties[$this->pkField])->in($objectIDs))
                    ->filter();
          $result = array();
          foreach($temp as $k=>$v) {
            $result[$k] = $v->{$property->name};
          }
        }
        else {
          throw new \Exception("Inproper value for \$object.");
        }
        break;
      case "hasMany":
        $repository = $this->db->repository($this->hasMany[$property->name]);
        if($object instanceof Model) {
          $field  = "{$this->table}_{$this->pkField}";
          $result = $repository
                     ->find()
                     ->filter( $repository->$field()->eq($object->{$this->pkField}) );
        }
        elseif($object instanceof ModelList) {
          $field = "{$this->table}_{$this->pkField}";
          $temp  = $repository
                    ->find()
                    ->filter( $repository->$field()->in($objectIDs)->orderby($field) );
          $result    = array();
          $curPk     = null;
          $modelList = null;
          foreach($temp as $k=>$v) {
            if($v->$field!=$curPk) {
              if($modelList!=null) {
                $result[$curPk] = $modelList;
              }
              $curPk     = $v->$field;
              $modelList = new ModelList($this, $temp);
            }
            $modelList[$k] = $v;
          }
          if($modelList!=null) {
            $result[$curPk] = $modelList;
          }
        }
        else {
          throw new \Exception("Inproper value for \$object.");
        }
        break;
      case "belongsTo":
        $btRepo  = $this->db->repository($this->belongsTo[$property->name]);
        $field   = $btRepo->pkField;
        $fkField = "{$btRepo->table}_{$btRepo->pkField}";
        if($object instanceof Model) {
          $result  = $btRepo->find()->pk( $object->$fkField );
        }
        elseif($object instanceof ModelList) {
          $fkValues = array();
          foreach($object as $o) {
            $fkValues[] = $o->$fkField;
          }
          $temp = $btRepo->find()->filter( $btRepo->$field()->in($fkValues) );
          foreach($object as $o) {
            $result[$o->{$this->pkField}] = $temp[$o->$fkField];
          }
        }
        else {
          throw new \Exception("Inproper value for \$object.");
        }
        break;
      default:
        echo $property->name." - ".$property->type."\n";
        break;
    }
    return $result;
  }
  public function delete($object) {
    if(!$object instanceof $this->modelClass) {
      throw new \InvalidArgumentException("Argument has to be an instance of {$this->modelClass}");
    }
    $sql = "DELETE FROM `{$this->table}` WHERE `{$this->pkField}`=:{$this->pkField}";
    $res = $this->db->prepare($sql);
    $res->bindParam(":{$this->pkField}",$object->{$this->pkField});
    $res->execute();
    unset($res);
    return $this;
  }
  public function saveAll(array $objects) {
    foreach($objects as $object) {
      if(!$object instanceof $this->modelClass) {
        throw new InvalidArgumentException("Argument has to be an instance of {$this->modelClass}");
      }
    }
    $fieldList   = array();
    $bindVars    = array("");
    $tempData    = array();
    foreach($this->properties as $property) {
      $tempData[$property->name] = "";
      $fieldList[] = "`{$property->name}`=?";
      $bindVars[0].= $property->type;
      $bindVars[]  =& $tempData[$property->name];
    }
    $set  = implode(",",$fieldList);
    $sql  = "REPLACE INTO `".TABLE_PREFIX."{$this->table}` SET {$set}";
    $res  = $this->db->prepare($sql);
    $mock = new ReflectionClass("mysqli_stmt");
    $bind = $mock->getMethod("bind_param");
    $bind->invokeArgs($res,$bindVars);
    foreach($objects as $object) {
      if(!$object instanceof $this->modelClass) {
        throw new InvalidArgumentException("Argument has to be an instance of {$this->modelClass}");
      }
      foreach($this->properties as $property) {
        $tempData[$property->name] = $object->{$property->name};
      }
      $res->execute();
    }
    unset($mock,$bind,$res);
    return $this;
  }
}
?>
