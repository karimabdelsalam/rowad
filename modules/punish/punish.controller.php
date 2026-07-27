<?php

class punish extends punish_model
{

  public function __construct()
  {
    parent::__construct();
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

  public function add()
  {
    if($_POST){
      $this->checkExist(array('employeeid'));
      $data = array();
      $data['employeeid'] = $_POST['employeeid'];
      $data['createddate'] = $_POST['createddate'];
      $data['amount'] = $_POST['amount'];
      $data['reason'] = $_POST['reason'];
      $data['reasontext'] = $_POST['reasontext'];
      $data['formonth'] = $_POST['formonth'];
      $data['foryear'] = $_POST['foryear'];
      $data['calendar'] = $_POST['calendar'];
      $punishid = $this->addrecord($data);
      $this->registerLog($punishid);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      if($this->config['salary_calendar'] == 1){
        $this->Smarty->assign('dates', array('months' => uCal::$F[$_SESSION['lang']['code']], 'start' => date('Y'), 'end' => (date('Y') + 5)));
      } else{
        $this->Smarty->assign('dates', array('months' => uCal::$uF[$_SESSION['lang']['code']], 'start' => uCal::currentYear(), 'end' => (uCal::currentYear() + 5)));
      }
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $this->checkExist(array('employeeid'));
      $data = array();
      $data['employeeid'] = $_POST['employeeid'];
      $data['createddate'] = $_POST['createddate'];
      $data['amount'] = $_POST['amount'];
      $data['reason'] = $_POST['reason'];
      $data['reasontext'] = $_POST['reasontext'];
      $data['formonth'] = $_POST['formonth'];
      $data['foryear'] = $_POST['foryear'];
      $data['calendar'] = $_POST['calendar'];
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      if($this->config['salary_calendar'] == 1){
        $this->Smarty->assign('dates', array('months' => uCal::$F[$_SESSION['lang']['code']], 'start' => date('Y'), 'end' => (date('Y') + 5)));
      } else{
        $this->Smarty->assign('dates', array('months' => uCal::$uF[$_SESSION['lang']['code']], 'start' => uCal::currentYear(), 'end' => (uCal::currentYear() + 5)));
      }
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['fullname']){
      $where[] = "(employees.fname LIKE '%$_GET[fullname]%' OR employees.lname LIKE '%$_GET[fullname]%' OR employees.fathname LIKE '%$_GET[fullname]%' OR employees.famname LIKE '%$_GET[fullname]%')";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
