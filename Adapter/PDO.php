<?php
namespace de\coding_keller\ORM\Adapter;
class PDO extends \PDO implements Adapter {
  protected $identifier     = "PDO";
  private $repositories     = array();
  public function __construct($host,$user,$pass,$name,$identifier="PDO",$charset="utf8") {
    $this->identifier = $identifier;
    parent::__construct("mysql:host={$host};dbname={$name}", $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"));
  }
  public function getName() {
    return $this->identifier;
  }
  public function repository($repository,$calledByClass=NULL) {
    $repository = $repository."_Repository";
    if(!isset($this->repositories[$repository])) {
      $this->repositories[$repository] = new $repository($this);
    }
    return $this->repositories[$repository];
  }
}
?>
