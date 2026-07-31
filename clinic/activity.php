<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('activity.view');

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to   = $_GET['to'] ?? date('Y-m-d');
$ok = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
if (!$ok($from)) $from = date('Y-m-d', strtotime('-7 days'));
if (!$ok($to))   $to = date('Y-m-d');

$userId = (int)($_GET['user'] ?? 0);
$entity = (string)($_GET['entity'] ?? '');
$action = (string)($_GET['action'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 60;

$conds = ['a.created_at BETWEEN ? AND ?'];
$args  = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($userId > 0)                            { $conds[] = 'a.user_id = ?';  $args[] = $userId; }
if (array_key_exists($entity, ACT_ENTITIES)) { $conds[] = 'a.entity = ?';   $args[] = $entity; }
if (array_key_exists($action, ACT_LABELS))   { $conds[] = 'a.action = ?';   $args[] = $action; }
$where = 'WHERE ' . implode(' AND ', $conds);

$st = $pdo->prepare("SELECT COUNT(*) FROM activity_log a $where");
$st->execute($args);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);

$st = $pdo->prepare(
    "SELECT a.* FROM activity_log a $where ORDER BY a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per)
);
$st->execute($args);
$rows = $st->fetchAll();

$users = $pdo->query('SELECT id, name FROM users ORDER BY name')->fetchAll();

// ملخص أكثر المستخدمين نشاطًا في الفترة
$st = $pdo->prepare(
    "SELECT a.user_name, COUNT(*) c FROM activity_log a $where GROUP BY a.user_name ORDER BY c DESC LIMIT 6"
);
$st->execute($args);
$topUsers = $st->fetchAll();

$badge = fn(string $act) => match ($act) {
    'delete' => 'bad',
    'create', 'pay' => 'ok',
    'login_fail' => 'bad',
    'login', 'logout' => 'muted',
    default => 'info',
};

page_header('سجل النشاط', 'activity.php');
?>
<div class="card">
    <div class="card-head">
        <h2>📜 مَن فعل ماذا</h2>
        <span class="muted"><?= number_format($total) ?> حدث في الفترة</span>
    </div>
    <form class="inline-form" method="get">
        <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
        <label>المستخدم
            <select name="user">
                <option value="">الكل</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>القسم
            <select name="entity">
                <option value="">الكل</option>
                <?php foreach (ACT_ENTITIES as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $entity === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>الإجراء
            <select name="action">
                <option value="">الكل</option>
                <?php foreach (ACT_LABELS as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $action === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-sm" type="submit">عرض</button>
        <a class="btn btn-light btn-sm" href="activity.php">إلغاء الفلتر</a>
    </form>
</div>

<?php if ($topUsers): ?>
<div class="stats">
    <?php foreach ($topUsers as $tu): ?>
        <div class="stat"><div class="label"><?= e($tu['user_name']) ?></div>
            <div class="value"><?= number_format((int)$tu['c']) ?> <small style="font-size:13px;font-weight:400">حدث</small></div></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المستخدم</th><th>الإجراء</th><th>القسم</th><th>التفاصيل</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="num"><?= e(date('d/m H:i', strtotime($r['created_at']))) ?></td>
                <td><strong><?= e($r['user_name']) ?></strong></td>
                <td><span class="badge <?= e($badge($r['action'])) ?>"><?= e(ACT_LABELS[$r['action']] ?? $r['action']) ?></span></td>
                <td><?= e(ACT_ENTITIES[$r['entity']] ?? $r['entity']) ?></td>
                <td><?= $r['entity'] === 'patient' && $r['entity_id']
                        ? '<a href="patient.php?id=' . (int)$r['entity_id'] . '">' . e($r['summary']) . '</a>'
                        : e($r['summary']) ?></td>
                <td class="num muted" dir="ltr"><?= e($r['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted">لا توجد أحداث مسجّلة في هذه الفترة.</td></tr><?php endif; ?>
        </tbody>
    </table></div>

    <?php if ($pages > 1): ?>
    <div class="actions" style="margin-top:12px">
        <?php
        $qs = fn(int $p) => 'activity.php?from=' . urlencode($from) . '&to=' . urlencode($to)
            . '&user=' . $userId . '&entity=' . urlencode($entity) . '&action=' . urlencode($action) . '&page=' . $p;
        $start = max(1, $page - 3);
        $end = min($pages, $start + 6);
        ?>
        <?php if ($page > 1): ?><a class="btn btn-light btn-sm" href="<?= e($qs($page - 1)) ?>">السابق</a><?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a class="btn btn-sm <?= $i === $page ? '' : 'btn-light' ?>" href="<?= e($qs($i)) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a class="btn btn-light btn-sm" href="<?= e($qs($page + 1)) ?>">التالي</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <p class="muted">
        السجل يحفظ كل عملية مؤثرة: إضافة وتعديل وحذف المرضى والقياسات والمواعيد والجرعات
        والباقات والمدفوعات، بالإضافة إلى تسجيلات الدخول ومحاولات الدخول الفاشلة.
        السجل للقراءة فقط ولا يمكن تعديله من داخل النظام.
    </p>
</div>
<?php page_footer();
