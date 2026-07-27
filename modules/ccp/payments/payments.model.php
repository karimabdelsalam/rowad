<?php

namespace clientarea;

class payments_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function hasAuth($id)
  {
    return $this->db->get_var("SELECT payments.id FROM payments
      LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent' AND JSON_SEARCH(rent_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL)
      LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell' AND JSON_SEARCH(sell_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL)
      WHERE (rent_contracts.buyer IS NOT NULL OR sell_contracts.buyer IS NOT NULL) AND payments.id = $id");
  }

  public function clientBuilds()
  {
    return $this->db->get_results("SELECT building.id,building.title,building_locs.location FROM building LEFT JOIN building_locs ON(building_locs.id=building.locid) WHERE building.id IN(".$this->getAllClientBuilds().")");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else {
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build,rent_contracts.buyer AS renters,sell_contracts.buyer AS buyers FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent' AND JSON_SEARCH(rent_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL)
    LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell' AND JSON_SEARCH(sell_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL)
    INNER JOIN payment_types ON(payment_types.name=payments.type) $inner WHERE (rent_contracts.buyer IS NOT NULL OR sell_contracts.buyer IS NOT NULL) $where ORDER BY payments.paydate, payments.id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $results[0]['totalAmounts'] = 0;
      foreach($results as $key => $result){
        if(!empty($results[$key]['renters'])){
          $results[$key]['relatedto'] = json_decode($results[$key]['renters'], true);
        }
        if(!empty($results[$key]['buyers'])){
          $results[$key]['relatedto'] = json_decode($results[$key]['buyers'], true);
        }
        $results[$key]['owner'] = json_decode($results[$key]['owner'], true);
        if(!empty($results[$key]['owner']['id'])){
          foreach($results[$key]['owner']['id'] as $key2 => $owner){
            $ownername = $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM owner WHERE id='" . $results[$key]['owner']['id'][$key2] . "'");
            $results[$key]['owner']['name'][$key2] = $this->formatOwnerName($ownername, true);
            $results[$key]['ownerinfo'][$key2] = $ownername;
          }
        }
        $results[0]['totalAmounts'] += $result['amount']-$result['paidamount'];
      }
    }
    return $results;
  }

}
