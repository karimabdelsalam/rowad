<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('backup.run');

/*
 * نسخة احتياطية كاملة من قاعدة البيانات بصيغة SQL.
 *
 * تُولَّد بالـ PHP مباشرة (بدون mysqldump) لأن كثيرًا من استضافات cPanel
 * المشتركة تمنع تشغيل أوامر النظام. الملف يُبَث للمتصفح صفًا صفًا حتى لا
 * يستهلك ذاكرة كبيرة مهما كبرت قاعدة البيانات.
 */

if (isset($_GET['download'])) {
    csrf_verify_get();

    $stamp = date('Y-m-d_His');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="clinic-backup-' . $stamp . '.sql"');
    header('Cache-Control: no-store');

    echo "-- نسخة احتياطية من نظام Pclinic\n";
    echo '-- العيادة: ' . setting('clinic_name', '') . "\n";
    echo '-- التاريخ: ' . date('Y-m-d H:i:s') . "\n";
    echo '-- قاعدة البيانات: ' . current_db_name() . "\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        echo "\n-- ----------------------------- جدول `$table`\n";
        echo "DROP TABLE IF EXISTS `$table`;\n$create;\n\n";

        $st = $pdo->query('SELECT * FROM `' . $table . '`');
        $buffer = [];
        $cols = null;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($cols === null) {
                $cols = '`' . implode('`,`', array_keys($row)) . '`';
            }
            $vals = array_map(
                fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                array_values($row)
            );
            $buffer[] = '(' . implode(',', $vals) . ')';

            if (count($buffer) >= 100) {          // دفعات صغيرة تحافظ على الذاكرة
                echo "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $buffer) . ";\n";
                $buffer = [];
                flush();
            }
        }
        if ($buffer) {
            echo "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $buffer) . ";\n";
        }
        flush();
    }
    echo "\nSET FOREIGN_KEY_CHECKS=1;\n-- انتهت النسخة الاحتياطية\n";
    activity($pdo, 'backup', 'system', null, 'تنزيل نسخة احتياطية');
    exit;
}

// إحصاءات لعرضها قبل التنزيل
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$stats = [];
$totalRows = 0;
foreach ($tables as $t) {
    $n = (int)$pdo->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
    $stats[$t] = $n;
    $totalRows += $n;
}
$sizeRow = $pdo->prepare(
    'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb
     FROM information_schema.TABLES WHERE table_schema = ?'
);
$sizeRow->execute([current_db_name()]);
$sizeMb = (float)$sizeRow->fetchColumn();

page_header('النسخ الاحتياطي', 'backup.php');
?>
<div class="card">
    <div class="card-head">
        <h2>💾 نسخة احتياطية من قاعدة البيانات</h2>
        <a class="btn" href="backup.php?download=1&amp;csrf=<?= e($_SESSION['csrf']) ?>">⬇ تنزيل النسخة الآن</a>
    </div>
    <p class="muted">
        يُنزَّل ملف <code>.sql</code> يحتوي على كل بيانات العيادة (المرضى، القياسات، المواعيد،
        الأنظمة الغذائية، الحقن، الباقات، المدفوعات، المصروفات، المستخدمين، الإعدادات).
    </p>
    <div class="stats" style="margin-bottom:0">
        <div class="stat accent"><div class="label">عدد الجداول</div><div class="value"><?= count($tables) ?></div></div>
        <div class="stat"><div class="label">إجمالي السجلات</div><div class="value"><?= number_format($totalRows) ?></div></div>
        <div class="stat"><div class="label">حجم قاعدة البيانات</div><div class="value"><?= e(num_fmt($sizeMb, 2)) ?> <small style="font-size:14px;font-weight:400">ميجابايت</small></div></div>
    </div>
</div>

<div class="card">
    <h2>📋 محتوى النسخة</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الجدول</th><th>عدد السجلات</th></tr></thead>
        <tbody>
        <?php
        $labels = [
            'patients' => 'المرضى', 'measurements' => 'القياسات', 'appointments' => 'المواعيد',
            'diet_plans' => 'الأنظمة الغذائية', 'diet_templates' => 'قوالب الأنظمة',
            'payments' => 'المدفوعات', 'expenses' => 'المصروفات', 'users' => 'المستخدمون',
            'settings' => 'الإعدادات', 'drugs' => 'الأدوية', 'drug_batches' => 'دفعات المخزون',
            'injection_plans' => 'بروتوكولات الحقن', 'injection_doses' => 'جرعات الحقن',
            'packages' => 'كتالوج الباقات', 'patient_packages' => 'باقات المرضى',
            'package_uses' => 'الجلسات المستهلكة', 'message_log' => 'سجل الرسائل',
        ];
        foreach ($stats as $t => $n): ?>
            <tr>
                <td><strong><?= e($labels[$t] ?? $t) ?></strong> <small class="muted" dir="ltr"><?= e($t) ?></small></td>
                <td class="num"><?= number_format($n) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>الإجمالي</td><td class="num"><?= number_format($totalRows) ?></td></tr></tfoot>
    </table></div>
</div>

<div class="card">
    <h2>↩️ كيف تستعيد النسخة؟</h2>
    <ol class="muted" style="padding-inline-start:20px">
        <li>من cPanel افتح <strong>phpMyAdmin</strong> واختر قاعدة بيانات العيادة.</li>
        <li>اضغط تبويب <strong>استيراد (Import)</strong>.</li>
        <li>اختر ملف <code>.sql</code> الذي نزّلته ثم اضغط <strong>تنفيذ (Go)</strong>.</li>
    </ol>
    <div class="alert alert-warning">
        الاستعادة تمسح البيانات الحالية وتضع مكانها بيانات النسخة — تأكد أنك تستعيد النسخة الصحيحة.
    </div>
    <p class="muted">
        <strong>نصيحة:</strong> خُذ نسخة أسبوعيًا على الأقل، واحتفظ بها خارج السيرفر
        (على جهازك أو Google Drive). يمكنك أيضًا جدولة نسخة تلقائية من
        cPanel ← <em>Backup Wizard</em>.
    </p>
</div>
<?php page_footer();
