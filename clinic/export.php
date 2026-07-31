<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/xlsx.php';
require_login();
require_perm('export.data');

$type = $_GET['type'] ?? '';

$validDate = fn(string $d): bool => (bool)(preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d));
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to   = (string)($_GET['to'] ?? date('Y-m-d'));
if (!$validDate($from)) $from = date('Y-m-01');
if (!$validDate($to))   $to = date('Y-m-d');

$period = 'الفترة من ' . fmt_date($from) . ' إلى ' . fmt_date($to);
$clinic = setting('clinic_name', 'العيادة');
$cur = setting('currency', 'ج.م');

switch ($type) {

    /* ------------------------------------------------------------ المرضى */
    case 'patients': {
        $q = trim((string)($_GET['q'] ?? ''));
        $where = '';
        $args = [];
        if ($q !== '') {
            $where = 'WHERE p.name LIKE ? OR p.phone LIKE ? OR p.code LIKE ?';
            $args = ["%$q%", "%$q%", "%$q%"];
        }
        $st = $pdo->prepare(
            "SELECT p.*,
                (SELECT weight FROM measurements m WHERE m.patient_id = p.id ORDER BY mdate DESC, id DESC LIMIT 1) last_weight,
                (SELECT mdate  FROM measurements m WHERE m.patient_id = p.id ORDER BY mdate DESC, id DESC LIMIT 1) last_date,
                (SELECT weight FROM measurements m WHERE m.patient_id = p.id ORDER BY mdate ASC,  id ASC  LIMIT 1) first_weight
             FROM patients p $where ORDER BY p.id"
        );
        $st->execute($args);
        $rows = $st->fetchAll();

        $x = new XlsxWriter('المرضى');
        $x->setTitle($clinic . ' — سجل المرضى', 'عدد المرضى: ' . count($rows) . ' — تاريخ التصدير: ' . fmt_date(date('Y-m-d')));
        $x->setColumns([
            ['الكود', XlsxWriter::TEXT, 12],
            ['الاسم', XlsxWriter::TEXT, 26],
            ['الهاتف', XlsxWriter::TEXT, 16],
            ['النوع', XlsxWriter::TEXT, 10],
            ['العمر', XlsxWriter::NUM, 9],
            ['الطول (سم)', XlsxWriter::NUM, 12],
            ['أول وزن', XlsxWriter::NUM, 11],
            ['آخر وزن', XlsxWriter::NUM, 11],
            ['التغير (كجم)', XlsxWriter::NUM, 13],
            ['BMI الحالي', XlsxWriter::NUM, 12],
            ['الهدف', XlsxWriter::TEXT, 28],
            ['حالة طبية', XlsxWriter::TEXT, 24],
            ['حساسية', XlsxWriter::TEXT, 20],
            ['آخر قياس', XlsxWriter::DATE, 13],
            ['تاريخ التسجيل', XlsxWriter::DATE, 14],
        ]);
        foreach ($rows as $r) {
            $diff = ($r['last_weight'] !== null && $r['first_weight'] !== null)
                ? round((float)$r['last_weight'] - (float)$r['first_weight'], 1) : null;
            $x->addRow([
                $r['code'], $r['name'], $r['phone'],
                $r['gender'] === 'male' ? 'ذكر' : 'أنثى',
                calc_age($r['birth_date']),
                $r['height_cm'], $r['first_weight'], $r['last_weight'], $diff,
                $r['last_weight'] !== null ? calc_bmi($r['last_weight'], $r['height_cm']) : null,
                $r['goal'], $r['medical_conditions'], $r['allergies'],
                $r['last_date'], substr((string)$r['created_at'], 0, 10),
            ]);
        }
        $x->download('المرضى-' . date('Y-m-d'));
    }

    /* --------------------------------------------------- قياسات مريض واحد */
    case 'measurements': {
        $pid = (int)($_GET['patient'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) {
            flash('المريض غير موجود.', 'danger');
            redirect('patients.php');
        }
        $st = $pdo->prepare('SELECT * FROM measurements WHERE patient_id = ? ORDER BY mdate, id');
        $st->execute([$pid]);
        $rows = $st->fetchAll();

        $x = new XlsxWriter('القياسات');
        $x->setTitle($clinic . ' — قياسات: ' . $p['name'], 'الكود: ' . $p['code'] . ($p['height_cm'] ? ' — الطول: ' . $p['height_cm'] . ' سم' : '') . ' — عدد القياسات: ' . count($rows));
        $x->setColumns([
            ['التاريخ', XlsxWriter::DATE, 13],
            ['الوزن (كجم)', XlsxWriter::NUM, 12],
            ['BMI', XlsxWriter::NUM, 10],
            ['التغير عن السابق', XlsxWriter::NUM, 15],
            ['الدهون %', XlsxWriter::NUM, 11],
            ['العضلات (كجم)', XlsxWriter::NUM, 13],
            ['الماء %', XlsxWriter::NUM, 10],
            ['الوسط (سم)', XlsxWriter::NUM, 12],
            ['الأرداف (سم)', XlsxWriter::NUM, 12],
            ['الذراع (سم)', XlsxWriter::NUM, 12],
            ['الفخذ (سم)', XlsxWriter::NUM, 12],
            ['ملاحظات', XlsxWriter::TEXT, 30],
        ]);
        $prev = null;
        foreach ($rows as $m) {
            $delta = $prev !== null ? round((float)$m['weight'] - $prev, 1) : null;
            $prev = (float)$m['weight'];
            $x->addRow([
                $m['mdate'], $m['weight'], calc_bmi($m['weight'], $p['height_cm']), $delta,
                $m['body_fat'], $m['muscle'], $m['water'],
                $m['waist'], $m['hips'], $m['arm'], $m['thigh'], $m['notes'],
            ]);
        }
        if (count($rows) > 1) {
            $total = round((float)end($rows)['weight'] - (float)$rows[0]['weight'], 1);
            $x->addTotalRow(['الإجمالي', null, null, $total]);
        }
        $x->download('قياسات-' . $p['name']);
    }

    /* ---------------------------------------------------------- المواعيد */
    case 'appointments': {
        $st = $pdo->prepare(
            'SELECT a.*, p.name AS pname, p.phone, p.code FROM appointments a
             JOIN patients p ON p.id = a.patient_id
             WHERE a.adate BETWEEN ? AND ? ORDER BY a.adate, a.atime'
        );
        $st->execute([$from, $to]);
        $rows = $st->fetchAll();

        $x = new XlsxWriter('المواعيد');
        $x->setTitle($clinic . ' — سجل المواعيد', $period . ' — العدد: ' . count($rows));
        $x->setColumns([
            ['التاريخ', XlsxWriter::DATE, 13],
            ['اليوم', XlsxWriter::TEXT, 11],
            ['الوقت', XlsxWriter::TEXT, 11],
            ['الكود', XlsxWriter::TEXT, 12],
            ['المريض', XlsxWriter::TEXT, 26],
            ['الهاتف', XlsxWriter::TEXT, 16],
            ['النوع', XlsxWriter::TEXT, 13],
            ['الحالة', XlsxWriter::TEXT, 12],
            ['تذكير واتساب', XlsxWriter::TEXT, 14],
            ['ملاحظات', XlsxWriter::TEXT, 28],
        ]);
        foreach ($rows as $a) {
            $x->addRow([
                $a['adate'], day_ar($a['adate']), fmt_time($a['atime']),
                $a['code'], $a['pname'], $a['phone'],
                APPT_TYPES[$a['type']] ?? $a['type'],
                APPT_STATUS[$a['status']] ?? $a['status'],
                $a['reminder_sent'] ? 'أُرسل' : 'لم يُرسل',
                $a['notes'],
            ]);
        }
        $x->download('المواعيد-' . $from . '-' . $to);
    }

    /* -------------------------------------------------------- المدفوعات */
    case 'payments': {
        require_perm('pay.view');
        $st = $pdo->prepare(
            'SELECT pay.*, p.name AS pname, p.code, u.name AS uname FROM payments pay
             LEFT JOIN patients p ON p.id = pay.patient_id
             LEFT JOIN users u ON u.id = pay.created_by
             WHERE pay.pdate BETWEEN ? AND ? ORDER BY pay.pdate, pay.id'
        );
        $st->execute([$from, $to]);
        $rows = $st->fetchAll();
        $sum = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));

        $x = new XlsxWriter('المدفوعات');
        $x->setTitle($clinic . ' — سجل المدفوعات', $period . ' — الإجمالي: ' . number_format($sum, 2) . ' ' . $cur);
        $x->setColumns([
            ['التاريخ', XlsxWriter::DATE, 13],
            ['اليوم', XlsxWriter::TEXT, 11],
            ['الكود', XlsxWriter::TEXT, 12],
            ['المريض', XlsxWriter::TEXT, 26],
            ['المبلغ (' . $cur . ')', XlsxWriter::MONEY, 15],
            ['طريقة الدفع', XlsxWriter::TEXT, 15],
            ['الخدمة', XlsxWriter::TEXT, 20],
            ['سجّلها', XlsxWriter::TEXT, 18],
            ['ملاحظات', XlsxWriter::TEXT, 26],
        ]);
        foreach ($rows as $r) {
            $x->addRow([
                $r['pdate'], day_ar($r['pdate']), $r['code'], $r['pname'] ?? '—',
                $r['amount'], PAY_METHODS[$r['method']] ?? $r['method'],
                $r['service'], $r['uname'] ?? '—', $r['notes'],
            ]);
        }
        $x->addTotalRow(['الإجمالي', null, null, count($rows) . ' دفعة', $sum]);
        $x->download('المدفوعات-' . $from . '-' . $to);
    }

    /* -------------------------------------------------------- المصروفات */
    case 'expenses': {
        require_perm('exp.view');
        $st = $pdo->prepare(
            'SELECT e.*, u.name AS uname FROM expenses e LEFT JOIN users u ON u.id = e.created_by
             WHERE e.edate BETWEEN ? AND ? ORDER BY e.edate, e.id'
        );
        $st->execute([$from, $to]);
        $rows = $st->fetchAll();
        $sum = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));

        $x = new XlsxWriter('المصروفات');
        $x->setTitle($clinic . ' — سجل المصروفات', $period . ' — الإجمالي: ' . number_format($sum, 2) . ' ' . $cur);
        $x->setColumns([
            ['التاريخ', XlsxWriter::DATE, 13],
            ['اليوم', XlsxWriter::TEXT, 11],
            ['البند', XlsxWriter::TEXT, 18],
            ['المبلغ (' . $cur . ')', XlsxWriter::MONEY, 15],
            ['سجّلها', XlsxWriter::TEXT, 18],
            ['ملاحظات', XlsxWriter::TEXT, 30],
        ]);
        foreach ($rows as $r) {
            $x->addRow([
                $r['edate'], day_ar($r['edate']),
                EXPENSE_CATS[$r['category']] ?? $r['category'],
                $r['amount'], $r['uname'] ?? '—', $r['notes'],
            ]);
        }
        $x->addTotalRow(['الإجمالي', null, count($rows) . ' بند', $sum]);
        $x->download('المصروفات-' . $from . '-' . $to);
    }

    /* ------------------------------------------------------ جرعات الحقن */
    case 'injections': {
        $st = $pdo->prepare(
            'SELECT i.*, p.name AS pname, p.code, d.name AS drug_name, u.name AS uname
             FROM injection_doses i
             JOIN patients p ON p.id = i.patient_id
             JOIN drugs d ON d.id = i.drug_id
             LEFT JOIN users u ON u.id = i.given_by
             WHERE i.dose_date BETWEEN ? AND ? ORDER BY i.dose_date, i.id'
        );
        $st->execute([$from, $to]);
        $rows = $st->fetchAll();

        $sumUnits = array_sum(array_map(fn($r) => (float)$r['units'], $rows));
        $sumAmount = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
        $sumPaid = array_sum(array_map(fn($r) => (float)$r['paid'], $rows));
        $isAdmin = can('profit.view');

        $x = new XlsxWriter('جرعات الحقن');
        $x->setTitle($clinic . ' — سجل جرعات الحقن',
            $period . ' — ' . num_fmt($sumUnits) . ' وحدة بإجمالي ' . number_format($sumAmount, 2) . ' ' . $cur);
        $cols = [
            ['التاريخ', XlsxWriter::DATE, 13],
            ['الكود', XlsxWriter::TEXT, 12],
            ['المريض', XlsxWriter::TEXT, 24],
            ['الدواء', XlsxWriter::TEXT, 30],
            ['الوحدات', XlsxWriter::NUM, 11],
            ['سعر الوحدة', XlsxWriter::MONEY, 13],
            ['المستحق', XlsxWriter::MONEY, 13],
            ['المدفوع', XlsxWriter::MONEY, 13],
            ['المتبقي', XlsxWriter::MONEY, 13],
            ['مكان الحقن', XlsxWriter::TEXT, 12],
            ['أعطاها', XlsxWriter::TEXT, 18],
            ['ملاحظات', XlsxWriter::TEXT, 24],
        ];
        if ($isAdmin) {
            array_splice($cols, 9, 0, [['ربح الجرعة', XlsxWriter::MONEY, 13]]);
        }
        $x->setColumns($cols);

        $sumProfit = 0.0;
        foreach ($rows as $r) {
            $profit = (float)$r['units'] * ((float)$r['unit_price'] - (float)$r['unit_cost']);
            $sumProfit += $profit;
            $cells = [
                $r['dose_date'], $r['code'], $r['pname'], $r['drug_name'],
                $r['units'], $r['unit_price'], $r['amount'], $r['paid'],
                (float)$r['amount'] - (float)$r['paid'],
            ];
            if ($isAdmin) {
                $cells[] = $profit;
            }
            $cells[] = INJ_SITES[$r['site']] ?? $r['site'];
            $cells[] = $r['uname'] ?? '—';
            $cells[] = $r['notes'];
            $x->addRow($cells);
        }
        $totals = ['الإجمالي', null, count($rows) . ' جرعة', null,
                   $sumUnits, null, $sumAmount, $sumPaid, $sumAmount - $sumPaid];
        if ($isAdmin) {
            $totals[] = $sumProfit;
        }
        $x->addTotalRow($totals);
        $x->download('جرعات-الحقن-' . $from . '-' . $to);
    }

    /* ------------------------------------------- كشف حساب حقن مريض واحد */
    case 'patient_injections': {
        $pid = (int)($_GET['patient'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) {
            flash('المريض غير موجود.', 'danger');
            redirect('patients.php');
        }
        $st = $pdo->prepare(
            'SELECT i.*, d.name AS drug_name FROM injection_doses i
             JOIN drugs d ON d.id = i.drug_id WHERE i.patient_id = ? ORDER BY i.dose_date, i.id'
        );
        $st->execute([$pid]);
        $rows = $st->fetchAll();

        $totUnits = array_sum(array_map(fn($r) => (float)$r['units'], $rows));
        $totAmount = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
        $totPaid = array_sum(array_map(fn($r) => (float)$r['paid'], $rows));
        $plan = active_plan($pdo, $pid);

        $x = new XlsxWriter('كشف حساب الحقن');
        $x->setTitle($clinic . ' — كشف حساب الحقن: ' . $p['name'],
            'الكود: ' . $p['code'] . ($plan
                ? ' — ' . $plan['drug_name'] . ' — ' . num_fmt($plan['weekly_units']) . ' وحدة أسبوعيًا بسعر '
                  . number_format((float)$plan['unit_price'], 2) . ' ' . $cur . ' للوحدة'
                : ''));
        $x->setColumns([
            ['التاريخ', XlsxWriter::DATE, 13],
            ['الدواء', XlsxWriter::TEXT, 30],
            ['الوحدات', XlsxWriter::NUM, 11],
            ['سعر الوحدة', XlsxWriter::MONEY, 13],
            ['المستحق', XlsxWriter::MONEY, 13],
            ['المدفوع', XlsxWriter::MONEY, 13],
            ['الرصيد التراكمي', XlsxWriter::MONEY, 16],
            ['ملاحظات', XlsxWriter::TEXT, 26],
        ]);
        $running = 0.0;
        foreach ($rows as $r) {
            $running += (float)$r['amount'] - (float)$r['paid'];
            $x->addRow([
                $r['dose_date'], $r['drug_name'], $r['units'], $r['unit_price'],
                $r['amount'], $r['paid'], $running, $r['notes'],
            ]);
        }
        $x->addTotalRow(['الإجمالي', count($rows) . ' جرعة', $totUnits, null,
                         $totAmount, $totPaid, $totAmount - $totPaid]);
        $x->download('كشف-حقن-' . $p['name']);
    }

    /* ------------------------------------------------------ باقات الجلسات */
    case 'packages': {
        [$df, $dfArgs] = doctor_filter('p');
        $st = $pdo->prepare(
            "SELECT pp.*, p.name AS pname, p.code, u.name AS doctor_name,
                (SELECT COUNT(*) FROM package_uses x WHERE x.patient_package_id = pp.id) AS used
             FROM patient_packages pp
             JOIN patients p ON p.id = pp.patient_id
             LEFT JOIN users u ON u.id = p.doctor_id
             WHERE 1=1 $df ORDER BY pp.id DESC"
        );
        $st->execute($dfArgs);
        $rows = $st->fetchAll();

        $totPrice = array_sum(array_map(fn($r) => (float)$r['price'], $rows));
        $totPaid = array_sum(array_map(fn($r) => (float)$r['paid'], $rows));

        $x = new XlsxWriter('باقات الجلسات');
        $x->setTitle($clinic . ' — باقات الجلسات', 'عدد الباقات: ' . count($rows)
            . ' — الإجمالي: ' . number_format($totPrice, 2) . ' ' . $cur);
        $x->setColumns([
            ['الكود', XlsxWriter::TEXT, 12],
            ['المريض', XlsxWriter::TEXT, 24],
            ['الطبيب', XlsxWriter::TEXT, 20],
            ['الباقة', XlsxWriter::TEXT, 26],
            ['الجلسات', XlsxWriter::NUM, 11],
            ['المستهلكة', XlsxWriter::NUM, 11],
            ['المتبقية', XlsxWriter::NUM, 11],
            ['من', XlsxWriter::DATE, 13],
            ['تنتهي', XlsxWriter::DATE, 13],
            ['السعر', XlsxWriter::MONEY, 13],
            ['المدفوع', XlsxWriter::MONEY, 13],
            ['المتبقي', XlsxWriter::MONEY, 13],
            ['الحالة', XlsxWriter::TEXT, 12],
        ]);
        foreach ($rows as $r) {
            $x->addRow([
                $r['code'], $r['pname'], $r['doctor_name'] ?? '—', $r['name'],
                (int)$r['sessions_total'], (int)$r['used'],
                (int)$r['sessions_total'] - (int)$r['used'],
                $r['start_date'], $r['expiry_date'],
                $r['price'], $r['paid'], (float)$r['price'] - (float)$r['paid'],
                PKG_STATUS[$r['status']] ?? $r['status'],
            ]);
        }
        $x->addTotalRow(['الإجمالي', count($rows) . ' باقة', null, null, null, null, null, null, null,
                         $totPrice, $totPaid, $totPrice - $totPaid]);
        $x->download('باقات-الجلسات-' . date('Y-m-d'));
    }

    /* -------------------------------------- المرضى المتوقفون عن المتابعة */
    case 'inactive': {
        require_perm('inactive.view');
        $days = max(7, min(365, (int)($_GET['days'] ?? setting('inactive_days', '45'))));
        $cutoff = date('Y-m-d', strtotime("-$days days"));
        [$df, $dfArgs] = doctor_filter('p');

        $st = $pdo->prepare("
            SELECT p.id, p.code, p.name, p.phone, p.goal, p.created_at, u.name AS doctor_name,
                (SELECT MAX(a.adate) FROM appointments a WHERE a.patient_id = p.id AND a.status='done') last_visit,
                (SELECT MAX(m.mdate) FROM measurements m WHERE m.patient_id = p.id) last_measure,
                (SELECT MAX(i.dose_date) FROM injection_doses i WHERE i.patient_id = p.id) last_dose,
                (SELECT MAX(pay.pdate) FROM payments pay WHERE pay.patient_id = p.id) last_pay,
                (SELECT m2.weight FROM measurements m2 WHERE m2.patient_id = p.id ORDER BY m2.mdate DESC, m2.id DESC LIMIT 1) last_weight,
                (SELECT COUNT(*) FROM appointments a2 WHERE a2.patient_id = p.id AND a2.status='scheduled' AND a2.adate >= CURDATE()) upcoming
            FROM patients p LEFT JOIN users u ON u.id = p.doctor_id WHERE 1=1 $df");
        $st->execute($dfArgs);

        $rows = [];
        foreach ($st->fetchAll() as $r) {
            if ((int)$r['upcoming'] > 0) {
                continue;
            }
            $dates = array_filter([$r['last_visit'], $r['last_measure'], $r['last_dose'],
                                   $r['last_pay'], substr((string)$r['created_at'], 0, 10)]);
            $last = $dates ? max($dates) : null;
            if ($last === null || $last > $cutoff) {
                continue;
            }
            $rows[] = $r + ['last_activity' => $last,
                'days_since' => (int)floor((strtotime(date('Y-m-d')) - strtotime($last)) / 86400)];
        }
        usort($rows, fn($a, $b) => $b['days_since'] <=> $a['days_since']);

        $x = new XlsxWriter('متوقفون عن المتابعة');
        $x->setTitle($clinic . ' — المرضى المتوقفون عن المتابعة',
            'لم يزوروا العيادة منذ ' . $days . ' يومًا — العدد: ' . count($rows));
        $x->setColumns([
            ['الكود', XlsxWriter::TEXT, 12],
            ['المريض', XlsxWriter::TEXT, 26],
            ['الطبيب', XlsxWriter::TEXT, 20],
            ['الهاتف', XlsxWriter::TEXT, 16],
            ['آخر نشاط', XlsxWriter::DATE, 13],
            ['عدد الأيام', XlsxWriter::NUM, 12],
            ['آخر وزن', XlsxWriter::NUM, 11],
            ['الهدف', XlsxWriter::TEXT, 30],
        ]);
        foreach ($rows as $r) {
            $x->addRow([$r['code'], $r['name'], $r['doctor_name'] ?? '—', $r['phone'],
                        $r['last_activity'], $r['days_since'], $r['last_weight'], $r['goal']]);
        }
        $x->addTotalRow(['الإجمالي', count($rows) . ' مريض']);
        $x->download('متوقفون-عن-المتابعة-' . date('Y-m-d'));
    }

    /* ---------------------------------------------------- التقرير الشهري */
    case 'report': {
        require_perm('report.view');
        $month = (string)($_GET['m'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month) || !strtotime($month . '-01')) {
            $month = date('Y-m');
        }
        $mFrom = $month . '-01';
        $mTo = date('Y-m-t', strtotime($mFrom));

        $one = function (string $sql, array $args) use ($pdo) {
            $st = $pdo->prepare($sql);
            $st->execute($args);
            return $st->fetchColumn();
        };
        $income = (float)$one('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pdate BETWEEN ? AND ?', [$mFrom, $mTo]);
        $expense = (float)$one('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE edate BETWEEN ? AND ?', [$mFrom, $mTo]);
        $newPatients = (int)$one('SELECT COUNT(*) FROM patients WHERE created_at BETWEEN ? AND ?', [$mFrom, $mTo . ' 23:59:59']);

        $st = $pdo->prepare('SELECT status, COUNT(*) c FROM appointments WHERE adate BETWEEN ? AND ? GROUP BY status');
        $st->execute([$mFrom, $mTo]);
        $apptStats = $st->fetchAll(PDO::FETCH_KEY_PAIR);

        $st = $pdo->prepare("SELECT COALESCE(NULLIF(service,''), 'غير محدد') s, SUM(amount) total, COUNT(*) c
                             FROM payments WHERE pdate BETWEEN ? AND ? GROUP BY s ORDER BY total DESC");
        $st->execute([$mFrom, $mTo]);
        $byService = $st->fetchAll();

        $st = $pdo->prepare('SELECT category, SUM(amount) total FROM expenses WHERE edate BETWEEN ? AND ? GROUP BY category ORDER BY total DESC');
        $st->execute([$mFrom, $mTo]);
        $byCat = $st->fetchAll();

        $x = new XlsxWriter('التقرير الشهري');
        $x->setTitle($clinic . ' — التقرير الشهري', 'شهر ' . month_ar($month) . ' (' . fmt_date($mFrom) . ' — ' . fmt_date($mTo) . ')');
        $x->setColumns([
            ['البيان', XlsxWriter::TEXT, 34],
            ['العدد', XlsxWriter::NUM, 12],
            ['القيمة (' . $cur . ')', XlsxWriter::MONEY, 18],
        ]);

        $x->addTotalRow(['الملخص المالي']);
        $x->addRow(['إجمالي الإيرادات', null, $income]);
        $x->addRow(['إجمالي المصروفات', null, $expense]);
        $x->addTotalRow(['صافي الربح', null, $income - $expense]);

        $x->addRow([null]);
        $x->addTotalRow(['الإيرادات حسب الخدمة']);
        foreach ($byService as $r) {
            $x->addRow([$r['s'], (int)$r['c'], $r['total']]);
        }

        $x->addRow([null]);
        $x->addTotalRow(['المصروفات حسب البند']);
        foreach ($byCat as $r) {
            $x->addRow([EXPENSE_CATS[$r['category']] ?? $r['category'], null, $r['total']]);
        }

        $x->addRow([null]);
        $x->addTotalRow(['المواعيد']);
        foreach (APPT_STATUS as $k => $label) {
            $x->addRow([$label, (int)($apptStats[$k] ?? 0), null]);
        }

        $st = $pdo->prepare(
            'SELECT d.name, SUM(i.units) units, SUM(i.amount) amount, SUM(i.paid) paid,
                    SUM(i.units * (i.unit_price - i.unit_cost)) profit, COUNT(*) c
             FROM injection_doses i JOIN drugs d ON d.id = i.drug_id
             WHERE i.dose_date BETWEEN ? AND ? GROUP BY d.id, d.name ORDER BY amount DESC'
        );
        $st->execute([$mFrom, $mTo]);
        $byDrug = $st->fetchAll();

        if ($byDrug) {
            $x->addRow([null]);
            $x->addTotalRow(['الحقن حسب الدواء (وحدات / قيمة)']);
            foreach ($byDrug as $r) {
                $x->addRow([$r['name'] . ' — ' . num_fmt($r['units']) . ' وحدة في ' . (int)$r['c'] . ' جرعة',
                            $r['units'], $r['amount']]);
            }
            $totalInj = array_sum(array_map(fn($r) => (float)$r['amount'], $byDrug));
            $paidInj = array_sum(array_map(fn($r) => (float)$r['paid'], $byDrug));
            $x->addTotalRow(['إجمالي الحقن', null, $totalInj]);
            $x->addRow(['منها محصَّل', null, $paidInj]);
            $x->addRow(['متأخرات الحقن', null, $totalInj - $paidInj]);
            $x->addRow(['ربح الحقن', null, array_sum(array_map(fn($r) => (float)$r['profit'], $byDrug))]);
        }

        $x->addRow([null]);
        $x->addTotalRow(['المرضى']);
        $x->addRow(['مرضى جدد هذا الشهر', $newPatients, null]);
        $x->addRow(['إجمالي المرضى', (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn(), null]);

        $x->download('تقرير-' . $month);
    }
}

flash('نوع التصدير غير معروف.', 'danger');
redirect('index.php');
