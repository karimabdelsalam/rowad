<?php

namespace ownerarea;

class transactions extends \ownerarea\transactions_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function printstatin()
  {
    $bool = $this->hasAuthStatIn($_GET['id']);
    if(! $bool) {
      $this->showmsg(gettext('غير مصرح لك الوصول الى هنا'), 0);
    }
    $stat = $this->auto_load('statementsin');
    $stat->printable();
  }

  public function printstatout()
  {
    $stat = $this->auto_load('statementsout');
    $stat->printable();
  }

  public function index()
  {
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "transactions.paydate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['typeid']){
      $where[] = "transactions.typeid='$_GET[typeid]'";
    }
    if($_GET['buildid']){
      $buildtitle = $this->db->get_var("SELECT title FROM building WHERE id='$_GET[buildid]'");
      $this->Smarty->assign('buildtitle', $buildtitle);
      $where[] = "transactions.buildid='$_GET[buildid]'";
    }
    if($_GET['rentid']){
      $buildid = $this->db->get_var("SELECT buildid FROM rent_contracts WHERE id='$_GET[rentid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "transactions.contractid='$_GET[rentid]' AND transactions.module='rent'";
    }
    if($_GET['sellid']){
      $buildid = $this->db->get_var("SELECT buildid FROM sell_contracts WHERE id='$_GET[sellid]'");
      $this->Smarty->assign('build', $this->auto_load_read($buildid, 'building'));
      $where[] = "transactions.contractid='$_GET[sellid]' AND transactions.module='sell'";
    }
    if($_GET['type']){
      switch($_GET['type']) {
        case 'outgoing':
          $where[] = "transactions.gone='1'";
          break;
        case 'pending':
          $where[] = "transactions.gone='0'";
          break;
      }
    }
    $url = parse_url($_SERVER['REQUEST_URI']);
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('params', preg_replace('/type=([a-zA-Z]+)&?/', '', $url['query']));
    $this->Smarty->assign('transtypes', $this->listTypes());
    $this->Smarty->assign('builds', $this->ownerBuilds());
    $this->Smarty->assign('results', $results);
    $this->Output('list');
  }

}
