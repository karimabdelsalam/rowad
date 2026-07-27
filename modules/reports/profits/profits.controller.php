<?php

class profits extends profits_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function index()
  {
    $where = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where = "BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    $results = $this->listrecord($where);
    $this->Smarty->assign('result', $results);
    $this->Output();
  }

}