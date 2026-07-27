<?php

class units extends units_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function index()
  {
    $this->Smarty->assign('start_year', date('Y')-10);

    if($_GET['fdate'] && $_GET['todate'] && $_GET['year']){
      $where = "BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    } else {
      $_GET['year'] = date('Y');
      $this->Output();
      exit;
    }
    $results = $this->listrecord($where);
    $this->Smarty->assign('results', $results);

    $this->enqueueJSLibrary('charts');
    $this->Output('', $results);
  }

}
