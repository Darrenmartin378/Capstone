<?php
require_once 'includes/student_init.php';

$pageTitle = 'Dashboard';

// Handle success message from login
$success_message = '';
if (isset($_GET['login']) && $_GET['login'] == 'success') {
    $success_message = 'Welcome back! You have successfully logged in.';
}

// Get counts for dashboard stats
$materialsCount = 0;
$materialsRes = $conn->query("SELECT COUNT(*) as count FROM reading_materials");
if ($materialsRes && $row = $materialsRes->fetch_assoc()) {
    $materialsCount = (int)$row['count'];
}

$questionsCount = 0;
$questionsRes = $conn->query("
    SELECT COUNT(DISTINCT qs.id) as count 
    FROM question_sets qs
    WHERE qs.section_id = $studentSectionId
    AND qs.set_title IS NOT NULL 
    AND qs.set_title != ''
");
if ($questionsRes && $row = $questionsRes->fetch_assoc()) {
    $questionsCount = (int)$row['count'];
}

$notificationsCount = 0;
$notificationsRes = $conn->query("SELECT COUNT(*) as count FROM announcements");
if ($notificationsRes && $row = $notificationsRes->fetch_assoc()) {
    $notificationsCount = (int)$row['count'];
}

ob_start();
?>
<style>

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Welcome Section */
    .welcome-section {
        background: #ffffff;
        color: #1e293b;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        text-align: center;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
    }

    .welcome-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #2563eb, #3b82f6, #2563eb);
    }

    .welcome-section h1 {
        margin: 0 0 10px 0;
        font-size: 32px;
        font-weight: 800;
        color: #1e293b;
    }

    .welcome-section p {
        margin: 0;
        color: #64748b;
        font-size: 16px;
    }

    /* Widgets Grid */
    .widgets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .widget {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .widget::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #2563eb, #3b82f6, #2563eb);
    }

    .widget:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border-color: #2563eb;
    }

    .widget-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .widget-icon {
        font-size: 24px;
        color: #2563eb;
    }

    .widget-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
    }

    /* Weather Widget */
    .weather-content {
        text-align: center;
    }

    .current-time {
        font-size: 28px;
        font-weight: bold;
        color: #2563eb;
        margin: 10px 0;
    }

    .weather-temp {
        font-size: 32px;
        font-weight: bold;
        color: #059669;
        margin: 10px 0;
    }

    .weather-desc {
        color: #64748b;
        font-size: 16px;
        margin: 5px 0;
        text-transform: capitalize;
    }

    .weather-location {
        color: #94a3b8;
        font-size: 14px;
    }

    /* Stats Widget */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .stat-item {
        text-align: center;
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #2563eb;
    }

    .stat-label {
        color: #64748b;
        font-size: 12px;
        margin-top: 5px;
    }

    /* Quick Actions Widget */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .quick-action-btn {
        background: #2563eb;
        color: white;
        border: 1px solid #1d4ed8;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        text-align: center;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        font-size: 14px;
    }

    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        background: #1d4ed8;
    }

    /* Calendar Widget */
    .calendar-content {
        text-align: center;
    }

    .current-date {
        font-size: 24px;
        font-weight: bold;
        color: #2563eb;
        margin-bottom: 10px;
    }

    .current-day {
        font-size: 18px;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .current-month-year {
        color: #64748b;
        font-size: 14px;
    }

    /* Recent Activity Widget */
    .activity-list {
        max-height: 200px;
        overflow-y: auto;
    }

    .activity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        font-size: 16px;
        width: 24px;
        text-align: center;
    }

    .activity-content {
        flex: 1;
    }

    .activity-title {
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 4px 0;
        font-size: 14px;
    }

    .activity-time {
        color: #64748b;
        font-size: 12px;
        margin: 0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .widgets-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }
        
        .dashboard-container {
            padding: 15px;
        }
    }

    /* Custom scrollbar for activity list */
    .activity-list::-webkit-scrollbar {
        width: 6px;
    }

    .activity-list::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }

    .activity-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }

    .activity-list::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<?php if ($success_message): ?>
<div class="success-message" style="background: rgba(212, 237, 218, 0.9); color: #155724; border: 1px solid #c3e6cb; padding: 12px 16px; border-radius: 8px; margin: 0 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); animation: fadeIn 0.7s; backdrop-filter: blur(10px);">
    <?php echo h($success_message); ?>
</div>
<?php endif; ?>

<div class="dashboard-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1>Welcome back, <?php echo h($studentName); ?>! 👋</h1>
        <p>Ready to continue your learning journey? Let's make today productive!</p>
    </div>

    <!-- Widgets Grid -->
    <div class="widgets-grid">
        <!-- Weather & Time Widget -->
        <div class="widget">
            <div class="widget-header">
                <span class="widget-icon" id="weatherIcon">🌤️</span>
                <span class="widget-title">Weather & Time</span>
            </div>
            <div class="weather-content">
                <div class="current-time" id="currentTime">--:--</div>
                <div class="weather-temp" id="weatherTemp">--°C</div>
                <div class="weather-desc" id="weatherDesc">Loading...</div>
                <div class="weather-location">Manila, Philippines</div>
            </div>
        </div>

        <!-- Learning Stats Widget -->
        <div class="widget">
            <div class="widget-header">
                <span class="widget-icon">📊</span>
                <span class="widget-title">Learning Stats</span>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $materialsCount; ?></div>
                    <div class="stat-label">Materials</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $questionsCount; ?></div>
                    <div class="stat-label">Questions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $notificationsCount; ?></div>
                    <div class="stat-label">Notifications</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="completedQuizzes">0</div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>

        <!-- Calendar Widget -->
        <div class="widget">
            <div class="widget-header">
                <span class="widget-icon">📅</span>
                <span class="widget-title">Today</span>
            </div>
            <div class="calendar-content">
                <div class="current-date" id="currentDate">--</div>
                <div class="current-day" id="currentDay">--</div>
                <div class="current-month-year" id="currentMonthYear">--</div>
            </div>
        </div>

        <!-- Quick Actions Widget -->
        <div class="widget">
            <div class="widget-header">
                <span class="widget-icon">⚡</span>
                <span class="widget-title">Quick Actions</span>
            </div>
            <div class="quick-actions">
                <a href="student_materials.php" class="quick-action-btn">
                    📚 Materials
                </a>
                <a href="clean_question_viewer.php" class="quick-action-btn">
                    ❓ Questions
                </a>
                <a href="student_announcements.php" class="quick-action-btn">
                    📢 Announcements
                </a>
                <a href="student_analytics.php" class="quick-action-btn">
                    📈 Analytics
                </a>
            </div>
        </div>

        <!-- Recent Activity Widget -->
        <div class="widget">
            <div class="widget-header">
                <span class="widget-icon">🕒</span>
                <span class="widget-title">Recent Activity</span>
            </div>
            <div class="activity-list">
                <?php
                // Get recent announcements
                $recentAnnouncements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
                if ($recentAnnouncements && $recentAnnouncements->num_rows > 0):
                    while ($announcement = $recentAnnouncements->fetch_assoc()):
                ?>
                    <div class="activity-item">
                        <div class="activity-icon">📢</div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo h($announcement['title']); ?></div>
                            <div class="activity-time"><?php echo h(date('M j, Y g:ia', strtotime($announcement['created_at']))); ?></div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <div class="activity-item">
                        <div class="activity-icon">💡</div>
                        <div class="activity-content">
                            <div class="activity-title">Welcome to CompreLearn!</div>
                            <div class="activity-time">Start exploring your learning materials</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Performance Widget -->
        <div class="widget">
            <div class="widget-header">
                <span class="widget-icon">🎯</span>
                <span class="widget-title">Performance</span>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" id="averageScore">--%</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="streak">0</div>
                    <div class="stat-label">Day Streak</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate widgets on load
    const widgets = document.querySelectorAll('.widget');
    widgets.forEach((widget, index) => {
        widget.style.opacity = '0';
        widget.style.transform = 'translateY(30px)';
        setTimeout(() => {
            widget.style.transition = 'all 0.6s ease';
            widget.style.opacity = '1';
            widget.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Update time every second
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour12: true, 
            hour: 'numeric', 
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('currentTime').textContent = timeString;
    }

    // Update date
    function updateDate() {
        const now = new Date();
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
        
        document.getElementById('currentDate').textContent = now.getDate();
        document.getElementById('currentDay').textContent = days[now.getDay()];
        document.getElementById('currentMonthYear').textContent = `${months[now.getMonth()]} ${now.getFullYear()}`;
    }

    // Fetch weather data
    async function fetchWeather() {
        try {
            // Mock weather data for demo (you can replace with real API)
            const mockWeather = {
                main: { temp: Math.floor(Math.random() * 8) + 26 }, // 26-34°C
                weather: [{ 
                    description: ['sunny', 'partly cloudy', 'cloudy', 'light rain'][Math.floor(Math.random() * 4)],
                    main: ['Clear', 'Clouds', 'Clouds', 'Rain'][Math.floor(Math.random() * 4)]
                }]
            };
            updateWeatherDisplay(mockWeather);
        } catch (error) {
            console.log('Weather fetch error:', error);
        }
    }

    function updateWeatherDisplay(data) {
        const temp = Math.round(data.main.temp);
        const desc = data.weather[0].description;
        
        document.getElementById('weatherTemp').textContent = `${temp}°C`;
        document.getElementById('weatherDesc').textContent = desc;
        
        // Update weather icon based on description
        const weatherIcon = document.getElementById('weatherIcon');
        const descLower = desc.toLowerCase();
        if (descLower.includes('sun') || descLower.includes('clear')) {
            weatherIcon.textContent = '☀️';
        } else if (descLower.includes('cloud')) {
            weatherIcon.textContent = '☁️';
        } else if (descLower.includes('rain')) {
            weatherIcon.textContent = '🌧️';
        } else {
            weatherIcon.textContent = '🌤️';
        }
    }

    // Initialize
    updateTime();
    updateDate();
    fetchWeather();
    
    // Update time every second
    setInterval(updateTime, 1000);
    
    // Update weather every 10 minutes
    setInterval(fetchWeather, 600000);

    // Simulate some performance data
    setTimeout(() => {
        document.getElementById('averageScore').textContent = '85%';
        document.getElementById('streak').textContent = '7';
        document.getElementById('completedQuizzes').textContent = '12';
    }, 1000);
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>
