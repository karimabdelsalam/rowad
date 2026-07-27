<?php

namespace ownerarea;

class sell extends \ownerarea\sell_model
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
    $sell = $this->auto_load('sell');
    $sell->printable();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['id']){
      $where[] = "sell_contracts.buildid='$_GET[id]'";
      $this->Smarty->assign('build', $this->auto_load_read($_GET['id'], 'building'));
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output('index');
  }

}
