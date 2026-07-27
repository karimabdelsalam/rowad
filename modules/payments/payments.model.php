<?php

class payments_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function checkEditStatus($contractid, $module)
  {
    $bool = $this->db->get_var("SELECT id FROM payments WHERE contractid='$contractid' AND module='$module' AND gone='1' LIMIT 1");
    if($bool){
      $this->showmsg(gettext('لا يمكن التعديل على العقد في وجود مبالغ مالية مرحلة'), 0);
    }
    $bool = $this->db->get_var("SELECT id FROM transactions WHERE contractid='$contractid' AND module='$module' AND gone='1' LIMIT 1");
    if($bool){
      $this->showmsg(gettext('لا يمكن التعديل على العقد في وجود مستحقات مالية مرحلة'), 0);
    }
    $this->db->query("DELETE FROM payments WHERE contractid='$contractid' AND module='$module' AND type!='expenses'");
  }

  public function buildManCostPeriod($buildManCost)
  {
    if($buildManCost['mcost_cycle'] == 'once'){
      $period_cycle = 0;
    } elseif($buildManCost['mcost_cycle'] == 'month'){
      $period_cycle = 1;
    } elseif($buildManCost['mcost_cycle'] == 'quartyear'){
      $period_cycle = 3;
    } elseif($buildManCost['mcost_cycle'] == 'midyear'){
      $period_cycle = 6;
    } elseif($buildManCost['mcost_cycle'] == 'year'){
      $period_cycle = 12;
    }

    if(!empty($buildManCost['mcost_next_date'])){
      $start_in_date = $buildManCost['mcost_next_date'];
    } else {
      $start_in_date = $buildManCost['mcost_start_date'];
    }

    $start_dates = explode('-', $start_in_date);
    if($buildManCost['calendar'] == 1){
      $next_date = date('Y-m-d', mktime(0, 0, 0, ($start_dates[1] + $period_cycle), $start_dates[2], $start_dates[0]));
    } else{
      $next_date = uCal::firstMonthDay($start_in_date, $period_cycle);
    }

    $owners = json_decode($this->db->get_var("SELECT owner FROM building WHERE id='$buildManCost[id]'"), true);
    foreach($owners['id'] as $key => $ownerid){
      if(!empty($owners['outgoings'])){
        $transaction = array();
        $transaction['typeid'] = 'mancost';
        $transaction['ownerid'] = $owners['id'][$key];
        $transaction['buildid'] = $buildManCost['id'];
        $transaction['amount'] = round(($buildManCost['mcost'] * $owners['outgoings'][$key]) / 100, 2);
        $transaction['direction'] = 'credit';
        $transaction['module'] = 'build';
        $transaction['paydate'] = $start_in_date;
        $transID = $this->db->insert('transactions', $transaction);
        if($this->config['income_tax_status'] == 1){
          $transaction['ownerid'] = 0;
          $transaction['amount'] = round($transaction['amount'] * ($this->config['income_tax_value'] / 100), 2);
          $transaction['typeid'] = 'comp_tax';
          $transaction['parent'] = $transID;
          $this->db->insert('transactions', $transaction);
        }
        $transaction['parent'] = 0;
      }
    }

    return ($period_cycle == 0)? NULL : $next_date;
  }

  public function expensesOnContract($buildid, $amount, $date, $calendar, $statementid, $taxs, $reason)
  {
    $build = $this->db->get_row("SELECT contractid,`type` FROM `building` WHERE id='$buildid'");

    $payment = array();
    $payment['buildid'] = $buildid;
    $payment['statoutid'] = $statementid;
    $payment['contractid'] = $build['contractid'];
    $payment['module'] = ($build['type'] == 'rent') ? 'rent' : 'sell';
    $payment['calendar'] = $calendar;
    $payment['type'] = 'expenses';
    $payment['paydate'] = ($calendar == 2) ? uCal::u2g($date) : $date;
    $payment['amount'] = $amount;
    $payment['tax'] = $taxs;
    $payment['tax_percent'] = $taxs > 0 ? $this->config['vat'] : 0;
    $payment['reason'] = empty($reason) ? '' : $reason;

    $this->db->insert('payments', $payment);
  }

  public function rentContractSetup($contractid, $data)
  {
    $payment = array();
    $payment['buildid'] = $data['buildid'];
    $payment['contractid'] = $contractid;
    $payment['module'] = 'rent';
    $payment['calendar'] = $data['calendar'];
    $payment['tax'] = $data['tax'];
    $payment['tax_percent'] = $data['tax_percent'];

    if($data['payment'] == 'day'){
      $cycle = 1;
      $payment['type'] = 'day_rent';
    } elseif($data['payment'] == 'month'){
      $cycle = 1;
      $payment['type'] = ($data['period'] == 'year') ? 'year_rent_month' : 'month_rent';
    } elseif($data['payment'] == 'quartyear'){
      $cycle = 3;
      $payment['type'] = 'year_rent_quartyear';
    } elseif($data['payment'] == 'midyear'){
      $cycle = 6;
      $payment['type'] = 'year_rent_midyear';
    } elseif($data['payment'] == 'year'){
      $cycle = 12;
      $payment['type'] = 'year_rent_year';
    }

    $comm_cycle = 0;
    if($data['comm_cycle'] == 'day'){
      $comm_cycle = 1;
    } elseif($data['comm_cycle'] == 'month'){
      $comm_cycle = 1;
    } elseif($data['comm_cycle'] == 'quartyear'){
      $comm_cycle = 3;
    } elseif($data['comm_cycle'] == 'midyear'){
      $comm_cycle = 6;
    } elseif($data['comm_cycle'] == 'year'){
      $comm_cycle = 12;
    }

    $startdate = explode('-', $data['startdate']);
    $totalAmount = 0;
    $totalComission = 0;
    $period = ($data['period'] == 'year') ? (($data['periodnum'] * 12) / $cycle) : $data['periodnum'];
    for($i = 0; $i < $period; $i++){
      if($data['calendar'] == 1){
        if($data['payment'] == 'day'){
          $payment['paydate'] = date('Y-m-d', mktime(0, 0, 0, $startdate[1], ($startdate[2] + ($i * $cycle)), $startdate[0]));
        } else {
          $payment['paydate'] = date('Y-m-d', mktime(0, 0, 0, ($startdate[1] + ($i * $cycle)), $startdate[2], $startdate[0]));
        }
      } else {
        if($data['payment'] == 'day'){
          $payment['paydate'] = date('Y-m-d', mktime(0, 0, 0, $startdate[1], ($startdate[2] + ($i * $cycle)), $startdate[0]));
        } else {
          $payment['paydate'] = uCal::firstMonthDay($data['startdate'], ($i * $cycle));
        }
      }
      if(empty($data['comm_cycle'])){
        $payment['commission2'] = 0;
        $payment['comm_type'] = 'money';
      } elseif(($data['comm_cycle'] == 'once' && $i == 0) || $data['comm_cycle'] == 'day' || ($data['comm_cycle'] != 'once' && (($i * $cycle) % $comm_cycle) == 0)){
        $payment['commission2'] = $data['commission'];
        $payment['comm_type'] = 'money';
      } else{
        $payment['commission2'] = 0;
        $payment['comm_type'] = 'money';
      }
      $payment['amount'] = $data['rentvalue']+$payment['commission2'];
      $totalAmount += $payment['amount'];
      $totalComission += $payment['commission2'];

      if($data['payment'] == 'day'){
        if($period == ($i+1)){
          $payment['amount'] = $totalAmount;
          $payment['commission2'] = $totalComission;
          $payment['alert'] = 15;
          $this->db->insert('payments', $payment);
        }
      } else {
        $this->db->insert('payments', $payment);
      }
    }
  }

  public function sellContractSetup($contractid, $data)
  {
    $payment = array();
    $payment['buildid'] = $data['buildid'];
    $payment['contractid'] = $contractid;
    $payment['module'] = 'sell';
    $payment['calendar'] = $data['calendar'];

    if($data['paytype'] == 'once'){
      $data['once'] = json_decode($data['once'], true);
      $payment['amount'] = $data['total'];
      if($data['once']['comissiontype'] == 'money'){
        $once_commission = $data['once']['comission'];
        $payment['comm_type'] = 'money';
      } else{
        $once_commission = ($data['total'] / 100) * $data['once']['comission'];
        $payment['comm_type'] = 'percent';
      }
      if($data['once']['comissionmethod'] == 'buyer'){
        $payment['commission'] = 0;
        $payment['commission2'] = $once_commission;
      } elseif($data['once']['comissionmethod'] == 'seller'){
        $payment['commission'] = $once_commission;
        $payment['commission2'] = 0;
      } elseif($data['once']['comissionmethod'] == 'fifty'){
        $payment['commission'] = $once_commission / 2;
        $payment['commission2'] = $once_commission / 2;
      }
      $payment['paydate'] = DATENOW;
      $payment['type'] = 'instant_sell';
      $payment['amount'] += $payment['commission2'];
      $this->db->insert('payments', $payment);
    } elseif($data['paytype'] == 'parts'){
      $data['parts'] = json_decode($data['parts'], true);
      $totalPartsAmount = 0;
      foreach($data['parts']['total'] as $key => $part){
        $totalPartsAmount += $data['parts']['total'][$key];
      }
      if($totalPartsAmount != $data['total']){
        $this->showmsg(gettext('خطأ! اجمالي مبالغ الدفعات لا يساوي اجمالي قيمة العقد'), 0);
      }
      foreach($data['parts']['total'] as $key => $part){
        $payment['amount'] = $data['parts']['total'][$key];
        $part_commission = $data['parts']['cash_commvalue'][$key];
        if($data['parts']['comissionmethod'][0] == 'buyer'){
          $payment['commission'] = 0;
          $payment['commission2'] = $part_commission;
        } elseif($data['parts']['comissionmethod'][0] == 'seller'){
          $payment['commission'] = $part_commission;
          $payment['commission2'] = 0;
        } elseif($data['parts']['comissionmethod'][0] == 'fifty'){
          $payment['commission'] = $part_commission / 2;
          $payment['commission2'] = $part_commission / 2;
        }
        $payment['comm_type'] = 'money';
        if($data['parts']['cheque_notnow'][$key] == 1){
          $payment['paydate'] = $data['parts']['cheque_todate'][$key];
        } else{
          $payment['paydate'] = $data['parts']['cheque_recdate'][$key];
        }
        $payment['type'] = 'parts_sell';
        $payment['amount'] += $payment['commission2'];
        $this->db->insert('payments', $payment);
      }
    } elseif($data['paytype'] == 'installment'){
      $data['installment'] = json_decode($data['installment'], true);
      $payment['type'] = 'installs_sell';
      $payment['amount'] = round(($data['total'] - $data['installment']['firstvalue']) / $data['installment']['installscount'], 2);
      $startdate = explode('-', $data['installment']['startdate']);
      if($data['installment']['period'] == 'month'){
        $cycle = 1;
      } elseif($data['installment']['period'] == 'quartyear'){
        $cycle = 3;
      } elseif($data['installment']['period'] == 'midyear'){
        $cycle = 6;
      } elseif($data['installment']['period'] == 'year'){
        $cycle = 12;
      }
      for($i = 1; $i <= $data['installment']['installscount']; $i++){
        if($data['calendar'] == 1){
          $payment['paydate'] = date('Y-m-d', mktime(0, 0, 0, ($startdate[1] + ($i * $cycle)), $startdate[2], $startdate[0]));
        } else{
          $payment['paydate'] = uCal::firstMonthDay($data['installment']['startdate'], ($i * $cycle));
        }
        $this->db->insert('payments', $payment);
      }
    }
  }

}
