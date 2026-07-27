<?php

class reports extends reports_model
{

  public function __construct()
  {
    parent::__construct();
    $this->Smarty->assign('categories', $this->getCategories());
  }

  public function delete()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->deleteExec(0, $ids);
      $this->redirect();
    } else {
      $this->deleteExec($_GET['id']);
    }
  }

  public function view()
  {
    $data = $this->readrecord($_GET['id']);
    header('Location: ' . MEDIAURL . '/' . $data['file']);
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['buildid'] = $_POST['buildid'];
      $data['catid'] = $_POST['catid'];
      $data['title'] = $_POST['title'];
      $data['issued_date'] = $_POST['issued_date'];
      $data['file'] = $this->Upload_File($_FILES['file'], 'reports', 'all');
      $newid = $this->addrecord($data);
      $this->registerLog($newid);
      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $newid, 'title' => $_POST['location'], 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/index/?buildid=' . $_POST['buildid'], 1);
    } else{
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['catid'] = $_POST['catid'];
      $data['title'] = $_POST['title'];
      $data['issued_date'] = $_POST['issued_date'];
      if(!empty($_FILES['file']['tmp_name'])) {
        $data['file'] = $this->Upload_File($_FILES['file'], 'reports', 'all');
      }
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/index/?buildid=' . $_POST['buildid'], 1);
    } else {
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "building_reports.title LIKE '%$_GET[title]%'";
    }
    if($_GET['buildid']){
      $where[] = "building_reports.buildid='$_GET[buildid]'";
    }
    if($_GET['catid']){
      $where[] = "building_reports.catid='$_GET[catid]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
