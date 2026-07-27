<?php

class banking_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function readmMethods()
  {
    return $this->db->get_results('SELECT * FROM statement_methods');
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT bankacc_transactions.*,statement_in.reason AS in_reason,statement_out.reason AS out_reason FROM bankacc_transactions
    LEFT JOIN statement_in ON(statement_in.id=bankacc_transactions.statementid)
    LEFT JOIN statement_out ON(statement_out.id=bankacc_transactions.statementid)
    $inner WHERE 1 $where ORDER BY bankacc_transactions.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);

    if($results){
      $results[0]['report']['accounts'] = $this->db->get_results("SELECT bank_accounts.fullname, bank_accounts.accno, COUNT(bankacc_transactions.id) AS counterValue FROM bankacc_transactions INNER JOIN bank_accounts ON(bank_accounts.id=bankacc_transactions.bankid) WHERE 1 $where GROUP BY bankacc_transactions.bankid");
      $results[0]['report']['credit'] = $this->db->get_row("SELECT SUM(bankacc_transactions.amount) AS totalValue FROM bankacc_transactions WHERE 1 $where AND bankacc_transactions.type='credit' GROUP BY bankacc_transactions.type");
      $results[0]['report']['debit'] = $this->db->get_row("SELECT SUM(bankacc_transactions.amount) AS totalValue FROM bankacc_transactions WHERE 1 $where AND bankacc_transactions.type='debit' GROUP BY bankacc_transactions.type");
    }

    return $results;
  }

}
