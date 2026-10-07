<?php
/* =========================================================
   STUDENT PORTAL — ONE FILE VERSION
   Put this file into: C:\xampp\htdocs\student-portal\index.php
   Then open: http://localhost/student-portal/
   ========================================================= */

session_start();

/* ---------- DATABASE ---------- */
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "erasyl";

$conn = new mysqli($host, $dbUser, $dbPass);

if ($conn->connect_error) {
    die("MySQL қатесі: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$conn->query("CREATE DATABASE IF NOT EXISTS `$dbName`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$conn->select_db($dbName);

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");

/* ---------- HELPERS ---------- */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function loggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!loggedIn()) {
        header("Location: ?page=login");
        exit;
    }
}

/* ---------- LOGOUT ---------- */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header("Location: ?page=login&logout=1");
    exit;
}

/* ---------- REGISTER ---------- */
$registerError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form"] ?? "") === "register") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $password2 = $_POST["password2"] ?? "";

    if ($name === "" || $email === "" || $username === "" || $password === "" || $password2 === "") {
        $registerError = "Барлық өрістерді толтырыңыз.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = "Email дұрыс енгізілмеген.";
    } elseif ($password !== $password2) {
        $registerError = "Парольдер сәйкес келмейді.";
    } elseif (strlen($password) < 6) {
        $registerError = "Пароль кемінде 6 таңбадан тұруы керек.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $registerError = "Бұл логин немесе Email бұрын тіркелген.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, username, password)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $name, $email, $username, $hash);

            if ($stmt->execute()) {
                header("Location: ?page=login&registered=1");
                exit;
            } else {
                $registerError = "Тіркелу кезінде қате пайда болды.";
            }
        }
    }
}

/* ---------- LOGIN ---------- */
$loginError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form"] ?? "") === "login") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, name, email, username, password
         FROM users WHERE username = ?"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["username"] = $user["username"];

            header("Location: ?page=dashboard");
            exit;
        }
    }

    $loginError = "Логин немесе пароль қате.";
}

/* ---------- PAGE ---------- */
$page = $_GET["page"] ?? "home";

$privatePages = ["dashboard", "profile", "protected"];

if (in_array($page, $privatePages, true)) {
    requireLogin();
}
?>
<!DOCTYPE html>
<html lang="kk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Portal</title>

<style>
* {
    box-sizing: border-box;
}

:root {
    --bg: #f5f7ff;
    --card: rgba(255,255,255,.92);
    --text: #172033;
    --muted: #6b7280;
    --primary: #5b5cf0;
    --primary2: #7c3aed;
    --success: #16a34a;
    --danger: #dc2626;
    --border: #e5e7eb;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: Inter, Arial, sans-serif;
    color: var(--text);
    background:
        radial-gradient(circle at 10% 10%, rgba(124,58,237,.14), transparent 30%),
        radial-gradient(circle at 90% 20%, rgba(91,92,240,.16), transparent 30%),
        var(--bg);
    min-height: 100vh;
}

a {
    text-decoration: none;
    color: inherit;
}

.navbar {
    position: sticky;
    top: 0;
    z-index: 20;
    backdrop-filter: blur(18px);
    background: rgba(255,255,255,.78);
    border-bottom: 1px solid rgba(229,231,235,.8);
}

.nav-inner {
    max-width: 1150px;
    margin: auto;
    padding: 16px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.logo {
    display: flex;
    align-items: center;
    gap: 11px;
    font-size: 20px;
    font-weight: 800;
}

.logo-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary2));
    box-shadow: 0 10px 25px rgba(91,92,240,.28);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
}

.nav-links a {
    padding: 10px 13px;
    border-radius: 10px;
    color: #4b5563;
    font-size: 14px;
    font-weight: 600;
}

.nav-links a:hover {
    background: #eef0ff;
    color: var(--primary);
}

.container {
    max-width: 1150px;
    margin: auto;
    padding: 55px 22px;
}

.hero {
    min-height: 590px;
    display: grid;
    grid-template-columns: 1.15fr .85fr;
    align-items: center;
    gap: 55px;
}

.badge {
    display: inline-flex;
    padding: 8px 13px;
    border-radius: 999px;
    background: #eeedff;
    color: var(--primary);
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 18px;
}

.hero h1 {
    font-size: clamp(42px, 6vw, 76px);
    line-height: .98;
    letter-spacing: -3px;
    margin: 0 0 24px;
}

.gradient-text {
    background: linear-gradient(135deg, var(--primary), var(--primary2));
    -webkit-background-clip: text;
    color: transparent;
}

.hero p {
    max-width: 620px;
    font-size: 18px;
    line-height: 1.7;
    color: var(--muted);
}

.buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 28px;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 13px 20px;
    border: 0;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 800;
    font-size: 14px;
    transition: .2s;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-primary {
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary2));
    box-shadow: 0 12px 25px rgba(91,92,240,.22);
}

.btn-light {
    background: white;
    border: 1px solid var(--border);
}

.btn-danger {
    color: white;
    background: var(--danger);
}

.visual-card {
    position: relative;
    padding: 28px;
    min-height: 420px;
    border-radius: 30px;
    background: linear-gradient(145deg, #ffffff, #eef0ff);
    box-shadow: 0 30px 80px rgba(35,40,90,.13);
    border: 1px solid rgba(255,255,255,.9);
}

.dashboard-preview {
    background: #171a2d;
    border-radius: 22px;
    padding: 23px;
    color: white;
    box-shadow: 0 25px 50px rgba(0,0,0,.2);
}

.preview-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #8b8fff;
    box-shadow: 0 0 20px #8b8fff;
}

.preview-number {
    font-size: 38px;
    font-weight: 800;
    margin: 20px 0 6px;
}

.preview-line {
    height: 9px;
    background: #2a2e4b;
    border-radius: 10px;
    margin: 15px 0;
}

.preview-line.short {
    width: 55%;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 20px;
}

.preview-box {
    padding: 20px;
    border-radius: 15px;
    background: #232742;
}

.section-title {
    text-align: center;
    margin-bottom: 35px;
}

.section-title h2 {
    font-size: 35px;
    margin: 0 0 10px;
}

.section-title p {
    color: var(--muted);
}

.features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.feature {
    padding: 27px;
    border-radius: 20px;
    background: var(--card);
    border: 1px solid var(--border);
    box-shadow: 0 12px 30px rgba(30,40,80,.05);
}

.feature-icon {
    font-size: 30px;
    margin-bottom: 15px;
}

.feature h3 {
    margin: 0 0 9px;
}

.feature p {
    color: var(--muted);
    line-height: 1.6;
}

.form-card {
    max-width: 530px;
    margin: 25px auto;
    padding: 35px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 24px;
    box-shadow: 0 25px 60px rgba(30,40,80,.08);
}

.form-card h2 {
    margin: 0 0 8px;
    font-size: 30px;
}

.form-subtitle {
    color: var(--muted);
    margin-bottom: 25px;
}

.field {
    margin-bottom: 17px;
}

.field label {
    display: block;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 7px;
}

.field input {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid var(--border);
    border-radius: 11px;
    outline: none;
    font-size: 15px;
    background: white;
}

.field input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(91,92,240,.1);
}

.form-card .btn {
    width: 100%;
    margin-top: 5px;
}

.alert {
    padding: 13px 15px;
    border-radius: 11px;
    margin-bottom: 18px;
    font-size: 14px;
    font-weight: 600;
}

.alert-error {
    color: #991b1b;
    background: #fee2e2;
}

.alert-success {
    color: #166534;
    background: #dcfce7;
}

.center {
    text-align: center;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.dashboard-header h1 {
    margin: 0;
    font-size: 36px;
}

.dashboard-header p {
    color: var(--muted);
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.info-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 19px;
    padding: 24px;
}

.info-label {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 8px;
}

.info-value {
    font-size: 20px;
    font-weight: 800;
    word-break: break-word;
}

.panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 22px;
    padding: 30px;
}

.panel h2 {
    margin-top: 0;
}

.profile-list {
    display: grid;
    gap: 12px;
}

.profile-row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 16px;
    border-radius: 12px;
    background: #f7f8ff;
}

.profile-row span:first-child {
    color: var(--muted);
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #166534;
    background: #dcfce7;
    padding: 9px 13px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 13px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--success);
}

.footer {
    margin-top: 70px;
    padding: 30px 20px;
    text-align: center;
    color: var(--muted);
    border-top: 1px solid var(--border);
}

@media (max-width: 850px) {
    .hero {
        grid-template-columns: 1fr;
        padding-top: 20px;
    }

    .features,
    .cards {
        grid-template-columns: 1fr;
    }

    .nav-inner {
        flex-direction: column;
    }

    .hero h1 {
        letter-spacing: -2px;
    }
}

@media (max-width: 550px) {
    .container {
        padding: 35px 15px;
    }

    .nav-links {
        justify-content: center;
    }

    .nav-links a {
        font-size: 12px;
        padding: 8px;
    }

    .hero {
        min-height: auto;
    }

    .visual-card {
        min-height: 320px;
        padding: 15px;
    }

    .form-card {
        padding: 23px;
    }

    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<!-- NAVIGATION -->
<header class="navbar">
    <div class="nav-inner">

        <a href="?page=home" class="logo">
            <span class="logo-icon">🎓</span>
            Student Portal
        </a>

        <nav class="nav-links">
            <a href="?page=home">Басты бет</a>

            <?php if (loggedIn()): ?>
                <a href="?page=dashboard">Кабинет</a>
                <a href="?page=profile">Профиль</a>
                <a href="?page=protected">Қорғалған бет</a>
                <a href="?action=logout">Шығу</a>
            <?php else: ?>
                <a href="?page=register">Тіркелу</a>
                <a href="?page=login">Кіру</a>
            <?php endif; ?>
        </nav>

    </div>
</header>

<main class="container">

<?php if ($page === "home"): ?>

    <!-- HOME -->
    <section class="hero">

        <div>
            <span class="badge">✨ STUDENT WEB PORTAL</span>

            <h1>
                Оқуға арналған
                <span class="gradient-text">заманауи портал</span>
            </h1>

            <p>
                Student Portal — пайдаланушыны тіркеу,
                авторизациялау және PHP сессияларын басқаруға
                арналған оқу web-жобасы.
            </p>

            <div class="buttons">
                <?php if (!loggedIn()): ?>
                    <a href="?page=register" class="btn btn-primary">
                        Тіркелу →
                    </a>
                    <a href="?page=login" class="btn btn-light">
                        Жүйеге кіру
                    </a>
                <?php else: ?>
                    <a href="?page=dashboard" class="btn btn-primary">
                        Жеке кабинет →
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="visual-card">

            <div class="dashboard-preview">

                <div class="preview-top">
                    <strong>Student Portal</strong>
                    <span class="dot"></span>
                </div>

                <small>Сіздің оқу кабинеті</small>

                <div class="preview-number">100%</div>
                <small>Сессия белсенді</small>

                <div class="preview-line"></div>
                <div class="preview-line short"></div>

                <div class="preview-grid">
                    <div class="preview-box">👤<br><small>Профиль</small></div>
                    <div class="preview-box">🔐<br><small>Қауіпсіздік</small></div>
                </div>

            </div>

        </div>

    </section>

    <section>
        <div class="section-title">
            <h2>Жоба мүмкіндіктері</h2>
            <p>Практикалық тапсырмадағы негізгі функциялар</p>
        </div>

        <div class="features">

            <div class="feature">
                <div class="feature-icon">👤</div>
                <h3>Тіркелу</h3>
                <p>Жаңа пайдаланушының аты-жөні, Email, логині және паролі MySQL базасына сақталады.</p>
            </div>

            <div class="feature">
                <div class="feature-icon">🔐</div>
                <h3>Авторизация</h3>
                <p>Логин мен пароль тексеріліп, дұрыс болған жағдайда пайдаланушының сессиясы басталады.</p>
            </div>

            <div class="feature">
                <div class="feature-icon">🛡️</div>
                <h3>Қорғалған беттер</h3>
                <p>Кабинетке, профильге және қорғалған ақпаратқа тек авторизацияланған пайдаланушы кіреді.</p>
            </div>

        </div>
    </section>

<?php elseif ($page === "register"): ?>

    <!-- REGISTER -->
    <div class="form-card">

        <h2>Аккаунт жасау</h2>
        <p class="form-subtitle">Student Portal жүйесіне тіркеліңіз.</p>

        <?php if ($registerError): ?>
            <div class="alert alert-error"><?= e($registerError) ?></div>
        <?php endif; ?>

        <form method="POST">

            <input type="hidden" name="form" value="register">

            <div class="field">
                <label>Аты-жөні</label>
                <input type="text" name="name" placeholder="Мысалы: Аружан Қалила" required>
            </div>

            <div class="field">
                <label>Email</label>
                <input type="email" name="email" placeholder="example@mail.com" required>
            </div>

            <div class="field">
                <label>Логин</label>
                <input type="text" name="username" placeholder="student123" required>
            </div>

            <div class="field">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="Кемінде 6 таңба" required>
            </div>

            <div class="field">
                <label>Парольді қайталау</label>
                <input type="password" name="password2" placeholder="Парольді қайта енгізіңіз" required>
            </div>

            <button class="btn btn-primary" type="submit">
                Тіркелу
            </button>

        </form>

        <p class="center">
            Аккаунтыңыз бар ма?
            <a href="?page=login" style="color:#5b5cf0;font-weight:800;"> Кіру</a>
        </p>

    </div>

<?php elseif ($page === "login"): ?>

    <!-- LOGIN -->
    <div class="form-card">

        <h2>Қош келдіңіз 👋</h2>
        <p class="form-subtitle">Аккаунтыңызға кіріңіз.</p>

        <?php if (isset($_GET["registered"])): ?>
            <div class="alert alert-success">
                Тіркелу сәтті аяқталды. Енді жүйеге кіріңіз.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["logout"])): ?>
            <div class="alert alert-success">
                Сіз жүйеден сәтті шықтыңыз.
            </div>
        <?php endif; ?>

        <?php if ($loginError): ?>
            <div class="alert alert-error"><?= e($loginError) ?></div>
        <?php endif; ?>

        <form method="POST">

            <input type="hidden" name="form" value="login">

            <div class="field">
                <label>Логин</label>
                <input type="text" name="username" placeholder="Логиніңіз" required>
            </div>

            <div class="field">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="Парольіңіз" required>
            </div>

            <button class="btn btn-primary" type="submit">
                Кіру
            </button>

        </form>

        <p class="center">
            Аккаунтыңыз жоқ па?
            <a href="?page=register" style="color:#5b5cf0;font-weight:800;"> Тіркелу</a>
        </p>

    </div>

<?php elseif ($page === "dashboard"): ?>

    <!-- DASHBOARD -->
    <div class="dashboard-header">
        <div>
            <h1>Жеке кабинет</h1>
            <p>Қош келдіңіз, <?= e($_SESSION["name"]) ?>!</p>
        </div>

        <a href="?action=logout" class="btn btn-danger">Шығу</a>
    </div>

    <div class="cards">

        <div class="info-card">
            <div class="info-label">Аты-жөні</div>
            <div class="info-value"><?= e($_SESSION["name"]) ?></div>
        </div>

        <div class="info-card">
            <div class="info-label">Логин</div>
            <div class="info-value"><?= e($_SESSION["username"]) ?></div>
        </div>

        <div class="info-card">
            <div class="info-label">Email</div>
            <div class="info-value"><?= e($_SESSION["email"]) ?></div>
        </div>

    </div>

    <div class="panel">

        <h2>Басқару</h2>

        <p style="color:#6b7280;">
            Төмендегі бөлімдер арқылы профиліңізді және сессияны тексеріңіз.
        </p>

        <div class="buttons">
            <a href="?page=profile" class="btn btn-primary">👤 Профиль</a>
            <a href="?page=protected" class="btn btn-light">🛡️ Қорғалған бет</a>
        </div>

    </div>

<?php elseif ($page === "profile"): ?>

    <!-- PROFILE -->
    <div class="panel">

        <div class="dashboard-header">
            <div>
                <h1>Профиль</h1>
                <p>Сіздің жеке мәліметтеріңіз</p>
            </div>

            <span class="status">
                <span class="status-dot"></span>
                Онлайн
            </span>
        </div>

        <div class="profile-list">

            <div class="profile-row">
                <span>Пайдаланушы ID</span>
                <strong><?= e($_SESSION["user_id"]) ?></strong>
            </div>

            <div class="profile-row">
                <span>Аты-жөні</span>
                <strong><?= e($_SESSION["name"]) ?></strong>
            </div>

            <div class="profile-row">
                <span>Email</span>
                <strong><?= e($_SESSION["email"]) ?></strong>
            </div>

            <div class="profile-row">
                <span>Логин</span>
                <strong><?= e($_SESSION["username"]) ?></strong>
            </div>

        </div>

        <div class="buttons">
            <a href="?page=dashboard" class="btn btn-primary">← Кабинетке оралу</a>
        </div>

    </div>

<?php elseif ($page === "protected"): ?>

    <!-- PROTECTED -->
    <div class="panel center">

        <div style="font-size:65px;">🛡️</div>

        <h1>Қорғалған ақпарат</h1>

        <p style="font-size:18px;color:#6b7280;">
            Пайдаланушы:
            <strong><?= e($_SESSION["name"]) ?></strong>
        </p>

        <div class="status">
            <span class="status-dot"></span>
            Сіздің жеке сессияңыз белсенді
        </div>

        <p style="color:#6b7280;margin-top:25px;">
            Бұл бетке тек авторизациядан өткен пайдаланушы ғана кіре алады.
        </p>

        <div class="buttons" style="justify-content:center;">
            <a href="?page=dashboard" class="btn btn-primary">← Кабинетке оралу</a>
        </div>

    </div>

<?php else: ?>

    <div class="panel center">
        <h1>404</h1>
        <p>Бет табылмады.</p>
        <a href="?page=home" class="btn btn-primary">Басты бетке</a>
    </div>

<?php endif; ?>

</main>

<footer class="footer">
    <strong>Student Portal</strong>
    <br>
    PHP + MySQL + Sessions
    <br><br>
    Практикалық жұмыс 2.2.79
</footer>

</body>
</html>
