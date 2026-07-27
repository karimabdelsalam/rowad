<?php

class newsletter extends newsletter_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function dashboard()
  {
    if($_POST){
      $this->checkExist(array('sender', 'subject', 'content'));
      $data = array();
      $data['subject'] = $_POST['subject'];
      $data['sender'] = $_POST['sender'];
      $data['message'] = htmlspecialchars_decode($_POST['content']);
      $data['sendtime'] = 0;

      if($_POST['usergroupid']){
        $usergroupids = array();
        foreach($_POST['usergroupid'] as $key => $groupid){
          if($_POST['usergroupid'][$key]){
            $usergroupids[] = $groupid;
          }
        }
        $this->queueUserEmails($usergroupids, $data);
      }

      if($_POST['email_list']){
        $this->queueMaillist($data);
      }

      if($_POST['employees']){
        $this->queueMailTable('employees');
      }
      if($_POST['buyer']){
        $this->queueMailTable('buyer');
      }
      if($_POST['owner']){
        $this->queueMailTable('owner');
      }

      $this->redirect(CPURL . '/' . Module . '/dashboard');
    } else{
      $this->Smarty->assign('usergroups', $this->listUserGroup());
      $this->Output();
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

  public function import()
  {
    if($_POST){
      $contacts = $this->Upload_File($_FILES['contacts'], 'uploads', array('csv', 'vcf', 'txt'));
      $content = $this->readfile(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $contacts);
      @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $contacts);
      if(preg_match_all('/[a-z\d._%+-]+@[a-z\d.-]+\.[a-z]{2,4}\b/i', $content, $matches)){
        $this->saveEmails($matches[0]);
        $this->showmsg(CPURL . '/' . Module, 1);
      } else{
        $this->showmsg(gettext('لم يتم العثور على اي بيانات تصلح كبريد إلكتروني. من فضلك تأكد ان ترميز الملف هو UTF-8'), 0);
      }
    } else{
      $this->Output();
    }
  }

  public function onesms()
  {
    if($_POST['sms']){
      $this->sendsms($_POST['number'], $_POST['sms']);
      $this->showmsg(CPURL . '/' . Module, 1);
    } else {
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    if($_GET['email']){
      $where[] = "email='$_GET[email]'";
    }
    $results = $this->listEmail($where);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
