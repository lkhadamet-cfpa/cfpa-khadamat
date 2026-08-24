<?php
// ==================== طبقة الأمان والجلسة ====================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$host = 'localhost';
$db   = 'social_services_sector';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$db_connected = false;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $db_connected = true;
} catch (\PDOException $e) {
    // وضع المحاكاة في حال عدم اتصال القاعدة محلياً
}

$alert_message = "";

// 1. معالجة طلب الطوارئ والتحويل المالي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_emergency_request') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("خطأ أمني: رمز التحقق غير صالح.");
    }
    if ($db_connected) {
        $worker_name = filter_var($_POST['worker_name'], FILTER_SANITIZE_SPECIAL_CHARS);
        $emergency_type = filter_var($_POST['emergency_type'], FILTER_SANITIZE_SPECIAL_CHARS);
        $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_INT);
        $details = filter_var($_POST['details'], FILTER_SANITIZE_SPECIAL_CHARS);
        $timestamp = filter_var($_POST['timestamp'], FILTER_SANITIZE_SPECIAL_CHARS);

        $stmt = $pdo->prepare("INSERT INTO emergency_requests (worker_name, emergency_type, requested_amount, details, request_date, status) VALUES (?, ?, ?, ?, ?, 'قيد التحويل البريدي الآلي')");
        $stmt->execute([$worker_name, $emergency_type, $amount, $details, $timestamp]);
        $alert_message = "تم تسجيل طلب الطوارئ وإدراج مستحقاتكم لمسار التحويل عن بُعد بنجاح 💸!";
    }
}

// 2. معالجة طلب عدم خصم السلفة (أسباب قاهرة مع رفع الملف)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_deduction_suspension') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("خطأ أمني: رمز التحقق غير صالح.");
    }
    if ($db_connected) {
        $worker_name = filter_var($_POST['worker_name'], FILTER_SANITIZE_SPECIAL_CHARS);
        $loan_ref = filter_var($_POST['loan_ref'], FILTER_SANITIZE_SPECIAL_CHARS);
        $reason = filter_var($_POST['reason'], FILTER_SANITIZE_SPECIAL_CHARS);
        $timestamp = filter_var($_POST['timestamp'], FILTER_SANITIZE_SPECIAL_CHARS);
        
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $doc_safe = 'without_attachment.pdf';
        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['doc_file']['tmp_name'];
            $file_orig_name = basename($_FILES['doc_file']['name']);
            $file_ext = strtolower(pathinfo($file_orig_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'png', 'jpg', 'jpeg'];
            if (in_array($file_ext, $allowed_extensions)) {
                $doc_safe = time() . '_' . preg_replace("/[^a-zA-Z0-9_-]/", "_", $file_orig_name);
                move_uploaded_file($file_tmp, $upload_dir . $doc_safe);
            }
        }

        $stmt = $pdo->prepare("INSERT INTO deduction_suspension_requests (worker_name, loan_reference, compelling_reason, attached_document, request_date, status) VALUES (?, ?, ?, ?, ?, 'قيد دراسة اللجنة')");
        $stmt->execute([$worker_name, $loan_ref, $reason, $doc_safe, $timestamp]);
        $alert_message = "تم إرسال طلب عدم الخصم وإرفاق الوثيقة الثبوتية بنجاح إلى قاعدة البيانات 🛡️!";
    }
}

// 3. معالجة الأرشيف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_archive') {
    if ($db_connected) {
        $ref = filter_var($_POST['ref'], FILTER_SANITIZE_SPECIAL_CHARS);
        $subject = filter_var($_POST['subject'], FILTER_SANITIZE_SPECIAL_CHARS);
        $target = filter_var($_POST['target'], FILTER_SANITIZE_SPECIAL_CHARS);
        $method = filter_var($_POST['method'], FILTER_SANITIZE_SPECIAL_CHARS);
        $body = filter_var($_POST['body'], FILTER_SANITIZE_SPECIAL_CHARS);
        $timestamp = filter_var($_POST['timestamp'], FILTER_SANITIZE_SPECIAL_CHARS);

        $stmt = $pdo->prepare("INSERT INTO official_archives (reference_number, subject, target_org, transmission_method, report_body, transmission_timestamp) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ref, $subject, $target, $method, $body, $timestamp]);
        $alert_message = "تم أرشفة المراسلة وحفظها بنجاح 🛡️!";
    }
}

$emergency_requests = [];
$suspension_requests = [];
if ($db_connected) {
    try {
        $stmt = $pdo->query("SELECT * FROM emergency_requests ORDER BY id DESC");
        $emergency_requests = $stmt->fetchAll();
        $stmt2 = $pdo->query("SELECT * FROM deduction_suspension_requests ORDER BY id DESC");
        $suspension_requests = $stmt2->fetchAll();
    } catch (\Exception $e) {}
}

$total_budget = 50000000; 
$spent_budget = 14200000; 
$remaining_budget = $total_budget - $spent_budget;
$percentage = round(($spent_budget / $total_budget) * 100, 1);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>النظام السيادي الكامل (Logiciel Ultimate V15.0)</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
    <style>
        :root { --bg-color: #eef2f5; --text-color: #1e293b; --box-bg: #ffffff; --header-bg: #f8fafc; --border-color: #cbd5e1; --topbar-bg: #0f172a; }
        [data-theme="dark"] { --bg-color: #0f172a; --text-color: #f1f5f9; --box-bg: #1e293b; --header-bg: #334155; --border-color: #475569; --topbar-bg: #020617; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, sans-serif; transition: background 0.3s, color 0.3s; }
        body { background: var(--bg-color); color: var(--text-color); line-height: 1.6; font-size: 14px; }
        .sys-topbar { background: var(--topbar-bg); color: white; padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; border-bottom: 2px solid #3b82f6; }
        .sys-topbar span { color: #f59e0b; font-weight: bold; }
        header { background: #1e3a8a; color: white; padding: 1.5rem; text-align: center; border-bottom: 4px solid #f59e0b; }
        header h1 { font-size: 1.4rem; margin-bottom: 5px; }
        header p { font-size: 0.85rem; color: #93c5fd; }
        .container { width: 96%; max-width: 1500px; margin: 15px auto; }
        .window-box { background: var(--box-bg); border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .window-header { background: var(--header-bg); padding: 12px 18px; border-bottom: 1px solid var(--border-color); font-weight: bold; color: #3b82f6; display: flex; justify-content: space-between; align-items: center; font-size: 1rem; }
        .window-body { padding: 20px; }
        .form-group { margin-bottom: 15px; text-align: inherit; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.88rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.9rem; background: var(--box-bg); color: var(--text-color); }
        input:focus, select:focus, textarea:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .btn-logiciel { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 0.9rem; }
        .btn-logiciel:hover { background: #1d4ed8; }
        .btn-success { background: #10b981; }
        .btn-danger { background: #ef4444; }
        .view-section { display: none; }
        .view-section.active { display: block; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        @media(max-width: 1000px) { .kpi-grid { grid-template-columns: 1fr 1fr; } }
        .kpi-card { background: var(--box-bg); border: 1px solid var(--border-color); border-right: 5px solid #3b82f6; padding: 15px; border-radius: 4px; }
        .kpi-card.green { border-right-color: #10b981; }
        .kpi-card.orange { border-right-color: #f59e0b; }
        .kpi-card.red { border-right-color: #ef4444; }
        .kpi-title { font-size: 0.8rem; opacity: 0.8; font-weight: bold; margin-bottom: 5px; }
        .kpi-value { font-size: 1.3rem; font-weight: bold; }
        .logiciel-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; margin-top: 10px; }
        .logiciel-table th, .logiciel-table td { border: 1px solid var(--border-color); padding: 10px 12px; text-align: inherit; }
        .logiciel-table th { background: #1e293b; color: white; font-weight: 600; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .theme-toggle, .lang-select { background: #334155; color: white; border: none; padding: 5px 10px; border-radius: 20px; cursor: pointer; font-size: 0.78rem; }
        footer { background: var(--topbar-bg); color: white; text-align: center; padding: 1.5rem; margin-top: 3rem; font-size: 0.85rem; border-top: 4px solid #1e3a8a; }
    </style>
</head>
<body>

    <div class="sys-topbar">
        <div id="topbar-title">النظام السيادي الشامل (Logiciel V15.0) - قطاع التكوين والتعليم المهنيين</div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <select id="language-switcher" class="lang-select" onchange="changeLanguage(this.value)">
                <option value="ar">العربية 🇩🇿</option>
                <option value="fr">Français 🇫🇷</option>
                <option value="en">English 🇬🇧</option>
            </select>
            <button class="theme-toggle" onclick="toggleTheme()" id="btn-theme-text">الوضع الليلي 🌓</button>
        </div>
    </div>

    <header>
        <h1 id="header-main-title">المنصة الرقمية القطاعية الموحدة - الإصدار النهائي متعدد اللغات</h1>
        <p id="header-sub-title">التحويل البريدي الآلي، طلبات تعليق خصم السلف، ودعم اللغات الثلاث</p>
    </header>

    <div class="container">

        <?php if($alert_message): ?>
            <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; text-align: center;">
                <?php echo $alert_message; ?>
            </div>
        <?php endif; ?>

        <!-- 1. واجهة الدخول -->
        <div id="login-view" class="view-section active">
            <div class="window-box" style="max-width: 600px; margin: 30px auto; border-top: 5px solid #f59e0b;">
                <div class="window-header" id="login-box-title">🔐 بوابة المصادقة الرقمية المشفرة</div>
                <div class="window-body">
                    <form onsubmit="handleLogin(event)">
                        <div class="form-group">
                            <label id="lbl-inst">اختر المؤسسة التابع لها:</label>
                            <select id="user-institution" required>
                                <option value="المعهد الوطني المتخصص في التكوين المهني - بسكرة">المعهد الوطني المتخصص في التكوين المهني (INSFP)</option>
                                <option value="مركز التكوين المهني والتمهن - فضيلة سعدان (إناث)">مركز التكوين المهني والتمهن - فضيلة سعدان (إناث)</option>
                                <option value="مديرية التكوين والتعليم المهني (الإدارة الولائية)">مديرية التكوين والتعليم المهني (الإدارة الولائية)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="lbl-role">حدد صفة المستفيد:</label>
                            <select id="user-role" required>
                                <option value="عامل" id="opt-worker">عامل بالقطاع</option>
                                <option value="متقاعد" id="opt-retired">متقاعد من القطاع</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="lbl-name">الاسم الكامل للعامل:</label>
                            <input type="text" id="worker-full-name" required placeholder="أدخل اسمك الكامل">
                        </div>
                        <div class="form-group">
                            <label id="lbl-ccp">رقم الحساب البريدي الجاري (CCP) لتحويل المستحقات:</label>
                            <input type="text" id="worker-ccp" required placeholder="مثال: 0012345678 المفتاح 99">
                        </div>
                        <button type="submit" class="btn-logiciel" style="width: 100%; padding: 12px;" id="btn-login-submit">تسجيل الدخول للنظام 🔓</button>
                    </form>

                    <hr style="margin: 20px 0; border:0; border-top: 1px solid var(--border-color);">

                    <div style="padding: 12px; border-radius: 4px; border: 1px dashed var(--border-color); text-align: center;">
                        <h4 style="color: #3b82f6; margin-bottom: 8px;" id="admin-portal-title">⚙️ بوابة رئيس اللجنة المشرف (التحكم السيادي)</h4>
                        <input type="password" id="admin-pass" placeholder="كلمة المرور المشفرة" style="margin-bottom: 8px; text-align: center;">
                        <button onclick="handleAdminLogin()" class="btn-logiciel btn-danger" style="width: 100%;" id="btn-admin-login">دخول لوحة التحكم السيادية 🛡️</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. واجهة العامل -->
        <div id="worker-view" class="view-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 10px 15px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--box-bg);">
                <div><span id="txt-beneficiary">المستفيد:</span> <span id="display-user-name" style="color: #2563eb; font-weight: bold;">عامل</span> | <span id="txt-account">رقم الحساب (CCP):</span> <span id="display-user-ccp" style="color: #10b981; font-weight: bold;">---</span></div>
                <button onclick="location.reload()" class="btn-logiciel btn-danger" style="padding: 5px 12px; font-size: 0.8rem;" id="btn-logout">تسجيل الخروج 🚪</button>
            </div>
            
            <div class="window-box" style="border-top: 5px solid #ef4444;">
                <div class="window-header" style="color: #ef4444;" id="emer-box-title">🚨 بوابة تقديم طلب الطوارئ واستلام المستحقات عن بُعد</div>
                <div class="window-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="submit_emergency_request">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="worker_name" id="form-worker-name" value="عامل">
                        <div class="grid-2">
                            <div class="form-group">
                                <label id="lbl-emer-type">نوع الحالة الطارئة / الخدمة:</label>
                                <select name="emergency_type" required>
                                    <option value="مصاريف علاج مستعجلة / عملية جراحية" id="opt-emer-1">مصاريف علاج مستعجلة / عملية جراحية</option>
                                    <option value="سلفة اجتماعية استثنائية (ظرف قاهر)" id="opt-emer-2">سلفة اجتماعية استثنائية (ظرف قاهر)</option>
                                    <option value="مساعدة مرضية طارئة" id="opt-emer-3">مساعدة مرضية طارئة</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label id="lbl-emer-amount">المبلغ المطلوب تحويله (دج):</label>
                                <input type="number" name="amount" required placeholder="أدخل المبلغ المقدر">
                            </div>
                        </div>
                        <div class="form-group">
                            <label id="lbl-emer-details">شرح الظرف الطارئ:</label>
                            <textarea name="details" rows="2" required placeholder="اكتب تفاصيل الحالة الطارئة..."></textarea>
                        </div>
                        <input type="hidden" name="timestamp" id="emergency-timestamp">
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" onclick="setEmergencyTime()" class="btn-logiciel btn-danger" style="flex: 2; padding: 10px;" id="btn-submit-emer">إرسال ملف الطوارئ للتحويل عن بُعد 💸</button>
                            <button type="button" onclick="generateWorkerReceiptPDF()" class="btn-logiciel btn-success" style="flex: 1; padding: 10px;" id="btn-pdf-receipt">إيصال الإيداع الرقمي (PDF) 🖨️</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="window-box" style="border-top: 5px solid #f59e0b;">
                <div class="window-header" style="color: #f59e0b;" id="susp-box-title">⚖️ طلب عدم خصم قسط السلفة المسترجعة من الراتب (لأسباب قاهرة)</div>
                <div class="window-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="submit_deduction_suspension">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="worker_name" id="form-worker-name-2" value="عامل">
                        <div class="grid-2">
                            <div class="form-group">
                                <label id="lbl-loan-ref">رقم مرجع السلفة المعنية بالخصم:</label>
                                <input type="text" name="loan_ref" required placeholder="مثال: LOAN-2026/042">
                            </div>
                            <div class="form-group">
                                <label id="lbl-doc-file">إرفاق وثيقة ثبوتية للظرف القاهر (PDF / صورة):</label>
                                <input type="file" name="doc_file" required accept=".pdf, .png, .jpg, .jpeg">
                            </div>
                        </div>
                        <div class="form-group">
                            <label id="lbl-susp-reason">شرح تفصيلي للأسباب القاهرة الموجبة لعدم الخصم:</label>
                            <textarea name="reason" rows="3" required placeholder="برر سبب طلبك..."></textarea>
                        </div>
                        <input type="hidden" name="timestamp" id="suspension-timestamp">
                        <button type="submit" onclick="setSuspensionTime()" class="btn-logiciel" style="background: #f59e0b; width: 100%; padding: 12px; color: #0f172a; font-weight: bold;" id="btn-submit-susp">إرسال طلب تجميد الخصم مع الوثيقة الثبوتية للجنة 📄</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. لوحة تحكم رئيس اللجنة السيادية -->
        <div id="admin-view" class="view-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #0f172a; color: #fff; padding: 15px; bo
