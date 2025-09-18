<?php
session_start();

echo "<h1>🔍 LOGIN FLOW TEST</h1>";

echo "<h2>📊 CURRENT SESSION STATUS:</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Status:</strong> " . session_status() . "<br>";
echo "<strong>Session Data:</strong><br>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
echo "</div>";

echo "<h2>🧪 TEST LOGIN FLOW:</h2>";
echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "1. <strong>Go to login page:</strong> <a href='login.php' target='_blank'>login.php</a><br>";
echo "2. <strong>Enter admin credentials:</strong> admin / admin123<br>";
echo "3. <strong>Click Sign In</strong><br>";
echo "4. <strong>Should redirect to:</strong> Admin/admin_dashboard.php<br>";
echo "5. <strong>If it redirects back to login:</strong> There's an authentication issue";
echo "</div>";

echo "<h2>🔧 DEBUGGING STEPS:</h2>";
echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "If login redirects back to login page:<br>";
echo "1. Check if session variables are set correctly<br>";
echo "2. Verify authentication guard logic<br>";
echo "3. Check if redirect paths are correct<br>";
echo "4. Look for any PHP errors in browser console";
echo "</div>";

echo "<h2>🎯 EXPECTED BEHAVIOR:</h2>";
echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "✅ <strong>After successful login:</strong><br>";
echo "&nbsp;&nbsp;&nbsp;• \$_SESSION['admin_logged_in'] = true<br>";
echo "&nbsp;&nbsp;&nbsp;• \$_SESSION['admin_id'] = admin ID<br>";
echo "&nbsp;&nbsp;&nbsp;• \$_SESSION['admin_username'] = 'admin'<br>";
echo "&nbsp;&nbsp;&nbsp;• \$_SESSION['admin_name'] = admin name<br>";
echo "&nbsp;&nbsp;&nbsp;• Redirect to Admin/admin_dashboard.php";
echo "</div>";

echo "<h2>🚀 TEST NOW:</h2>";
echo "<p><a href='login.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block; font-size: 18px; font-weight: bold;'>🔐 TEST LOGIN</a></p>";

echo "<h2>📝 MANUAL SESSION TEST:</h2>";
echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>Set admin session manually:</strong><br>";
echo "<a href='?set_admin=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Set Admin Session</a><br>";
echo "<a href='?clear_session=1' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Clear Session</a>";
echo "</div>";

// Handle manual session setting
if (isset($_GET['set_admin'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_name'] = 'System Administrator';
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>✅ Admin session set manually!</div>";
    echo "<script>setTimeout(() => window.location.href = 'Admin/admin_dashboard.php', 1000);</script>";
}

if (isset($_GET['clear_session'])) {
    session_unset();
    session_destroy();
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>🗑️ Session cleared!</div>";
    echo "<script>setTimeout(() => window.location.reload(), 1000);</script>";
}
?>
