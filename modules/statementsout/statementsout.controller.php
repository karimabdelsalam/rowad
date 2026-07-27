<?php

class statementsout extends statementsout_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function delattach()
  {
    if($_GET['id']){
      $this->deleteAttachFile($_GET['id']);
      $this->redirect();
    }
  }

  public function delete()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->deleteExec(0, $ids);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function transinfo()
  {
    $transaction = $this->auto_load_read($_GET['transid'], 'transactions');
    if(empty($transaction)){
      echo 1;
    } else{
      $transaction['paydate'] = ($transaction['calendar'] == 2) ? uCal::g2u($transaction['paydate']) : $transaction['paydate'];
      echo json_encode($transaction);
    }
    exit;
  }

  public function printable()
  {
    $this->turnTempArabic();
    require LIB_DIR . '/I18N/Arabic.php';
    $Arabic = new I18N_Arabic('Numbers');
    $statement = $this->readrecord($_GET['id']);
    $banksmodule = $this->auto_load('banks');

    if($statement['pay_method'] == 'cash'){
      $pay_method = gettext('نقدا');
      $payment = '';
    } elseif($statement['pay_method'] == 'cheque'){
      $pay_method = gettext('شيك');
      $bank = $banksmodule->readrecord($statement['cheque']['bankid']);
      $payment = '<table dir="rtl" align="center" style="font-weight:bold; width:100%; border-collapse:collapse; border: solid 1px #ccc;text-align: center;">';
      $payment .= '<tr><td style="background:#000; color:#fff; padding:10px">'.gettext('رقم الشيك').'</td>';
      $payment .= '<td style="background:#000; color:#fff">'.gettext('اسم البنك').'</td>';
      $payment .= '<td style="background:#000; color:#fff">'.gettext('تاريخه').'</td></tr>';
      $payment .= '<tr><td style="padding:5px">' . $statement['cheque']['no'] . '</td>';
      $payment .= '<td>' . $bank['title'] . '</td>';
      $payment .= '<td>' . (($statement['calendar'] == 2) ? uCal::g2u($statement['cheque']['issuedate']) : $statement['cheque']['issuedate']) . '</td></tr>';
      $payment .= '</table>';
    } elseif($statement['pay_method'] == 'bank'){
      $pay_method = gettext('تحويل بنكي');
      $bank = $banksmodule->readrecord($statement['bank']['tobankid']);
      $payment = '<table dir="rtl" align="center" style="font-weight:bold; width:100%; border-collapse:collapse; border: solid 1px #ccc;text-align: center;">';
      $payment .= '<tr><td style="background:#000; color:#fff; padding:10px">'.gettext('رقم مرجع التحويل').'</td>';
      $payment .= '<td style="background:#000; color:#fff">'.gettext('اسم البنك').'</td>';
      $payment .= '<td style="background:#000; color:#fff">'.gettext('الى رقم حساب').'</td>';
      $payment .= '<td style="background:#000; color:#fff">'.gettext('تاريخه').'</td>';
      $payment .= '<td style="background:#000; color:#fff">'.gettext('من بنك').'</td>';
      $payment .= '<td style="background:#000; color:#fff">'.gettext('رقم الحساب').'</td></tr>';
      $payment .= '<tr><td style="padding:5px">' . $statement['bank']['refno'] . '</td>';
      $payment .= '<td>' . $bank['title'] . '</td>';
      $payment .= '<td>' . $statement['bank']['toaccno'] . '</td>';
      $payment .= '<td>' . (($statement['calendar'] == 2) ? uCal::g2u($statement['bank']['issuedate']) : $statement['bank']['issuedate']) . '</td>';
      $payment .= '<td>' . $statement['frombank']['bankname'] . '</td>';
      $payment .= '<td>' . $statement['frombank']['accno'] . '</td></tr>';
      $payment .= '</table>';
    }

    $replaces = array(
      'number' => $statement['id'],
      'date' => ($statement['calendar'] == 2) ? uCal::g2u($statement['issueddate']) : $statement['issueddate'],
      'from_type' => $statement['method'],
      'build' => $statement['build'],
      'amount' => $statement['amount'],
      'amount_text' => $Arabic->int2str($statement['amount']),
      'name' => $statement['paidto'],
      'pay_method' => $pay_method,
      'pay_details' => $payment,
      'reason' => $statement['reason'],
      'notes' => $statement['notes']
      );
    $this->processDoc($replaces, 3);
  }

  public function add()
  {
    if($_POST){
      $this->checkPayments();

      $taxs = $_POST['taxs'] == 1 ? ($_POST['amount']/100) * $this->config['vat'] : 0;
      $data = array();
      $data['typeid'] = $_POST['typeid'];
      $data['from_type'] = $_POST['from_type'];
      switch($_POST['from_type']){
        case 'owner':
          $this->checkExist(array('ownerid'));
          $data['from_id'] = implode(',', $_POST['ownerid']);
          break;
        case 'employee':
          $this->checkExist(array('employeeid'));
          $data['from_id'] = $_POST['employeeid'];
          break;
        case 'buildowner':
          $this->checkExist(array('buildownerid'));
          $data['from_id'] = $_POST['buildownerid'];
          $data['buildid'] = $_POST['buildid'];
          break;
        case 'buildrenter':
          $this->checkExist(array('renterid'));
          $data['from_id'] = $_POST['renterid'];
          $data['buildid'] = $_POST['buildid'];
          break;
        case 'buildbuyer':
          $this->checkExist(array('buyerid'));
          $data['from_id'] = $_POST['buyerid'];
          $data['buildid'] = $_POST['buildid'];
          break;
        case 'govorg':
          $this->checkExist(array('govorgid'));
          $data['from_id'] = $_POST['govorgid'];
          break;
        case 'citizen':
          $this->checkExist(array('citizenid'));
          $data['from_id'] = $_POST['citizenid'];
          break;
        case 'comorg':
          $this->checkExist(array('comorgid'));
          $data['from_id'] = $_POST['comorgid'];
          break;
      }
      if($_POST['pay_method'] == 'cash'){
        $data['bank'] = '';
        $data['cheque'] = '';
      } elseif($_POST['pay_method'] == 'cheque'){
        $data['bank'] = '';
        $_POST['cheque']['copy'] = $this->Upload_File($_FILES['cheque_copy'], 'statements', 'image');
        $_POST['cheque']['issuedate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['cheque']['issuedate']) : $_POST['cheque']['issuedate'];
        $data['cheque'] = json_encode($_POST['cheque'], JSON_UNESCAPED_UNICODE);
      } elseif($_POST['pay_method'] == 'bank'){
        $_POST['bank']['copy'] = $this->Upload_File($_FILES['bank_copy'], 'statements', 'image');
        $_POST['bank']['issuedate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['bank']['issuedate']) : $_POST['bank']['issuedate'];
        $data['bank'] = json_encode($_POST['bank'], JSON_UNESCAPED_UNICODE);
        $data['cheque'] = '';
      }
      if(!empty($_POST['buildid'])){
        $data['buildid'] = $_POST['buildid'];
      }
      $data['accid'] = $_POST['accid'];
      $data['calendar'] = $_POST['calendar'];
      $data['pay_method'] = $_POST['pay_method'];
      $data['amount'] = $_POST['amount'] + $taxs;
      $data['reason'] = $_POST['reason'];
      $data['notes'] = $_POST['notes'];
      $data['issueddate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['issueddate']) : $_POST['issueddate'];
      $statementid = $this->addrecord($data);
      $this->registerLog($statementid);
      $this->moveAttachments($_POST['tempid'], $statementid);
      $this->checkBankTransaction($statementid, $_POST['accid']);

      if(!empty($_POST['buildid']) && $_POST['buildexpenses'] == 1){
        if($_POST['expensestype'] == 'owner'){
          $transaction = $this->auto_load('transactions');
          $transaction->collectStatement($statementid, $data);
        } elseif($_POST['expensestype'] == 'renter' || $_POST['expensestype'] == 'buyer'){
          $payments = $this->auto_load('payments');
          $payments->expensesOnContract($_POST['buildid'], $data['amount'], $_POST['issueddate'], $_POST['calendar'], $statementid, $taxs, $_POST['reason']);
        }
      }

      if($_POST['from_type'] == 'buildowner'){
        $this->sendNotify('ownerAmountTo', array(), $this->readrecord($statementid));
      }

      if($_GET['offerprint']) {
        $this->showmsg('showStatoutPrintModal', 'callback', ['function' => 'showStatoutPrintModal' , 'param' => $statementid ]);
      } else {
        $this->showmsg(CPURL . '/' . Module . '/', 1);
      }
    } else{
      $this->Smarty->assign('companyowners', $this->readOwners());
      $this->Smarty->assign('bankaccounts', $this->auto_load_list('bank_accounts'));
      $this->Smarty->assign('outtypes', $this->auto_load_list('outtypes'));
      $this->Smarty->assign('banks', $this->auto_load_list('banks'));
      $this->Smarty->assign('methods', $this->readmMethods());

      $data = [];

      if(! empty($_GET['transid'])){
        $transactions = $this->auto_load('transactions');
        $data['transactions'] = $transactions->listrecord(['transactions.id IN('.$_GET['transid'].')'], false);
        $data['amount'] = $data['transactions'][0]['totalAmounts'];
        if(count($data['transactions']) == 1){
          $_GET['ownerid'] = $data['transactions'][0]['ownerid'];
          $_GET['buildid'] = $data['transactions'][0]['buildid'];
        }
      }

      if(! empty($_GET['renterid'])){
        $data['from_type'] = 'buildrenter';
        $data['from_id'] = $_GET['renterid'];
        $data['from'] = $this->auto_load_read($_GET['renterid'], 'buyer');
      }
      if(! empty($_GET['ownerid'])){
        $data['from_type'] = 'buildowner';
        $data['from_id'] = $_GET['ownerid'];
        $data['from'] = $this->auto_load_read($_GET['ownerid'], 'owner');
      }
      if(! empty($_GET['buildid'])){
        $data['buildid'] = $_GET['buildid'];
        $data['build'] = $this->db->get_var("SELECT title FROM building WHERE id='$_GET[buildid]'");
      }

      $this->Smarty->assign('data', $data);

      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['typeid'] = $_POST['typeid'];
      $data['from_type'] = $_POST['from_type'];
      switch($_POST['from_type']){
        case 'owner':
          $this->checkExist(array('ownerid'));
          $data['from_id'] = implode(',', $_POST['ownerid']);
          break;
        case 'employee':
          $this->checkExist(array('employeeid'));
          $data['from_id'] = $_POST['employeeid'];
          break;
        case 'buildowner':
          $this->checkExist(array('buildownerid'));
          $data['from_id'] = $_POST['buildownerid'];
          $data['buildid'] = $_POST['buildid'];
          break;
        case 'buildrenter':
          $this->checkExist(array('renterid'));
          $data['from_id'] = $_POST['renterid'];
          $data['buildid'] = $_POST['buildid'];
          break;
        case 'buildbuyer':
          $this->checkExist(array('buyerid'));
          $data['from_id'] = $_POST['buyerid'];
          $data['buildid'] = $_POST['buildid'];
          break;
        case 'govorg':
          $this->checkExist(array('govorgid'));
          $data['from_id'] = $_POST['govorgid'];
          break;
        case 'citizen':
          $this->checkExist(array('citizenid'));
          $data['from_id'] = $_POST['citizenid'];
          break;
        case 'comorg':
          $this->checkExist(array('comorgid'));
          $data['from_id'] = $_POST['comorgid'];
          break;
      }
      if($_POST['pay_method'] == 'cash'){
        $data['bank'] = '';
        $data['cheque'] = '';
      } elseif($_POST['pay_method'] == 'cheque'){
        $data['bank'] = '';
        if(!empty($_FILES['cheque_copy']['tmp_name'])){
          $_POST['cheque']['copy'] = $this->Upload_File($_FILES['cheque_copy'], 'statements', 'image');
        }
        $_POST['cheque']['issuedate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['cheque']['issuedate']) : $_POST['cheque']['issuedate'];
        $data['cheque'] = json_encode($_POST['cheque'], JSON_UNESCAPED_UNICODE);
      } elseif($_POST['pay_method'] == 'bank'){
        if(!empty($_FILES['bank_copy']['tmp_name'])){
          $_POST['bank']['copy'] = $this->Upload_File($_FILES['bank_copy'], 'statements', 'image');
        }
        $_POST['bank']['issuedate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['bank']['issuedate']) : $_POST['bank']['issuedate'];
        $data['bank'] = json_encode($_POST['bank'], JSON_UNESCAPED_UNICODE);
        $data['cheque'] = '';
      }
      $data['accid'] = $_POST['accid'];
      $data['calendar'] = $_POST['calendar'];
      $data['pay_method'] = $_POST['pay_method'];
      if(!empty($_POST['amount'])){
        $data['amount'] = $_POST['amount'];
      }
      $data['reason'] = $_POST['reason'];
      $data['notes'] = $_POST['notes'];
      $data['issueddate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['issueddate']) : $_POST['issueddate'];
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);
      $this->checkBankTransaction($_POST['id'], $_POST['accid']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('companyowners', $this->readOwners());
      $this->Smarty->assign('methods', $this->readmMethods());
      $this->Smarty->assign('bankaccounts', $this->auto_load_list('bank_accounts'));
      $this->Smarty->assign('outtypes', $this->auto_load_list('outtypes'));
      $this->Smarty->assign('banks', $this->auto_load_list('banks'));
      $this->Smarty->assign('data', $this->readrecord($_GET['id'], true));
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "statement_out.issueddate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    if($_GET['famount'] != '' && $_GET['toamount'] != ''){
      $where[] = "statement_out.amount BETWEEN '" . $_GET['famount'] . "' AND '" . $_GET['toamount'] . "'";
    }
    if($_GET['from_type']){
      $where[] = "statement_out.from_type='$_GET[from_type]'";
    }
    if($_GET['serial']){
      $where[] = "statement_out.id='$_GET[serial]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Smarty->assign('methods', $this->readmMethods());

    $this->Output();
  }

}
