<?php

use chillerlan\QRCode\QRCode;

class sell extends sell_model
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

  public function invoice()
  {
    if($_GET['id']){
      $this->redirect(CPURL.'/invoices/create/?sellid='.$_GET['id']);
    }
  }

  public function archiveit()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markArhcive(0, $ids, 1);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->markArhcive($_GET['id'], '', 1);
      $this->redirect(CPURL . '/' . Module);
    }
  }

  public function unarchive()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markArhcive(0, $ids, 0);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->markArhcive($_GET['id'], '', 0);
      $this->redirect(CPURL . '/' . Module);
    }
  }

  public function printable()
  {
    $this->turnTempArabic();
    require LIB_DIR . '/I18N/Arabic.php';
    require(LIB_DIR.'/qrcode/autoload.php');

    $Arabic = new I18N_Arabic('Numbers');
    $contract = $this->readrecord($_GET['id']);
    $daynum = date('w', $contract['postdate']);
    $buyermodule = $this->auto_load('buyer');
    $buildmodule = $this->auto_load('building');
    $ownermodule = $this->auto_load('owner');

    $buyers = '';
    foreach($contract['buyers'] as $key => $buyerid){
      $buyer = $buyermodule->readrecord($buyerid);
      $buyers .= '<tr>';
      $buyers .= '<td>' . $buyer['fullname'] . '</td>';
      if($buyer['type'] != 'person'){
        $buyers .= '<td style="text-align:center">' . $contract['buyer']['share'][$key] . '%</td>';
        $buyers .= '<td></td>';
        $buyers .= '<td style="text-align:center">' . $buyer['com_idnumber'] . '</td>';
        $buyers .= '<td style="text-align:center">' . $buyer['com_mobile'] . '</td>';
        $buyers .= '</tr><tr><td>'.gettext('ويمثلها').': ' . $buyer['person'] . '</td>';
        $buyers .= '<td style="text-align:center">ــ</td>';
      }
      else{
        $buyers .= '<td style="text-align:center">' . $contract['buyer']['share'][$key] . '%</td>';
      }
      $buyers .= '<td style="text-align:center">' . $buyer['national'] . '</td>';
      $buyers .= '<td style="text-align:center">' . $buyer['idnumber'] . '</td>';
      $buyers .= '<td style="text-align:center">' . $buyer['mobile'] . '</td>';
      $buyers .= '</tr>';
    }

    $building = $buildmodule->readrecord($contract['buildid']);
    $owners = '';
    foreach($building['owners'] as $key => $ownerid){
      $owner = $ownermodule->readrecord($ownerid);
      $owners .= '<tr>';
      $owners .= '<td>' . $owner['fullname'] . '</td>';
      $owners .= '<td style="text-align:center">' . $building['owner']['share'][$key] . '%</td>';
      $owners .= '<td style="text-align:center">' . $owner['national'] . '</td>';
      $owners .= '<td style="text-align:center">' . $owner['idnumber'] . '</td>';
      $owners .= '<td style="text-align:center">' . $owner['mobile'] . '</td>';
      $owners .= '</tr>';
    }

    $position = '';
    foreach($building['plots']['no'] as $key => $no){
      $position .= '<tr>';
      $position .= '<td>' . $building['plots']['no'][$key] . '</td>';
      $position .= '<td>' . $building['plots']['size'][$key] . '</td>';
      $position .= '<td>' . $this->processDimensions($building['plots']['north_type'][$key], $building['plots']['north_width'][$key], $building['plots']['north_plotno'][$key]) . ' '.gettext('بطول').' (' . $building['plots']['north_length'][$key] . ') '.gettext('م').'</td>';
      $position .= '<td>' . $this->processDimensions($building['plots']['south_type'][$key], $building['plots']['south_width'][$key], $building['plots']['south_plotno'][$key]) . ' '.gettext('بطول').' (' . $building['plots']['south_length'][$key] . ') '.gettext('م').'</td>';
      $position .= '<td>' . $this->processDimensions($building['plots']['east_type'][$key], $building['plots']['east_width'][$key], $building['plots']['east_plotno'][$key]) . ' '.gettext('بطول').' (' . $building['plots']['east_length'][$key] . ') '.gettext('م').'</td>';
      $position .= '<td>' . $this->processDimensions($building['plots']['west_type'][$key], $building['plots']['west_width'][$key], $building['plots']['west_plotno'][$key]) . ' '.gettext('بطول').' (' . $building['plots']['west_length'][$key] . ') '.gettext('م').'</td>';
      $position .= '</tr>';
    }

    $meters = '';
    foreach($building['meters']['no'] as $key => $meterno){
      $meters .= '<tr>';
      $meters .= '<td><p style="text-align:center">' . $building['meters']['type_name'][$key] . '</p></td>';
      $meters .= '<td><p style="text-align:center">' . $building['meters']['owner_type_name'][$key] . '</p></td>';
      $meters .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['no'][$key] . '</p></td>';
      $meters .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['pay_no'][$key] . '</p></td>';
      $meters .= '<td><p dir="LTR" style="text-align:center">' . $building['meters']['pay_code'][$key] . '</p></td>';
      $meters .= '</tr>';
    }

    $deeds = '';
    foreach($building['deed']['no'] as $key => $deedno){
      $deeds .= '<tr>';
      $deeds .= '<td><p style="text-align:center">' . $building['deed']['type_name'][$key] . '</p></td>';
      $deeds .= '<td><p style="text-align:center">' . $building['deed']['no'][$key] . '</p></td>';
      $deeds .= '<td><p dir="LTR" style="text-align:center">' . $building['deed']['source'][$key] . '</p></td>';
      $deeds .= '<td><p dir="LTR" style="text-align:center">' . $building['deed']['date'][$key] . '</p></td>';
      $deeds .= '</tr>';
    }

    $comission = 'ـــ';
    $firstvalue = 'ـــ';
    $payment = '';
    if($contract['paytype'] == 'once'){
      $payment = gettext('وقد دفع الطرف الثاني عند تحرير هذا العقد مبلغ وقدره'). ': ' . $contract['total'] . ' ' . $this->config['currency'] . ' '.gettext('فقط').' ' . $Arabic->int2str($contract['total']) . ' ' . $this->config['currency'] . ' '.gettext('لا غير');
      if($contract['once']['comissionmethod'] == 'buyer'){
        $payment .= '<br> '.gettext('ويتحمل الطرف الثاني قيمة عمولة المكتب وقدرها').': ';
      } elseif($contract['once']['comissionmethod'] == 'seller'){
        $payment .= '<br> '.gettext('ويتحمل الطرف الأول قيمة عمولة المكتب وقدرها').': ';
      } elseif($contract['once']['comissionmethod'] == 'fifty'){
        $payment .= '<br> '.gettext('ويتحمل كل طرف مناصفة قيمة عمولة المكتب وقدرها').': ';
      }
      if($contract['once']['comissiontype'] == 'money'){
        $comission = $contract['once']['comission'];
      } elseif($contract['once']['comissiontype'] == 'percent'){
        $comission = round(($contract['total'] / 100) * $contract['once']['comission'], 2);
      }
      $payment .= $comission . ' ' . $this->config['currency'] . ' '.gettext('فقط').' ' . $Arabic->int2str($comission) . ' ' . $this->config['currency'] . ' '. gettext('لا غير');
    }
    elseif($contract['paytype'] == 'parts'){
      $comission = 0;
      $payment = gettext('يتم تسديد مبلغ وقدره').' ' . $contract['total'] . ' ' . $this->config['currency'] . ' '.gettext('فقط').' ' . $Arabic->int2str($contract['total']) . ' ' . $this->config['currency'] . ' '.gettext('لا غير مقسمة على').' ' . count($contract['parts']['total']) . ' ' . gettext('دفعة') . '<br><br>';
      for($i = 0; $i < count($contract['parts']['total']); $i++){
        if($contract['parts']['cheque_notnow'][$i] == 1){
          $partdate = ($contract['calendar'] == 2) ? uCal::g2u($contract['parts']['cheque_todate'][$i]) : $contract['parts']['cheque_todate'][$i];
        } else{
          $partdate = ($contract['calendar'] == 2) ? uCal::g2u($contract['parts']['cheque_recdate'][$i]) : $contract['parts']['cheque_recdate'][$i];
        }
        $payment .= gettext('دفعة').' ' . ($i + 1) . ': '.gettext('يتم تسديد مبلغ وقدره').' ' . $contract['parts']['total'][$i] . ' ' . $this->config['currency'] . ' '.gettext('فقط').' ' . $Arabic->int2str($contract['parts']['total'][$i]) . ' ' . $this->config['currency'] . ' '.gettext('لا غير في تاريخ').' ' . $partdate;
        $partsComission = $contract['parts']['cash_commvalue'][$i];
        if(!empty($partsComission)){
          $payment .= '<br>';
          $comission += $partsComission;
          if($contract['parts']['comissionmethod'][0] == 'buyer'){
            $payment .= gettext('ويتحمل الطرف الثاني قيمة عمولة المكتب وقدره').': ';
          } elseif($contract['parts']['comissionmethod'][0] == 'seller'){
            $payment .= gettext('ويتحمل الطرف الأول قيمة عمولة المكتب وقدره').': ';
          } elseif($contract['parts']['comissionmethod'][0] == 'fifty'){
            $payment .= gettext('ويتحمل كل طرف مناصفة قيمة عمولة المكتب وقدره').': ';
          }
          $payment .= $partsComission . ' ' . $this->config['currency'] . ' '.gettext('فقط').' ' . $Arabic->int2str($partsComission) . ' ' . $this->config['currency'] . ' '.gettext('لا غير');
        }
        $payment .= '<br><br>';
      }
      $payment .= gettext('وعلى الطرف الثاني أن يلتزم بالسداد على حسب طريقة الدفع المحددة أعلاه');
    }
    elseif($contract['paytype'] == 'installment'){
      if($contract['installment']['period'] == 'month'){
        $period = gettext('شهرية');
      } elseif($contract['installment']['period'] == 'quartyear'){
        $period = gettext('ربع سنوية');
      } elseif($contract['installment']['period'] == 'midyear'){
        $period = gettext('نصف سنوية');
      } elseif($contract['installment']['period'] == 'year'){
        $period = gettext('سنوية');
      }
      $installdate = ($contract['calendar'] == 2) ? uCal::g2u($contract['installment']['startdate']) : $contract['installment']['startdate'];
      $payment = gettext('يتم تسديد مبلغ وقدره').' ' . $contract['total'] . ' ' . $this->config['currency'] . ' '.gettext('فقط').' ' . $Arabic->int2str($contract['total']) . ' ' . $this->config['currency'] . ' '.gettext('لا غير على هيئة اقساط').' ' . $period . '<br>';
      $payment .= gettext('عدد الأقساط') . ' ' . $contract['installment']['installscount'] . ' '.gettext('فقط').' ' . $Arabic->int2str($contract['installment']['installscount']) . ' '.gettext('لا غير تبدأ من تاريخ').' ' . $installdate . '<br>';
      $payment .= gettext('تم تسديد مبلغ وقدره').' ' . $contract['installment']['firstvalue'] . ' ' . $this->config['currency'] . ' '.gettext('فقط').' ' . $Arabic->int2str($contract['installment']['firstvalue']) . ' ' . $this->config['currency'] . ' '.gettext('لا غير كدفعة أولى').' ';
      $payment .= '<br>'.gettext('وعلى الطرف الثاني أن يلتزم بالسداد على حسب طريقة الدفع المحددة أعلاه');
      $firstvalue = $contract['installment']['firstvalue'];
    }

    $blockReplaces = array(
      'meters' => $meters,
      'deeds' => $deeds,
      'position' => $position,
      'owners' => $owners,
      'buyers' => $buyers,
      'rights' => (empty($contract['allrights'])) ? '' : ('<li>' . implode('</li><li>', $contract['allrights']) . '</li>')
      );
    $replaces = array(
      'serial' => $contract['id'],
      'number' => $contract['id'],
      'date' => ($contract['calendar'] == 2) ? uCal::g2u($contract['postdate']) : $contract['postdate'],
      'day' => uCal::$l['ar'][$daynum],
      'title' => $building['title'],
      'address' => $building['loc_details'],
      'city' => $building['city_name'],
      'district' => $building['district'],
      'type' => $building['type_name'],
      'category' => $building['buildcat_name'],
      'desc' => $building['details'],
      'planno' => $building['planno'],
      'platno' => $building['platno'],
      'size' => (empty($building['size']))? '--' : $building['size'] . gettext('م2'),
      'zone' => $building['zone'],
      'street' => $building['street'],
      'floor' => (empty($building['floor']))? '--' : $building['floor'],
      'rooms' => (empty($building['rooms']))? '--' : $building['rooms'],
      'furnished' => $building['furnished_text'],
      'commission' => $comission,
      'amount' => $contract['total'],
      'total' => $contract['total']+$comission,
      'total_str' => $Arabic->int2str($contract['total']+$comission),
      'pay_method' => $contract['paytypename'],
      'pay_details' => $payment,
      'firstvalue' => $firstvalue,
      'buildno' => $building['buildno'],
      'qrcode_owner' => '<images src="'.(new QRCode)->render(CPURL.'/client/?secret='.$this->encodeHash('type=sell&id='.$contract['id'].'&person=owner&time='.TIMENOW)).'" alt="QR Code" />',
      'qrcode_buyer' => '<images src="'.(new QRCode)->render(CPURL.'/client/?secret='.$this->encodeHash('type=sell&id='.$contract['id'].'&person=customer&time='.TIMENOW)).'" alt="QR Code" />'
      );
    $this->processDoc($replaces, 9, $blockReplaces);
  }

  public function add()
  {
    if($_POST){
      if(empty($_POST['buyer']['id'])){
        $this->showmsg(gettext('نسيت اختيار مشتري العقار لا يمكن المتابعة'), 0);
      }
      if($_POST['paytype'] == 'parts' && empty($_POST['parts']['total'])){
        $this->showmsg(gettext('لم تقم بإضافة اي دفعات'), 0);
      }
      $data = array();
      $data['buildid'] = $_POST['buildid'];
      $data['calendar'] = $_POST['calendar'];
      $data['postdate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['postdate']) : $_POST['postdate'];
      $data['buyer'] = json_encode($this->cleanArray($_POST['buyer'], 'id'), JSON_UNESCAPED_UNICODE);
      $data['rightsid'] = $_POST['rightsid'];
      $data['extrarights'] = json_encode($this->cleanArray($_POST['extrarights']), JSON_UNESCAPED_UNICODE);
      $data['total'] = $_POST['total'];
      $data['paytype'] = $_POST['paytype'];
      if($_POST['paytype'] == 'once'){
        $data['once'] = json_encode($_POST['once'], JSON_UNESCAPED_UNICODE);
        $data['installment'] = '';
        $data['parts'] = '';
      } elseif($_POST['paytype'] == 'parts'){
        $_POST['parts'] = $this->cleanArray($_POST['parts'], 'total');
        if(empty($_POST['parts']['total'])){
          $this->showmsg(gettext('لم تقم بإدخال بيانات ومواعيد الدفعات'), 0);
        }
        $data['once'] = '';
        $data['installment'] = '';
        foreach($_POST['parts']['total'] as $key => $part){
          if($_POST['parts']['cheque_notnow'][$key] == 1){
            $_POST['parts']['cheque_todate'][$key] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['parts']['cheque_todate'][$key]) : $_POST['parts']['cheque_todate'][$key];
          } else{
            $_POST['parts']['cheque_recdate'][$key] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['parts']['cheque_recdate'][$key]) : $_POST['parts']['cheque_recdate'][$key];
          }
        }
        $data['parts'] = json_encode($_POST['parts'], JSON_UNESCAPED_UNICODE);
      } elseif($_POST['paytype'] == 'installment'){
        $data['once'] = '';
        $_POST['installment']['startdate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['installment']['startdate']) : $_POST['installment']['startdate'];
        $data['installment'] = json_encode($_POST['installment'], JSON_UNESCAPED_UNICODE);
        $data['parts'] = '';
      }
      $this->checkTotalAmounts();
      $sellid = $this->addrecord($data);
      $this->registerLog($sellid);
      $this->moveAttachments($_POST['tempid'], $sellid);
      $this->updateBuildContract($_POST['buildid'], $sellid);

      $payments = $this->auto_load('payments');
      $payments->sellContractSetup($sellid, $data);

      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      if(empty($_GET['id'])){
        $this->Output('choose_build');
        exit;
      }
      $build = $this->auto_load_read($_GET['id'], 'building');
      if($build['active'] == 0){
        $this->showmsg(gettext('لا يمكن اضافة عقد جديد لعقار غير نشط'), 0);
      }
      if($build['available'] == 0){
        $this->showmsg(gettext('لا يمكن اضافة عقد جديد لعقار مشغول'), 0);
      }
      $this->Smarty->assign('samples', $this->auto_load_list('sample'));
      $this->Smarty->assign('rights', $this->auto_load_list('rights'));
      $this->Smarty->assign('build', $build);
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      if(empty($_POST['buyer']['id'])){
        $this->showmsg(gettext('نسيت اختيار مشتري العقار لا يمكن المتابعة'), 0);
      }
      if($_POST['paytype'] == 'parts' && empty($_POST['parts']['total'])){
        $this->showmsg(gettext('لم تقم بإضافة اي دفعات'), 0);
      }
      $data = array();
      $data['buildid'] = $_POST['buildid'];
      $data['calendar'] = $_POST['calendar'];
      $data['postdate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['postdate']) : $_POST['postdate'];
      $data['buyer'] = json_encode($this->cleanArray($_POST['buyer'], 'id'), JSON_UNESCAPED_UNICODE);
      $data['rightsid'] = $_POST['rightsid'];
      $data['extrarights'] = json_encode($this->cleanArray($_POST['extrarights']), JSON_UNESCAPED_UNICODE);
      $data['total'] = $_POST['total'];
      $data['paytype'] = $_POST['paytype'];
      if($_POST['paytype'] == 'once'){
        $data['once'] = json_encode($_POST['once'], JSON_UNESCAPED_UNICODE);
        $data['installment'] = '';
        $data['parts'] = '';
      } elseif($_POST['paytype'] == 'parts'){
        $_POST['parts'] = $this->cleanArray($_POST['parts'], 'total');
        if(empty($_POST['parts']['total'])){
          $this->showmsg(gettext('لم تقم بإدخال بيانات ومواعيد الدفعات'), 0);
        }
        $data['once'] = '';
        $data['installment'] = '';
        foreach($_POST['parts']['total'] as $key => $part){
          if($_POST['parts']['cheque_notnow'][$key] == 1){
            $_POST['parts']['cheque_todate'][$key] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['parts']['cheque_todate'][$key]) : $_POST['parts']['cheque_todate'][$key];
          } else{
            $_POST['parts']['cheque_recdate'][$key] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['parts']['cheque_recdate'][$key]) : $_POST['parts']['cheque_recdate'][$key];
          }
        }
        $data['parts'] = json_encode($_POST['parts'], JSON_UNESCAPED_UNICODE);
      } elseif($_POST['paytype'] == 'installment'){
        $data['once'] = '';
        $_POST['installment']['startdate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['installment']['startdate']) : $_POST['installment']['startdate'];
        $data['installment'] = json_encode($_POST['installment'], JSON_UNESCAPED_UNICODE);
        $data['parts'] = '';
      }
      $this->checkTotalAmounts();
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);

      if($_POST['dimremoved'] == 1){
        $payments = $this->auto_load('payments');
        $payments->checkEditStatus($_POST['id'], 'sell');
        $payments->sellContractSetup($_POST['id'], $data);
      }

      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $contract = $this->readrecord($_GET['id']);
      $this->Smarty->assign('data', $contract);
      $this->Smarty->assign('samples', $this->auto_load_list('sample'));
      $this->Smarty->assign('rights', $this->auto_load_list('rights'));
      $this->Smarty->assign('build', $this->auto_load_read($contract['buildid'], 'building'));
      $this->Output();
    }
  }

  public function checkTotalAmounts()
  {
    if($_POST['paytype'] == 'parts'){
      $total = $_POST['total'];
      $totalAmount = 0;
      foreach($_POST['parts']['total'] as $amount){
        $totalAmount += $amount;
      }
      if($totalAmount != $total){
        $this->showmsg(gettext('إجمالي مبالغ الدفعات لا يساوي قيمة العقد.'), 0);
      }
    }
  }

  public function archive()
  {
    $this->index();
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['id']){
      $where[] = "sell_contracts.buildid='$_GET[id]'";
      $this->Smarty->assign('build', $this->auto_load_read($_GET['id'], 'building'));
    }
    if(Action == 'archive'){
      $where[] = "sell_contracts.archive='1'";
    } else{
      $where[] = "sell_contracts.archive='0'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output('index');
  }

}
