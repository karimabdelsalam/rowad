<?php

class invoices_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function createInvoice($data)
  {
    return $this->db->insert('invoices', $data);
  }

  public function readInvoice($invoiceid)
  {
    return $this->db->get_row("SELECT * FROM invoices WHERE id='$invoiceid'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT * FROM invoices $inner $where ORDER BY id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $buyermodule = $this->auto_load('buyer');
      foreach($results as $key => $result){
        if(!empty($results[$key]['buyerid'])){
          $customer = $buyermodule->readrecord($results[$key]['buyerid']);
          $results[$key]['customer'] = $customer['fullname'];
        } else {
          $results[$key]['customer'] = $results[$key]['name'];
        }
      }
    }
    return $results;
  }

}
