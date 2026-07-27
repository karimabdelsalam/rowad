<?php

namespace ownerarea;

class support extends \ownerarea\support_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function send()
  {
    if($_POST){
      $this->checkExist(['buildid','subject','message']);

      $data = array();
      $data['type'] = $_POST['type'];
      $data['ownerid'] = $_SESSION['ownerid'];
      $data['buildid'] = $_POST['buildid'];
      $data['subject'] = $_POST['subject'];
      $data['message'] = $_POST['message'];
      $data['code'] = 'TEMP';
      $data['read_state'] = 0;
      if(!empty($_FILES['attachment']['tmp_name'])) {
        $data['file'] = $this->Upload_File($_FILES['attachment'], 'support', 'all');
      } else {
        $data['file'] = '';
      }

      $id = $this->addrecord($data);

      $prefix = (!empty($this->config['support_ticket_prefix']))? $this->config['support_ticket_prefix'] : strtoupper($this->AlphaSalt('3'));
      $idCode = $prefix . '-' . ($id+1000);
      $this->updaterecord(['code' => $idCode], $id);

      $this->addNotification($this->vgettext('طلب جديد'), $_SESSION['userinfo']['name'] .' ' . $this->vgettext('ارسل طلب جديد للدعم الفني'), 'alert', 0);

      $this->showmsg(gettext('تم استقبال طلبكم بنجاح و سيتم الرد عليكم في اقرب وقت ممكن. يمكنكم المتابعة برقم الطلب #') . $idCode, 'success');
    } else {
      $this->Smarty->assign('builds', $this->ownerBuilds());
      $this->Output();
    }
  }

}
