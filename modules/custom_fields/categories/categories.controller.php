<?php

class categories extends categories_model
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
      $this->redirect(CPURL . '/' . Module . '/index/');
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['build_catid'] = $_POST['build_catid'];

      $newid = $this->addrecord($data);
      $this->registerLog($newid);

      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $newid, 'title' => $_POST['title'], 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/index/', 1);
    } else{
      $this->Smarty->assign('buildcats', $this->auto_load_list('buildcats'));

      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['build_catid'] = $_POST['build_catid'];

      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);

      $this->showmsg(CPURL . '/' . Module . '/index/', 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Smarty->assign('buildcats', $this->auto_load_list('buildcats'));

      $this->Output('edit');
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "custom_fields_cats.title='$_GET[title]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
