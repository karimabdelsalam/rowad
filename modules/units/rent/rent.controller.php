<?php

namespace ownerarea;

class rent extends \ownerarea\rent_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function printable()
  {
    $bool = $this->hasAuthContract($_GET['id']);
    if(! $bool) {
      $this->showmsg(gettext('غير مصرح لك الوصول الى هنا'), 0);
    }
    $rent = $this->auto_load('rent');
    $rent->printable();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['id']){
      $where[] = "rent_contracts.buildid='$_GET[id]'";
      $this->Smarty->assign('build', $this->auto_load_read($_GET['id'], 'building'));
    }
    if(Action == 'archive'){
      $where[] = "rent_contracts.archive='1'";
    } else{
      $where[] = "rent_contracts.archive='0'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output('index');
  }

}
