<?php

namespace ownerarea;

class payments extends \ownerarea\payments_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function printable()
  {
    $bool = $this->hasAuth($_GET['id']);
    if(! $bool) {
      $this->showmsg(gettext('غير مصرح لك الوصول الى هنا'), 0);
    }

    $payment = $this->auto_load('pays_pending');
    $payment->printable();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "payments.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['buyerid']){
      $this->Smarty->assign('buyer', $this->auto_load_read($_GET['buyerid'], 'buyer'));
      $where[] = "JSON_CONTAINS(JSON_EXTRACT(rent_contracts.buyer, '$.id[*]'), '\"$_GET[buyerid]\"', '$')";
    }
    if($_GET['buildid']){
      $buildtitle = $this->db->get_var("SELECT title FROM building WHERE id='$_GET[buildid]'");
      $this->Smarty->assign('buildtitle', $buildtitle);
      $where[] = "payments.buildid='$_GET[buildid]'";
    }
    if($_GET['rentid']){
      $buildid = $this->db->get_var("SELECT buildid FROM rent_contracts WHERE id='$_GET[rentid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "payments.contractid='$_GET[rentid]' AND module='rent'";
    }
    if($_GET['sellid']){
      $buildid = $this->db->get_var("SELECT buildid FROM sell_contracts WHERE id='$_GET[sellid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "payments.contractid='$_GET[sellid]' AND module='sell'";
    }
    if($_GET['type']){
      switch($_GET['type']) {
        case 'gone':
          $where[] = "payments.gone='1'";
          break;
        case 'pending':
          $where[] = "payments.gone='0' AND paydate>NOW()";
          break;
        case 'late':
          $where[] = "payments.gone='0' AND paydate<NOW()";
          break;
      }
    }

    $url = parse_url($_SERVER['REQUEST_URI']);
    $this->Smarty->assign('params', preg_replace('/type=([a-zA-Z]+)&?/', '', $url['query']));

    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Smarty->assign('builds', $this->ownerBuilds());
    $this->Smarty->assign('clients', $this->ownerClients());
    $this->enqueueJSLibrary('charts');
    $this->Output('', $results);
  }

}
