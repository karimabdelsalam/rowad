<?php

namespace clientarea;

class units extends \clientarea\units_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function view()
  {
    $this->Smarty->assign('build', $this->readrecord($_GET['id']));
    $this->Output('printable');
  }

  public function printable()
  {
    $this->Smarty->assign('build', $this->readrecord($_GET['id']));
    $this->printVersion();
  }

  public function index(){
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "title LIKE '%$_GET[title]%'";
    }
    if($_GET['type']){
      $where[] = "type='$_GET[type]'";
    }
    if($_GET['locid']){
      $where[] = "locid='$_GET[locid]'";
    }

    $results = $this->listrecord($where, true, $inner);

    if($results){
      $pays_pending = $this->auto_load('pays_pending');
      $rent_contracts = $this->auto_load('rent');
      $sell_contracts = $this->auto_load('sell');
      foreach($results as $key => $result){
        if($result['type'] == 'rent' && !empty($result['contractid'])){
          $results[$key]['nextpayment'] = $pays_pending->rentPaymentInfo($result['contractid']);
          $results[$key]['contract_info'] = $rent_contracts->readrecord($result['contractid'], true);
        } elseif($result['type'] == 'sale' && !empty($result['contractid'])) {
          $results[$key]['nextpayment'] = $pays_pending->salePaymentInfo($result['contractid']);
          $results[$key]['contract_info'] = $sell_contracts->readrecord($result['contractid'], true);
        }
      }
    }

    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
