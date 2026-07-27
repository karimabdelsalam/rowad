<?php

class building_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function getBuilding($id)
  {
    return $this->db->get_row("SELECT id,title FROM building WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '', $buildid=0)
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    INNER JOIN payment_types ON(payment_types.name=payments.type) $inner WHERE 1 $where ORDER BY payments.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $results[0]['report']['totalAmounts'] = $this->db->get_var("SELECT SUM(amount) FROM payments $inner WHERE 1 $where");
      $results[0]['report']['contracts'] = $this->db->get_results("SELECT payments.contractid, SUM(payments.amount) AS counterValue FROM payments WHERE 1 $where GROUP BY payments.contractid");

      $results[0]['report']['delays'] = $this->db->get_results("SELECT payments.contractid, COUNT(payments.id) AS counterValue FROM payments WHERE 1 $where AND `gone`=1 AND DATEDIFF(`gone_date`,`paydate`) > 20 GROUP BY payments.contractid");
      $results[0]['report']['debits'] = $this->db->get_results("SELECT payments.contractid, SUM(payments.amount) AS counterValue FROM payments WHERE 1 $where AND `gone`=0 AND `paydate` < NOW() GROUP BY payments.contractid");

      $results[0]['report']['outcome'] = $this->db->get_var("SELECT SUM(amount) FROM statement_out WHERE issueddate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "' AND buildid='$buildid'");
      $results[0]['report']['income'] = $this->db->get_var("SELECT SUM(amount) FROM statement_in WHERE issueddate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "' AND buildid='$buildid'");

      $buildType = $this->db->get_var("SELECT type FROM building WHERE id='$buildid'");
      if(!empty($results[0]['report']['contracts']) && $buildType == 'rent'){
        $busyMonths = $allMonths = 0;
        foreach($results[0]['report']['contracts'] as $contract){
          $contractDates = $this->db->get_row("SELECT startdate,enddate FROM rent_contracts WHERE id='$contract[contractid]'");
          if(strtotime($contractDates['enddate']) > strtotime($_GET['todate'])){
            $contractDates['enddate'] = $_GET['todate'];
          }
          $busyMonths += $this->monthsInBetween($contractDates['startdate'], $contractDates['enddate']);
        }
        $allMonths = $this->monthsInBetween($_GET['fdate'], $_GET['todate']);
        $results[0]['report']['freeMonths'] = $allMonths-$busyMonths;
        $results[0]['report']['busyMonths'] = $busyMonths;

        $openContracts = $this->db->get_results("SELECT startdate,enddate FROM `rent_contracts` WHERE buildid='$buildid' AND startdate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "' ORDER BY startdate ASC");
        if($openContracts){
          foreach($openContracts as $openContract){
            $results[0]['report']['contracts_timeline'][] = [ 'start' => $openContract['startdate'], 'end' => $openContract['enddate'] ];
          }
        }

      }
    }
    return $results;
  }

  private function monthsInBetween($date1, $date2){
    $ts1 = strtotime($date1);
    $ts2 = strtotime($date2);

    $year1 = date('Y', $ts1);
    $year2 = date('Y', $ts2);

    $month1 = date('m', $ts1);
    $month2 = date('m', $ts2);

    return (($year2 - $year1) * 12) + ($month2 - $month1);
  }

}
