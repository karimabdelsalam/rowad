<?php

class pages extends pages_model
{

  public function __construct()
  {
    parent::__construct();
    $this->Smarty->assign('pagepositions', $this->getPositions());
  }

  public function order()
  {
    if($_POST['sortedIDs']){
      $ids = json_decode(stripslashes(htmlspecialchars_decode($_POST['sortedIDs'])), false);
      $order = 1;
      foreach($ids as $id){
        $id = str_replace('menuSortOrder', '', $id);
        $data = array();
        $data['listorder'] = $order;
        $this->savePage($data, $id);
        $order++;
      }
    }
  }

  public function active()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasActive(0, $ids);
    } else{
      $this->markasActive($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function inactive()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasinActive(0, $ids);
    } else{
      $this->markasinActive($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
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

  public function edit()
  {
    if($_POST){
      $data = array();
      if($_POST['type'] == 'menu'){
        unset($_POST['linkin'], $_POST['linkout'], $_POST['content']);
      } elseif($_POST['type'] == 'page'){
        unset($_POST['linkout']);
      } elseif($_POST['type'] == 'linkin'){
        unset($_POST['linkout'], $_POST['content']);
      } elseif($_POST['type'] == 'linkout'){
        unset($_POST['linkin'], $_POST['content']);
      }
      $data['title'] = $_POST['title'];
      $data['type'] = $_POST['type'];
      $data['parent'] = $_POST['parent'];
      if(!empty($_POST['linkin'])){
        $data['link'] = $_POST['linkin'];
      } elseif(!empty($_POST['linkout'])){
        $data['link'] = $_POST['linkout'];
      }
      $data['positionid'] = $_POST['positionid'];
      $data['content'] = htmlspecialchars_decode($_POST['content']);
      $data['active'] = $_POST['active'];
      $this->savePage($data, $_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/index/?parent=' . $_POST['parent'], 1);
    } else{
      $this->Smarty->assign('mainpages', $this->listPages(array("pages.type='menu'"), false));
      $this->Smarty->assign('data', $this->readPage($_GET['id']));
      $this->Output();
    }
  }

  public function add()
  {
    if($_POST){
      $data = array();
      if($_POST['type'] == 'menu'){
        unset($_POST['linkin'], $_POST['linkout'], $_POST['content']);
      } elseif($_POST['type'] == 'page'){
        unset($_POST['linkout']);
      } elseif($_POST['type'] == 'linkin'){
        unset($_POST['linkout'], $_POST['content']);
      } elseif($_POST['type'] == 'linkout'){
        unset($_POST['linkin'], $_POST['content']);
      }
      $data['title'] = $_POST['title'];
      $data['type'] = $_POST['type'];
      $data['parent'] = $_POST['parent'];
      if(!empty($_POST['linkin'])){
        $data['link'] = $_POST['linkin'];
      } elseif(!empty($_POST['linkout'])){
        $data['link'] = $_POST['linkout'];
      }
      $data['positionid'] = $_POST['positionid'];
      $data['content'] = htmlspecialchars_decode($_POST['content']);
      $data['active'] = $_POST['active'];
      $this->addPage($data);
      $this->showmsg(CPURL . '/' . Module, 1);
    } else{
      $this->Smarty->assign('mainpages', $this->listPages(array("pages.type='menu'"), false));
      $this->Output('edit');
    }
  }

  public function index()
  {
    $where = array();
    if($_GET['title']){
      $where[] = "pages.title LIKE '%$_GET[title]%'";
    }
    if($_GET['positionid']){
      $where[] = "pages.positionid='$_GET[positionid]'";
    }
    if($_GET['parent']){
      $where[] = "pages.parent='$_GET[parent]'";
    } else{
      $where[] = "pages.parent='0'";
    }
    $results = $this->listPages($where);
    $this->Smarty->assign('results', $results);
    $this->enqueuejs('jquery-ui-sortable.min');
    $this->Output();
  }

}