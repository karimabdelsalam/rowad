<?php

class statementsout_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function deleteAttachFile($id)
  {
    $fileinfo = $this->db->get_row("SELECT * FROM attachments WHERE id='$id'");
    @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $fileinfo['path']);
    $this->registerLog($_GET['id'], 'attachments', 'name');
    $this->db->query("DELETE FROM attachments WHERE id='$id'");
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->deleteExec($id);
      }
    } else{
      //$this->verifyDelete("SELECT id FROM transactions WHERE statid='$id' AND gone='1'", gettext('هذا السند مرتبط به حركات تم ترحيلها'));

      $bool = $this->db->get_var("SELECT id FROM payments WHERE statoutid='$id' AND gone='1'");
      if($bool){
        $this->showmsg(gettext('السند رقم' . ' ' . $id . ' ' . 'مرتبط بأحد الحركات المرحلة في المستحقات المالية برقم' . ' ' . $bool), 0);
      }
      $bool = $this->db->get_var("SELECT id FROM transactions WHERE statoutid='$id' AND gone='1'");
      if($bool){
        $this->showmsg(gettext('السند رقم' . ' ' . $id . ' ' . 'مرتبط بأحد الحركات المرحلة في الإيرادات المالية برقم' . ' ' . $bool), 0);
      }

      $expenses = $this->db->get_results("SELECT id FROM payments WHERE statoutid='$id' AND type='expenses'");
      if($expenses){
        foreach($expenses as $expense){
          $this->db->query("DELETE FROM payments WHERE id='$expense[id]'");
        }
      }

      $expenses = $this->db->get_results("SELECT id FROM transactions WHERE statoutid='$id' AND (typeid='outgoings' OR typeid='mancost')");
      if($expenses){
        foreach($expenses as $expense){
          $this->db->query("DELETE FROM transactions WHERE id='$expense[id]'");
        }
      }

      $this->registerLog($id, 'statement_out', 'id');
      $this->db->query("DELETE FROM statement_out WHERE id='$id'");

      $this->db->query("UPDATE payments SET statoutid = 0 WHERE statoutid='$id'");
      $this->db->query("UPDATE transactions SET statoutid = 0 WHERE statoutid='$id'");

      $transaction = $this->db->get_row("SELECT id,amount,bankid FROM bankacc_transactions WHERE statementid='$id' AND type='debit'");
      if($transaction){
        $this->db->query("UPDATE bank_accounts SET balance=balance+" . $transaction['amount'] . " WHERE id='" . $transaction['bankid'] . "'");
        $this->db->query("DELETE FROM bankacc_transactions WHERE statementid='$id' AND type='debit'");
      }
    }
  }

  public function readOwners()
  {
    return $this->db->get_results('SELECT * FROM company_owners ORDER BY id ASC');
  }

  public function readmMethods()
  {
    return $this->db->get_results('SELECT * FROM statement_methods');
  }

  public function checkPayments()
  {
    if(!empty($_POST['transids'])){
      $bool = $this->db->get_row("SELECT id,statoutid FROM transactions WHERE statoutid > 0 AND id IN(" . $_POST['transids'] . ") LIMIT 1");
      if($bool){
        $this->showmsg(gettext('لا يمكن انشاء السند بسبب العثور على ان الحركة رقم'.' '.$bool['id'].' '.'مرتبطة بأحد السندات برقم'.' '.$bool['statoutid']), 0);
      }
      $bool = $this->db->get_var("SELECT transactions.id FROM transactions INNER JOIN transaction_types ON(transaction_types.id=transactions.typeid)"
        . " WHERE transaction_types.outcome = 0 AND transactions.id IN(" . $_POST['transids'] . ")");
      if($bool){
        $this->showmsg(gettext('حركة الإيرادات المالية برقم'.' '.$bool.' '.'لا تقبل انشاء سند صرف'), 0);
      }
    }
  }

  public function addrecord($data)
  {
    $id = $this->db->insert('statement_out', $data);
    if(!empty($_POST['transids'])){
      $this->db->query("UPDATE transactions SET statoutid='$id' WHERE id IN(" . $_POST['transids'] . ")");
      $transactions = $this->auto_load('transactions');
      $transactions->finishStat(0, $_POST['transids']);
    }
    return $id;
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('statement_out', $data, array('id' => $id));
  }

  public function checkBankTransaction($statementid, $bankid)
  {
    $transaction = $this->db->get_row("SELECT id,amount,bankid FROM bankacc_transactions WHERE statementid='$statementid' AND type='debit'");
    $statement = $this->db->get_row("SELECT * FROM statement_out WHERE id='$statementid'");
    $statement['bank'] = json_decode($statement['bank'], true);
    if(!empty($bankid) && !empty($transaction) && $transaction['bankid'] != $bankid){
      $this->db->query("UPDATE bank_accounts SET balance=balance+" . $transaction['amount'] . " WHERE id='" . $transaction['bankid'] . "'");
      $this->db->query("UPDATE bank_accounts SET balance=balance-" . $transaction['amount'] . " WHERE id='$bankid'");
      $this->db->update('bankacc_transactions', array('bankid' => $bankid), ['id' => $transaction['id']]);
    }
    if(!empty($bankid) && empty($transaction)){
      $this->db->insert('bankacc_transactions', array('bankid' => $bankid, 'statementid' => $statementid, 'amount' => $statement['amount'], 'type' => 'debit'));
      $this->db->query("UPDATE bank_accounts SET balance=balance-" . $statement['amount'] . " WHERE id='$bankid'");
    } elseif(empty($bankid) && !empty($transaction)){
      $this->db->query("DELETE FROM bankacc_transactions WHERE id='$transaction[id]'");
      $this->db->query("UPDATE bank_accounts SET balance=balance+" . $transaction['amount'] . " WHERE id='" . $transaction['bankid'] . "'");
    }
  }

  public function readrecord($id, $show_related_payments=false)
  {
    $statement = $this->db->get_row("SELECT statement_out.*,statement_methods.title AS method,outgoings_types.title AS type FROM statement_out
    INNER JOIN outgoings_types ON(outgoings_types.id=statement_out.typeid)
    INNER JOIN statement_methods ON(statement_methods.name=statement_out.from_type) WHERE statement_out.id='$id'");
    $statement['cheque'] = json_decode($statement['cheque'], true);
    $statement['bank'] = json_decode($statement['bank'], true);
    if($statement['pay_method'] == 'bank'){
      $statement['frombank'] = $this->db->get_row("SELECT bank_accounts.*,banks.title AS bankname FROM bank_accounts INNER JOIN banks ON(banks.id=bank_accounts.bankid) WHERE bank_accounts.id='" . $statement['bank']['frombankid'] . "'");
    }
    if(!empty($statement['buildid'])){
      $statement['build'] = $this->db->get_var("SELECT title FROM building WHERE id='$statement[buildid]'");
    }
    switch($statement['from_type']){
      case 'owner':
        $statement['from_id'] = explode(',', $statement['from_id']);
        $statement['paidto'] = array();
        foreach($statement['from_id'] as $paidto){
          $statement['from'] = $this->db->get_row("SELECT fname,lname,fathname,famname FROM company_owners WHERE id='$paidto'");
          $statement['paidto'][] = $statement['from']['fname'] . ' ' . $statement['from']['fathname'] . ' ' . $statement['from']['lname'] . ' ' . $statement['from']['famname'];
        }
        $statement['paidto'] = implode(gettext(' و '), $statement['paidto']);
        break;
      case 'employee':
        $statement['from'] = $this->db->get_row("SELECT fname,lname,fathname,famname FROM employees WHERE id='$statement[from_id]'");
        $statement['paidto'] = $statement['from']['fname'] . ' ' . $statement['from']['fathname'] . ' ' . $statement['from']['lname'] . ' ' . $statement['from']['famname'];
        break;
      case 'buildowner':
        $statement['from'] = $this->db->get_row("SELECT fname,lname,fathname,famname FROM owner WHERE id='$statement[from_id]'");
        $statement['paidto'] = $statement['from']['fname'] . ' ' . $statement['from']['fathname'] . ' ' . $statement['from']['lname'] . ' ' . $statement['from']['famname'];
        break;
      case 'buildrenter':
        $statement['from'] = $this->auto_load_read($statement['from_id'], 'buyer');
        $statement['paidto'] = $statement['from']['fullname'];
        break;
      case 'buildbuyer':
        $statement['from'] = $this->auto_load_read($statement['from_id'], 'buyer');
        $statement['paidto'] = $statement['from']['fullname'];
        break;
      case 'govorg':
        $statement['from']['title'] = $this->db->get_var("SELECT title FROM gov_orgs WHERE id='$statement[from_id]'");
        $statement['paidto'] = $statement['from']['title'];
        break;
      case 'citizen':
        $statement['from']['title'] = $this->db->get_var("SELECT title FROM citizens WHERE id='$statement[from_id]'");
        $statement['paidto'] = $statement['from']['title'];
        break;
      case 'comorg':
        $statement['from']['title'] = $this->db->get_var("SELECT title FROM trade_orgs WHERE id='$statement[from_id]'");
        $statement['paidto'] = $statement['from']['title'];
        break;
    }

    if($show_related_payments){
      $pays_pending = $this->auto_load('pays_pending');
      $statement['payments'] = $pays_pending->listrecord(["payments.statoutid='$id'"], false, '', true);

      $transactions = $this->auto_load('transactions');
      $statement['transactions'] = $transactions->listrecord(["transactions.statoutid='$id'"], false);
    }

    $statement['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");

    return $statement;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT statement_out.*,statement_methods.title AS method,outgoings_types.title AS type FROM statement_out
    INNER JOIN outgoings_types ON(outgoings_types.id=statement_out.typeid)
    INNER JOIN statement_methods ON(statement_methods.name=statement_out.from_type)
    $inner $where ORDER BY statement_out.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
