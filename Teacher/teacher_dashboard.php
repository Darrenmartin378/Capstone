<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

$pageTitle = 'Dashboard';

// Basic counts (optional)
$materialsCount = 0;
try { $r=$conn->query("SELECT COUNT(*) c FROM reading_materials WHERE teacher_id=".(int)($_SESSION['teacher_id']??0)); if($r&&($x=$r->fetch_assoc())) $materialsCount=(int)$x['c']; } catch(Throwable $e){}

ob_start();
?>
<style>
.dashboard-header{display:flex;align-items:center;justify-content:space-between;margin:8px 0 14px 0}
.dashboard-header h1{margin:0;font-size:22px}

/* Grid */
.widgets-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:20px}

/* Card */
.widget{grid-column:span 3;background:#fff;border:1px solid #e6e9ee;border-radius:18px;padding:18px;box-shadow:0 6px 14px rgba(17,24,39,.06);transition:transform .18s ease, box-shadow .18s ease}
.widget:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(17,24,39,.10)}
.widget.w-wide{grid-column:span 6}
.widget .head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.icon-bubble{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:linear-gradient(180deg,#f8fbff,#eef3ff);border:1px solid #e3e8ff;font-size:18px;box-shadow:inset 0 -2px 0 rgba(0,0,0,.04)}
.widget .title{font-weight:800;color:#111827;letter-spacing:.2px}
.widget .body{display:block}

/* Specifics */
.weather-time{display:flex;align-items:center;justify-content:space-between}
.big{font-size:28px;font-weight:900;letter-spacing:.3px}
.muted{color:#6b7280;font-size:12px}

/* Quick actions */
.quick{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
.quick a{background:linear-gradient(180deg,#f8fbff,#eef3ff);color:#111827;padding:10px 14px;border-radius:12px;text-decoration:none;border:1px solid #e3e8ff;box-shadow:0 1px 2px rgba(0,0,0,.03);font-weight:700;letter-spacing:.2px}
.quick a:hover{filter:brightness(1.02);transform:translateY(-1px)}

/* Stats */
.stat-value{font-size:34px;font-weight:900;margin-bottom:2px}
.stat-sub{color:#64748b;font-size:12px}

@media (max-width: 1100px){.widget{grid-column:span 6}.widget.w-wide{grid-column:span 12}}
@media (max-width: 720px){.widget{grid-column:span 12}}
</style>

<div class="dashboard-header">
  <h1>Dashboard</h1>
  <div class="muted">Overview</div>
</div>

<div class="widgets-grid">
  <div class="widget w-wide">
    <div class="head"><div class="icon-bubble"><i class="fas fa-hand-peace"></i></div><div class="title">Welcome back</div></div>
    <div class="big"><?= htmlspecialchars($_SESSION['teacher_name'] ?? 'Teacher'); ?></div>
    <div class="muted">Have a productive day!</div>
  </div>
  <div class="widget">
    <div class="head"><div class="icon-bubble"><i class="fas fa-cloud-sun"></i></div><div class="title">Weather & Time</div></div>
    <div class="weather-time">
      <div>
        <div id="tTime" class="big">--:--</div>
        <div class="muted">Local time</div>
      </div>
      <div style="text-align:right">
        <div id="tTemp" class="big">--°C</div>
        <div id="tDesc" class="muted">Loading...</div>
      </div>
    </div>
  </div>
  <div class="widget">
    <div class="head"><div class="icon-bubble"><i class="fas fa-chart-bar"></i></div><div class="title">Your Stats</div></div>
    <div class="stat-value"><?= (int)$materialsCount; ?></div>
    <div class="stat-sub">Materials uploaded</div>
  </div>
  <div class="widget">
    <div class="head"><div class="icon-bubble"><i class="fas fa-bolt"></i></div><div class="title">Quick Actions</div></div>
    <div class="quick">
      <a href="teacher_content.php">New Material</a>
      <a href="clean_question_creator.php">Create Questions</a>
      <a href="teacher_practice_tests.php">Practice Sets</a>
      <a href="teacher_announcements.php">Announcements</a>
    </div>
  </div>
</div>

<script>
function updateClock(){
  const d=new Date();
  const s=d.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',second:'2-digit',hour12:true});
  const el=document.getElementById('tTime'); if(el) el.textContent=s;
}
setInterval(updateClock,1000); updateClock();

async function fetchWeather(){
  try{
    const r=await fetch('https://api.open-meteo.com/v1/forecast?latitude=14.6&longitude=121.0&current_weather=true');
    if(!r.ok) throw new Error('net');
    const d=await r.json();
    const t=Math.round(d.current_weather.temperature);
    document.getElementById('tTemp').textContent = t+'°C';
    document.getElementById('tDesc').textContent = 'Manila';
  }catch(e){
    // Fallback mock
    document.getElementById('tTemp').textContent = (25+Math.floor(Math.random()*6))+'°C';
    document.getElementById('tDesc').textContent = 'Offline';
  }
}
fetchWeather();
</script>
<?php
$content = ob_get_clean();
render_teacher_header('teacher_dashboard.php', $_SESSION['teacher_name'] ?? 'Teacher');
echo $content;
render_teacher_footer();
?>

