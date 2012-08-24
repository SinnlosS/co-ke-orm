<?php
namespace de\coding_keller\ORM\Adapter;
class Registry {
  protected static $instance = null;
  protected $adapters        = array();
  protected $default         = "PDO";

  protected function __construct() {}
  private final function __clone() {}
  public static function getInstance() {
    if(self::$instance===null) {
      self::$instance = new self;
    }
    return self::$instance;
  }
  public function registerAdapter(Adapter $adapter) {
    $this->adapters[$adapter->getName()] = $adapter;
  }
  public function getAdapter($name="default") {
    $name = $name=="default" ? $this->default : $name;
    if(!isset($this->adapters[$name])) {
      throw new InvalidArgumentException("Unknown Adapter '{$name}'");
    }
    return $this->adapters[$name];
  }
}
?>