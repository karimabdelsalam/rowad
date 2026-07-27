<?php

class statementsin_model extends core
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
      $bool = $this->db->get_var("SELECT id FROM payments WHERE statementid='$id' AND gone='1'");
      if($bool){
        $this->showmsg(gettext('السند رقم' . ' ' . $id . ' ' . 'مرتبط بأحد الحركات المرحلة في المستحقات المالية برقم' . ' ' . $bool), 0);
      }
      $bool = $this->db->get_var("SELECT id FROM transactions WHERE statinid='$id' AND gone='1'");
      if($bool){
        $this->showmsg(gettext('السند رقم' . ' ' . $id . ' ' . 'مرتبط بأحد الحركات المرحلة في الإيرادات المالية برقم' . ' ' . $bool), 0);
      }
      $this->registerLog($id, 'statement_in', 'id');
      $this->db->query("DELETE FROM statement_in WHERE id='$id'");

      $this->db->query("UPDATE payments SET statementid = 0 WHERE statementid='$id'");
      $this->db->query("UPDATE transactions SET statinid = 0 WHERE statinid='$id'");

      $transaction = $this->db->get_row("SELECT id,amount,bankid FROM bankacc_transactions WHERE statementid='$id' AND type='credit'");
      if($transaction){
        $this->db->query("UPDATE bank_accounts SET balance=balance-" . $transaction['amount'] . " WHERE id='" . $transaction['bankid'] . "'");
        $this->db->query("DELETE FROM bankacc_transactions WHERE statementid='$id' AND type='credit'");
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
    if(!empty($_POST['paymentids'])){
      $bool = $this->db->get_row("SELECT id,statementid FROM payments WHERE statementid > 0 AND id IN(" . $_POST['paymentids'] . ") LIMIT 1");
      if($bool){
        $this->showmsg(gettext('لا يمكن انشاء السند بسبب العثور على ان المستحقات المالية رقم'.' '.$bool['id'].' '.'مرتبطة بأحد السندات برقم'.' '.$bool['statementid']), 0);
      }
    }
    if(!empty($_POST['transids'])){
      $bool = $this->db->get_row("SELECT id,statinid FROM transactions WHERE statinid > 0 AND id IN(" . $_POST['transids'] . ") LIMIT 1");
      if($bool){
        $this->showmsg(gettext('لا يمكن انشاء السند بسبب العثور على ان حركة الإيرادات المالية برقم'.' '.$bool['id'].' '.'مرتبطة بأحد السندات برقم'.' '.$bool['statinid']), 0);
      }
      $bool = $this->db->get_var("SELECT transactions.id FROM transactions INNER JOIN transaction_types ON(transaction_types.id=transactions.typeid)"
        . " WHERE transaction_types.income = 0 AND transactions.id IN(" . $_POST['transids'] . ")");
      if($bool){
        $this->showmsg(gettext('حركة الإيرادات المالية برقم'.' '.$bool.' '.'لا تقبل انشاء سند قبض'), 0);
      }
    }
  }

  public function addrecord($data)
  {
    $id = $this->db->insert('statement_in', $data);
    if(!empty($_POST['paymentids'])){
      $this->db->query("UPDATE payments SET statementid='$id' WHERE id IN(" . $_POST['paymentids'] . ")");
      $pays_pending = $this->auto_load('pays_pending');
      $pays_pending->processExec(0, $_POST['paymentids']);
    }
    if(!empty($_POST['transids'])){
      $this->db->query("UPDATE transactions SET statinid='$id' WHERE id IN(" . $_POST['transids'] . ")");
      $transactions = $this->auto_load('transactions');
      $transactions->finishStat(0, $_POST['transids']);
    }
    return $id;
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('statement_in', $data, array('id' => $id));
  }

  public function checkBankTransaction($statementid, $bankid)
  {
    $transaction = $this->db->get_row("SELECT id,amount,bankid FROM bankacc_transactions WHERE statementid='$statementid' AND type='credit'");
    $statement = $this->db->get_row("SELECT * FROM statement_in WHERE id='$statementid'");
    if(!empty($bankid) && !empty($transaction) && $transaction['bankid'] != $bankid){
      $this->db->query("UPDATE bank_accounts SET balance=balance-" . $transaction['amount'] . " WHERE id='" . $transaction['bankid'] . "'");
      $this->db->query("UPDATE bank_accounts SET balance=balance+" . $transaction['amount'] . " WHERE id='$bankid'");
      $this->db->update('bankacc_transactions', array('bankid' => $bankid), ['id' => $transaction['id']]);
    }
    if(!empty($bankid) && empty($transaction)){
      $this->db->insert('bankacc_transactions', array('bankid' => $bankid, 'statementid' => $statementid, 'amount' => $statement['amount'], 'type' => 'credit'));
      $this->db->query("UPDATE bank_accounts SET balance=balance+" . $statement['amount'] . " WHERE id='$bankid'");
    } elseif(empty($bankid) && !empty($transaction)){
      $this->db->query("DELETE FROM bankacc_transactions WHERE id='$transaction[id]'");
      $this->db->query("UPDATE bank_accounts SET balance=balance-" . $transaction['amount'] . " WHERE id='" . $transaction['bankid'] . "'");
    }
  }

  public function readrecord($id, $show_related_payments=false)
  {
    $statement = $this->db->get_row("SELECT statement_in.*,statement_methods.title AS fromTypeName,outgoings_types.title AS type FROM statement_in
    INNER JOIN statement_methods ON(statement_methods.name=statement_in.from_type)
    INNER JOIN outgoings_types ON(outgoings_types.id=statement_in.typeid)
    WHERE statement_in.id='$id'");
    $statement['cheque'] = json_decode($statement['cheque'], true);
    $statement['bank'] = json_decode($statement['bank'], true);
    if($statement['pay_method'] == 'bank'){
      $statement['tobank'] = $this->db->get_row("SELECT bank_accounts.*,banks.title AS bankname FROM bank_accounts INNER JOIN banks ON(banks.id=bank_accounts.bankid) WHERE bank_accounts.id='" . $statement['bank']['tobankid'] . "'");
    }
    if(!empty($statement['buildid'])){
      $statement['buildTitle'] = $this->db->get_var("SELECT title FROM building WHERE id='$statement[buildid]'");
    }
    switch($statement['from_type']){
      case 'owner':
        $statement['from_id'] = explode(',', $statement['from_id']);
        $statement['paidto'] = array();
        foreach($statement['from_id'] as $paidto){
          $statement['from'] = $this->db->get_row("SELECT fname,lname,fathname,famname FROM company_owners WHERE id='$paidto'");
          $statement['paidto'][] = $statement['from']['fname'] . ' ' . $statement['from']['fathname'] . ' ' . $statement['from']['lname'] . ' ' . $statement['from']['famname'];
        }
        $statement['paidto'] = implode(' و ', $statement['paidto']);
        break;
      case 'employee':
        $statement['from'] = $this->db->get_row("SELECT fname,lname,fathname,famname FROM employees WHERE id='$statement[from_id]'");
        $statement['paidto'] = $statement['from']['fname'] . ' ' . $statement['from']['fathname'] . ' ' . $statement['from']['lname'] . ' ' . $statement['from']['famname'];
        break;
      case 'buildowner':
        $statement['from'] = $this->db->get_row("SELECT fname,lname,fathname,famname FROM owner WHERE id='$statement[from_id]'");
        $statement['paidto'] = $statement['from']['fname'] . ' ' . $statement['from']['fathname'] . ' ' . $statement['from']['lname'] . ' ' . $statement['from']['famname'];
        $statement['build'] = $this->db->get_var("SELECT title FROM building WHERE id='$statement[buildid]'");
        break;
      case 'buildrenter':
        $statement['from'] = $this->auto_load_read($statement['from_id'], 'buyer');
        $statement['paidto'] = $statement['from']['fullname'];
        $statement['build'] = $this->db->get_var("SELECT title FROM building WHERE id='$statement[buildid]'");
        break;
      case 'buildbuyer':
        $statement['from'] = $this->auto_load_read($statement['from_id'], 'buyer');
        $statement['paidto'] = $statement['from']['fullname'];
        $statement['build'] = $this->db->get_var("SELECT title FROM building WHERE id='$statement[buildid]'");
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
      $pays_processed = $this->auto_load('pays_processed');
      $statement['payments'] = $pays_processed->listrecord(["payments.statementid='$id'"], false);

      $transactions = $this->auto_load('transactions');
      $statement['transactions'] = $transactions->listrecord(["transactions.statinid='$id'"], false);
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
    $sql = "SELECT statement_in.*,statement_methods.title AS method FROM statement_in INNER JOIN statement_methods ON(statement_methods.name=statement_in.from_type) $inner $where ORDER BY statement_in.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
