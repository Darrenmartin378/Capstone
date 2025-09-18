<?php
session_start();

echo "<h1>🔍 COMPREHENSIVE LOGIN DEBUG</h1>";

// Test the exact login flow
if (isset($_POST['test_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "<h2>🧪 TESTING LOGIN FLOW:</h2>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Username:</strong> $username<br>";
    echo "<strong>Password:</strong> $password<br>";
    echo "</div>";
    
    if (empty($username) || empty($password)) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>❌ Empty username or password</div>";
    } else {
        try {
            // Database connection
            $conn = new mysqli("localhost", "root", "", "compre_learn");
            if ($conn->connect_error) {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>❌ Database connection failed: " . $conn->connect_error . "</div>";
            } else {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>✅ Database connected successfully</div>";
                
                // Check Admin credentials (exact same code as login.php)
                $stmt = $conn->prepare("SELECT id, username, password_hash, full_name FROM admins WHERE username = ? AND is_active = 1");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($admin = $result->fetch_assoc()) {
                    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>✅ Admin found in database</div>";
                    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                    echo "<strong>Admin ID:</strong> " . $admin['id'] . "<br>";
                    echo "<strong>Username:</strong> " . $admin['username'] . "<br>";
                    echo "<strong>Full Name:</strong> " . $admin['full_name'] . "<br>";
                    echo "<strong>Password Hash:</strong> " . substr($admin['password_hash'], 0, 20) . "...<br>";
                    echo "</div>";
                    
                    // Test password verification
                    if (password_verify($password, $admin['password_hash'])) {
                        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>✅ Password verification successful</div>";
                        
                        // Set session variables (exact same as login.php)
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_name'] = $admin['full_name'];
                        session_regenerate_id(true);
                        
                        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>✅ Session variables set</div>";
                        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                        echo "<strong>Session Data:</strong><br>";
                        echo "<pre>" . print_r($_SESSION, true) . "</pre>";
                        echo "</div>";
                        
                        // Test authentication guard
                        echo "<h3>🔒 TESTING AUTHENTICATION GUARD:</h3>";
                        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
                            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>✅ Authentication guard would pass</div>";
                            
                            echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                            echo "🎯 <strong>Login successful! Ready to redirect to admin dashboard!</strong><br>";
                            echo "<a href='Admin/admin_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Go to Admin Dashboard</a>";
                            echo "</div>";
                        } else {
                            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>❌ Authentication guard would fail</div>";
                        }
                        
                    } else {
                        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>❌ Password verification failed</div>";
                    }
                } else {
                    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>❌ Admin not found in database</div>";
                }
            }
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Test direct admin dashboard access
if (isset($_GET['test_dashboard'])) {
    echo "<h2>🎯 TESTING DIRECT DASHBOARD ACCESS:</h2>";
    
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0;'>✅ Session is valid, redirecting to dashboard...</div>";
        echo "<script>setTimeout(() => window.location.href = 'Admin/admin_dashboard.php', 1000);</script>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>❌ No valid session, would redirect to login</div>";
    }
}

echo "<h2>🧪 TEST LOGIN:</h2>";
echo "<form method='POST'>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 10px 0;'>";
echo "<label><strong>Username:</strong></label><br>";
echo "<input type='text' name='username' value='admin' style='width: 100%; padding: 10px; margin: 5px 0; border: 1px solid #ccc; border-radius: 5px;'><br><br>";
echo "<label><strong>Password:</strong></label><br>";
echo "<input type='password' name='password' value='admin123' style='width: 100%; padding: 10px; margin: 5px 0; border: 1px solid #ccc; border-radius: 5px;'><br><br>";
echo "<button type='submit' name='test_login' style='background: #007bff; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;'>Test Login</button>";
echo "</div>";
echo "</form>";

echo "<h2>🔍 CURRENT SESSION:</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Status:</strong> " . session_status() . "<br>";
echo "<strong>Session Data:</strong><br>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
echo "</div>";

echo "<h2>🎯 QUICK TESTS:</h2>";
echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<a href='?test_dashboard=1' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Test Dashboard Access</a>";
echo "<a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Go to Login Page</a>";
echo "<a href='Admin/admin_dashboard.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Direct Dashboard</a>";
echo "<a href='?clear=1' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Clear Session</a>";
echo "</div>";

// Clear session
if (isset($_GET['clear'])) {
    session_unset();
    session_destroy();
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0;'>🗑️ Session cleared!</div>";
    echo "<script>setTimeout(() => window.location.reload(), 1000);</script>";
}
?>
