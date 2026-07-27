<?php

class profits_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function listrecord($where = '')
  {
    if(!empty($where)){
      $where = 'AND createdtime ' . $where;
      $where2 = 'AND paydate ' . $where;
    }
    $results['bankcredits'] = $this->db->get_var("SELECT SUM(amount) FROM bankacc_transactions WHERE type='credit' $where");
    $results['bankdebits'] = $this->db->get_var("SELECT SUM(amount) FROM bankacc_transactions WHERE type='debit' $where");

    $results['statement_in'] = $this->db->get_var("SELECT SUM(amount) FROM statement_in WHERE 1 $where");
    $results['statement_out'] = $this->db->get_var("SELECT SUM(amount) FROM statement_out WHERE 1 $where");

    $results['off_profits'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE typeid IN('commission','commission2','mancost') AND gone='1' $where2");
    $results['taxes'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE typeid='comp_tax' AND gone='1' $where2");

    $results['builds_outgoings'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE typeid='outgoings' $where2");
    return $results;
  }

}
