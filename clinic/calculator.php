<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('calc.use');

/*
 * حاسبة الاحتياج اليومي من السعرات والماكروز.
 *
 * المعادلة: Mifflin-St Jeor وهي المعتمدة في الإرشادات الحديثة لأنها الأدق
 * للأشخاص ذوي الوزن الزائد مقارنة بـ Harris-Benedict.
 *   الرجال: (10 × الوزن) + (6.25 × الطول) − (5 × العمر) + 5
 *   النساء: (10 × الوزن) + (6.25 × الطول) − (5 × العمر) − 161
 */

const ACTIVITY = [
    '1.2'   => 'خامل — عمل مكتبي بدون رياضة',
    '1.375' => 'نشاط خفيف — رياضة 1-3 أيام أسبوعيًا',
    '1.55'  => 'نشاط متوسط — رياضة 3-5 أيام أسبوعيًا',
    '1.725' => 'نشاط عالٍ — رياضة 6-7 أيام أسبوعيًا',
    '1.9'   => 'نشاط شديد — عمل بدني + تمارين يومية',
];

const GOALS = [
    'lose_fast' => ['إنقاص سريع (−25%)', -0.25],
    'lose'      => ['إنقاص معتدل (−20%)', -0.20],
    'lose_slow' => ['إنقاص بطيء (−10%)', -0.10],
    'keep'      => ['ثبات الوزن', 0.0],
    'gain'      => ['زيادة كتلة عضلية (+15%)', 0.15],
];

$patient = null;
$pid = (int)($_GET['patient'] ?? 0);
if ($pid) {
    $st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
    $st->execute([$pid]);
    $patient = $st->fetch() ?: null;
    if ($patient && !can_access_patient($patient)) {
        flash('هذا المريض تحت رعاية طبيب آخر.', 'danger');
        redirect('patients.php');
    }
}

// القيم الافتراضية من ملف المريض إن وُجد
$lastWeight = null;
if ($patient) {
    $st = $pdo->prepare('SELECT weight FROM measurements WHERE patient_id = ? ORDER BY mdate DESC, id DESC LIMIT 1');
    $st->execute([$pid]);
    $lastWeight = $st->fetchColumn() ?: null;
}

$in = fn(string $k, $d = '') => $_GET[$k] ?? $d;
$gender = ($_GET['gender'] ?? ($patient['gender'] ?? 'female')) === 'male' ? 'male' : 'female';
$age    = (int)($_GET['age'] ?? (calc_age($patient['birth_date'] ?? null) ?? 30));
$height = (float)($_GET['height'] ?? ($patient['height_cm'] ?? 165));
$weight = (float)($_GET['weight'] ?? ($lastWeight ?? 80));
$act    = (string)($_GET['activity'] ?? '1.375');
$goal   = (string)($_GET['goal'] ?? 'lose');
$protPerKg = (float)($_GET['protein'] ?? 1.6);
$fatPct    = (float)($_GET['fat'] ?? 25);

if (!array_key_exists($act, ACTIVITY))  $act = '1.375';
if (!array_key_exists($goal, GOALS))    $goal = 'lose';
$age    = max(10, min(100, $age));
$height = max(100.0, min(230.0, $height));
$weight = max(30.0, min(300.0, $weight));
$protPerKg = max(0.8, min(2.5, $protPerKg));
$fatPct    = max(15.0, min(40.0, $fatPct));

// الحسابات
$bmr = $gender === 'male'
    ? 10 * $weight + 6.25 * $height - 5 * $age + 5
    : 10 * $weight + 6.25 * $height - 5 * $age - 161;
$tdee   = $bmr * (float)$act;
$target = $tdee * (1 + GOALS[$goal][1]);

$bmi = calc_bmi($weight, $height);
[$bmiLabel, $bmiClass] = $bmi !== null ? bmi_label($bmi) : ['—', 'muted'];

// الوزن المثالي — معادلة Devine مع نطاق BMI الصحي
$idealDevine = $gender === 'male'
    ? 50.0 + 0.9 * ($height - 152.0)
    : 45.5 + 0.9 * ($height - 152.0);
$healthyMin = 18.5 * (($height / 100) ** 2);
$healthyMax = 24.9 * (($height / 100) ** 2);

// الماكروز
$protG   = $protPerKg * $weight;
$fatG    = ($target * ($fatPct / 100)) / 9;
$carbKcal = $target - ($protG * 4) - ($fatG * 9);
$carbG   = max(0.0, $carbKcal / 4);

$water = round($weight * 0.035, 1);              // لتر/يوم تقريبًا
$weeklyLoss = GOALS[$goal][1] < 0 ? abs(($tdee - $target) * 7) / 7700 : 0;  // 7700 سعر ≈ 1 كجم دهون

page_header('حاسبة السعرات والاحتياج اليومي', 'calculator.php');
?>
<div class="card">
    <div class="card-head">
        <h2>🧮 حاسبة السعرات (Mifflin-St Jeor)</h2>
        <?php if ($patient): ?>
            <a class="btn btn-light btn-sm" href="patient.php?id=<?= $pid ?>">← ملف <?= e($patient['name']) ?></a>
        <?php endif; ?>
    </div>
    <form method="get">
        <?php if ($pid): ?><input type="hidden" name="patient" value="<?= $pid ?>"><?php endif; ?>
        <div class="grid4">
            <label>النوع
                <select name="gender">
                    <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>أنثى</option>
                    <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>ذكر</option>
                </select>
            </label>
            <label>العمر (سنة) <input type="number" min="10" max="100" name="age" value="<?= $age ?>" required></label>
            <label>الطول (سم) <input type="number" step="0.5" min="100" max="230" name="height" value="<?= e(num_fmt($height)) ?>" required></label>
            <label>الوزن (كجم) <input type="number" step="0.1" min="30" max="300" name="weight" value="<?= e(num_fmt($weight)) ?>" required></label>
        </div>
        <div class="grid2">
            <label>مستوى النشاط
                <select name="activity">
                    <?php foreach (ACTIVITY as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= $act === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>الهدف
                <select name="goal">
                    <?php foreach (GOALS as $k => [$label, $_]): ?>
                        <option value="<?= e($k) ?>" <?= $goal === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="grid2">
            <label>البروتين (جم لكل كجم من الوزن)
                <input type="number" step="0.1" min="0.8" max="2.5" name="protein" value="<?= e(num_fmt($protPerKg)) ?>"></label>
            <label>نسبة الدهون من السعرات (%)
                <input type="number" step="1" min="15" max="40" name="fat" value="<?= e(num_fmt($fatPct, 0)) ?>"></label>
        </div>
        <button class="btn" type="submit">احسب</button>
    </form>
</div>

<div class="stats">
    <div class="stat"><div class="label">معدل الأيض الأساسي BMR</div>
        <div class="value"><?= number_format($bmr) ?> <small style="font-size:14px;font-weight:400">سعر</small></div>
        <small class="muted">ما يحرقه الجسم في الراحة التامة</small></div>
    <div class="stat"><div class="label">الاحتياج اليومي TDEE</div>
        <div class="value"><?= number_format($tdee) ?> <small style="font-size:14px;font-weight:400">سعر</small></div>
        <small class="muted">مع نشاطك الحالي — للثبات على وزنك</small></div>
    <div class="stat accent"><div class="label">السعرات المستهدفة</div>
        <div class="value" style="color:#0f766e"><?= number_format($target) ?> <small style="font-size:14px;font-weight:400">سعر/يوم</small></div>
        <small class="muted"><?= e(GOALS[$goal][0]) ?></small></div>
    <?php if ($weeklyLoss > 0): ?>
    <div class="stat"><div class="label">النزول المتوقع</div>
        <div class="value"><?= e(num_fmt($weeklyLoss)) ?> <small style="font-size:14px;font-weight:400">كجم/أسبوع</small></div>
        <small class="muted">بالالتزام الكامل بالسعرات</small></div>
    <?php endif; ?>
</div>

<div class="grid2" style="gap:20px;align-items:start">
    <div class="card">
        <h2>🥗 توزيع الماكروز اليومي</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>العنصر</th><th>الكمية</th><th>السعرات</th><th>النسبة</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>بروتين</strong> <small class="muted">(<?= e(num_fmt($protPerKg)) ?> جم/كجم)</small></td>
                    <td class="num"><strong><?= number_format($protG) ?> جم</strong></td>
                    <td class="num"><?= number_format($protG * 4) ?></td>
                    <td class="num"><?= $target > 0 ? round($protG * 4 * 100 / $target) : 0 ?>%</td>
                </tr>
                <tr>
                    <td><strong>دهون</strong></td>
                    <td class="num"><strong><?= number_format($fatG) ?> جم</strong></td>
                    <td class="num"><?= number_format($fatG * 9) ?></td>
                    <td class="num"><?= e(num_fmt($fatPct, 0)) ?>%</td>
                </tr>
                <tr>
                    <td><strong>كربوهيدرات</strong></td>
                    <td class="num"><strong><?= number_format($carbG) ?> جم</strong></td>
                    <td class="num"><?= number_format($carbG * 4) ?></td>
                    <td class="num"><?= $target > 0 ? round($carbG * 4 * 100 / $target) : 0 ?>%</td>
                </tr>
            </tbody>
            <tfoot><tr><td>الإجمالي</td><td></td><td class="num"><?= number_format($target) ?> سعر</td><td class="num">100%</td></tr></tfoot>
        </table></div>
        <p class="muted">💧 الماء المقترح: <strong><?= e(num_fmt($water)) ?> لتر يوميًا</strong> (35 مل لكل كجم).</p>
    </div>

    <div class="card">
        <h2>⚖️ مؤشرات الوزن</h2>
        <div class="table-wrap"><table>
            <tbody>
                <tr><td>مؤشر كتلة الجسم BMI</td>
                    <td class="num"><strong><?= $bmi !== null ? e((string)$bmi) : '—' ?></strong>
                        <span class="badge <?= e($bmiClass) ?>"><?= e($bmiLabel) ?></span></td></tr>
                <tr><td>الوزن المثالي (Devine)</td>
                    <td class="num"><strong><?= e(num_fmt($idealDevine)) ?> كجم</strong></td></tr>
                <tr><td>النطاق الصحي للوزن</td>
                    <td class="num"><strong><?= e(num_fmt($healthyMin)) ?> — <?= e(num_fmt($healthyMax)) ?> كجم</strong></td></tr>
                <tr><td>الفرق عن أعلى النطاق الصحي</td>
                    <td class="num">
                    <?php $over = $weight - $healthyMax; ?>
                    <strong style="color:<?= $over > 0 ? '#b91c1c' : '#15803d' ?>">
                        <?= $over > 0 ? 'يحتاج إنقاص ' . num_fmt($over) . ' كجم' : 'داخل النطاق الصحي ✔' ?>
                    </strong></td></tr>
            </tbody>
        </table></div>
        <p class="muted">
            المعادلة المستخدمة <strong>Mifflin-St Jeor</strong> وهي الأدق لمن لديهم وزن زائد.
            النتائج تقديرية وتُعدَّل حسب استجابة المريض الفعلية في المتابعة.
        </p>
        <?php if ($patient): ?>
        <div class="actions">
            <a class="btn" href="plan_edit.php?patient=<?= $pid ?>&calories=<?= (int)round($target) ?>">
                إنشاء نظام غذائي بـ <?= number_format($target) ?> سعر</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php page_footer();
