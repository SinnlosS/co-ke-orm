<?php
namespace de\coding_keller\ORM;
spl_autoload_register("\de\coding_keller\ORM\Autoloader::classes");
spl_autoload_register("\de\coding_keller\ORM\Autoloader::interfaces");
abstract class Autoloader {
  public static function classes($class) {
    if(!substr($class, 0, 21)=="\de\coding_keller\ORM") {
      return false;
    }
    $class = substr($class, 21);
    $file  = __DIR__.DIRECTORY_SEPARATOR.str_replace("\\",DIRECTORY_SEPARATOR,$class).".php";
    return self::load($file);
  }
  public static function interfaces($interface) {
    if(!substr($interface, 0, 21)=="\de\coding_keller\ORM") {
      return false;
    }
    $interface = substr($interface, 21);
    $file      = __DIR__.DIRECTORY_SEPARATOR."interfaces".DIRECTORY_SEPARATOR.str_replace("\\",DIRECTORY_SEPARATOR,$interface).".php";
    return self::load($file);
  }
  private static function load($file) {
    if (!file_exists($file)) {
      return false;
    }
    require $file;
  }
}
?>
