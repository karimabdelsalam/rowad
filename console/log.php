<?php
/** سجل نشاط الكونسول. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

$rows = $pdo->query('SELECT l.*, u.name AS uname FROM console_log l
                     LEFT JOIN console_users u ON u.id = l.user_id
                     ORDER BY l.id DESC LIMIT 300')->fetchAll();

page_header('سجل النشاط', 'log.php');
?>
<div class="card">
    <h2>📜 آخر 300 حدث</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المستخدم</th><th>الحدث</th><th>التفاصيل</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="num"><small><?= e(date('Y/m/d H:i', strtotime($r['created_at']))) ?></small></td>
                <td><?= e($r['uname'] ?? 'النظام') ?></td>
                <td><span class="badge muted"><?= e($r['action']) ?></span></td>
                <td><?= e($r['summary']) ?></td>
                <td dir="ltr"><small class="muted"><?= e($r['ip']) ?></small></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">لا يوجد نشاط بعد.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
