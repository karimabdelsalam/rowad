<?php

class maintenance extends maintenance_model
{
  private $count = 0;
  private $loop = 0;

  public function __construct()
  {
    parent::__construct();
  }

  public function dbrepair()
  {
    global $db_config;
    $this->count = 2;
    for($i = 0; $i < $this->count; $i++){
      $this->loop++;
      if($this->buildProgress(__FUNCTION__, 2) === false){
        continue;
      }
      if($_SESSION['progressbar'][__FUNCTION__] == 1){
        $tables = $this->db->get_results("SHOW TABLES FROM $db_config[dbname]", 'ARRAY_N');
        if($tables){
          foreach($tables as $table){
            $this->db->query("REPAIR TABLE  `$table[0]`");
          }
        }
      }
    }
  }

  public function backup()
  {
    $this->count = 2;
    for($i = 0; $i < $this->count; $i++){
      $this->loop++;
      if($this->buildProgress(__FUNCTION__, 2) === false){
        continue;
      }
      if($_SESSION['progressbar'][__FUNCTION__] == 1){
        $this->startBackup();
      }
    }
  }

  private function buildProgress($method, $times)
  {
    $limit = ceil($this->count / $times);
    if(empty($_SESSION['progressbar'][$method]) OR $_SESSION['progressbar'][$method] >= $this->count){
      $history = $_SESSION['progressbar'][$method] = 0;
    } else{
      $history = $_SESSION['progressbar'][$method];
    }
    if($this->loop >= ($history + $limit)){
      $_SESSION['progressbar'][$method] = $history + $limit;
      echo ceil(($this->loop / $this->count) * 100);
      exit;
    } elseif($this->loop < $history){
      return false;
    }
  }


  public function index()
  {
    if($_GET['tool']){
      call_user_func_array(array($this, $_GET['tool']), array());
    } else{
      $this->Output();
    }
  }

}