<?php

ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

session_start();
$_SESSION['login_attempt'] = [
    'count' => 0,
    'time' => 0
];

$mysqli = new mysqli("localhost", "root", "", "sqlinjectiongun");
if ($mysqli->connect_error) {
    error_log("Koneksi database gagal: " . $mysqli->connect_error);
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}


$max_attempts = 3;
$lockout_time = 900; 

if (!isset($_SESSION['login_attempt']) || !is_array($_SESSION['login_attempt'])) {
    $_SESSION['login_attempt'] = [
        'count' => 0,
        'time' => 0
    ];
}

// Cek jika user dikunci
if ($_SESSION['login_attempt']['count'] >= $max_attempts) {
    $time_since_last = time() - $_SESSION['login_attempt']['time'];
    if ($time_since_last < $lockout_time) {
        $remaining = $lockout_time - $time_since_last;
        die("Terlalu banyak percobaan. Coba lagi dalam " . gmdate("i:s", $remaining));
    } else {
        $_SESSION['login_attempt'] = [
            'count' => 0,
            'time' => 0
        ];
    }
}

// ======================
// PROSES LOGIN
// ======================
$error = "";
$recaptcha_secret = "6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe"; // Google test secret key

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validasi reCAPTCHA
        if (!isset($_POST['g-recaptcha-response'])) {
            throw new Exception("Verifikasi keamanan gagal!");
        }

        $recaptcha_response = $_POST['g-recaptcha-response'];
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $recaptcha_secret,
            'response' => $recaptcha_response
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ]
        ];
        $context = stream_context_create($options);
        $verify = file_get_contents($url, false, $context);
        $captcha_success = json_decode($verify);

        if (!$captcha_success || !$captcha_success->success) {
            throw new Exception("Verifikasi reCAPTCHA gagal!");
        }

        // Validasi input
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[a-z0-9_]{3,20}$/i', $username)) {
            throw new Exception("Username hanya boleh huruf/angka/underscore (3-20 karakter)");
        }

        if (strlen($password) < 8 || 
            !preg_match('/[A-Z]/', $password) || 
            !preg_match('/[0-9]/', $password)) {
            throw new Exception("Password minimal 8 karakter, termasuk huruf besar dan angka");
        }

        // Cek user di database
        $stmt = $mysqli->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Sukses login
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'),
                    'last_login' => time()
                ];
                $_SESSION['login_attempt'] = [
                    'count' => 0,
                    'time' => 0
                ];
                $stmt->close();
                header("Location: dashboard.php");
                exit();
            }
        }

        // Jika gagal login
        $_SESSION['login_attempt']['count']++;
        $_SESSION['login_attempt']['time'] = time();
        throw new Exception("Username atau password salah!");

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Aman</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://www.google.com/recaptcha/api.js?render=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></script>
    <style>
        body { font-family: Arial; background: #f4f4f4; display: flex; justify-content: center; padding-top: 60px; }
        .container { background: white; padding: 25px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 350px; border-radius: 10px; }
        h2 { text-align: center; }
        .form-group { margin-bottom: 15px; }
        input { width: 100%; padding: 10px; font-size: 14px; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; font-size: 16px; border-radius: 5px; }
        .error { background: #fdd; color: #a00; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .security-info { font-size: 12px; color: gray; margin-top: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2> Login Aman</h2>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="form-group">
                <input type="text" 
                       name="username" 
                       placeholder="Username"
                       required
                       pattern="[a-zA-Z0-9_]{3,20}"
                       title="3-20 karakter huruf, angka, atau underscore">
            </div>
            <div class="form-group">
                <input type="password" 
                       name="password" 
                       placeholder="Password"
                       required
                       minlength="8"
                       title="Minimal 8 karakter, dengan huruf besar dan angka">
            </div>

            <input type="hidden" name="g-recaptcha-response" id="recaptchaResponse">

            <button type="submit"
                    class="g-recaptcha"
                    data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"
                    data-callback="onSubmit"
                    data-action="submit">Login</button>

            <div class="security-info">
                <small>⏳ Timeout: 15 menit setelah 3 percobaan gagal</small>
            </div>
        </form>
    </div>

    <script>
        grecaptcha.ready(function() {
            grecaptcha.execute('6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI', {action: 'submit'}).then(function(token) {
                document.getElementById('recaptchaResponse').value = token;
            });
        });
        function onSubmit(token) {
            document.querySelector("form").submit();
        }
    </script>
</body>
</html>
<?php
$mysqli->close();
?>
