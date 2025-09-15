<?php
// verify_code.php
$error = '';
$email = $_GET['email'] ?? '';
$type = $_GET['type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $type = $_POST['type'] ?? '';
    $code = $_POST['code'] ?? '';
    $conn = new mysqli("localhost", "root", "", "compre_learn");
    if ($conn->connect_error) {
        $error = 'Database connection failed.';
    } else {
        $stmt = $conn->prepare("SELECT token FROM password_resets WHERE email = ? AND user_type = ? AND code = ? AND expires_at > NOW() AND used = 0 ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("sss", $email, $type, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $token = $row['token'];
            header("Location: reset_password.php?token=$token&type=$type");
            exit();
        } else {
            $error = 'Invalid or expired verification code.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Code</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; height: 100vh; width: 100vw; overflow: hidden; background-color: #f0f2f5; }
        .left-panel { width: 55%; height: 100%; background: url('assets/images/login.jpg') no-repeat center center; background-size: cover; }
        .right-panel { width: 45%; height: 100%; background: linear-gradient(to bottom right,rgb(5, 143, 255),rgb(64, 223, 143)); display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 2rem; box-sizing: border-box; }
        .verify-code-container { width: 100%; max-width: 350px; }
        .logo { margin-bottom: 2rem; text-align: center; }
        .logo-image { max-width: 120px; height: auto; display: block; margin: 0 auto; }
        h2 { font-size: 1.2rem; font-weight: normal; margin-bottom: 1rem; color: #f1f1f1; }
        .verify-code-container input[type="text"] { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; background-color: #f1f1f1; color: #333; }
        .verify-code-container input[type="submit"] { width: 100%; padding: 12px; margin: 8px 0; font-size: 1em; color: white; background-color: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 8px; cursor: pointer; transition: background-color 0.3s, border-color 0.3s, transform 0.3s; font-weight: 500; }
        .verify-code-container input[type="submit"]:hover { background-color: rgba(255, 255, 255, 0.3); border-color: white; transform: translateY(-2px); }
        .error-message { color: #ffdddd; background-color: rgba(231, 76, 60, 0.7); padding: 10px; border-radius: 5px; margin-top: 15px; }
        a { color: #007bff; display: block; margin: 10px 0 0 0; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="verify-code-container fade-in">
            <div class="logo">
                <img src="assets/images/comprelogo.png" alt="Compre Learn Logo" class="logo-image">
            </div>
            <h2>Enter Verification Code</h2>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="text" name="code" placeholder="Enter the 6-digit code" required maxlength="6">
                <input type="submit" value="Verify Code">
            </form>
            <a href="Student/student_forgot_password.php">Back to Forgot Password</a>
        </div>
    </div>
</body>
</html> 