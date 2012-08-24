<?php
namespace de\coding_keller\ORM\Adapter;
interface Adapter {
  public function getName();
  public function repository($repository);
}
?>
