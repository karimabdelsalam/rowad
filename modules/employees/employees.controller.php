<?php

class employees extends employees_model
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

  public function search()
  {
    if(strlen(utf8_decode($_GET['q'])) < 1){
      //return false;
    }
    $results = $this->searchQuery($_GET['q']);
    echo json_encode($results);
  }

  public function printable()
  {
    $this->Smarty->assign('data', $this->readrecord($_GET['id']));
    $this->printVersion();
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['fname'] = $_POST['fname'];
      $data['lname'] = $_POST['lname'];
      $data['fathname'] = $_POST['fathname'];
      $data['famname'] = $_POST['famname'];
      $data['address1'] = $_POST['address1'];
      $data['address2'] = $_POST['address2'];
      $data['nationality'] = $_POST['nationality'];
      $data['idtype'] = $_POST['idtype'];
      $data['idsource'] = $_POST['idsource'];
      $data['idnum'] = $_POST['idnum'];
      $data['idexpire'] = ($_POST['calendar'] == 1) ? $_POST['idexpire'] : uCal::u2g($_POST['idexpire']);
      $data['startdate'] = ($_POST['calendar'] == 1) ? $_POST['startdate'] : uCal::u2g($_POST['startdate']);
      $data['education'] = $_POST['education'];
      $data['maritalstatus'] = $_POST['maritalstatus'];
      $data['salary'] = $_POST['salary'];
      $data['rentcomm'] = $_POST['rentcomm'];
      $data['sellcomm'] = $_POST['sellcomm'];
      $data['homephone'] = $_POST['homephone'];
      $data['mobile'] = $_POST['mobile'];
      $data['mobile2'] = $_POST['mobile2'];
      $data['email'] = $_POST['email'];
      $data['calendar'] = $_POST['calendar'];
      $employeeid = $this->addrecord($data);
      $this->registerLog($employeeid);
      $this->moveAttachments($_POST['tempid'], $employeeid);
      if($_POST['quick_form']){
        $qformTitle = $_POST['fname'] . ' ' . $_POST['lname'] . ' ' . $_POST['fathname'] . ' ' . $_POST['famname'];
        $this->showmsg(json_encode(array('id' => $employeeid, 'title' => $qformTitle, 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->loadNationalities();
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['fname'] = $_POST['fname'];
      $data['lname'] = $_POST['lname'];
      $data['fathname'] = $_POST['fathname'];
      $data['famname'] = $_POST['famname'];
      $data['address1'] = $_POST['address1'];
      $data['address2'] = $_POST['address2'];
      $data['nationality'] = $_POST['nationality'];
      $data['idtype'] = $_POST['idtype'];
      $data['idsource'] = $_POST['idsource'];
      $data['idnum'] = $_POST['idnum'];
      $data['idexpire'] = ($_POST['calendar'] == 1) ? $_POST['idexpire'] : uCal::u2g($_POST['idexpire']);
      $data['startdate'] = ($_POST['calendar'] == 1) ? $_POST['startdate'] : uCal::u2g($_POST['startdate']);
      $data['education'] = $_POST['education'];
      $data['maritalstatus'] = $_POST['maritalstatus'];
      $data['salary'] = $_POST['salary'];
      $data['rentcomm'] = $_POST['rentcomm'];
      $data['sellcomm'] = $_POST['sellcomm'];
      $data['homephone'] = $_POST['homephone'];
      $data['mobile'] = $_POST['mobile'];
      $data['mobile2'] = $_POST['mobile2'];
      $data['email'] = $_POST['email'];
      $data['calendar'] = $_POST['calendar'];
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->loadNationalities();
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
    if($_GET['mobile']){
      $where[] = "employees.mobile='$_GET[mobile]'";
    }
    if($_GET['email']){
      $where[] = "employees.email='$_GET[email]'";
    }
    if($_GET['fdate'] && $_GET['todate']){
      $where[] = "employees.startdate BETWEEN '" . $_GET['fdate'] . "' AND '" . $_GET['todate'] . "'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
