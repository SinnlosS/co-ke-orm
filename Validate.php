<?php
namespace de\coding_keller\ORM;
class Validate {
  public static function bool($value) {
    return $value==0 || $value==1;
  }
  public static function email($value) {
    return filter_var($value,FILTER_SANITIZE_EMAIL);
  }
  public static function url($value) {
    return filter_var($value, FILTER_SANITIZE_URL);
  }
  public static function string($value,$minlength=null,$maxlength=null) {
    if(!is_string($value)) {
      throw new \Exception("kein string");
    }
    $value = trim($value);
    if(!is_string($value)) {
      return false;
    }
    if($minlength!=null || $maxlength!=null) {
      $length = strlen($value);
      if(($minlength!=null && $length<$minlength) || ($maxlength!=null && $length>$maxlength)) {
        return false;
      }
    }
    return true;
  }
  public static function int($value,$minvalue=null,$maxvalue=null) {
    if(!ctype_digit($value) && !is_int($value)) {
      return false;
    }
    if(($minvalue!=null && $value<$minvalue) || ($maxvalue!=null && $value>$maxvalue)) {
      return false;
    }
    return true;
  }
  // validates against format YYYY-MM-DD
  public static function date($value) {
    $value = trim($value);
    if($value=="0000-00-00") {
      return true;
    }
    if(strlen($value)!==10) {
      return false;
    }
    if(strstr(strstr($value,"-"),"-")===false) {
      return false;
    }
    list($y,$m,$d) = explode("-",$value);
    return checkdate($m,$d,$y);
  }
  // validates against format HH:II:SS
  public static function time($value) {
    $value = trim($value);
    if(strlen($value)!==8) {
      return false;
    }
    if(strstr(strstr($value,":"),":")===false) {
      return false;
    }
    list($h,$m,$s) = explode(":",$value);
    if($h<0 || $h>23 || $m<0 || $m>59 || $s<0 || $s>59) {
      return false;
    }
    return true;
  }
  // validates against format YYYY-MM-DD HH:II:SS
  public static function datetime($value) {
    $value = trim($value);
    if(!strpos($value, " ")) {
      return false;
    }
    list($date,$time) = explode(" ",$value);
    return self::date($date) && self::time($time);
  }
}
?>
