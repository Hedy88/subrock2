<?php
namespace ChenTube;
use PDO;
use PDOException;

/**
 * ChenTube MySQL Lib
 *
 * @author billygoat891
*/
class MySQL {
  public function __construct($host, $db, $username, $password)
  {
      try {
          // New connection
          $this->conn = new PDO("mysql:host=$host;dbname=$db", $username, $password, [
            PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES		=> false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = 'America/Chicago'"
          ]);
        
          // set the PDO error mode to exception
          $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } 
        catch(PDOException $e) {
          echo $e->getMessage();
        }
  }

  public function query($query, $params = [])
  {
    $stmt = $this->conn->prepare($query);
    $stmt->execute($params);

    return $stmt;
  }
}


?>