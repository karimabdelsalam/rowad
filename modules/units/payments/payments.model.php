<?php

namespace ownerarea;

class payments_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function hasAuth($id)
  {
    return $this->db->get_var("SELECT payments.id FROM payments
      INNER JOIN building ON(building.id=payments.buildid)
      WHERE JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL AND payments.id = $id");
  }

  public function ownerBuilds()
  {
    return $this->db->get_results("SELECT building.id,building.title,building_locs.location FROM building LEFT JOIN building_locs ON(building_locs.id=building.locid) WHERE JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL");
  }

  public function ownerClients()
  {
    $clientids = [];
    $clients = $this->db->get_results("SELECT rent_contracts.buyer AS rentClients,sell_contracts.buyer AS sellClients FROM building
      LEFT JOIN rent_contracts ON(rent_contracts.buildid=building.id)
      LEFT JOIN sell_contracts ON(sell_contracts.buildid=building.id)
      WHERE JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL");
    if($clients) {
      foreach($clients as $allclient) {
        $allclient['rentClients'] = json_decode($allclient['rentClients'], true);
        if(!empty($allclient['rentClients'])) {
          foreach($allclient['rentClients']['id'] as $clientid) {
            $clientids[] = $clientid;
          }
        }
        if(!empty($allclient['sellClients'])) {
          $allclient['sellClients'] = json_decode($allclient['sellClients'], true);
          foreach($allclient['sellClients']['id'] as $clientid) {
            $clientids[] = $clientid;
          }
        }
      }
    }

    $clientList = [];
    if(!empty($clientids)) {
      $results = $this->db->get_results("SELECT id,fname,lname,fathname,famname,company,type FROM buyer WHERE id IN(".implode(',', $clientids).") GROUP BY id");
      if($results){
        foreach($results as $result) {
          $clientList[] = array('id' => $result['id'], 'name' => $this->formatClientName($result, true));
        }
      }
    }
    return $clientList;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL";
    if(empty($where)){
      $where = '';
    } else {
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build,rent_contracts.buyer AS renters,sell_contracts.buyer AS buyers FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent')
    LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell')
    INNER JOIN payment_types ON(payment_types.name=payments.type) $inner WHERE 1 $where ORDER BY payments.paydate, payments.id ASC";
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
