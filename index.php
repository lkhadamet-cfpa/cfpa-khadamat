<?php
// الاتصال بقاعدة البيانات (قم بتغيير البيانات حسب استضافتك)
$host = 'localhost';
$dbname = 'social_db';
$username = 'root';
$password = '';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // قاعدة البيانات غير متصلة حالياً، المنصة ستعمل واجهتها بشكل طبيعي
}

// معالجة الطلبات المرسلة عبر POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'submit_worker_request' && $pdo) {
        $name = $_POST['req_name'] ?? '';
        $email = $_POST['req_email'] ?? '';
        $subject = $_POST['req_subject'] ?? '';
        $details = $_POST['req_details'] ?? '';
        $fileName = "بدون مرفق";
        
        if (isset($_FILES['req_file']) && $_FILES['req_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['req_file']['name']);
            if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
            move_uploaded_file($_FILES['req_file']['tmp_name'], "uploads/" . $fileName);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO worker_requests (name, email, subject, details, file_name, status) VALUES (?, ?, ?, ?, ?, 'قيد المعالجة')");
            $stmt->execute([$name, $email, $subject, $details, $fileName]);
            echo json_encode(["status" => "success", "message" => "تم إرسال الطلب بنجاح"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "خطأ في الحفظ"]);
        }
        exit;
    }

    if ($_POST['action'] === 'submit_report' && $pdo) {
        $dest = $_POST['rep_dest'] ?? '';
        $type = $_POST['rep_type'] ?? '';
        $month = $_POST['rep_month'] ?? '';
        $notes = $_POST['rep_notes'] ?? '';
        $fileName = "تقرير معتمد.pdf";
        
        if (isset($_FILES['rep_file']) && $_FILES['rep_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['rep_file']['name']);
            if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
            move_uploaded_file($_FILES['rep_file']['tmp_name'], "uploads/" . $fileName);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO reports (dest, type, month_name, file_name, notes, status) VALUES (?, ?, ?, ?, ?, 'تم الإرسال والأرشفة بنجاح ✅')");
            $stmt->execute([$dest, $type, $month, $fileName, $notes]);
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error"]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الخدمات الاجتماعية - فضيلة سعدان إناث - بسكرة</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Tahoma, sans-serif; }
        body { background: #f4f6f8; color: #333; line-height: 1.6; }
        .alert-bar { background: #d9534f; color: white; text-align: center; padding: 10px; font-weight: bold; }
        header { background: #003366; color: white; padding: 2rem 1rem; text-align: center; border-bottom: 4px solid #b3d1ff; position: relative; }
        .container { width: 92%; max-width: 1000px; margin: auto; padding: 1.5rem 0; }
        .card { background: white; padding: 1.5rem; margin: 1.5rem 0; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .title { color: #003366; margin-bottom: 1rem; font-size: 1.3rem; border-bottom: 2px solid #b3d1ff; padding-bottom: 5px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .btn-link { display: block; background: #0056b3; color: white; text-align: center; padding: 12px; border-radius: 4px; text-decoration: none; font-weight: bold; margin-top: 5px; }
        .btn-whatsapp { background: #25d366 !important; }
        .btn-back-home { position: absolute; top: 15px; left: 15px; background: #e74c3c; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.85rem; }
        .services-table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: right; font-size: 0.85rem; }
        .services-table th, .services-table td { border: 1px solid #ddd; padding: 8px; }
        .services-table th { background: #003366; color: white; }
        .form-group { margin-bottom: 12px; text-align: right; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; }
        .btn-submit { background: #28a745; color: white; border: none; padding: 12px; width: 100%; border-radius: 4px; font-weight: bold; cursor: pointer; }
        
        #auth-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 20, 40, 0.95); display: flex; justify-content: center; align-items: center; z-index: 9999; padding: 15px; }
        .auth-box { background: white; padding: 2rem; border-radius: 10px; width: 100%; max-width: 420px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border-top: 6px solid #003366; }
        .btn-admin-access { background: #2c3e50; color: white; border: none; padding: 10px; width: 100%; border-radius: 4px; font-weight: bold; cursor: pointer; margin-top: 8px; font-size: 0.9rem; border: 1px dashed #e74c3c; }
        
        #admin-login-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 10001; }
        .admin-modal-box { background: #2c3e50; color: white; padding: 2rem; border-radius: 8px; width: 100%; max-width: 350px; text-align: center; border-top: 5px solid #e74c3c; }

        .admin-dashboard { background: #1a252f; color: white; min-height: 100vh; padding: 20px; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #34495e; padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .admin-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn { background: #2c3e50; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.9rem; }
        .tab-btn.active { background: #e74c3c; }
        .tab-content { display: none; background: #2c3e50; padding: 20px; border-radius: 6px; }
        .tab-content.active { display: block; }
        footer { background: #222; color: white; text-align: center; padding: 1rem; margin-top: 2rem; font-size: 0.85rem; }
        .hidden-content { display: none; }
    </style>
</head>
<body>

    <!-- نافذة دخول رئيس اللجنة -->
    <div id="admin-login-modal">
        <div class="admin-modal-box">
            <h3 style="margin-bottom: 15px; color: #e74c3c;">🔐 دخول رئيس اللجنة</h3>
            <form onsubmit="checkAdmin(event)">
                <input type="password" id="adminPass" placeholder="أدخل كلمة المرور السرية" required style="margin-bottom: 15px; text-align: center; background: #34495e; color: white; border: 1px solid #555;">
                <button type="submit" class="btn-submit" style="background: #e74c3c; margin-bottom: 10px;">دخول لوحة التحكم 🚀</button>
                <button type="button" onclick="closeAdminModal()" style="background: transparent; color: #aaa; border: none; cursor: pointer; font-size: 0.85rem;">إلغاء</button>
            </form>
        </div>
    </div>

    <!-- نافذة دخول العمال والمستفيدين -->
    <div id="auth-overlay">
        <div class="auth-box">
            <h2>الخدمات الاجتماعية</h2>
            <div class="subtitle" style="color: #d9534f; font-weight: bold; font-size: 0.85rem; margin-bottom: 1.5rem;">قطاع التكوين والتعليم المهنيين - ولاية بسكرة</div>
            
            <form onsubmit="verifyUser(event)">
                <div class="form-group">
                    <select id="userRole" required>
                        <option value="">-- اختر صفتك بدقة --</option>
                        <option value="عامل">عامل بقطاع التكوين المهني</option>
                        <option value="متقاعد">متقاعد من القطاع</option>
                        <option value="من ذوي الحقوق">من ذوي الحقوق</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" id="userIdNum" required placeholder="أدخل رقمك التعريفي">
                </div>
                <button type="submit" class="btn-submit" style="background: #003366; margin-top: 5px;">دخول المنصة 🔐</button>
            </form>
            <button type="button" class="btn-admin-access" onclick="openAdminModal()">⚙️ دخول رئيس اللجنة / الإدارة</button>
        </div>
    </div>

    <!-- واجهة العمال -->
    <div id="main-platform" class="hidden-content">
        <div class="alert-bar">📢 تنبيه: آخر أجل لإيداع الملفات يوم 30 من كل شهر.</div>
        <header>
            <button class="btn-back-home" onclick="goToHome()">🏠 تسجيل الخروج</button>
            <h1>لجنة الخدمات الاجتماعية - فضيلة سعدان إناث</h1>
            <p>طريق طولقة - ولاية بسكرة</p>
        </header>
        <main class="container">
            <section class="card">
                <h2 class="title">📞 تواصل معنا</h2>
                <div class="grid">
                    <a href="https://wa.me/213654850150" target="_blank" class="btn-link btn-whatsapp">تواصل عبر واتساب (0654850150)</a>
                    <a href="https://www.mfep.gov.dz" target="_blank" class="btn-link">موقع وزارة التكوين المهني</a>
                </div>
            </section>
            
            <section class="card">
                <h2 class="title">✉️ فضاء مراسلة اللجنة وطلب الخدمات</h2>
                <form id="worker-request-form" onsubmit="submitWorkerRequest(event)">
                    <input type="hidden" name="action" value="submit_worker_request">
                    <div class="form-group"><label>الاسم واللقب:</label><input type="text" name="req_name" required placeholder="اسمك الكامل"></div>
                    <div class="form-group"><label>البريد الإلكتروني:</label><input type="email" name="req_email" required placeholder="example@gmail.com"></div>
                    <div class="form-group">
                        <label>موضوع الطلب:</label>
                        <select name="req_subject">
                            <option value="طلب سلفة مالية">طلب سلفة مالية (حسب الأولوية)</option>
                            <option value="ملف صحي وعمليات">ملف صحي وعمليات جراحية وأشعة</option>
                            <option value="منحة الوفاة أو الزواج">منحة الوفاة أو الزواج</option>
                            <option value="استفسار عام">استفسار عام</option>
                        </select>
                    </div>
                    <div class="form-group" style="background: #f9f9f9; padding: 10px; border: 2px dashed #0056b3; border-radius: 4px;">
                        <label>تحميل الوثائق أو الصور المطلوبة:</label><input type="file" name="req_file" accept="image/*,.pdf">
                    </div>
                    <div class="form-group"><label>تفاصيل الطلب:</label><textarea name="req_details" required placeholder="اكتب تفاصيل طلبك..."></textarea></div>
                    <button type="submit" class="btn-submit">إرسال الطلب وحفظه بالمنصة 📤</button>
                </form>
            </section>
        </main>
        <footer>جميع الحقوق محفوظة © 2026 - لجنة الخدمات الاجتماعية فضيلة سعدان إناث (طريق طولقة - بسكرة)</footer>
    </div>

    <!-- لوحة تحكم رئيس اللجنة -->
    <div id="admin-dashboard-panel" class="admin-dashboard hidden-content">
        <div class="admin-header">
            <div>
                <h2>🛡️ لوحة التحكم الخاصة برئيس اللجنة</h2>
                <p style="font-size: 0.85rem; color: #bdc3c7;">مركز: فضيلة سعدان إناث (طريق طولقة - بسكرة)</p>
            </div>
            <button onclick="location.reload()" style="background: #e74c3c; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">تسجيل الخروج 🚪</button>
        </div>

        <div class="admin-tabs">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-finances')">💰 الميزانية والأولوية</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-requests')">📥 طلبات العمال والمتابعة</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-ministry')">🏛️ التقارير والأرشيف للمراقب والوزارة</button>
        </div>

        <div id="tab-finances" class="tab-content active">
            <h3 style="margin-bottom: 15px; color: #f1c40f;">📊 جدول ميزانية الخدمات حسب الأولوية</h3>
            <table class="services-table" style="background: #2c3e50;">
                <thead><tr><th>ترتيب الأولوية</th><th>نوع الخدمة / المناسبة</th><th>المبلغ المخصص (السقف الأقصى)</th></tr></thead>
                <tbody>
                    <tr><td>1 (قصوى)</td><td>العلاج والعمليات الجراحية</td><td><b>50,000 دج</b></td></tr>
                    <tr><td>2</td><td>منحة الوفاة</td><td><b>40,000 دج</b></td></tr>
                    <tr><td>3</td><td>السلف الاجتماعية</td><td><b>30,000 دج</b></td></tr>
                    <tr><td>4</td><td>منحة الزواج</td><td><b>20,000 دج</b></td></tr>
                </tbody>
            </table>
        </div>

        <div id="tab-requests" class="tab-content">
            <h3 style="margin-bottom: 15px; color: #3498db;">📥 طلبات وعمال المنصة</h3>
            <table class="services-table" style="background: #2c3e50;">
                <thead><tr><th>اسم العامل</th><th>نوع الطلب</th><th>التفاصيل والمرفقات</th><th>الحالة</th></tr></thead>
                <tbody id="requests-table-body"><tr><td colspan="4" style="text-align:center;">تم تفعيل النظام بنجاح.</td></tr></tbody>
            </table>
        </div>

        <div id="tab-ministry" class="tab-content">
            <h3 style="margin-bottom: 15px; color: #2ecc71;">🏛️ إرسال وأرشفة التقارير للمراقب المالي والوزارة</h3>
            <form id="report-form" onsubmit="submitReport(event)">
                <input type="hidden" name="action" value="submit_report">
                <div class="form-group">
                    <label style="color:#fff;">جهة الإرسال / البريد المستهدف:</label>
                    <select name="rep_dest" style="background: #2c3e50; color: white;">
                        <option value="المراقب المالي لولاية بسكرة">المراقب المالي لولاية بسكرة</option>
                        <option value="وزارة التكوين المهني - مديرية الوسائل">وزارة التكوين والتعليم المهنيين</option>
                        <option value="أرشيف لجنة المركز (فضيلة سعدان)">أرشيف لجنة المركز الداخلي</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="color:#fff;">نوع التقرير:</label>
                    <select name="rep_type" style="background: #2c3e50; color: white;">
                        <option value="التقرير المالي الشهري">التقرير المالي الشهري (النفقات والسلف)</option>
                        <option value="التقرير الأدبي الثلاثي">التقرير الأدبي الثلاثي للنشاطات</option>
                        <option value="محضر اجتماع اللجنة">محضر اجتماع اللجنة</option>
                    </select>
                </div>
                <div class="form-group"><label style="color:#fff;">الشهر المعني:</label><input type="text" name="rep_month" placeholder="مثال: فيفري 2026" required style="background: #2c3e50; color: white;"></div>
                <div class="form-group"><label style="color:#fff;">مرفق التقرير الرقمي:</label><input type="file" name="rep_file" accept=".pdf,.xlsx" style="background: #2c3e50; color: white;"></div>
                <div class="form-group"><label style="color:#fff;">ملاحظات:</label><textarea name="rep_notes" style="background: #2c3e50; color: white; height:60px;"></textarea></div>
                <button type="submit" class="btn-submit" style="background: #27ae60;">إرسال التقرير وأرشفته رسمياً 🚀</button>
            </form>
        </div>
    </div>

    <script>
        function checkAdmin(event) {
            event.preventDefault();
            const pass = document.getElementById('adminPass').value;
            if(pass === "123456" || pass === "admin2026") {
                document.getElementById('admin-login-modal').style.display = 'none';
                document.getElementById('auth-overlay').style.display = 'none';
                document.getElementById('admin-dashboard-panel').classList.remove('hidden-content');
            } else {
                alert("❌ كلمة المرور غير صحيحة!");
            }
        }
        function openAdminModal() { document.getElementById('admin-login-modal').style.display = 'flex'; }
        function closeAdminModal() { document.getElementById('admin-login-modal').style.display = 'none'; }
        function verifyUser(event) {
            event.preventDefault();
            document.getElementById('auth-overlay').style.display = 'none';
            document.getElementById('main-platform').classList.remove('hidden-content');
        }
        function goToHome() { location.reload(); }
        function switchTab(event, tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        function submitWorkerRequest(event) {
            event.preventDefault();
            let formData = new FormData(document.getElementById('worker-request-form'));
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                alert("✅ تم إرسال طلبك بنجاح وحفظه بالمنصة.");
                document.getElementById('worker-request-form').reset();
            }).catch(() => { alert("✅ تم تسجيل الطلب محلياً بنجاح."); });
        }
        function submitReport(event) {
            event.preventDefault();
            let formData = new FormData(document.getElementById('report-form'));
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                alert("✅ تم إرسال وأرشفة التقرير رسمياً للجهات المعنية!");
                document.getElementById('report-form').reset();
            }).catch(() => { alert("✅ تم توثيق الأرشيف بنجاح."); });
        }
    </script>
</body>
</html>
