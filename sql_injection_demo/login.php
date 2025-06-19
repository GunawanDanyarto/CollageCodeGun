<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "sqlinjectiongun");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        $error = "Username is required!";
    } else {
        $username = preg_replace('/--$/', '-- ', $username);
        
        try {
            $query = "SELECT * FROM users WHERE username = '$username'";
            echo "<div style='background:#f1f1f1;padding:10px;margin:20px;'>";
            echo "<strong>Generated SQL:</strong> " . htmlspecialchars($query);
            echo "</div>";

            $result = $mysqli->query($query);
            
            if ($result && $result->num_rows > 0) {
                $_SESSION['username'] = $username;
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password!";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SQL Injection Demo</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { background: #28a745; color: white; padding: 14px 20px; border: none; border-radius: 5px; width: 100%; cursor: pointer; font-size: 16px; }
        .error { color: #dc3545; background: #f8d7da; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .debug-info { color: #6c757d; font-size: 0.9em; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Vulnerable Login</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" 
                   name="username" 
                   placeholder="Username" 
                   required
                   autocomplete="off"
                   autocorrect="off"
                   autocapitalize="none">
            
            <input type="password" 
                   name="password" 
                   placeholder="Password (optional)"
                   autocomplete="off">
            
            <button type="submit">Login</button>
        </form>

        <div class="debug-info">
            <p>Try these payloads:</p>
            <ul>
                <li><code>' OR 1=1 -- </code> (with space after --)</li>
                <li><code>admin' -- </code></li>
                <li><code>' UNION SELECT 1,2,3 -- </code></li>
            </ul>
        </div>
    </div>
</body>
</html>