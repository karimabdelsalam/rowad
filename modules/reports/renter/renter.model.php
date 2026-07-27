<?php

class renter_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function getRenterContracts($id)
  {
    $ids = array();
    $rentercontracts = $this->db->get_results("SELECT id FROM rent_contracts WHERE JSON_CONTAINS(JSON_EXTRACT(buyer, '$.id[*]'), '\"$id\"', '$')");
    if($rentercontracts){
      foreach($rentercontracts as $rentercontract){
        $ids[] = $rentercontract['id'];
      }
      $ids = implode(',', $ids);
    }
    return $ids;
  }

  public function getRenter($id)
  {
    return $this->db->get_row("SELECT id,fname,lname,fathname,famname FROM buyer WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    INNER JOIN payment_types ON(payment_types.name=payments.type) $inner $where ORDER BY payments.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $results[0]['totalAmounts'] = $this->db->get_var("SELECT SUM(amount) FROM payments $inner $where");
    }
    return $results;
  }

}
