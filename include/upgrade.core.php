<?php

if($this->config['version'] == 1.0){

  $this->db->query("CREATE TABLE IF NOT EXISTS `meter_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` char(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $this->db->query("INSERT INTO `meter_types` (`id`, `title`) VALUES
(1, 'كهرباء'),
(2, 'غاز'),
(3, 'مياه');");

  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `icon`, `position`, `shortcut`, `hidden`) VALUES (129, '1', 'meter_types', 'انواع العدادات', 'calculator', '12', '0', '120');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES
(592, 93, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(593, 96, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(594, 92, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(595, 102, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(596, 105, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(597, 107, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(598, 110, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(599, 126, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(600, 127, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(601, 128, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0),
(602, 129, 0, 'index', '', 'التحكم في الأنواع', 'edit', 0, 0, 1, 0),
(603, 129, 0, 'add', '', 'اضافة نوع جديد', 'plus-circle', 0, 0, 2, 0),
(604, 129, 0, 'edit', '', 'تعديل البيانات', 'pencil', 0, 0, 1, 1),
(605, 129, 0, 'delete', '', 'حذف', 'trash-o', 1, 0, 2, 1),
(606, 129, 0, 'multiadd', '', 'اضافة متعددة', 'clone', 0, 0, 3, 0);
");

  $this->db->query("ALTER TABLE `building` ADD `meters` MEDIUMTEXT NOT NULL AFTER `deed`;");

  $buildings = $this->db->get_results("SELECT id,deed,elecno,waterno,elecaccnum,wateraccnum FROM building");
  if($buildings){
    foreach($buildings as $building){
      $data = [];

      $data['meters'] = [];
      $data['meters']['typeid'] = ['1','3'];
      $data['meters']['owner_type'] = ['private','private'];
      $data['meters']['no'] = [ $building['elecno'], $building['waterno'] ];
      $data['meters']['pay_no'] = [ $building['elecaccnum'], $building['wateraccnum'] ];
      $data['meters'] = json_encode($data['meters'], JSON_UNESCAPED_UNICODE);

      $data['deed'] = [];
      $building['deed'] = json_decode($building['deed'], true);
      if(!empty($building['deed']['no'])){
        foreach($building['deed'] as $key => $dead){
          $data['deed'][$key] = [ $dead ];
        }
      }
      $data['deed'] = json_encode($data['deed'], JSON_UNESCAPED_UNICODE);

      $this->db->update('building', $data, [ 'id' => $building['id'] ]);
    }
  }

  $this->db->query("ALTER TABLE `building` DROP `elecno`, DROP `waterno`, DROP `elecaccnum`, DROP `wateraccnum`;");

  $this->db->query("ALTER TABLE `rent_contracts` ADD `meters` MEDIUMTEXT NOT NULL AFTER `rightsid`;");

  $rent_contracts = $this->db->get_results("SELECT id,elecno,watercno,buildid FROM rent_contracts");
  if($rent_contracts){
    foreach($rent_contracts as $rent_contract){
      $meters = [];
      $building_meters = json_decode($this->db->get_var("SELECT meters FROM building WHERE id='$rent_contract[buildid]'"), true);
      if(!empty($building_meters['typeid'])){
        foreach($building_meters['typeid'] as $key => $typeid){
          if($typeid == 1){
            $meters[$building_meters['no'][$key]] = $rent_contract['elecno'];
          } elseif($typeid == 3){
            $meters[$building_meters['no'][$key]] = $rent_contract['watercno'];
          }
        }
        $this->db->update('rent_contracts', [ 'meters' => json_encode($meters, JSON_UNESCAPED_UNICODE) ], [ 'id' => $rent_contract['id'] ]);
      }
    }
  }

  $this->db->query("ALTER TABLE `rent_contracts` DROP `elecno`, DROP `watercno`;");

  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('style', 'modern');");

  $this->db->query("ALTER TABLE `user` CHANGE `joindate` `joindate` INT(11) UNSIGNED NOT NULL;");

  $this->db->query("ALTER TABLE `building` ADD `calendar` TINYINT NOT NULL AFTER `tax`, ADD `mcost` CHAR(10) NOT NULL DEFAULT '0' AFTER `calendar`, ADD `mcost_cycle` CHAR(20) NOT NULL AFTER `mcost`, ADD `mcost_start_date` DATE NULL AFTER `mcost_cycle`, ADD `mcost_next_date` DATE NULL AFTER `mcost_start_date`, ADD INDEX (`mcost_next_date`);");
  $this->db->query("UPDATE `building` SET `calendar`='".$this->config['system_calendar']."'");

  $this->db->query("INSERT INTO `transaction_types` (`id`, `type`) VALUES ('mancost', 'إدارة مِلك');");
  $this->db->query("ALTER TABLE `transactions` CHANGE `module` `module` ENUM('rent','sell','build') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;");

  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (607, '15', '0', 'onesms', '', 'مراسلة رقم SMS', 'commenting', '0', '0', '0', '2');");

  $this->db->query("ALTER TABLE `user` DROP `sms_code`, DROP `sms_code_timeout`;");

  $this->db->query("ALTER TABLE `statement_out` ADD `transid` INT NOT NULL AFTER `buildid`;");

  $this->db->query("UPDATE `email_templates` SET `active` = '1' WHERE `action` = 'smsLogin';");

  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (608, '79', '0', 'index', '', 'تسجيل الدخول', 'sign-in', '0', '0', '0', '3');");

  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (609, '109', '0', 'transinfo', '', '', '', '0', '0', '0', '3');");

  $this->db->query("CREATE TABLE IF NOT EXISTS `login_activity` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userid` int(10) UNSIGNED NOT NULL,
  `code` char(10) NOT NULL,
  `timeout` int(10) UNSIGNED NOT NULL,
  `retries` tinyint(4) NOT NULL,
  `signature` char(32) NOT NULL,
  `ip` char(100) NOT NULL,
  `agent` MEDIUMTEXT NOT NULL,
  `login_time` int(10) UNSIGNED NOT NULL,
  `verified` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`,`signature`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;");

  $this->config['version'] = 2.0;
}
if($this->config['version'] <= 2.0){
  $this->config['version'] = 2.1;
}
if($this->config['version'] <= 2.1){
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('income_tax_status', '0'), ('income_tax_value', '0');");

  $this->db->query("ALTER TABLE `transactions` ADD `tax` CHAR(15) NOT NULL DEFAULT '0' AFTER `amount`;");
  $this->db->query("ALTER TABLE `building` DROP `bid`, ADD `bids` MEDIUMTEXT NOT NULL AFTER `type`;");

  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `icon`, `position`, `shortcut`, `hidden`) VALUES (130, '1', 'reports/corp_taxes', 'ضريبة دخل الشركة', 'bank', '6', '0', '114');");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'كشف حساب ضرائب العقار' WHERE `cp_module_action`.`id` = 518;");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (610, '130', '0', 'index', '', 'كشف حساب ضريبة الدخل للمؤسسة', 'briefcase', '0', '0', '1', '0'), (611, '130', '0', 'view', '', 'عرض التفاصيل', 'eye', '0', '2', '1', '1'), (612, '130', '0', 'printable', '', 'طباعة', 'print', '0', '0', '1', '1');");

  $this->config['version'] = 3.0;
}
if($this->config['version'] <= 3.0){
  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `icon`, `position`, `shortcut`, `hidden`) VALUES (131, '1', 'invoices', 'فواتير', 'newspaper-o', '5', '0', '111');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES
(613, 131, 0, 'create', '', 'اصدار فاتورة', '', 0, 0, 0, 2),
(614, 112, 0, 'processninv', 'payid', 'ترحيل واصدار فاتورة', 'lock', 1, 7, 4, 1),
(615, 112, 0, 'invoice', 'payid', 'اصدار فاتورة', 'newspaper-o', 1, 7, 5, 1),
(616, 113, 0, 'invoice', 'payid', 'اصدار فاتورة', 'newspaper-o', 1, 7, 4, 1),
(617, 97, 0, 'invoice', '', 'طباعة فاتورة الإيجارات', 'newspaper-o', 0, 7, 6, 1),
(618, 97, 0, 'invoiceall', '', 'طباعة فاتورة شاملة', 'newspaper-o', 0, 7, 6, 1),
(619, 98, 0, 'invoice', '', 'طباعة فاتورة بالدفعات', 'newspaper-o', 0, 7, 6, 1),
(620, 131, 0, 'printable', '', 'طباعة', 'print', 0, 7, 1, 1),
(621, 131, 0, 'index', '', 'مشاهدة الفواتير', 'lock', 0, 0, 1, 0);");
  $this->db->query("INSERT INTO `payment_types` (`name`, `title`) VALUES ('expenses', 'مصروفات');");
  $this->db->query("ALTER TABLE `payments` ADD `invoiceid` INT NOT NULL AFTER `statementid`;");
  $this->db->query("ALTER TABLE `payments` CHANGE `type` `type` ENUM('month_rent','year_rent_month','instant_sell','parts_sell','installs_sell','year_rent_midyear','year_rent_year','year_rent_quartyear','expenses') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;");

  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('invoices_calendar', '2');");
  $this->db->query("ALTER TABLE `user` ADD `super` BOOLEAN NOT NULL AFTER `reset_code`;");
  $this->db->query("UPDATE `user` SET `super`='1' WHERE id='1'");

  $this->db->query("CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` char(200) NOT NULL,
  `query` mediumtext NOT NULL,
  `created_date` date NOT NULL,
  `calendar` tinyint(1) NOT NULL,
  `inserted_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` mediumtext NOT NULL,
  `paid` char(15) NOT NULL DEFAULT '0',
  `total` char(15) NOT NULL DEFAULT '0',
  `totaltaxs` char(15) NOT NULL DEFAULT '0',
  `paidamounts` char(15) NOT NULL DEFAULT '0',
  `items` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;");

  $this->db->query("INSERT INTO `print_samples` VALUES (12, 'نموذج فاتورة', '&lt;div style=&quot;font-family:arial,helvetica,sans-serif;width:297mm;direction:rtl;font-size:15px;font-weight:bold&quot;&gt;\n&lt;h1 style=&quot;text-align:center&quot;&gt;&lt;span style=&quot;color:#B22222&quot;&gt;&lt;span style=&quot;font-size:28px&quot;&gt;فـــاتـورة&lt;/span&gt;&lt;/span&gt;&lt;/h1&gt;\n\n&lt;p&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;تُحرر إلى:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{fullname}&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;رقم الفاتورة:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{no}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td&gt;&lt;/td&gt;\n			&lt;td&gt;&lt;/td&gt;\n			&lt;td style=&quot;width: 85px;&quot;&gt;التاريخ:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{created_date}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear: both; text-align: center;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:15px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:369px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;البند&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:229px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الوصف&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;العمولة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;القيمة&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n		&lt;tr&gt;\n			&lt;td colspan=&quot;5&quot;&gt;{items}&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;height:5px&quot;&gt;&lt;/div&gt;\n\n&lt;table align=&quot;left&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:30%; margin-left: 8%;&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:87px&quot;&gt;الإجمالي الفرعي&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{total}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;ضريبة&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{taxs}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;الإجمالي&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{total_wtaxes}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبالغ المستلمة&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{paid}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبلغ المدفوع&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{collected}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;باقي&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{remain}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:10px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;ملاحظات:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{notes}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المدير&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المحاسب&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الختم الرسمي&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n&lt;/div&gt;', '&lt;div style=&quot;font-family:arial,helvetica,sans-serif;width:297mm;direction:rtl;font-size:15px;font-weight:bold&quot;&gt;\n&lt;h1 style=&quot;text-align:center&quot;&gt;&lt;span style=&quot;color:#B22222&quot;&gt;&lt;span style=&quot;font-size:28px&quot;&gt;فـــاتـورة&lt;/span&gt;&lt;/span&gt;&lt;/h1&gt;\n\n&lt;p&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;تُحرر إلى:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{fullname}&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;رقم الفاتورة:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{no}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td&gt;&lt;/td&gt;\n			&lt;td&gt;&lt;/td&gt;\n			&lt;td style=&quot;width: 85px;&quot;&gt;التاريخ:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{created_date}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear: both; text-align: center;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:15px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:369px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;البند&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:229px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الوصف&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;العمولة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;القيمة&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n		&lt;tr&gt;\n			&lt;td colspan=&quot;5&quot;&gt;{items}&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;height:5px&quot;&gt;&lt;/div&gt;\n\n&lt;table align=&quot;left&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:30%; margin-left: 8%;&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204); width:87px&quot;&gt;الإجمالي الفرعي&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{total}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;ضريبة&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{taxs}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;الإجمالي&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{total_wtaxes}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبالغ المستلمة&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{paid}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبلغ المدفوع&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{collected}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;باقي&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{remain}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:10px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;ملاحظات:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{notes}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المدير&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المحاسب&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الختم الرسمي&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n&lt;/div&gt;', 1, 1);");

  $this->config['version'] = 3.1;
}
if($this->config['version'] <= 3.1){
  $this->db->query("ALTER TABLE `rent_contracts` ADD INDEX(`startdate`), ADD INDEX(`enddate`);");

  $this->config['version'] = 3.2;
}
if($this->config['version'] <= 3.2){
  $this->db->query("ALTER TABLE `cp_module` ADD `info` CHAR(150) NOT NULL AFTER `title`;");
  $this->db->query("INSERT INTO `transaction_types` (`id`, `type`) VALUES ('commission2', 'عمولة المكتب من الطرف الثاني');");
  $this->db->query("ALTER TABLE `statement_out` DROP `transid`;");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `cp_module_action`.`id` = 614;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مشاهدة مستحقات غير محصلة' WHERE `cp_module_action`.`id` = 510;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مشاهدة مستحقات مدفوعة' WHERE `cp_module_action`.`id` = 511;");
  $this->db->query("UPDATE `cp_module_action` SET `name` = 'add',`title` = 'ترحيل بسند قبض',`ajax` = '5',`param` = 'payid',`actiongroup` = '0',`refmoduleid` = '108' WHERE id = 506;");
  $this->db->query("UPDATE `cp_module_action` SET `name` = 'add',`param` = 'transid', `title` = 'ترحيل بسند صرف', `ajax` = '5',`refmoduleid` = '109',`actiongroup` = '0' WHERE `id` = 570;");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (622, '124', '108', 'add', 'transid', 'ترحيل بسند قبض', 'lock', '1', '5', '3', '1');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (623, '124', '0', 'statmout', '', 'ترحيل بسند صرف', '', '1', '0', '1', '2');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (624, '124', '0', 'statmin', '', 'ترحيل بسند قبض', '', '1', '0', '1', '2');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (625, '112', '108', 'statmin', '', 'ترحيل بسند قبض', '', '1', '0', '1', '2');");
  $this->db->query("ALTER TABLE `statement_in` DROP `paymentid`;");
  $this->db->query("ALTER TABLE `transactions` CHANGE `statid` `statoutid` INT(11) NOT NULL;");
  $this->db->query("ALTER TABLE `transactions` ADD `statinid` INT UNSIGNED NOT NULL AFTER `module`;");
  $this->db->query("UPDATE `cp_module` SET `title` = 'مستحقات غير محصلة', `info` = 'الإيجارات والأقساط او اية مبالغ مراد تحصيلها من مشتري العقارات او المستأجرين' WHERE `id` = 112;");
  $this->db->query("UPDATE `cp_module` SET `title` = 'مستحقات مدفوعة ومرحلة',`info` = 'ما تم تحصيله من ايجارات او أقساط من مشتري العقارات او المستأجرين' WHERE `id` = 113;");
  $this->db->query("UPDATE `cp_module` SET `title` = 'ايرادات غير مدفوعة',`info` = 'ايرادات العقارات من ايجارات او أقساط او مصروفات مراد تسليمها لمالكي العقارات او خصم المصاريف والأرباح والعمولات منهم' WHERE `id` = 124;");
  $this->db->query("UPDATE `cp_module` SET `title` = 'ايرادات مدفوعة ومرحلة',`info` = 'المبالغ التي تسليمها لمالكي العقارات او المستحقات التي تم تحصيلها كعمولات او مصاريف' WHERE `id` = 125;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مستحقات مدفوعة ومرحلة' WHERE `id` = 534;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مستحقات غير محصلة' WHERE `id` = 535;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'ايرادات مدفوعة ومرحلة' WHERE `id` = 573;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'ايرادات غير مدفوعة' WHERE `id` = 574;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مستحقات مدفوعة ومرحلة' WHERE `id` = 537;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مستحقات غير محصلة' WHERE `id` = 538;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'ايرادات مدفوعة ومرحلة' WHERE `id` = 575;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'ايرادات غير مدفوعة' WHERE `id` = 576;");

  $this->db->query("ALTER TABLE `transaction_types` ADD `income` BOOLEAN NOT NULL AFTER `type`, ADD `outcome` BOOLEAN NOT NULL AFTER `income`;");
  $this->db->query("UPDATE `transaction_types` SET `type` = 'ايرادات مالك العقار', `outcome` = '1' WHERE `id` = 'payment';");
  $this->db->query("UPDATE `transaction_types` SET `type` = 'ضريبة عقارية', `income` = '1', `outcome` = '1' WHERE `id` = 'tax';");
  $this->db->query("UPDATE `transaction_types` SET `income` = '1' WHERE `id` = 'commission';");
  $this->db->query("UPDATE `transaction_types` SET `income` = '1', `outcome` = '1' WHERE `id` = 'outgoings';");
  $this->db->query("UPDATE `transaction_types` SET `income` = '1' WHERE `id` = 'mancost';");
  $this->db->query("UPDATE `transaction_types` SET `income` = '1' WHERE `id` = 'commission2';");
  $this->db->query("INSERT INTO `transaction_types` (`id`, `type`, `income`, `outcome`) VALUES ('comp_tax', 'ضريبة دخل الشركة', '0', '1');");
  $this->db->query("INSERT INTO `transaction_types` (`id`, `type`, `income`, `outcome`) VALUES ('owner_tax', 'ضريبة دخل مالك عقار', '1', '1');");

  $this->db->query("ALTER TABLE `transactions` DROP `tax`, ADD `parent` INT UNSIGNED NOT NULL AFTER `module`;");
  $this->db->query("ALTER TABLE `payments` ADD `statoutid` INT UNSIGNED NOT NULL AFTER `statementid`;");

  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('owner_tax_status', '0'), ('owner_tax_value', '0'), ('phone_length', '9'), ('mobile_length', '11');");
  $this->db->query("ALTER TABLE `building` ADD `size` SMALLINT UNSIGNED NOT NULL AFTER `loc_details`, ADD `zone` CHAR(100) NOT NULL AFTER `size`, ADD `street` CHAR(100) NOT NULL AFTER `zone`, ADD `floor` SMALLINT UNSIGNED NOT NULL AFTER `street`, ADD `rooms` SMALLINT UNSIGNED NOT NULL AFTER `floor`, ADD `furnished` BOOLEAN NOT NULL AFTER `rooms`;");

  $this->config['version'] = 3.3;
}
if($this->config['version'] <= 3.3){
  $this->db->query("ALTER TABLE `user` ADD `lang` CHAR(2) NOT NULL AFTER `idnum`;");
  $this->db->query("UPDATE `user` SET `lang` = 'ar'");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '43', '0', 'lang', '', 'تغيير لغة النظام', 'edit', '0', '0', '0', '3');");

  $this->config['version'] = 3.4;
}
if($this->config['version'] <= 3.4){
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('lang_file_ar', ''), ('lang_file_en', '');");

  $this->config['version'] = 3.5;
}
if($this->config['version'] <= 3.5){
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('lang', 'ar');");

  $this->config['version'] = 3.6;
}
if($this->config['version'] <= 3.6){
  $config = $this->readlocalfile(INC_DIR.'/config.php');
  $this->storelocalfile(INC_DIR.'/config.php', str_replace('session_start();', '', $config));

  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES (132, '1', 'reports/income', 'الإيرادات', '', 'calculator', '2', '0', '114');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (627, '132', '0', 'index', '', 'عرض الإيرادات', 'calculator', '0', '0', '1', '0');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (628, '132', '0', 'printable', '', 'طباعة', 'print', '0', '0', '2', '1');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (629, '132', '0', 'view', '', 'عرض التفاصيل', 'eye', '0', '2', '1', '1');");

  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES (133, '1', 'reports/banking', 'الحسابات البنكية', '', 'exchange', '3', '0', '114');");
  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES (134, '1', 'reports/units', 'اداء العقارات', '', 'bar-chart', '5', '0', '114');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (630, '133', '0', 'index', '', 'عرض تفاصيل الحساب', 'exchange', '0', '0', '1', '0');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (631, '134', '0', 'index', '', 'تقرير اداء العقارات', 'bar-chart', '0', '0', '1', '0');");

  $this->db->query("ALTER TABLE `statement_in` ADD `typeid` INT NOT NULL AFTER `buildid`;");
  if($this->config['lang'] == 'ar'){
    $this->db->query("INSERT INTO `outgoings_types` (`id`, `title`) VALUES (NULL, 'غير محدد');");
  } else {
    $this->db->query("INSERT INTO `outgoings_types` (`id`, `title`) VALUES (NULL, 'Undefined');");
  }
  $this->db->query("UPDATE `statement_in` SET `typeid` = '".$this->db->insert_id."'");

  $this->config['version'] = 4.0;
}
if($this->config['version'] <= 4.0){
  $this->db->query("INSERT INTO `payment_types` (`name`, `title`) VALUES ('day_rent', 'ايجار يومي');");
  $this->db->query("ALTER TABLE `payments` CHANGE `type` `type` ENUM('month_rent','year_rent_month','instant_sell','parts_sell','installs_sell','year_rent_midyear','year_rent_year','year_rent_quartyear','expenses','day_rent') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;");
  $this->db->query("ALTER TABLE `building` ADD `rent_day` CHAR(10) NOT NULL AFTER `rent_month`;");
  $this->db->query("ALTER TABLE `attachments` ADD `timepost` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `tempid`;");

  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (632, '108', '0', 'delattach', '', 'حذف ملف مرفق', '', '0', '0', '0', '2');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (633, '109', '0', 'delattach', '', 'حذف ملف مرفق', '', '0', '0', '0', '2');");

  $this->config['version'] = 4.1;
}
if($this->config['version'] <= 4.1){
  $this->config['version'] = 4.2;
}
if($this->config['version'] <= 4.2){
  $this->config['version'] = 4.3;
}
if($this->config['version'] <= 4.3){
  $this->db->query("UPDATE `cp_module` SET `hidden` = '91',`icon` = 'user' WHERE `cp_module`.`id` = 89;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '91',`icon` = 'users' WHERE `cp_module`.`id` = 90;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '91',`icon` = 'envelope' WHERE `cp_module`.`id` = 121;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '3',`icon` = 'tasks' WHERE `cp_module`.`id` = 4;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '3',`icon` = 'paper-plane' WHERE `cp_module`.`id` = 15;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '3',`icon` = 'gavel' WHERE `cp_module`.`id` = 16;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '111',`icon` = 'arrow-circle-o-left' WHERE `cp_module`.`id` = 108;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '111',`icon` = 'arrow-circle-o-right' WHERE `cp_module`.`id` = 109;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '104',`icon` = 'exclamation-triangle' WHERE `cp_module`.`id` = 103;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '91',`icon` = 'refresh' WHERE `cp_module`.`id` = 97;");
  $this->db->query("UPDATE `cp_module` SET `hidden` = '91',`icon` = 'handshake-o' WHERE `cp_module`.`id` = 98;");

  $this->config['version'] = 4.4;
}
if($this->config['version'] <= 4.4){
  $this->config['version'] = 4.5;
}
if($this->config['version'] <= 4.5){
  $this->config['version'] = 4.6;
}
if($this->config['version'] <= 4.6){
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('license_fail', '0');");
  $this->config['version'] = 4.7;
}
if($this->config['version'] <= 4.7){
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (634, '112', '0', 'reset_partpay', '', 'إلغاء الدفعات الجزأية', 'eraser', '0', '3', '2', '1');");
  $this->config['version'] = 4.8;
}
if($this->config['version'] <= 4.81){
  $this->db->query("ALTER TABLE `buyer` ADD `billing_info` TEXT NOT NULL AFTER `workphone`;");
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('billing_info', '');");
  $this->db->query("ALTER TABLE `invoices` ADD `buyerid` INT UNSIGNED NOT NULL AFTER `id`;");
  $this->db->query('INSERT INTO `print_samples` (`id`, `title`, `content`, `backup`, `header`, `footer`) VALUES (13, \'نموذج فاتورة ضريبية\', \'&lt;div style=&quot;font-family:arial,helvetica,sans-serif;width:297mm;direction:rtl;font-size:15px;font-weight:bold&quot;&gt;\n&lt;h1 style=&quot;text-align:center&quot;&gt;&lt;span style=&quot;color:#B22222&quot;&gt;&lt;span style=&quot;font-size:28px&quot;&gt;فاتورة ضريبية&lt;/span&gt;&lt;/span&gt;&lt;/h1&gt;\n\n&lt;p&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:25%&quot;&gt;{qrcode}&lt;/td&gt;\n			&lt;td style=&quot;width:30%&quot;&gt;\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;رقم الفاتورة:&lt;/td&gt;\n						&lt;td&gt;{no}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc; margin-top: 40px;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;تاريخ اصدار الفاتورة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{created_date}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;تاريخ التوريد:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{created_date}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%; margin-top: 40px;&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:50%&quot;&gt;\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color:rgb(172, 172, 172); font-size: 18px; text-align: right; color: #fff;&quot;&gt;العميل:&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;الإسم:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{fullname}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم المبنى:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.buildno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;اسم الشارع:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.street}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الحي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.district}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;المدينة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.city}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;البلد:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer_country}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرمز البريدي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.postalcode}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرقم الإضافي للعنوان&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.addno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم تسجيل ضريبة القيمة المضافة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.taxno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;معرف آخر&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.otherid}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:50%&quot;&gt;\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color:rgb(172, 172, 172); font-size: 18px; text-align: right; color: #fff;&quot;&gt;المورد:&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;الإسم:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{org_arname}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم المبنى:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.buildno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;اسم الشارع:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.street}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الحي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.district}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;المدينة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.city}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;البلد:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller_country}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرمز البريدي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.postalcode}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرقم الإضافي للعنوان&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.addno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم تسجيل ضريبة القيمة المضافة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.taxno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;معرف آخر&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.otherid}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear: both; text-align: center;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:15px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;th colspan=&quot;8&quot; style=&quot;background-color: rgb(172, 172, 172); text-align: right; font-size: 18px; color: #fff; padding: 8px;&quot;&gt;توصيف السلعة أو الخدمة&lt;/th&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:229px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;المجموع (شامل ضريبة القيمة المضافة)&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;مبلغ الضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;نسبة الضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;خصومات&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;المبلغ الخاضع للضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;الكمية&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;سعر الوحدة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:369px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;تفاصيل السلع أو الخدمات&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n		&lt;tr&gt;\n			&lt;td colspan=&quot;8&quot;&gt;{items}&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;height:30px&quot;&gt;&lt;/div&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;width:86%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:50%&quot;&gt;\n			&lt;table align=&quot;right&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color: rgb(172, 172, 172); text-align: right; font-size: 18px; color: #fff; padding: 8px;&quot;&gt;إجمالي المبالغ&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204); width:190px&quot;&gt;الإجمالي غير شاملة ضريبة القيمة المضافة&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{total}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;مجموع الخصومات&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;0.00 {$}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;الإجمالي الخاضع للضريبة (غير شاملة ضريبة القيمة المضافة)&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{total}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;مجموع ضريبة القيمة المضافة&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{taxs}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;إجمالي المبلغ المستحق&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{total_wtaxes}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:50%; vertical-align: baseline;&quot;&gt;\n			&lt;table align=&quot;left&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:80%;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color: rgb(172, 172, 172); text-align: right; font-size: 18px; color: #fff; padding: 8px;&quot;&gt;إجمالي المستلم&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبالغ المستلمة&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{paid}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبلغ المدفوع&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{collected}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;باقي&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{remain}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;height:5px&quot;&gt;&lt;/div&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;ملاحظات:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{notes}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المدير&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المحاسب&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الختم الرسمي&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n&lt;/div&gt;\', \'&lt;div style=&quot;font-family:arial,helvetica,sans-serif;width:297mm;direction:rtl;font-size:15px;font-weight:bold&quot;&gt;\n&lt;h1 style=&quot;text-align:center&quot;&gt;&lt;span style=&quot;color:#B22222&quot;&gt;&lt;span style=&quot;font-size:28px&quot;&gt;فاتورة ضريبية&lt;/span&gt;&lt;/span&gt;&lt;/h1&gt;\n\n&lt;p&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:25%&quot;&gt;{qrcode}&lt;/td&gt;\n			&lt;td style=&quot;width:30%&quot;&gt;\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;رقم الفاتورة:&lt;/td&gt;\n						&lt;td&gt;{no}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc; margin-top: 40px;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;تاريخ اصدار الفاتورة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{created_date}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;تاريخ التوريد:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{created_date}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%; margin-top: 40px;&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:50%&quot;&gt;\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color:rgb(172, 172, 172); font-size: 18px; text-align: right; color: #fff;&quot;&gt;العميل:&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;الإسم:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{fullname}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم المبنى:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.buildno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;اسم الشارع:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.street}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الحي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.district}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;المدينة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.city}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;البلد:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer_country}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرمز البريدي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.postalcode}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرقم الإضافي للعنوان&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.addno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم تسجيل ضريبة القيمة المضافة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.taxno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;معرف آخر&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{customer.billing.otherid}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:50%&quot;&gt;\n			&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%; border: 2px solid #ccc;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color:rgb(172, 172, 172); font-size: 18px; text-align: right; color: #fff;&quot;&gt;المورد:&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;width:30%;border: 2px solid #ccc;&quot;&gt;الإسم:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{org_arname}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم المبنى:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.buildno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;اسم الشارع:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.street}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الحي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.district}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;المدينة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.city}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;البلد:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller_country}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرمز البريدي:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.postalcode}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;الرقم الإضافي للعنوان&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.addno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;رقم تسجيل ضريبة القيمة المضافة:&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.taxno}&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;معرف آخر&lt;/td&gt;\n						&lt;td style=&quot;border: 2px solid #ccc;&quot;&gt;{seller.billing.otherid}&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear: both; text-align: center;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:15px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;th colspan=&quot;8&quot; style=&quot;background-color: rgb(172, 172, 172); text-align: right; font-size: 18px; color: #fff; padding: 8px;&quot;&gt;توصيف السلعة أو الخدمة&lt;/th&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:229px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;المجموع (شامل ضريبة القيمة المضافة)&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;مبلغ الضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;نسبة الضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;خصومات&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;المبلغ الخاضع للضريبة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;الكمية&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:120px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;سعر الوحدة&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;background-color:rgb(107, 107, 107); width:369px&quot;&gt;\n			&lt;p style=&quot;text-align:center; color: #fff;&quot;&gt;تفاصيل السلع أو الخدمات&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n		&lt;tr&gt;\n			&lt;td colspan=&quot;8&quot;&gt;{items}&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;!--{items}--&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;height:30px&quot;&gt;&lt;/div&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;width:86%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:50%&quot;&gt;\n			&lt;table align=&quot;right&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:100%;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color: rgb(172, 172, 172); text-align: right; font-size: 18px; color: #fff; padding: 8px;&quot;&gt;إجمالي المبالغ&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204); width:190px&quot;&gt;الإجمالي غير شاملة ضريبة القيمة المضافة&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{total}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;مجموع الخصومات&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;0.00 {$}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;الإجمالي الخاضع للضريبة (غير شاملة ضريبة القيمة المضافة)&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{total}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;مجموع ضريبة القيمة المضافة&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{taxs}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;إجمالي المبلغ المستحق&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{total_wtaxes}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:50%; vertical-align: baseline;&quot;&gt;\n			&lt;table align=&quot;left&quot; border=&quot;1&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:80%;&quot;&gt;\n				&lt;tbody&gt;\n					&lt;tr&gt;\n						&lt;th colspan=&quot;2&quot; style=&quot;background-color: rgb(172, 172, 172); text-align: right; font-size: 18px; color: #fff; padding: 8px;&quot;&gt;إجمالي المستلم&lt;/th&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبالغ المستلمة&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{paid}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;المبلغ المدفوع&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{collected}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n					&lt;tr&gt;\n						&lt;td style=&quot;background-color:rgb(204, 204, 204);&quot;&gt;باقي&lt;/td&gt;\n						&lt;td&gt;\n						&lt;p style=&quot;text-align: right;&quot;&gt;{remain}&lt;/p&gt;\n						&lt;/td&gt;\n					&lt;/tr&gt;\n				&lt;/tbody&gt;\n			&lt;/table&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;height:5px&quot;&gt;&lt;/div&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;5&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width: 65px;&quot;&gt;ملاحظات:&lt;/td&gt;\n			&lt;td&gt;\n			&lt;p style=&quot;text-align: right;&quot;&gt;{notes}&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n\n&lt;table align=&quot;center&quot; border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; style=&quot;border-collapse:collapse; font-weight:bold; padding:5px; width:85%&quot;&gt;\n	&lt;tbody&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المدير&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;توقيع المحاسب&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p style=&quot;text-align:center&quot;&gt;الختم الرسمي&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n		&lt;tr&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n			&lt;td style=&quot;width:239px&quot;&gt;\n			&lt;p&gt;&lt;/p&gt;\n			&lt;/td&gt;\n		&lt;/tr&gt;\n	&lt;/tbody&gt;\n&lt;/table&gt;\n\n&lt;div style=&quot;clear:both;&quot;&gt;&lt;/div&gt;\n\n&lt;p style=&quot;height:30px&quot;&gt;&lt;/p&gt;\n&lt;/div&gt;\', 0, 0);');
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '131', '0', 'taxbill', '', 'طباعة فاتورة ضريبية', 'file-text-o', '0', '7', '2', '1');");
  $this->config['version'] = 4.82;
}
if($this->config['version'] <= 4.82){
  $this->config['version'] = 4.83;
}
if($this->config['version'] <= 4.83){
  $this->db->query("ALTER TABLE `attachments` ADD INDEX(`moduleid`, `objectid`);");
  $this->db->query("ALTER TABLE `attachments` ADD INDEX(`tempid`);");
  $this->db->query("ALTER TABLE `bankacc_transactions` ADD INDEX(`bankid`);");
  $this->db->query("ALTER TABLE `bankacc_transactions` ADD INDEX(`statementid`);");
  $this->db->query("ALTER TABLE `bankacc_transactions` ADD INDEX(`type`);");
  $this->db->query("ALTER TABLE `bank_accounts` ADD INDEX(`bankid`);");
  $this->db->query("ALTER TABLE `bank_accounts` ADD INDEX(`main`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`locid`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`citytid`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`contractid`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`buyercat`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`district`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`available`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`active`);");
  $this->db->query("ALTER TABLE `building` ADD INDEX(`owner`);");
  $this->db->query("ALTER TABLE `buyer` ADD INDEX(`nationality`);");
  $this->db->query("ALTER TABLE `buyer` ADD INDEX(`idnumber`);");
  $this->db->query("ALTER TABLE `buyer` ADD INDEX(`type`);");
  $this->db->query("ALTER TABLE `buyer` ADD INDEX(`fname`, `lname`, `fathname`, `famname`);");
  $this->db->query("ALTER TABLE `citizens` ADD INDEX(`title`);");
  $this->db->query("ALTER TABLE `citizens` ADD INDEX(`nationality`);");
  $this->db->query("ALTER TABLE `city` ADD INDEX(`countryid`);");
  $this->db->query("ALTER TABLE `company_owners` ADD INDEX(`fname`, `lname`, `fathname`, `famname`);");
  $this->db->query("ALTER TABLE `company_owners` ADD INDEX(`nationality`);");
  $this->db->query("ALTER TABLE `company_owners` ADD INDEX(`city`);");
  $this->db->query("ALTER TABLE `cp_logs` ADD INDEX(`userid`);");
  $this->db->query("ALTER TABLE `cp_logs` ADD INDEX(`moduleid`);");
  $this->db->query("ALTER TABLE `cp_module_action` ADD INDEX(`modulid`);");
  $this->db->query("ALTER TABLE `cp_module_action` ADD INDEX(`hidden`);");

  $this->db->query("ALTER TABLE `district` ADD INDEX(`countryid`);");
  $this->db->query("ALTER TABLE `email_templates` ADD INDEX(`action`);");
  $this->db->query("ALTER TABLE `employees` ADD INDEX(`fname`, `lname`, `fathname`, `famname`);");
  $this->db->query("ALTER TABLE `employees` ADD INDEX(`nationality`);");
  $this->db->query("ALTER TABLE `employees` ADD INDEX(`idnum`);");
  $this->db->query("ALTER TABLE `employees` ADD INDEX(`idexpire`);");
  $this->db->query("ALTER TABLE `employees` ADD INDEX(`alert`);");
  $this->db->query("ALTER TABLE `employees` ADD INDEX(`startdate`);");
  $this->db->query("ALTER TABLE `gov_licenses` ADD INDEX(`expiredate`);");
  $this->db->query("ALTER TABLE `gov_licenses` ADD INDEX(`notify`, `alert`);");
  $this->db->query("ALTER TABLE `invoices` ADD INDEX(`buyerid`);");
  $this->db->query("ALTER TABLE `invoices` ADD INDEX(`created_date`);");
  $this->db->query("ALTER TABLE `letters` ADD INDEX(`type`);");
  $this->db->query("ALTER TABLE `notes` ADD INDEX(`userid`);");
  $this->db->query("ALTER TABLE `owner` ADD INDEX(`fname`, `lname`, `fathname`, `famname`);");
  $this->db->query("ALTER TABLE `owner` ADD INDEX(`nationality`);");
  $this->db->query("ALTER TABLE `owner` ADD INDEX(`idnumber`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`buildid`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`contractid`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`statementid`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`statoutid`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`invoiceid`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`paydate`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`type`);");
  $this->db->query("ALTER TABLE `payments` ADD INDEX(`gone`);");
  $this->db->query("ALTER TABLE `punish` ADD INDEX(`employeeid`);");
  $this->db->query("ALTER TABLE `rent_contracts` ADD INDEX(`buildid`);");
  $this->db->query("ALTER TABLE `rent_contracts` ADD INDEX(`buyer`);");
  $this->db->query("ALTER TABLE `rent_contracts` ADD INDEX(`ended`);");
  $this->db->query("ALTER TABLE `sell_contracts` ADD INDEX(`buildid`);");
  $this->db->query("ALTER TABLE `sell_contracts` ADD INDEX(`buyer`);");
  $this->db->query("ALTER TABLE `sell_contracts` ADD INDEX(`status`);");
  $this->db->query("ALTER TABLE `sell_contracts` ADD INDEX(`archive`);");

  $this->db->query("ALTER TABLE `statement_in` ADD INDEX(`buildid`);");
  $this->db->query("ALTER TABLE `statement_in` ADD INDEX(`typeid`);");
  $this->db->query("ALTER TABLE `statement_in` ADD INDEX(`accid`);");
  $this->db->query("ALTER TABLE `statement_out` ADD INDEX(`buildid`);");
  $this->db->query("ALTER TABLE `statement_out` ADD INDEX(`typeid`);");
  $this->db->query("ALTER TABLE `statement_out` ADD INDEX(`accid`);");
  $this->db->query("ALTER TABLE `task` ADD INDEX(`tomanagerid`);");
  $this->db->query("ALTER TABLE `task` ADD INDEX(`done`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`typeid`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`ownerid`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`buildid`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`contractid`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`module`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`parent`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`direction`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`gone`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`statinid`);");
  $this->db->query("ALTER TABLE `transactions` ADD INDEX(`statoutid`);");

  $this->db->query("ALTER TABLE `user` CHANGE `password` `password` CHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;");
  $this->db->query("ALTER TABLE `user` ADD INDEX(`username`);");
  $this->db->query("ALTER TABLE `user` DROP `reset_code`;");

  $this->db->query("CREATE TABLE IF NOT EXISTS `reset_codes` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userid` int(10) UNSIGNED NOT NULL,
    `code` char(100) NOT NULL,
    `created_time` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `code` (`code`),
    KEY `userid` (`userid`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  if($this->config['lang'] == 'ar'){
    $this->db->query("UPDATE `email_templates` SET `content` = '&lt;p&gt;مرحبا,&lt;/p&gt;\n\n&lt;p&gt;يمكنك استعادة كلمة المرور الخاصة بكم عن طريق اتباع الرابط التالي:&lt;/p&gt;\n\n&lt;p&gt;{resetlink}&lt;br /&gt;\n&lt;br /&gt;\nمع تحيات الادارة&lt;br /&gt;\n{org_arname}&lt;br /&gt;\n{org_address}&lt;br /&gt;\n{org_mobile}-{org_phone}&lt;/p&gt;' WHERE `id` = 1;");
    $this->db->query("UPDATE `email_templates` SET `sms` = 'تم استعادة حسابك بنجاح ويمكنك الدخول الآن بإسم المستخدم {username} وكلمة المرور {password}',sms_active='1' WHERE `id` = 2;");
  } else {
    $this->db->query("UPDATE `email_templates` SET `content` = '&lt;p&gt;Hello,&lt;/p&gt;\r\n\r\n&lt;p&gt;You can recover your password by following the below link:&lt;/p&gt;\r\n\r\n&lt;p&gt;{resetlink}&lt;br /&gt;\r\n&lt;br /&gt;\r\nBest regards,&lt;br /&gt;\r\n{org_enname}&lt;br /&gt;\r\n{org_address}&lt;br /&gt;\r\n{org_mobile}-{org_phone}&lt;/p&gt;' WHERE `id` = 1;");
    $this->db->query("UPDATE `email_templates` SET `sms` = 'Your account has been successfully restored and you can now login with username {username} and password {password}',sms_active='1' WHERE `id` = 2;");
  }

  $this->db->query("ALTER TABLE `buyer` CHANGE `company` `company` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
  $this->db->query("UPDATE `buyer` SET `company`=NULL WHERE `company`=''");

  $this->db->query("UPDATE `cp_module_action` SET `title` = 'اضافة وحدة جديدة' WHERE `id` = 414;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'التحكم في الوحدات' WHERE `id` = 415;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'حذف الوحدة' WHERE `id` = 417;");

  $this->db->query("UPDATE `cp_module_action` SET `title` = 'عرض جميع الوحدات' WHERE `id` = 565;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'اضافة عقار جديد' WHERE `id` = 566;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'حذف العقار' WHERE `id` = 568;");
  $this->db->query("UPDATE `cp_module` SET `position` = '-1' WHERE `id` = 123;");

  $this->db->query("UPDATE `cp_module_action` SET `position` = '2' WHERE `cp_module_action`.`id` = 415;");
  $this->db->query("UPDATE `cp_module_action` SET `position` = '3' WHERE `cp_module_action`.`id` = 414;");

  $this->db->query("ALTER TABLE `owner` ADD `userid` INT UNSIGNED NOT NULL AFTER `id`, ADD INDEX (`userid`);");
  $this->db->query("ALTER TABLE `buyer` ADD `userid` INT UNSIGNED NOT NULL AFTER `type`, ADD INDEX (`userid`);");
  $this->db->query("INSERT INTO `user_group` (`id`, `title`) VALUES ('2', 'مالك عقار'), ('3', 'عميل');");
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('smslogin_clients', '0'), ('phone_code', '966');");

  $this->db->query("ALTER TABLE `cp_module` CHANGE `hidden` `hidden` SMALLINT NOT NULL;");

  $this->db->query("CREATE TABLE IF NOT EXISTS `support` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` enum('request','enquiry','problem','suggestion') NOT NULL,
    `code` char(30) NOT NULL,
    `ownerid` int(10) UNSIGNED NOT NULL,
    `clientid` int(10) UNSIGNED NOT NULL,
    `buildid` int(10) UNSIGNED NOT NULL,
    `subject` char(100) NOT NULL,
    `message` mediumtext NOT NULL,
    `read_state` tinyint(1) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `code` (`code`),
    KEY `type` (`type`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $this->db->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userid` int(10) UNSIGNED NOT NULL,
    `subject` CHAR(100) NOT NULL,
    `content` mediumtext NOT NULL,
    `icon` char(10) NOT NULL,
    `read_state` tinyint(1) NOT NULL,
    `created_time` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `userid` (`userid`),
    KEY `read_state` (`read_state`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES 
    (135, 2, 'dashboard', 'لوحة التحكم', '', '', 1, 0, 0),
    (136, 2, 'login', 'الحساب', '', '', 0, 0, -1),
    (137, 2, 'property', 'العقارات', '', 'building-o', 1, 1, 135),
    (138, 2, 'home', 'اعدادات النظام', '', 'dashboard', 0, 0, -1),
    (139, 2, 'units', 'عرض جميع الوحدات', '', 'home', 2, 1, 135),
    (140, 2, 'units/payments', 'الايجارات و الأقساط', '', 'calendar', 3, 1, 135),
    (141, 2, 'units/transactions', 'المستحقات المالية', '', 'money', 4, 1, 135),
    (142, 2, 'units/support', 'ارسال استفسار او شكوى', '', 'life-ring', 6, 0, 135),
    (143, 1, 'support', 'الدعم الفني', '', 'life-ring', 11, 1, 91),
    (144, 2, 'units/rent', 'عقود الإيجار', '', 'clock-o', 4, 0, 135),
    (145, 2, 'units/sell', 'عقود البيع', '', 'calendar-check-o', 5, 0, 135),
    (146, 2, 'login', 'الحساب', '', '', 0, 0, -1),
    (147, 3, 'dashboard', 'لوحة التحكم', '', '', 1, 0, 0),
    (148, 3, 'login', 'الحساب', '', '', 0, 0, -1),
    (149, 3, 'home', 'اعدادات النظام', '', 'dashboard', 0, 0, -1),
    (150, 3, 'ccp/units', 'عرض جميع الوحدات', '', 'home', 2, 1, 147),
    (151, 3, 'ccp/payments', 'الايجارات و الأقساط', '', 'calendar', 3, 1, 147),
    (152, 3, 'ccp/support', 'ارسال استفسار او شكوى', '', 'life-ring', 6, 0, 147),
    (153, 3, 'ccp/rent', 'عقود الإيجار', '', 'clock-o', 4, 0, 147),
    (154, 3, 'ccp/sell', 'عقود البيع', '', 'calendar-check-o', 5, 0, 147),
    (155, 3, 'login', 'الحساب', '', '', 0, 0, -1);");

  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES 
    (636, 89, 0, 'reset', '', 'استعادة بيانات الدخول', '', 0, 0, 0, 2),
    (637, 90, 0, 'reset', '', 'استعادة بيانات الدخول', '', 0, 0, 0, 2),
    (638, 91, 0, 'locations/index', '', 'التحكم في العقارات', 'building-o', 0, 0, 1, 0),
    (639, 138, 0, 'resize', '', 'اعادة تحجيم', '', 0, 0, 0, 3),
    (640, 137, 0, 'index', '', 'عرض العقارات', 'building-o', 0, 0, 1, 0),
    (641, 135, 0, 'index', '', 'الرئيسية', 'dashboard', 0, 0, 0, 3),
    (642, 135, 0, 'myaccount', '', 'تعديل حسابي', 'edit', 0, 0, 0, 3),
    (643, 135, 0, 'lang', '', 'تغيير لغة النظام', 'edit', 0, 0, 0, 3),
    (644, 139, 0, 'index', '', 'قائمة الوحدات', 'home', 0, 0, 0, 0),
    (645, 140, 0, 'index', '', 'قائمة الدفعات', 'calendar', 0, 0, 0, 0),
    (646, 141, 0, 'index', '', 'عرض المستحقات', 'money', 0, 0, 0, 0),
    (647, 142, 0, 'send', '', 'نموذج الإرسال', 'paper-plane', 0, 0, 0, 0),
    (648, 143, 0, 'index', '', 'مشاهدة الطلبات', 'edit', 0, 0, 1, 0),
    (649, 143, 0, 'show', '', 'عرض الطلب', 'list-alt', 0, 0, 1, 1),
    (650, 143, 0, 'read', '', 'غلق الطلب', 'eye', 1, 1, 2, 1),
    (651, 143, 0, 'unread', '', 'فتح الطلب', 'eye-slash', 1, 1, 3, 1),
    (652, 143, 0, 'delete', '', 'حذف', 'trash-o', 1, 0, 4, 1),
    (653, 144, 0, 'index', '', 'مشاهدة العقود', 'edit', 0, 0, 0, 0),
    (655, 144, 0, 'printable', '', 'طباعة العقد', 'print', 0, 0, 1, 1),
    (656, 140, 0, 'printable', '', 'طباعة', 'print', 0, 0, 1, 1),
    (657, 89, 0, 'loginas', '', 'الدخول كمالك', 'sign-in', 0, 0, 3, 1),
    (658, 90, 0, 'loginas', '', 'الدخول كعميل', 'sign-in', 0, 0, 3, 1),
    (659, 135, 0, 'masklogout', '', 'تسجيل الخروج كعميل', '', 0, 0, 0, 3),
    (660, 146, 0, 'logout', '', 'تسجيل الخروج', 'sign-out', 0, 0, 0, 3),
    (661, 141, 0, 'printstatin', '', 'طباعة سند قبض', '', 0, 0, 0, 3),
    (662, 141, 0, 'printstatout', '', 'طباعة سند صرف', '', 0, 0, 0, 3),
    (663, 145, 0, 'index', '', 'مشاهدة العقود', 'edit', 0, 0, 0, 0),
    (664, 145, 0, 'printable', '', 'طباعة العقد', 'print', 0, 0, 1, 1),
    (665, 147, 0, 'index', '', 'الرئيسية', 'dashboard', 0, 0, 0, 3),
    (666, 147, 0, 'myaccount', '', 'تعديل حسابي', 'edit', 0, 0, 0, 3),
    (667, 147, 0, 'lang', '', 'تغيير لغة النظام', 'edit', 0, 0, 0, 3),
    (668, 147, 0, 'masklogout', '', 'تسجيل الخروج كعميل', '', 0, 0, 0, 3),
    (669, 149, 0, 'resize', '', 'اعادة تحجيم', '', 0, 0, 0, 3),
    (670, 150, 0, 'index', '', 'قائمة الوحدات', 'home', 0, 0, 0, 0),
    (671, 151, 0, 'index', '', 'قائمة الدفعات', 'calendar', 0, 0, 0, 0),
    (672, 151, 0, 'printable', '', 'طباعة', 'print', 0, 0, 1, 1),
    (673, 152, 0, 'send', '', 'نموذج الإرسال', 'paper-plane', 0, 0, 0, 0),
    (674, 153, 0, 'index', '', 'مشاهدة العقود', 'edit', 0, 0, 0, 0),
    (675, 153, 0, 'printable', '', 'طباعة العقد', 'print', 0, 0, 1, 1),
    (677, 154, 0, 'index', '', 'مشاهدة العقود', 'edit', 0, 0, 0, 0),
    (678, 154, 0, 'printable', '', 'طباعة العقد', 'print', 0, 0, 1, 1),
    (679, 155, 0, 'logout', '', 'تسجيل الخروج', 'sign-out', 0, 0, 0, 3);");

  $this->config['version'] = 4.84;
}
if($this->config['version'] <= 4.84){
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'سحابة الملفات' WHERE `id` = 7;");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `id` = 551");
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('total_file_size', '0');");
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('total_units', '0');");
  $this->db->query("DELETE FROM `cp_module` WHERE `id` = 122");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `id` = 552");
  $this->db->query("DELETE FROM `cp_module` WHERE `id` = 16");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `id` = 72");

  $this->db->query("UPDATE `cp_module` SET `position` = '-1' WHERE `id` = 124;");
  $this->db->query("UPDATE `cp_module` SET `position` = '-1' WHERE `id` = 125;");
  $this->db->query("UPDATE `cp_module` SET `position` = '-1' WHERE `id` = 112;");
  $this->db->query("UPDATE `cp_module` SET `position` = '-1' WHERE `id` = 113;");

  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مشاهدة السجلات' WHERE `id` = 510;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مشاهدة السجلات' WHERE `id` = 511;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'الإيجارات والمصاريف', name='list', refmoduleid='112', `icon` = 'calendar' WHERE `id` = 534;");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `id` = 535;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'الأقساط و المصاريف', name='list', refmoduleid='112', `icon` = 'calendar' WHERE `id` = 537;");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `id` = 538;");

  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مستحقات مالك العقار', `icon` = 'usd', `name` = 'index' WHERE `id` = 573;");
  $this->db->query("UPDATE `cp_module_action` SET `title` = 'مستحقات مالك العقار', `icon` = 'usd', `name` = 'index' WHERE `id` = 575;");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `id` = 574;");
  $this->db->query("DELETE FROM `cp_module_action` WHERE `id` = 576;");
  $this->db->query("UPDATE `cp_module_action` SET `icon` = 'cloud-upload' WHERE `id` = 7;");

  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES
  (156, 1, 'transactions', 'مستحقات مالك العقار', '', 'money', 1, 0, 111),
  (157, 1, 'pays_pending', 'الإيجارات و الأقساط', '', 'calendar', 2, 0, 111);");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (681, 157, 0, 'list', '', 'مشاهدة السجلات', 'calendar', 0, 0, 1, 0);");

  $this->db->query("UPDATE `cp_module` SET `title` = 'ايجارات و أقساط غير محصلة' WHERE `id` = 112;");
  $this->db->query("UPDATE `cp_module` SET `title` = 'ايجارات و أقساط مدفوعة ومرحلة' WHERE `id` = 113;");
  $this->db->query("UPDATE `cp_module` SET `title` = 'مستحقات ملاك غير مدفوعة' WHERE `id` = 124;");
  $this->db->query("UPDATE `cp_module` SET `title` = 'مستحقات ملاك مدفوعة ومرحلة' WHERE `id` = 125;");

  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('skip_guide_widget', '0');");
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('rest_api_key', '".$this->salt(32)."');");
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('support_ticket_prefix', '');");
  $this->db->query("ALTER TABLE `support` ADD `file` CHAR(50) NOT NULL AFTER `message`;");

  $this->db->query("CREATE TABLE IF NOT EXISTS `user_access_token` (
    `userid` int(10) UNSIGNED NOT NULL,
    `token` char(32) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`userid`),
    KEY `token` (`token`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES
(158, 1, 'building/reports', 'أرشيف التقارير', '', 'archive', -1, 0, 91),
(159, 2, 'units/reports', 'أرشيف التقارير', '', 'archive', -1, 0, 135);");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES
(682, 158, 0, 'index', '', 'عرض التقارير', 'edit', 0, 0, 1, 0),
(683, 158, 0, 'add', 'buildid', 'اضافة تقرير جديد', 'plus-circle', 0, 0, 2, 0),
(684, 158, 0, 'edit', '', 'تعديل البيانات', 'pencil', 0, 0, 1, 1),
(685, 158, 0, 'delete', '', 'حذف', 'trash-o', 1, 0, 3, 1),
(686, 158, 0, 'view', '', 'مشاهدة التقرير', 'eye', 0, 7, 2, 1),
(687, 159, 0, 'index', '', 'عرض التقارير', 'edit', 0, 0, 1, 0),
(688, 159, 0, 'view', '', 'مشاهدة التقرير', 'eye', 0, 7, 1, 1);");

  $this->db->query("CREATE TABLE IF NOT EXISTS `building_reports` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `buildid` int(10) UNSIGNED NOT NULL,
    `catid` smallint(5) UNSIGNED NOT NULL,
    `title` char(100) NOT NULL,
    `issued_date` date NOT NULL,
    `file` char(50) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `buildid` (`buildid`,`catid`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $this->db->query("CREATE TABLE IF NOT EXISTS `building_reports_cats` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` char(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  if($this->config['lang'] == 'ar'){
    $this->db->query("INSERT INTO `building_reports_cats` (`id`, `name`) VALUES (NULL, 'التقارير الإسبوعية'), (NULL, 'التقارير الشهرية'), (NULL, 'التقارير السنوية'), (NULL, 'تقارير الصيانة');");
  } else {
    $this->db->query("INSERT INTO `building_reports_cats` (`id`, `name`) VALUES (NULL, 'Weekly Reports'), (NULL, 'Monthly Reports'), (NULL, 'Yearly Reports'), (NULL, 'Maintenance Reports');");
  }

  $this->db->query("ALTER TABLE `building` CHANGE `owner` `owner` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;");
  $this->db->query("ALTER TABLE `rent_contracts` CHANGE `buyer` `buyer` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;");
  $this->db->query("ALTER TABLE `sell_contracts` CHANGE `buyer` `buyer` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;");

  $this->config['version'] = 4.85;
}
if($this->config['version'] <= 4.85){
  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES (160, '2', 'pns', 'مركز التنبيهات', '', '', '0', '0', '-1');");
  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES (161, '3', 'pns', 'مركز التنبيهات', '', '', '0', '0', '-1');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '160', '0', 'list', '', 'مشاهدة السجلات', 'inbox', '0', '0', '1', '0');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '161', '0', 'list', '', 'مشاهدة السجلات', 'inbox', '0', '0', '1', '0');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '160', '0', 'device_token', '', 'تسجيل توكن', 'inbox', '0', '0', '1', '0');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '161', '0', 'device_token', '', 'تسجيل توكن', 'inbox', '0', '0', '1', '0');");
  $this->db->query("CREATE TABLE IF NOT EXISTS `device_tokens` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userid` int(10) UNSIGNED NOT NULL,
  `token` char(255) NOT NULL,
  `token_md5` char(32) NOT NULL,
  `platform` enum('ios','android') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token_md5` (`token_md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $this->db->query("UPDATE `setting` SET `value` = 'AIzaSyBYGlAFOMFbuy6mytd4B0f1niiNWy-dzzE' WHERE `varname` = 'gmap_key';");

  $this->db->query("ALTER TABLE `owner` ADD `ec_passcode` SMALLINT UNSIGNED NOT NULL AFTER `workphone`;");
  $this->db->query("ALTER TABLE `buyer` ADD `ec_passcode` SMALLINT UNSIGNED NOT NULL AFTER `calendar`;");

  $this->config['version'] = 4.86;
}
if($this->config['version'] <= 4.86){
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (680, 156, 0, 'index', '', 'مشاهدة السجلات', 'money', 0, 0, 1, 0);");

  $this->config['version'] = 4.87;
}
if($this->config['version'] <= 4.87){
  $this->db->query("ALTER TABLE `building_locs` ADD `buildcat` INT UNSIGNED NOT NULL AFTER `location`, ADD `citytid` INT UNSIGNED NOT NULL AFTER `buildcat`, ADD `platno` CHAR(50) NOT NULL AFTER `buildcat`, ADD `planno` CHAR(50) NOT NULL AFTER `platno`, ADD `buildno` CHAR(15) NOT NULL AFTER `planno`, ADD `district` INT UNSIGNED NOT NULL AFTER `buildno`, ADD `floors` SMALLINT NOT NULL AFTER `district`;");
  $this->db->query("ALTER TABLE `building_locs` ADD `size` SMALLINT NOT NULL AFTER `floors`, ADD `zone` CHAR(100) NOT NULL AFTER `size`, ADD `street` CHAR(100) NOT NULL AFTER `zone`, ADD `gps_location` MEDIUMTEXT NOT NULL AFTER `street`, ADD `loc_details` MEDIUMTEXT NOT NULL AFTER `gps_location`;");

  $this->config['version'] = 4.88;
}
if($this->config['version'] <= 4.88){
  $this->db->query("ALTER TABLE `building_locs` CHANGE `floor` `floors` SMALLINT(6) NOT NULL;");
  $this->db->query("UPDATE `cp_module` SET `position` = '5' WHERE `cp_module`.`id` = 101;");
  $this->db->query("ALTER TABLE `user` ADD INDEX(`mobile`);");

  $this->config['version'] = 4.89;
}
if($this->config['version'] <= 4.89){
  $this->config['version'] = 4.9;
}
if($this->config['version'] <= 4.9){
  $this->config['version'] = 4.91;
}
if($this->config['version'] <= 4.91){
  $this->config['version'] = 4.92;
}
if($this->config['version'] <= 4.92){
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, 123, 0, 'report', '', 'طباعة تقرير شامل', 'calendar', 0, 0, 3, 1);");
  $this->config['version'] = 4.93;
}
if($this->config['version'] <= 4.93){
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, 123, 0, 'reportdaily', '', 'طباعة تقرير شهري', 'calendar', 0, 0, 4, 1);");
  $this->config['version'] = 4.94;
}
if($this->config['version'] <= 4.94){
  $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES (NULL, '1', 'reports/rent', 'الإيجارات والمصاريف', '', 'calendar-check-o', '7', '0', '114');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '162', '0', 'index', '', 'اعداد تقرير بالمصاريف والإيجارات', 'calendar-check-o', '0', '0', '1', '0');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '91', '0', 'report', '', 'طباعة تقرير شامل', '', '1', '0', '7', '2');");
  $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES (NULL, '91', '0', 'reportdaily', '', 'طباعة تقرير شهري', '', '1', '0', '8', '2');");
  $this->config['version'] = 4.95;
}
if($this->config['version'] <= 4.95){
    $this->db->query("CREATE TABLE IF NOT EXISTS `custom_fields` (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `catid` int(10) UNSIGNED NOT NULL,
      `type` enum('checkbox','text') NOT NULL,
      `title` char(100) NOT NULL,
      `description` char(150) NOT NULL,
      `suffix` char(100) NOT NULL,
      `icon` char(20) NOT NULL,
      `required` tinyint(1) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $this->db->query("CREATE TABLE IF NOT EXISTS `custom_fields_cats` (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `build_catid` int(10) UNSIGNED NOT NULL,
      `title` char(100) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $this->db->query("CREATE TABLE IF NOT EXISTS `custom_fields_data` (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `build_id` int(10) UNSIGNED NOT NULL,
      `field_id` int(10) UNSIGNED NOT NULL,
      `data` varchar(1000) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $this->db->query("INSERT INTO `cp_module` (`id`, `groupid`, `name`, `title`, `info`, `icon`, `position`, `shortcut`, `hidden`) VALUES 
    (163, 1, 'custom_fields', 'الحقول الإضافية', '', 'sitemap', 0, 0, 1),
    (164, 1, 'custom_fields/categories', 'الحقول الإضافية', '', 'sitemap', 3, 0, 1);");

    $this->db->query("INSERT INTO `cp_module_action` (`id`, `modulid`, `refmoduleid`, `name`, `param`, `title`, `icon`, `actiongroup`, `ajax`, `position`, `hidden`) VALUES 
    (NULL, 164, 0, 'index', '', 'عرض الأقسام', 'edit', 0, 0, 1, 0),
    (NULL, 164, 0, 'add', '', 'اضافة قسم جديد', 'plus-circle', 0, 0, 2, 0),
    (NULL, 164, 0, 'edit', '', 'تعديل', 'pencil', 0, 0, 1, 1),
    (NULL, 164, 0, 'delete', '', 'حذف', 'trash-o', 1, 0, 2, 1),
    (NULL, 163, 0, 'index', '', 'عرض الحقول الإضافية', 'edit', 0, 0, 1, 0),
    (NULL, 163, 0, 'add', '', 'اضافة حقل جديد', 'plus-circle', 0, 0, 2, 0),
    (NULL, 163, 0, 'edit', '', 'تعديل', 'pencil', 0, 0, 1, 1),
    (NULL, 163, 0, 'delete', '', 'حذف', 'trash-o', 1, 0, 2, 1),
    (NULL, 91, 0, 'custom_fields', '', '', '', 0, 0, 0, 3),
    (NULL, 43, 0, 'qrcode', '', 'طباعة اكواد الوصول السريع', 'qrcode', 0, 0, 0, 3);");

    $this->db->query("INSERT INTO `custom_fields` (`id`, `catid`, `type`, `title`, `description`, `suffix`, `icon`, `required`) VALUES
    (1, 1, 'text', 'غرفة المعيشة', '', 'م2', 'chair', 0),
    (2, 1, 'text', 'غرفة النوم', '', 'م2', 'bed', 0),
    (3, 1, 'text', 'الحمام', '', 'م2', 'bath', 0),
    (4, 1, 'text', 'الجراج', '', 'م2', 'parking', 0),
    (5, 2, 'checkbox', 'مكيف هواء', '', '', 'snowflake', 0),
    (6, 2, 'checkbox', 'حمام سباحة', '', '', 'swimming-pool', 0),
    (7, 2, 'checkbox', 'Wifi', '', '', 'wifi-3', 0),
    (8, 2, 'checkbox', 'حراسة 24 ساعة', '', '', 'user-secret', 0),
    (9, 2, 'checkbox', 'صالة ألعاب', '', '', 'dumbbell', 0);");

    $this->db->query("INSERT INTO `custom_fields_cats` (`id`, `build_catid`, `title`) VALUES
    (1, 7, 'مساحة و عدد الغرف'),
    (2, 7, 'وسائل الراحة');");

    $this->config['version'] = 5.0;
}
if($this->config['version'] <= 5){
  $this->config['version'] = 5.1;
}
if($this->config['version'] <= 5.1){
  $this->config['version'] = 5.2;
}
if($this->config['version'] <= 5.2){
  $this->config['version'] = 5.3;
}

if($this->config['version'] <= 5.3){
  $this->config['version'] = 5.4;
}

if($this->config['version'] <= 5.4){
  $this->db->query("INSERT INTO `setting` (`varname`, `value`) VALUES ('vat', '15');");
  $this->db->query("ALTER TABLE `payments` ADD `reason` MEDIUMTEXT NULL DEFAULT NULL AFTER `comm_type`;");
  $this->config['version'] = 5.5;
}

if($this->config['version'] <= 5.5){
  $this->config['version'] = 5.6;
}

$this->config['version'] = $this->version;
$this->save_setting('version', $this->version);
$this->save_setting('active', 1);

$superadmin = $this->db->get_var('SELECT id FROM user WHERE super=1');
$this->db->query('DELETE FROM cp_permission WHERE userid='.$superadmin);
$actions = $this->db->get_results('SELECT id,modulid FROM cp_module_action WHERE hidden<3');
foreach($actions as $action){
  $permission = array();
  $permission['userid'] = $superadmin;
  $permission['modulid'] = $action['modulid'];
  $permission['actionid'] = $action['id'];
  $permission['permission'] = 1;
  $this->db->insert('cp_permission', $permission);
}

//Check system updates
$updatestring = $this->curl($this->updateServer, 'post', array('purchase_code' => $this->config['purchase_code']));
$update = json_decode($updatestring, true);
if(!empty($update)){
  $this->db->update('setting', array('value' => $updatestring), array('varname' => 'updatecore'));
}
//Check system updates

global $db_config;
$tables = $this->db->get_results("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".$db_config['dbname']."' AND ENGINE = 'MyISAM'");
if($tables) {
  foreach($tables as $table) {
    $this->db->query("ALTER TABLE `".$table['TABLE_NAME']."` ENGINE=INNODB");
  }
}

$this->delDir(CACHE_DIR, true);
