<?php
namespace de\coding_keller\ORM\Adapter;
class PDO extends \PDO implements Adapter {
  protected $identifier   = "PDO";
  private $repositories   = array();
  private $errorLogFolder = NULL;
  public function __construct($host,$user,$pass,$name,$identifier="PDO",$charset="utf8",$errorLogFolder=NULL) {
    if($errorLogFolder!==NULL && is_dir($errorLogFolder)) {
      $this->errorLogFolder = $errorLogFolder;
    }
    $this->identifier = $identifier;
    parent::__construct("mysql:host={$host};dbname={$name}", $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"));
  }
  public function getName() {
    return $this->identifier;
  }
  public function repository($repository) {
    $repository = $repository."_Repository";
    if(!isset($this->repositories[$repository])) {
      $this->repositories[$repository] = new $repository($this);
    }
    return $this->repositories[$repository];
  }
  public function log() {
    if($this->errorLogFolder===NULL) {
      throw new \Exception("No ErrorLogFolder defined");
    }
    $today = date("Y-m-d");
    if(!file_exists($this->errorLogFolder."/$today.txt")) {
      
    }
  }
}
?>
