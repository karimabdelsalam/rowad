<?php
use chillerlan\QRCode\QRCode;

class dashboard extends dashboard_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function masklogout()
  {
    define('OUTPUT_NO_SIDEBAR', true);

    if(empty($_SESSION['adminid'])){
      $this->showmsg(gettext('غير مصرح لك الوصول الى هنا'), 0);
    }
    $adminID = $_SESSION['adminid'];

    session_destroy();
    session_name(COOKIEPREFIX);
    session_start();
    $_SESSION['userid'] = $adminID;
    $this->redirect(BASEURL.'/'.ADMIN_DIR_PATH);
    exit;
  }

  private function newNote()
  {
    if($_POST){
      $data = array();
      $data['userid'] = $_SESSION['userid'];
      $data['content'] = $_POST['content'];
      $data['timepost'] = TIMENOW;
      $id = $this->saveNote($data);
      $this->Smarty->assign('notes', $this->notesList("notes.id='$id'"));
      $this->showmsg($this->Fetch('widget_notes'), '#myNotesWidget');
    } else{
      $this->Output('newnote');
    }
  }

  public function lang()
  {
    if($_GET['code'] && in_array($_GET['code'], ['ar','en'])){
      $manager = $this->auto_load('manager');
      $data['lang'] = $_GET['code'];
      $manager->updateManager($data, $_SESSION['userid']);
      $this->redirect();
    }
  }

  public function myaccount()
  {
    $manager = $this->auto_load('manager');
    if($_POST){
      $this->checkExist(array('name'));
      $data = array();
      if($_POST['password']){
        $data['password'] = md5($_POST['password']);
      }
      if(!empty($_FILES['picture']['tmp_name'])){
        $data['picture'] = $this->Upload_File($_FILES['picture'], 'user_img', 'image');
      }
      $data['name'] = $_POST['name'];
      $data['mobile'] = $_POST['mobile'];
      $data['lang'] = $_POST['lang'];
      $manager->updateManager($data, $_SESSION['userid']);
      $this->showmsg(CPURL . '/' . Module, 1);
    } else{
      $this->Smarty->assign('manager', $manager->getUserInfo($_SESSION['userid']));
      $this->Output();
    }
  }

  private function adminDashboard()
  {
    $this->Smarty->assign('notes', $this->notesList("notes.userid='$_SESSION[userid]'"));
    $this->Smarty->assign('notifications', $this->notifsList(20));
    $this->Smarty->assign('tasks', $this->TaskList());
    $this->Smarty->assign('stats', $this->collectStats());

    $contracts = $this->auto_load('rent');
    $where = array("(rent_contracts.enddate<=DATE(NOW()) OR rent_contracts.enddate<=DATE(NOW() + INTERVAL 15 DAY))", 'building.contractid=rent_contracts.id', "building.available='0'");
    $this->Smarty->assign('endedContracts', $contracts->listrecord($where, false));

    $payments = $this->auto_load('pays_pending');
    $where = array("(payments.paydate<=DATE(NOW()) OR payments.paydate<=DATE(NOW() + INTERVAL 15 DAY))", "payments.gone='0'");
    $this->Smarty->assign('endedPayments', $payments->listrecord($where, false));

    $licenses = $this->auto_load('home/govlicense');
    $where = array("(expiredate<=DATE(NOW()) OR expiredate<=DATE(NOW() + INTERVAL 15 DAY))");
    $this->Smarty->assign('endedLicenses', $licenses->listrecord($where, false));

    $employees = $this->auto_load('employees');
    $where = array("(idexpire<=DATE(NOW()) OR idexpire<=DATE(NOW() + INTERVAL 15 DAY))");
    $this->Smarty->assign('endedEmployees', $employees->listrecord($where, false));

    $bank_accounts = $this->auto_load('bank_accounts');
    $this->Smarty->assign('accTransactions', $bank_accounts->listTransactions(array(), 10, false, true));

    $this->Smarty->assign('login_activities', $this->loginActivities());

    $this->Smarty->assign('start_year', date('Y')-10);

    $params = $this->graphicStats();

    $this->enqueuejs('jquery.flot.min');
    $this->enqueuejs('jquery.flot.resize.min');
    $this->enqueuejs('jquery.flot.tooltip.min');
    $this->enqueuejs('jquery.flot.orderBars');
    $this->Output('admin', $params);
  }

  private function ownerDashboard()
  {
    $this->Smarty->assign('notes', $this->notesList("notes.userid='$_SESSION[userid]'"));
    $this->Smarty->assign('stats', $this->collectOwnerStats());
    $this->Smarty->assign('notifications', $this->notifsList(20));

    $ownerBuildIDs = $this->ownerBuilds();

    $contracts = $this->auto_load('rent');
    $where = array(
      "(rent_contracts.enddate<=DATE(NOW()) OR rent_contracts.enddate<=DATE(NOW() + INTERVAL 15 DAY))",
      'building.contractid=rent_contracts.id',
      "building.available='0'",
      "building.id IN($ownerBuildIDs)",
    );
    $this->Smarty->assign('endedContracts', $contracts->listrecord($where, false));

    $payments = $this->auto_load('pays_pending');
    $where = array(
      "(payments.paydate<=DATE(NOW()) OR payments.paydate<=DATE(NOW() + INTERVAL 15 DAY))",
      "payments.gone='0'",
      "payments.buildid IN($ownerBuildIDs)",
    );
    $this->Smarty->assign('endedPayments', $payments->listrecord($where, false));

    $this->Smarty->assign('login_activities', $this->loginActivities());

    $this->Smarty->assign('start_year', date('Y')-10);

    $params = $this->ownerGraphicStats();

    $this->enqueuejs('jquery.flot.min');
    $this->enqueuejs('jquery.flot.resize.min');
    $this->enqueuejs('jquery.flot.tooltip.min');
    $this->enqueuejs('jquery.flot.orderBars');
    $this->Output('owner', $params);
  }

  private function clientDashboard()
  {
    $this->Smarty->assign('notes', $this->notesList("notes.userid='$_SESSION[userid]'"));
    $this->Smarty->assign('stats', $this->collectClientStats());
    $this->Smarty->assign('notifications', $this->notifsList(20));

    $contracts = $this->auto_load('rent');
    $where = array(
      "(rent_contracts.enddate<=DATE(NOW()) OR rent_contracts.enddate<=DATE(NOW() + INTERVAL 15 DAY))",
      'building.contractid=rent_contracts.id',
      "building.available='0'",
      "JSON_SEARCH(rent_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL",
    );
    $this->Smarty->assign('endedContracts', $contracts->listrecord($where, false));

    $payments = $this->auto_load('ccp/payments', false, CLIENT_DIR_PATH);
    $where = array(
      "(payments.paydate<=DATE(NOW()) OR payments.paydate<=DATE(NOW() + INTERVAL 15 DAY))",
      "payments.gone='0'",
    );
    $this->Smarty->assign('endedPayments', $payments->listrecord($where, false));

    $this->Smarty->assign('login_activities', $this->loginActivities());

    $this->Smarty->assign('start_year', date('Y')-10);

    $this->Output('client');
  }

    public function qrcode()
    {
        require(LIB_DIR.'/qrcode/autoload.php');

        $this->Smarty->assign('owner_code', (new QRCode)->render(CPURL . '/' . OWNER_DIR_PATH));
        $this->Smarty->assign('client_code', (new QRCode)->render(CPURL . '/' . CLIENT_DIR_PATH));
        $this->Smarty->assign('frontend_code', (new QRCode)->render(BASEURL));
        $this->Smarty->assign('custom_font', 1);

        if($_GET['print']) {
            switch($_GET['print']) {
                case 'owner':
                    $this->printVersion('qrcode_owner');
                    break;
                case 'client':
                    $this->printVersion('qrcode_client');
                    break;
                case 'sales':
                    $this->printVersion('qrcode_sales');
                    break;
            }
        } else {
            $this->Output();
        }
    }

  public function index()
  {
    if($_GET['done']){
      $this->finishTask($_GET['done']);
    } elseif($_GET['note']){
      $this->deleteNote($_GET['note']);
    }  elseif($_GET['notification']){
      $this->readNotification($_GET['notification']);
    } elseif($_GET['do'] == 'secure_logout'){
      $this->secureLogout();
      $this->redirect(CPURL.'/login/logout');
    }  elseif($_GET['do'] == 'mark_all_read'){
      $this->readAllNotifications();
      $this->redirect();
    } elseif($_GET['do'] == 'newnote'){
      $this->newNote();
    } elseif($_SESSION['userinfo']['groupid'] == 1){
      $this->adminDashboard();
    } elseif($_SESSION['userinfo']['groupid'] == 2){
      $this->ownerDashboard();
    } elseif($_SESSION['userinfo']['groupid'] == 3){
      $this->clientDashboard();
    }
  }

}
