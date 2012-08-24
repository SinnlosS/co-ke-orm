<?php
/*
 * Demo showing how to use co-ke-orm
 * co-ke-orm requires PHP-Version 5.3+
 * 
 * First of all include the Autoloader:
 */
require_once 'Autoloader.php';

/*
 * Next Step we register and fetch our PDO-Adapter 
 */
de\coding_keller\ORM\Adapter\Registry::getInstance()
        ->registerAdapter(
                new de\coding_keller\ORM\Adapter\PDO(
                        "dbHost","dbUser","dbPass","dbName"
                )
);
$adapter = \de\coding_keller\ORM\Adapter\Registry::getInstance()->getAdapter();
/*
 * Now we can build our repositories and models.
 * Let's start with mapping a user-table
 */
class User_Model extends \de\coding_keller\ORM\Model {}
class User_Repository extends \de\coding_keller\ORM\Repository {
  public function __construct(PDO $db) {
    // Set PDO-Object, tablename, PK-Field and name of Model-Class
    parent::__construct($db,"user","id","User_Model");
    // Set table-columns
    $this->setProperties(
      array(
        new \de\coding_keller\ORM\Property("id"),
        new \de\coding_keller\ORM\Property("name"),
        new \de\coding_keller\ORM\Property("group_id"),
        new \de\coding_keller\ORM\Property("registerDate")
      )
    );
    // A user belongs to a group:
    $this->belongsTo("Group");
  }
}

/*
 * Now let's map group and comment tables
 */
class Group_Model extends \de\coding_keller\ORM\Model {}
class Group_Repository extends \de\coding_keller\ORM\Repository {
  public function __construct(PDO $db) {
    parent::__construct($db,"group","id","Group_Model");
    $this->setProperties(
      array(
        new \de\coding_keller\ORM\Property("id"),
        new \de\coding_keller\ORM\Property("name")
      )
    );
    // A group has many users
    $this->hasMany("User");
  }
}

/*
 * Alright, now we have built the Repositories and Models, let's start
 * Fetch the Repositories from the adapter
 */
$UserRepository    = $adapter->repository("User");
$GroupRepository   = $adapter->repository("Group");

// Let's create some groups
$adminGroup = $GroupRepository->create(
        array(
           "name"=>"Admin" 
        )
);
$userGroup = $GroupRepository->create(
        array(
            "name"=>"User"
        )
);

// Now lets create some Users
$admin = $UserRepository->create(
        array(
            "name"         => "Peter",
            "group_id"     => $adminGroup->id,
            "registerDate" => date("Y-m-d")
        )
);
$user_1 = $UserRepository->create(
        array(
            "name"         => "Michael",
            "group_id"     => $userGroup->id,
            "registerDate" => date("Y-m-d")
        )
);
$user_2 = $UserRepository->create(
        array(
            "name"         => "Andrea",
            "group_id"     => $userGroup->id,
            "registerDate" => date("Y-m-d")
        )
);

/*
 * Now let's do some finding in our repositories
 */
$allGroups = $GroupRepository->find()->all();
foreach($allGroups as $group) {
  echo "Group '{$group->name}' has ".$group->User->count()." Users:<br/>";
  foreach($group->User as $user) {
    echo "> {$user->name}<br/>";
  }
  echo "------------";
}
?>
