<?php

namespace ownerarea;

class units extends \ownerarea\units_model
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
    if($_GET['available']){
      $where[] = "available='" . intval($_GET['available']) . "'";
    }
    if($_GET['status']){
      $where[] = "active='" . intval($_GET['status']) . "'";
    }
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "timepost BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    $results = $this->listrecord($where, true, $inner);

    if($results){
      $pays_pending = $this->auto_load('pays_pending');
      $rent_contracts = $this->auto_load('rent');
      $sell_contracts = $this->auto_load('sell');
      $building = $this->auto_load('building');
      $results[0]['total_amounts'] = 0;
      $results[0]['total_paid'] = 0;
      foreach($results as $key => $result){
        $results[$key]['unit_info'] = $building->readrecord($result['id']);
        if($result['type'] == 'rent' && !empty($result['contractid'])){
          $results[$key]['nextpayment'] = $pays_pending->rentPaymentInfo($result['contractid']);
          $results[$key]['contract_info'] = $rent_contracts->readrecord($result['contractid'], true);
          $openContracts = $this->db->get_results("SELECT startdate,enddate FROM `rent_contracts` WHERE buildid='$result[id]' AND startdate BETWEEN DATE(NOW() - INTERVAL 2 YEAR) AND DATE(NOW() + INTERVAL 5 YEAR) ORDER BY startdate ASC");
          if($openContracts){
            foreach($openContracts as $openContract){
              $results[$key]['contracts_timeline'][] = [ 'start' => $openContract['startdate'], 'end' => $openContract['enddate'] ];
            }
          }
        } elseif($result['type'] == 'sale' && !empty($result['contractid'])) {
          $results[$key]['nextpayment'] = $pays_pending->salePaymentInfo($result['contractid']);
          $results[$key]['contract_info'] = $sell_contracts->readrecord($result['contractid'], true);
        }
        if(defined('API_ENABLED') && API_ENABLED) {
          $results[$key]['payments']['paid'] = $this->db->get_results("SELECT * FROM `payments` WHERE buildid='$result[id]' AND contractid='$result[contractid]' AND gone='1'");
          $results[$key]['payments']['pending'] = $this->db->get_results("SELECT * FROM `payments` WHERE buildid='$result[id]' AND contractid='$result[contractid]' AND gone='0'");
          $results[0]['total_amounts'] += $results[$key]['contract_info']['alltotal'] ?? 0;
          $results[0]['total_paid'] += $results[$key]['contract_info']['allpaid'] ?? 0;
        }
      }
    }

    $this->Smarty->assign('results', $results);
    if($_GET['ownerid']){
      $this->Smarty->assign('owner', $this->auto_load_read($_GET['ownerid'], 'owner'));
    }
    $this->Smarty->assign('locations', $this->auto_load_list('building/locations'));

    $this->enqueueJSLibrary('timeline');
    $this->Output();
  }

}
