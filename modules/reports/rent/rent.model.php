<?php

class rent_model extends core
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
    $where[] = "payments.module='rent'";
    $where = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT payments.*,payment_types.title AS paymenttype,building.owner,building.title AS build,building.loc_details,building.details,rent_contracts.notes AS contract_notes
     ,CONCAT(buyer.fname, ' ', buyer.fathname, ' ', buyer.lname, ' ', buyer.famname) As buyerName,buyer.mobile AS buyerMobile,building_locs.location FROM payments
    INNER JOIN building ON(building.id=payments.buildid)
    LEFT JOIN building_locs ON(building_locs.id=building.locid)
    INNER JOIN rent_contracts ON(rent_contracts.id=payments.contractid)
    INNER JOIN buyer ON(buyer.id=JSON_UNQUOTE(JSON_EXTRACT(rent_contracts.`buyer`, '$.id[0]')))
    INNER JOIN payment_types ON(payment_types.name=payments.type)
    $inner $where ORDER BY payments.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $totalAmounts = $this->db->get_row("SELECT SUM(amount) AS amount,SUM(paidamount) AS paidamount FROM payments $inner $where");
      $results[0]['totalAmounts'] = $totalAmounts['amount'] - $totalAmounts['paidamount'];
    }
    return $results;
  }

}
