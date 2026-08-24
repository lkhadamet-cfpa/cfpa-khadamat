<?php
session_start();
require_once 'config/database.php';

// التحقق من أن المستخدم مسجل الدخول كمسؤول (رئيس اللجنة)
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$msg = "";

// معالجة تحديث حالة الطلب
if (isset($_POST['update_status'])) {
    $req_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $req_id])) {
        $msg = "تم تحديث حالة الطلب بنجاح!";
    }
}

// معالجة تحديث الإعلانات العامة ومواعيد الاجتماعات
if (isset($_POST['update_settings'])) {
    $announcement = $_POST['announcement'];
    $meeting_info = $_POST['meeting_info'];
    
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'announcement'")->execute([$announcement]);
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'meeting_info'")->execute([$meeting_info]);
    
    $msg = "تم تحديث إعلانات المنصة ومواعيد الاجتماعات بنجاح!";
}

// جلب الإحصائيات والأرقام الحية
$total_reqs = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$new_reqs = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'جديد'")->fetchColumn();
$pending_reqs = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'قيد الدراسة'")->fetchColumn();
$accepted_reqs = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'مقبول'")->fetchColumn();

// جلب جميع الطلبات الواردة من قاعدة البيانات
$requests_stmt = $pdo->query("SELECT * FROM requests ORDER BY created_at DESC");
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الإعلانات الحالية للعرض في نموذج التعديل
$announcement_val = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'announcement'")->fetchColumn();
$meeting_val = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'meeting_info'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم التنفيذية - لجنة الخدمات الاجتماعية 2026</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Tahoma, sans-serif; }
        body { background: #111d28; color: white; line-height: 1.6; padding: 20px; }
        .container { max-width: 1400px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #2c3e50; padding-bottom: 15px; margin-bottom: 20px; }
        .card { background: #1e2b37; padding: 1.5rem; margin: 1.5rem 0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); border-top: 4px solid #f39c12; }
        .title { color: #f39c12; margin-bottom: 1rem; font-size: 1.15rem; border-bottom: 1px solid #34495e; padding-bottom: 8px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        @media(max-width: 768px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
        .stat-box { background: #2c3e50; color: white; padding: 20px; border-radius: 6px; text-align: center; border-bottom: 4px solid #3498db; }
        .stat-box h3 { font-size: 1.8rem; color: #f39c12; margin-bottom: 5px; }
        .form-group { margin-bottom: 15px; text-align: right; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; color: #bdc3c7; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #4f5d73; border-radius: 5px; font-size: 0.95rem; background: #2c3e50; color: white; }
        input:focus, select:focus, textarea:focus { border-color: #f39c12; outline: none; }
        .btn { background: #27ae60; color: white; border: none; padding: 10px 15px; border-radius: 5px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn:hover { opacity: 0.9; }
        .table-container { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; text-align: right; font-size: 0.88rem; }
        th, td { border: 1px solid #34495e; padding: 10px; }
        th { background: #1b4f72; color: white; }
        tr:nth-child(even) { background: #16222c; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .badge-new { background: #e74c3c; color: white; }
        .badge-pending { background: #f39c12; color: white; }
        .badge-accepted { background: #27ae60; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🛡️ غرفة القيادة ولوحة التحكم التنفيذية لرئيس اللجنة</h2>
            <div>
                <span style="margin-left: 15px; color: #f39c12;">مرحباً، <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'المسؤول'); ?></span>
                <a href="login.php" style="background: #c0392b; color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; font-size: 0.85rem;">تسجيل الخروج 🚪</a>
            </div>
        </div>

        <?php if(!empty($msg)): ?>
            <div style="background: #27ae60; color: white; padding: 12px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-weight: bold;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <!-- الإحصائيات الحية -->
        <div class="stats-grid">
            <div class="stat-box"><h3><?php echo $total_reqs; ?></h3><p>إجمالي الطلبات الواردة</p></div>
            <div class="stat-box" style="border-bottom-color: #e74c3c;"><h3><?php echo $new_reqs; ?></h3><p>طلبات جديدة بانتظار المعالجة</p></div>
            <div class="stat-box" style="border-bottom-color: #f39c12;"><h3><?php echo $pending_reqs; ?></h3><p>طلبات قيد الدراسة</p></div>
            <div class="stat-box" style="border-bottom-color: #27ae60;"><h3><?php echo $accepted_reqs; ?></h3><p>طلبات مقبولة</p></div>
        </div>

        <!-- جدول متابعة ومعالجة طلبات العمال -->
        <div class="card">
            <h3 class="title">📋 متابعة ومعالجة طلبات ومراسلات العمال الواردة</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>رقم الملف</th>
                            <th>اسم العامل</th>
                            <th>الرقم المهني</th>
                            <th>نوع الطلب</th>
                            <th>الموضوع والتفاصيل</th>
                            <th>المرفق الثبوتي</th>
                            <th>الحالة الحالية</th>
                            <th>إجراء وتغيير الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($requests) > 0): ?>
                            <?php foreach($requests as $req): ?>
                            <tr>
                                <td><b>#REQ-<?php echo $req['id']; ?></b></td>
                                <td><?php echo htmlspecialchars($req['worker_name']); ?></td>
                                <td><?php echo htmlspecialchars($req['job_id']); ?></td>
                                <td><?php echo htmlspecialchars($req['category']); ?></td>
                                <td>
                                    <b><?php echo htmlspecialchars($req['subject']); ?></b><br>
                                    <small style="color: #bdc3c7;"><?php echo htmlspecialchars($req['details']); ?></small>
                                </td>
                                <td>
                                    <?php if(!empty($req['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($req['file_path']); ?>" target="_blank" style="color: #3498db; text-decoration: underline;">عرض المرفق 📎</a>
                                    <?php else: ?>
                                        <span style="color: #7f8c8d;">لا يوجد مرفق</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($req['status']=='جديد')?'badge-new':(($req['status']=='قيد الدراسة')?'badge-pending':'badge-accepted'); ?>">
                                        <?php echo htmlspecialchars($req['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form action="" method="POST" style="display: flex; gap: 5px;">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <select name="new_status" style="padding: 5px; font-size: 0.8rem; width: 110px;">
                                            <option value="جديد" <?php if($req['status']=='جديد') echo 'selected'; ?>>جديد</option>
                                            <option value="قيد التدقيق" <?php if($req['status']=='قيد التدقيق') echo 'selected'; ?>>قيد التدقيق</option>
                                            <option value="ملف ناقص" <?php if($req['status']=='ملف ناقص') echo 'selected'; ?>>ملف ناقص</option>
                                            <option value="قيد الدراسة" <?php if($req['status']=='قيد الدراسة') echo 'selected'; ?>>قيد الدراسة</option>
                                            <option value="مقبول" <?php if($req['status']=='مقبول') echo 'selected'; ?>>مقبول</option>
                                            <option value="مرفوض" <?php if($req['status']=='مرفوض') echo 'selected'; ?>>مرفوض</option>
                                            <option value="منفذ" <?php if($req['status']=='منفذ') echo 'selected'; ?>>منفذ</option>
                                            <option value="مؤرشف" <?php if($req['status']=='مؤرشف') echo 'selected'; ?>>مؤرشف</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn" style="padding: 5px 8px; font-size: 0.8rem;">تحديث</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align: center; padding: 20px; color: #7f8c8d;">لا توجد أي طلبات مرسلة حتى الآن.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- لوحة التحكم في الإعلانات والمواعيد -->
        <div class="card">
            <h3 class="title">📢 التحكم في الإعلانات ومواعيد الاجتماعات المعروضة للعمال</h3>
            <form action="" method="POST">
                <div class="form-group">
                    <label>نص التنبيه العام وشروط إيداع الملفات:</label>
                    <input type="text" name="announcement" value="<?php echo htmlspecialchars($announcement_val); ?>" required>
                </div>
                <div class="form-group">
                    <label>جدول مواعيد الاجتماعات والجمعية العامة:</label>
                    <input type="text" name="meeting_info" value="<?php echo htmlspecialchars($meeting_val); ?>" required>
                </div>
                <button type="submit" name="update_settings" class="btn" style="background: #2980b9;">تحديث ونشر الإعلانات فوراً 🔄</button>
            </form>
        </div>
    </div>
</body>
</html>
