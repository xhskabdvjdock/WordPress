<?php
session_start();
$db = new SQLite3('grades.db');

// إنشاء الجداول إذا لم تكن موجودة
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT
    )");

$db->exec("
    CREATE TABLE IF NOT EXISTS grades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        student_name TEXT,
        subjects TEXT,
        total INTEGER,
        average FLOAT,
        grade TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

// تسجيل الدخول
if (isset($_POST['login'])) {
    $username = SQLite3::escapeString($_POST['username']);
    $password = $_POST['password'];
    
    $result = $db->querySingle("SELECT * FROM users WHERE username = '$username'", true);
    
    if ($result && password_verify($password, $result['password'])) {
        $_SESSION['user_id'] = $result['id'];
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "بيانات الدخول غير صحيحة!";
    }
}

// تسجيل الخروج
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// حساب العلامات
if (isset($_POST['calculate']) && isset($_SESSION['user_id'])) {
    $student_name = SQLite3::escapeString($_POST['student_name']);
    $marks = $_POST['marks'];
    
    $total = array_sum($marks);
    $average = $total / count($marks);
    $grade = ($average >= 90) ? 'A' : 
            ($average >= 80) ? 'B' :
            ($average >= 70) ? 'C' :
            ($average >= 60) ? 'D' : 'F';
    
    $subjects = json_encode($marks);
    
    $db->exec("INSERT INTO grades (user_id, student_name, subjects, total, average, grade)
              VALUES ({$_SESSION['user_id']}, '$student_name', '$subjects', $total, $average, '$grade')");
}

// استرجاع السجلات
$records = [];
if (isset($_SESSION['user_id'])) {
    $result = $db->query("SELECT * FROM grades WHERE user_id = {$_SESSION['user_id']} ORDER BY timestamp DESC");
    while ($row = $result->fetchArray()) {
        $records[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>نظام حساب العلامات</title>
    <style>
        body {font-family: Arial, sans-serif; max-width: 800px; margin: auto; padding: 20px;}
        .box {border: 1px solid #ccc; padding: 20px; margin: 20px 0;}
        table {width: 100%; border-collapse: collapse;}
        td, th {border: 1px solid #ddd; padding: 8px;}
    </style>
</head>
<body>

<?php if (!isset($_SESSION['user_id'])): ?>
    <!-- نموذج تسجيل الدخول -->
    <div class="box">
        <h2>تسجيل الدخول</h2>
        <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        <form method="post">
            <input type="text" name="username" placeholder="اسم المستخدم" required>
            <input type="password" name="password" placeholder="كلمة المرور" required>
            <button type="submit" name="login">دخول</button>
        </form>
        <p>حساب افتراضي: admin / 1234</p>
    </div>

<?php else: ?>
    <!-- واجهة المستخدم -->
    <a href="?logout" style="float:left;">تسجيل الخروج</a>
    <h1>نظام حساب العلامات</h1>
    
    <!-- نموذح إدخال البيانات -->
    <div class="box">
        <form method="post">
            <input type="text" name="student_name" placeholder="اسم الطالب" required>
            <h3>إدخال العلامات:</h3>
            <?php
            $subjects = ['اللغة العربية', 'الرياضيات', 'العلوم', 'اللغة الإنجليزية'];
            foreach ($subjects as $subject) {
                echo "<p>$subject: <input type='number' name='marks[]' min='0' max='100' required></p>";
            }
            ?>
            <button type="submit" name="calculate">حساب النتائج</button>
        </form>
    </div>

    <!-- عرض النتائج -->
    <?php if (!empty($records)): ?>
    <div class="box">
        <h2>السجلات السابقة</h2>
        <table>
            <tr>
                <th>اسم الطالب</th>
                <th>المواد</th>
                <th>المجموع</th>
                <th>المعدل</th>
                <th>الدرجة</th>
                <th>التاريخ</th>
            </tr>
            <?php foreach ($records as $record): ?>
            <tr>
                <td><?= $record['student_name'] ?></td>
                <td>
                    <?php 
                    $marks = json_decode($record['subjects'], true);
                    foreach ($marks as $key => $value) {
                        echo $subjects[$key].": $value<br>";
                    }
                    ?>
                </td>
                <td><?= $record['total'] ?></td>
                <td><?= number_format($record['average'], 2) ?></td>
                <td><?= $record['grade'] ?></td>
                <td><?= $record['timestamp'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php
// إنشاء مستخدم افتراضي إذا لم يكن موجود
$check = $db->querySingle("SELECT COUNT(*) FROM users");
if ($check == 0) {
    $password = password_hash('1234', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password) VALUES ('admin', '$password')");
}
?>
</body>
</html>
