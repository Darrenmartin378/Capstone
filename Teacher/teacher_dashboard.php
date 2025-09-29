<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';
render_teacher_header('teacher_dashboard.php', $_SESSION['teacher_name'] ?? 'Teacher');

// Handle success message from login
$success_message = '';
if (isset($_GET['login']) && $_GET['login'] == 'success') {
    $success_message = 'Welcome back! You have successfully logged in.';
}

// Get teacher ID from session
$teacherId = $_SESSION['teacher_id'] ?? 0;

// Materials
$result = $conn->query("SELECT COUNT(*) AS total FROM reading_materials WHERE teacher_id = $teacherId");
$row = $result->fetch_assoc();
$materialsCount = (int)$row['total'];

// Questions (count distinct set titles)
$qResult = $conn->query("SELECT COUNT(DISTINCT set_title) AS total FROM question_bank WHERE teacher_id = $teacherId");
$qRow = $qResult->fetch_assoc();
$questionsCount = (int)$qRow['total'];

// Assessments
$aResult = $conn->query("SELECT COUNT(*) AS total FROM assessments WHERE teacher_id = $teacherId");
$aRow = $aResult->fetch_assoc();
$assessmentsCount = (int)$aRow['total'];

// Assignments (count assigned assessments)
$asResult = $conn->query("SELECT COUNT(*) AS total FROM assessment_assignments WHERE assessment_id IN (SELECT id FROM assessments WHERE teacher_id = $teacherId)");
$asRow = $asResult->fetch_assoc();
$assignmentsCount = (int)$asRow['total'];

// Practice Sets (Warm-ups)
$ptResult = $conn->query("SELECT COUNT(*) AS total FROM warm_ups WHERE teacher_id = $teacherId");
$ptRow = $ptResult->fetch_assoc();
$practiceSetsCount = (int)$ptRow['total'];
?>

<style>
/* Enhanced Dashboard Layout */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 32px;
    margin-bottom: 40px;
    padding: 20px 0;
}

.card {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 24px;
    box-shadow: 
        0 10px 40px rgba(79, 70, 229, 0.08),
        0 4px 16px rgba(0, 0, 0, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
    padding: 36px 28px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(79, 70, 229, 0.06);
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.card:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 
        0 20px 60px rgba(79, 70, 229, 0.15),
        0 8px 24px rgba(0, 0, 0, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.card:hover::before {
    opacity: 1;
}
/* Enhanced Card Elements */
.card .icon {
    font-size: 3rem;
    color: #6366f1;
    margin-bottom: 16px;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

.card:hover .icon {
    transform: scale(1.1) rotate(5deg);
    color: #4f46e5;
}

.card > div:nth-child(2) {
    font-size: 1.2rem;
    color: #374151;
    margin-bottom: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    position: relative;
    z-index: 2;
}

.card .count {
    font-size: 2.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 24px;
    position: relative;
    z-index: 2;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card .btn {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    border: none;
    border-radius: 16px;
    padding: 14px 28px;
    font-weight: 600;
    margin-top: 14px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
    letter-spacing: 0.5px;
    position: relative;
    z-index: 2;
}

.card .btn:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

.card-btn {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    border: none;
    border-radius: 16px;
    padding: 14px 28px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
    outline: none;
    display: inline-block;
    letter-spacing: 0.5px;
    position: relative;
    z-index: 2;
}

.card-btn:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}
/* Enhanced Quick Links */
.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 40px;
    padding: 30px;
    background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 24px;
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.06);
}

.quick-links .btn {
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    color: #fff;
    border: none;
    padding: 16px 24px;
    border-radius: 16px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 4px 16px rgba(245, 158, 11, 0.3);
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
}

.quick-links .btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.quick-links .btn:hover::before {
    left: 100%;
}

.quick-links .btn:hover {
    background: linear-gradient(135deg, #fbbf24 0%, #fb923c 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(245, 158, 11, 0.4);
}

/* Enhanced Weather Card Styles */
.weather-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    color: white;
    position: relative;
    overflow: hidden;
}

.weather-full-width {
    grid-column: 1 / -1;
    margin-top: 20px;
}

.weather-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    animation: weatherGlow 4s ease-in-out infinite alternate;
}

@keyframes weatherGlow {
    0% { transform: rotate(0deg) scale(1); }
    100% { transform: rotate(180deg) scale(1.1); }
}

.weather-card .icon {
    color: #ffd700;
    font-size: 3rem;
    margin-bottom: 16px;
    animation: weatherFloat 3s ease-in-out infinite;
    position: relative;
    z-index: 2;
}

@keyframes weatherFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.weather-info {
    margin: 20px 0;
    position: relative;
    z-index: 2;
}

.temperature {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 12px;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    background: linear-gradient(45deg, #ffd700, #ffffff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.weather-desc {
    font-size: 1rem;
    opacity: 0.95;
    margin-bottom: 8px;
    text-transform: capitalize;
    font-weight: 500;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.location {
    font-size: 0.9rem;
    opacity: 0.9;
    font-weight: 400;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.weather-card .card-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 2;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.weather-card .card-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

/* Enhanced Weather Widget Layout */
.weather-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    position: relative;
    z-index: 2;
}

.weather-main-info {
    display: flex;
    align-items: center;
    gap: 24px;
}

.weather-icon-container {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.weather-icon-container i {
    font-size: 2.5rem;
    color: #ffd700;
    animation: weatherFloat 3s ease-in-out infinite;
}

.weather-primary {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.temperature {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    background: linear-gradient(45deg, #ffd700, #ffffff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.weather-desc {
    font-size: 1.2rem;
    opacity: 0.95;
    text-transform: capitalize;
    font-weight: 500;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.location {
    font-size: 1rem;
    opacity: 0.9;
    font-weight: 400;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 8px;
}

.location::before {
    content: '\f3c5';
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 0.9rem;
}

.weather-actions {
    display: flex;
    align-items: center;
}

.weather-refresh-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 2;
}

.weather-refresh-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: rotate(180deg);
}

.weather-refresh-btn i {
    font-size: 1.2rem;
}

.weather-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 24px;
    position: relative;
    z-index: 2;
}

.weather-detail-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 16px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.weather-detail-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

.weather-detail-item i {
    font-size: 1.5rem;
    color: #ffd700;
    margin-bottom: 8px;
}

.detail-label {
    font-size: 0.9rem;
    opacity: 0.8;
    margin-bottom: 4px;
    font-weight: 500;
}

.detail-value {
    font-size: 1.1rem;
    font-weight: 600;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

/* Additional Enhancements */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.card {
    animation: fadeInUp 0.6s ease-out;
}

.card:nth-child(1) { animation-delay: 0.1s; } /* Weather card */
.card:nth-child(2) { animation-delay: 0.2s; } /* Materials */
.card:nth-child(3) { animation-delay: 0.3s; } /* Questions */
.card:nth-child(4) { animation-delay: 0.4s; } /* Assessments */
.card:nth-child(5) { animation-delay: 0.5s; } /* Assignments */
.card:nth-child(6) { animation-delay: 0.6s; } /* Practice Sets */

.quick-links {
    animation: fadeInUp 0.8s ease-out 0.7s both;
}

/* Success Message Enhancement */
.success-message {
    animation: fadeInUp 0.5s ease-out, pulse 2s ease-in-out 1s;
    border-left: 4px solid #10b981;
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.2);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .dashboard-cards {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
    }
}

@media (max-width: 900px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .quick-links {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 20px;
    }
    
    .card {
        padding: 28px 20px;
    }
    
    .card .icon {
        font-size: 2.5rem;
    }
    
    .card .count {
        font-size: 2.2rem;
    }
}

@media (max-width: 600px) {
    .dashboard-container {
        padding: 0 15px;
    }
    
    .card {
        padding: 24px 16px;
    }
    
    .card .icon {
        font-size: 2rem;
    }
    
    .card .count {
        font-size: 1.8rem;
    }
    
    .temperature {
        font-size: 2rem;
    }
    
    /* Weather widget mobile responsiveness */
    .weather-header {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
    
    .weather-main-info {
        flex-direction: column;
        gap: 16px;
    }
    
    .weather-icon-container {
        width: 60px;
        height: 60px;
    }
    
    .weather-icon-container i {
        font-size: 2rem;
    }
    
    .temperature {
        font-size: 2.5rem;
    }
    
    .weather-details {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .weather-detail-item {
        padding: 12px;
    }
    
    .weather-detail-item i {
        font-size: 1.2rem;
    }
    
    .detail-label {
        font-size: 0.8rem;
    }
    
    .detail-value {
        font-size: 1rem;
    }
}
</style>

<div class="dashboard-container">
    <?php if ($success_message): ?>
    <div class="success-message">
        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

<div class="dashboard-cards">
    <div class="card weather-card weather-full-width">
        <div class="weather-header">
            <div class="weather-main-info">
                <div class="weather-icon-container">
                    <i class="fas fa-cloud-sun" id="weather-icon"></i>
                </div>
                <div class="weather-primary">
                    <div class="temperature" id="temperature">--°C</div>
                    <div class="weather-desc" id="weather-description">Loading...</div>
                    <div class="location" id="location">--</div>
                </div>
            </div>
            <div class="weather-actions">
                <button class="weather-refresh-btn" onclick="refreshWeather()" id="refresh-btn">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="weather-details" id="weather-details">
            <div class="weather-detail-item">
                <i class="fas fa-eye"></i>
                <span class="detail-label">Visibility</span>
                <span class="detail-value" id="visibility">-- km</span>
            </div>
            <div class="weather-detail-item">
                <i class="fas fa-tint"></i>
                <span class="detail-label">Humidity</span>
                <span class="detail-value" id="humidity">--%</span>
            </div>
            <div class="weather-detail-item">
                <i class="fas fa-wind"></i>
                <span class="detail-label">Wind</span>
                <span class="detail-value" id="wind">-- m/s</span>
            </div>
            <div class="weather-detail-item">
                <i class="fas fa-thermometer-half"></i>
                <span class="detail-label">Feels Like</span>
                <span class="detail-value" id="feels-like">--°C</span>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-book"></i></div>
        <div>Materials</div>
        <div class="count"><?php echo $materialsCount; ?></div>
        <button class="card-btn" onclick="window.location.href='teacher_content.php'">Manage</button>
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-question-circle"></i></div>
        <div>Questions</div>
        <div class="count"><?php echo $questionsCount; ?></div>
        <a href="clean_question_creator.php" class="btn">Open</a>
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-calendar-alt"></i></div>
        <div>Assignments</div>
        <div class="count"><?php echo $assignmentsCount; ?></div>
        
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-fire"></i></div>
        <div>Practice Sets</div>
        <div class="count"><?php echo $practiceSetsCount; ?></div>
        <a href="teacher_practice_tests.php" class="btn">Create</a>
    </div>
</div>
<div class="quick-links">
    <a href="teacher_notifications.php" class="btn">Announcements</a>
    <a href="teacher_analytics.php" class="btn">Analytics</a>
    <a href="teacher_account.php" class="btn">Account</a>
</div>
</div>

<script>
// Enhanced Weather Widget Functionality
let weatherData = null;
let isLoading = false;

// Weather icon mapping with more comprehensive coverage
const weatherIcons = {
    'clear sky': 'fas fa-sun',
    'few clouds': 'fas fa-cloud-sun',
    'scattered clouds': 'fas fa-cloud',
    'broken clouds': 'fas fa-cloud',
    'overcast clouds': 'fas fa-cloud',
    'shower rain': 'fas fa-cloud-rain',
    'rain': 'fas fa-cloud-rain',
    'light rain': 'fas fa-cloud-drizzle',
    'moderate rain': 'fas fa-cloud-rain',
    'heavy rain': 'fas fa-cloud-showers-heavy',
    'thunderstorm': 'fas fa-bolt',
    'light thunderstorm': 'fas fa-bolt',
    'heavy thunderstorm': 'fas fa-bolt',
    'snow': 'fas fa-snowflake',
    'light snow': 'fas fa-snowflake',
    'heavy snow': 'fas fa-snowflake',
    'mist': 'fas fa-smog',
    'fog': 'fas fa-smog',
    'haze': 'fas fa-smog',
    'dust': 'fas fa-smog',
    'sand': 'fas fa-smog',
    'ash': 'fas fa-smog',
    'squall': 'fas fa-wind',
    'tornado': 'fas fa-tornado'
};

// Multiple weather API endpoints for better reliability
const weatherAPIs = [
    {
        name: 'OpenWeatherMap',
        url: (lat, lon) => `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&appid=YOUR_API_KEY&units=metric`,
        fallback: true
    },
    {
        name: 'WeatherAPI',
        url: (lat, lon) => `https://api.weatherapi.com/v1/current.json?key=YOUR_API_KEY&q=${lat},${lon}&aqi=no`,
        fallback: true
    }
];

// Get user's location and fetch weather
async function getWeather() {
    if (isLoading) return;
    
    isLoading = true;
    updateLoadingState(true);
    
    try {
        // Get user's location
        const position = await getCurrentPosition();
        const { latitude, longitude } = position.coords;
        
        // Try multiple weather APIs
        let weatherData = null;
        for (const api of weatherAPIs) {
            try {
                if (api.name === 'OpenWeatherMap') {
                    // Use a free demo API or implement with your own key
                    const response = await fetch(`https://api.openweathermap.org/data/2.5/weather?lat=${latitude}&lon=${longitude}&appid=demo&units=metric`);
                    if (response.ok) {
                        weatherData = await response.json();
                        break;
                    }
                } else if (api.name === 'WeatherAPI') {
                    // Alternative API implementation
                    const response = await fetch(`https://api.weatherapi.com/v1/current.json?key=demo&q=${latitude},${longitude}&aqi=no`);
                    if (response.ok) {
                        const data = await response.json();
                        weatherData = transformWeatherAPIData(data);
                        break;
                    }
                }
            } catch (apiError) {
                console.log(`${api.name} failed:`, apiError);
                continue;
            }
        }
        
        if (weatherData) {
            updateWeatherDisplay(weatherData);
        } else {
            throw new Error('All weather APIs failed');
        }
        
    } catch (error) {
        console.log('Weather service unavailable, using demo data');
        // Enhanced demo data with more details
        const demoData = {
            main: { 
                temp: 28, 
                feels_like: 30, 
                humidity: 70 
            },
            weather: [{ description: 'clear sky' }],
            name: 'Cabuyao, Laguna, Philippines',
            visibility: 10000,
            wind: { speed: 2.8 }
        };
        updateWeatherDisplay(demoData);
    } finally {
        isLoading = false;
        updateLoadingState(false);
    }
}

// Get current position with fallback
function getCurrentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation not supported'));
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            resolve,
            () => {
                // Fallback to demo location (Cabuyao, Laguna, Philippines)
                resolve({
                    coords: {
                        latitude: 14.2726,
                        longitude: 121.1262
                    }
                });
            },
            { timeout: 5000 }
        );
    });
}

// Transform WeatherAPI data to match OpenWeatherMap format
function transformWeatherAPIData(data) {
    return {
        main: {
            temp: data.current.temp_c,
            feels_like: data.current.feelslike_c,
            humidity: data.current.humidity
        },
        weather: [{ description: data.current.condition.text.toLowerCase() }],
        name: data.location.name + ', ' + data.location.country,
        visibility: data.current.vis_km * 1000,
        wind: { speed: data.current.wind_kph / 3.6 }
    };
}

// Update loading state
function updateLoadingState(loading) {
    const refreshBtn = document.getElementById('refresh-btn');
    const refreshIcon = refreshBtn.querySelector('i');
    
    if (loading) {
        refreshBtn.disabled = true;
        refreshIcon.style.animation = 'spin 1s linear infinite';
    } else {
        refreshBtn.disabled = false;
        refreshIcon.style.animation = '';
    }
}

// Enhanced weather display update
function updateWeatherDisplay(data) {
    const temperature = Math.round(data.main.temp);
    const description = data.weather[0].description;
    const location = data.name;
    const feelsLike = Math.round(data.main.feels_like || data.main.temp);
    const humidity = data.main.humidity || 0;
    const visibility = data.visibility ? Math.round(data.visibility / 1000) : 0;
    const windSpeed = data.wind ? Math.round(data.wind.speed) : 0;
    
    // Update main weather info
    document.getElementById('temperature').textContent = `${temperature}°C`;
    document.getElementById('weather-description').textContent = description;
    document.getElementById('location').textContent = location;
    
    // Update detailed weather info
    document.getElementById('feels-like').textContent = `${feelsLike}°C`;
    document.getElementById('humidity').textContent = `${humidity}%`;
    document.getElementById('visibility').textContent = `${visibility} km`;
    document.getElementById('wind').textContent = `${windSpeed} m/s`;
    
    // Update weather icon with animation
    const iconElement = document.getElementById('weather-icon');
    const iconClass = weatherIcons[description] || 'fas fa-cloud-sun';
    
    // Add transition effect
    iconElement.style.opacity = '0';
    setTimeout(() => {
        iconElement.className = iconClass;
        iconElement.style.opacity = '1';
    }, 200);
    
    // Store weather data
    weatherData = data;
    
    // Add success animation
    const weatherCard = document.querySelector('.weather-card');
    weatherCard.style.animation = 'pulse 0.6s ease-in-out';
    setTimeout(() => {
        weatherCard.style.animation = '';
    }, 600);
}

// Enhanced refresh weather function
function refreshWeather() {
    if (isLoading) return;
    
    getWeather();
}

// Add spin animation for loading
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Initialize weather on page load
document.addEventListener('DOMContentLoaded', function() {
    getWeather();
    
    // Refresh weather every 10 minutes
    setInterval(getWeather, 600000);
});
</script>

<?php
render_teacher_footer();
?>


