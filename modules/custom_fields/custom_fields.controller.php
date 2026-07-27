<?php

class custom_fields extends custom_fields_model
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

  public function search()
  {
    if(strlen(utf8_decode($_GET['q'])) < 1){
      //return false;
    }
    $results = $this->searchQuery($_GET['q']);
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['catid'] = $_POST['catid'];
      $data['type'] = $_POST['type'];
      $data['title'] = $_POST['title'];
      $data['description'] = $_POST['description'];
      $data['suffix'] = $_POST['suffix'];
      $data['icon'] = $_POST['icon'];
      $data['required'] = $_POST['required'];

      $newid = $this->addrecord($data);
      $this->registerLog($newid);

      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $newid, 'title' => $_POST['title'], 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/index/?catid=' . $_POST['catid'], 1);
    } else {
      $allicons = $this->readlocalfile(ROOT_DIR . '/assets/css/front-font-icons.css');
      preg_match_all('/faback-([a-z0-9-]+):before/', $allicons, $fonticons);
      $this->Smarty->assign('fontIcons', $fonticons[1]);
      $this->enqueueCSS('front-font-icons');

      $this->Smarty->assign('categories', $this->auto_load_list('custom_fields/categories'));
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $field = $this->readrecord($_POST['id']);

      $data = array();
      $data['title'] = $_POST['title'];
      $data['description'] = $_POST['description'];
      $data['suffix'] = $_POST['suffix'];
      $data['icon'] = $_POST['icon'];
      $data['required'] = $_POST['required'];

      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);

      $this->showmsg(CPURL . '/' . Module . '/index/?catid=' . $field['catid'], 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));

      $allicons = $this->readlocalfile(ROOT_DIR . '/assets/css/front-font-icons.css');
      preg_match_all('/faback-([a-z0-9-]+):before/', $allicons, $fonticons);
      $this->Smarty->assign('fontIcons', $fonticons[1]);
      $this->enqueueCSS('front-font-icons');

      $this->Smarty->assign('categories', $this->auto_load_list('custom_fields/categories'));
      $this->Output('edit');
    }
  }

  public function index()
  {
    $where = [];
    $inner = '';
    if($_GET['catid']) {
      $where[] = "custom_fields.catid='$_GET[catid]'";
    }
    if($_GET['title']) {
      $where[] = "custom_fields.title LIKE '%$_GET[title]%'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
