<?php
require('../../config.php');
require_login();

global $DB, $USER, $PAGE, $OUTPUT;
$userid = $USER->id;

// ==========================================
// 1. ROLE DETECTION (STRICT NATIVE API)
// ==========================================
$syscontext = context_system::instance();

$is_admin = is_siteadmin(); 
$is_faculty = false;

// A. Check System Roles (Matches your 'Assign System Roles' screenshot)
if (!$is_admin) {
    $sys_roles = get_user_roles($syscontext, $userid, true);
    if ($sys_roles) {
        foreach ($sys_roles as $role) {
            if ($role->shortname === 'manager') {
                $is_admin = true;
                break;
            }
            if (in_array($role->shortname, ['editingteacher', 'teacher', 'externalteacher', 'coursecreator'])) {
                $is_faculty = true;
                break;
            }
        }
    }
}

// B. Check Active Course Roles (Catches normal course-level teachers)
if (!$is_admin && !$is_faculty) {
    $courses = enrol_get_users_courses($userid, true);
    foreach ($courses as $c) {
        $coursecontext = context_course::instance($c->id);
        $course_roles = get_user_roles($coursecontext, $userid, true);
        if ($course_roles) {
            foreach ($course_roles as $role) {
                if (in_array($role->shortname, ['editingteacher', 'teacher', 'externalteacher', 'coursecreator'])) {
                    $is_faculty = true;
                    break 2; // Exits both loops immediately to save load time
                }
            }
        }
    }
}


// ==========================================
// 2. AJAX HANDLER (Bridge to Flask API)
// ==========================================
if (isset($_GET['fetch_data'])) {
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $generate_ai = optional_param('generate_ai', 0, PARAM_INT);
    
    // Ensure this matches your active ngrok URL
//$flask_url = "http://localhost:5000/api/dashboard";
$flask_url = "http://13.126.114.242:5000//api/dashboard";
    
    $payload = [
        "userid" => $userid,
        "role" => $is_admin ? 'admin' : ($is_faculty ? 'faculty' : 'student'),
        "courseid" => $courseid,
        "generate_ai" => (bool)$generate_ai
    ];

    $ch = curl_init($flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    // CRITICAL: ngrok-skip-browser-warning prevents ngrok from blocking the API call
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'ngrok-skip-browser-warning: true'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    header('Content-Type: application/json');
    if(curl_errno($ch)) {
        echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
    } elseif ($http_code != 200) {
        echo json_encode(['error' => 'HTTP Error ' . $http_code, 'details' => $response]);
    } else {
        echo $response;
    }
    
    curl_close($ch);
    exit;
}


// ==========================================
// 3. PAGE SETUP & STYLES
// ==========================================
$PAGE->set_url(new moodle_url('/local/intelligentdashboard/index.php'));
$PAGE->set_context($syscontext);
$PAGE->set_title('Intelligent Dashboard');

echo $OUTPUT->header();

echo "
<style>
    :root { 
        --primary: #4CAF50; 
        --secondary: #2E7D32; 
        --accent: #81C784; 
        --bg-light: #f9fbf9; 
    }
    .dashboard-container { font-family: 'Arial', sans-serif; max-width: 1200px; margin: 0 auto; }
    
    /* Search Dropdown */
    .search-wrapper { position: relative; max-width: 400px; margin-bottom: 30px; }
    .search-input { width: 100%; padding: 15px; border-radius: 10px; border: 2px solid #ddd; outline: none; font-size: 16px; box-sizing: border-box; transition: 0.3s;}
    .search-input:focus { border-color: var(--primary); }
    .dropdown-list { position: absolute; top: 100%; left: 0; right: 0; background: white; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: none; z-index: 100; border-radius: 10px; max-height: 250px; overflow-y: auto; margin-top: 5px; border: 1px solid #eee;}
    .dropdown-list.show { display: block; }
    .dropdown-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f9f9f9; transition: 0.2s; }
    .dropdown-item:hover { background: var(--bg-light); color: var(--secondary); font-weight: bold; }
    
    /* KPIs */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .kpi-card { background: var(--primary); color: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: 0.3s;}
    .kpi-card:hover { transform: translateY(-5px); }
    .kpi-card.dark { background: var(--secondary); }
    .kpi-card h3 { font-size: 16px; font-weight: normal; margin-bottom: 10px; opacity: 0.9; color: white;}
    .kpi-card h1 { font-size: 36px; margin: 0; color: white;}
    
    /* AI Box */
    .ai-box { background: white; border-radius: 15px; padding: 30px; border-left: 8px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 40px;}
    .ai-btn { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.3s; font-size: 14px;}
    .ai-btn:hover { background: var(--secondary); }
    #ai-content p { line-height: 1.6; color: #4f566b; font-size: 16px; margin-bottom: 20px; white-space: pre-wrap;}
</style>
<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>";


// ==========================================
// 4. MAIN HTML STRUCTURE
// ==========================================
echo "<div class='dashboard-container'>";
echo "<h1 style='margin-bottom:30px; color:#333;'>Welcome, " . $USER->firstname . "</h1>";

// Searchable Course Dropdown (Safely load enrolled courses)
$courses = enrol_get_users_courses($userid, true);
echo "
    <div class='search-wrapper'>
        <input type='text' id='courseSearch' class='search-input' placeholder='🔍 Search or select a course...' onclick='toggleDrop()' onkeyup='filterDrop()'>
        <div id='courseDrop' class='dropdown-list'>
            <div class='dropdown-item' onclick='selectCourse(0, \"All Courses\")'>🌍 All Courses</div>";
            foreach ($courses as $c) {
                echo "<div class='dropdown-item' onclick='selectCourse($c->id, \"".htmlspecialchars($c->fullname, ENT_QUOTES)."\")'>$c->fullname</div>";
            }
echo "  </div>
    </div>
    <input type='hidden' id='currentCourseId' value='0'>";


// ==========================================
// 5. ROLE-BASED UI RENDERING
// ==========================================
if ($is_admin) {
    render_admin_ui();
} else if ($is_faculty) {
    render_faculty_ui();
} else {
    render_student_ui();
}

echo "</div>"; // End dashboard-container


// --- Admin UI ---
function render_admin_ui() {
    echo "
    <h2 style='color: var(--secondary); margin-bottom: 20px;'>Institutional Command Center</h2>
    <div class='kpi-grid'>
        <div class='kpi-card'><h3>Active Users (30d)</h3><h1 id='kpi-comp'>0</h1></div>
        <div class='kpi-card dark'><h3>Avg Completion %</h3><h1 id='kpi-mods'>0</h1></div>
        <div class='kpi-card'><h3>Total Platform Hours</h3><h1 id='kpi-hours'>0</h1></div>
        <div class='kpi-card dark'><h3>Total Certificates</h3><h1 id='kpi-certs'>0</h1></div>
    </div>
    
    <div style='background:white; padding:20px; border-radius:15px; box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
        <h3 style='color:var(--secondary); margin-bottom: 15px;'>Platform Engagement Trend</h3>
        <div style='position: relative; height: 300px; width: 100%;'>
            <canvas id='mainChart'></canvas>
        </div>
    </div>
    
    <div class='ai-box'>
        <h2 style='color:var(--secondary); margin-bottom: 15px;'>🤖 Institutional Macro-Trends</h2>
        <div id='ai-content'>
            <p>Analyze adoption rates, identify bottleneck courses, and forecast infrastructure needs.</p>
            <button id='ai-btn' class='ai-btn' onclick='fetchData(true)'>Generate Platform Report</button>
        </div>
    </div>";
}

// --- Faculty UI ---
function render_faculty_ui() {
    echo "
    <h2 style='color: var(--secondary); margin-bottom: 20px;'>Class Performance & Early Warning</h2>
    <div class='kpi-grid'>
        <div class='kpi-card'><h3>Avg. Quiz Accuracy</h3><h1 id='kpi-accuracy'>0%</h1></div>
        <div class='kpi-card dark'><h3>Assignment Submission</h3><h1 id='kpi-assign'>0%</h1></div>
        <div class='kpi-card'><h3>Modules Passed (Avg)</h3><h1 id='kpi-mods'>0</h1></div>
        <div class='kpi-card dark'><h3>Total Dedicated Hours</h3><h1 id='kpi-hours'>0</h1></div>
        <div class='kpi-card'><h3>Total Certificates</h3><h1 id='kpi-certs'>0</h1></div>
    </div>
    
    <div style='background:white; padding:20px; border-radius:15px; box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
        <h3 style='color:var(--secondary); margin-bottom: 5px;'>Dedication vs. Grade (Correlation)</h3>
        <p style='font-size:12px; color:#666; margin-bottom: 15px;'>High dedication but low grade indicates an at-risk student.</p>
        <div style='position: relative; height: 300px; width: 100%;'>
            <canvas id='mainChart'></canvas>
        </div>
    </div>
    
    <div class='ai-box'>
        <h2 style='color:var(--secondary); margin-bottom: 15px;'>🤖 Faculty AI Assistant</h2>
        <div id='ai-content'>
            <p><strong>At-Risk Student Predictor:</strong> Analyzes historical dedication and quiz velocity.</p>
            <p><strong>Prerequisite Warning System:</strong> Flags students attempting advanced modules with low foundational grades.</p>
            <p><strong>Feedback Assistant:</strong> Generates supportive intervention templates.</p>
            <button id='ai-btn' class='ai-btn' onclick='fetchData(true)' style='margin-top:15px;'>Run Class Risk Analysis</button>
        </div>
    </div>";
}

// --- Student UI ---
function render_student_ui() {
    global $CFG; // This grabs your exact Moodle domain automatically!
    
    // Build the dynamic URL
    $cert_url = $CFG->wwwroot . '/mod/customcert/my_certificates.php';

    echo "
    <div class='kpi-grid'>
        <div class='kpi-card'><h3>Courses Completed</h3><h1 id='kpi-comp'>0</h1></div>
        <div class='kpi-card dark'><h3>Modules Passed</h3><h1 id='kpi-mods'>0</h1></div>
        
        <a href='{$cert_url}' style='text-decoration: none; color: inherit; display: block;'>
            <div class='kpi-card' style='cursor: pointer; transition: 0.3s;'>
                <h3>Certificates Earned ↗</h3><h1 id='kpi-certs'>0</h1>
            </div>
        </a>

        <div class='kpi-card dark'><h3>Learning Hours</h3><h1 id='kpi-hours'>0 hrs</h1></div>
        <div class='kpi-card'><h3>Current Streak 🔥</h3><h1 id='kpi-streak'>0 Days</h1></div>
    </div>
    
    <div style='margin-bottom: 40px;'>
        <a href='planner.php' style='display: inline-flex; align-items: center; background: var(--secondary); color: white; padding: 15px 25px; border-radius: 12px; text-decoration: none; font-weight: bold; box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3); transition: 0.3s;'>
            🚀 Open AI Agent & Planner
        </a>
    </div>
    
    <div style='background:white; padding:20px; border-radius:15px; box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
        <h3 style='color:var(--secondary); margin-bottom: 15px;'>Learning Hours Trend</h3>
        <div style='position: relative; height: 300px; width: 100%;'>
            <canvas id='mainChart'></canvas>
        </div>
    </div>
    
    <div class='ai-box' style='margin-top:40px; padding:30px;'>
        <div style='display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #f0f2f5; padding-bottom:15px; margin-bottom:20px;'>
            <h2 style='margin:0; color: var(--secondary);'>🤖 AI Weekly Performance Report</h2>
            <span id='ai_status_label' style='background: var(--bg-light); color: var(--secondary); padding: 5px 15px; border-radius: 10px; font-weight: bold; font-size: 12px;'>READY TO ANALYZE</span>
        </div>
        
        <div style='display:grid; grid-template-columns: 1fr 2fr; gap:30px;'>
            <div style='background: var(--bg-light); padding:20px; border-radius:15px; border: 1px solid #eee;'>
                <p style='margin:10px 0;'><strong>Quiz Accuracy:</strong> <span id='stat-accuracy' style='color: var(--primary); font-weight:bold;'>0%</span></p>
                <p style='margin:10px 0;'><strong>Assignments:</strong> <span id='stat-assign'>0</span></p>
                <p style='margin:10px 0;'><strong>Velocity:</strong> <span id='stat-velocity'>0 mod/hr</span></p>
                <p style='margin:10px 0; font-size:12px; color:#888;'><strong>Recent Focus:</strong><br><span id='stat-topics'>...</span></p>
            </div>
            
            <div id='ai-content' style='color:#4f566b; line-height:1.6; font-size:16px; white-space: pre-wrap; display: flex; align-items: center; justify-content: center; flex-direction: column;'>
                <p id='ai-placeholder-text'>Click below to generate new insights for this selection.</p>
                <button id='ai-btn' class='ai-btn' onclick='fetchData(true)'>Generate AI Insights</button>
            </div>
        </div>
    </div>
    
    <div style='margin-top:50px;'>
        <h2>Achievement Badges 🏆</h2>
        <div style='display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;' id='badges-container'>
            </div>
    </div>";
}

// ==========================================
// 6. JAVASCRIPT ENGINE
// ==========================================
echo "
<script>
    let chart = null;

    // --- Dropdown Logic ---
    function toggleDrop() { 
        document.getElementById('courseDrop').classList.toggle('show'); 
    }
    
    function filterDrop() {
        let val = document.getElementById('courseSearch').value.toUpperCase();
        let items = document.querySelectorAll('.dropdown-item');
        for (let i = 1; i < items.length; i++) { // Skip 'All Courses' index 0
            items[i].style.display = items[i].innerText.toUpperCase().includes(val) ? '' : 'none';
        }
    }
    
    function selectCourse(id, name) {
        document.getElementById('currentCourseId').value = id;
        document.getElementById('courseSearch').value = name;
        document.getElementById('courseDrop').classList.remove('show');
        fetchData(false); // Fetch numbers immediately without AI
    }
    
    // Close dropdown if clicked outside
    window.onclick = function(event) {
        if (!event.target.matches('.search-input')) {
            document.getElementById('courseDrop').classList.remove('show');
        }
    }

    // --- Data Fetching Logic ---
    function fetchData(genAi) {
        let cid = document.getElementById('currentCourseId').value;
        let aiContent = document.getElementById('ai-content');
        let aiBtn = document.getElementById('ai-btn');
        let aiStatus = document.getElementById('ai_status_label');
        let aiPlaceholder = document.getElementById('ai-placeholder-text');
        
        // UI Loading state for AI
        if(genAi && aiBtn) {
            aiBtn.style.display = 'none';
            if(aiPlaceholder) aiPlaceholder.style.display = 'none';
            if(aiStatus) {
                aiStatus.innerHTML = 'ANALYZING...';
                aiStatus.style.background = '#fff3e0';
                aiStatus.style.color = '#ff9800';
            }
            aiContent.innerHTML += '<p id=\"ai-loading\" style=\"color:#ff9800; font-weight:bold;\">⏳ AI Coach is analyzing...</p>';
        } else if (!genAi && aiBtn) {
            // Reset AI box if they switch courses
            aiBtn.style.display = 'inline-block';
            if(aiPlaceholder) aiPlaceholder.style.display = 'block';
            let loadingMsg = document.getElementById('ai-loading');
            if(loadingMsg) loadingMsg.remove();
            
            // Clean out any previously generated report text
            let oldReports = aiContent.querySelectorAll('.generated-report');
            oldReports.forEach(el => el.remove());
            
            if(aiStatus) {
                aiStatus.innerHTML = 'READY TO ANALYZE';
                aiStatus.style.background = 'var(--bg-light)';
                aiStatus.style.color = 'var(--secondary)';
            }
        }
        
        // Fetch from PHP Bridge
fetch('/moodle/local/intelligentdashboard/index.php?fetch_data=1&courseid=' + cid + '&generate_ai=' + (genAi ? 1 : 0))
            .then(response => response.json())
            .then(data => {
                if(data.error) {
                    console.error('Backend Error:', data.error);
                    return;
                }
                
                // 1. Update Top KPIs (Supports Admin/Faculty/Student generic IDs)
                if(document.getElementById('kpi-comp')) document.getElementById('kpi-comp').innerText = data.kpi.completed_courses || data.kpi.completed || 0;
                if(document.getElementById('kpi-mods')) document.getElementById('kpi-mods').innerText = data.kpi.modules_passed || data.kpi.modules_avg || data.kpi.modules || 0;
                if(document.getElementById('kpi-certs')) document.getElementById('kpi-certs').innerText = data.kpi.certificates || 0;
                if(document.getElementById('kpi-hours')) document.getElementById('kpi-hours').innerText = (data.kpi.learning_hours || data.kpi.hours || 0) + ' hrs';
                if(document.getElementById('kpi-streak')) document.getElementById('kpi-streak').innerText = (data.kpi.streak || 0) + ' Days';
                
                // Faculty specific KPIs
                if(document.getElementById('kpi-accuracy')) document.getElementById('kpi-accuracy').innerText = data.kpi.accuracy || '0%';
                if(document.getElementById('kpi-assign')) document.getElementById('kpi-assign').innerText = data.kpi.assignment_rate || '0%';

                // 2. Update AI Stats Box (Student Left Column)
                if(data.stats) {
                    if(document.getElementById('stat-accuracy')) document.getElementById('stat-accuracy').innerText = (data.stats.avg_quiz || 0) + '%';
                    if(document.getElementById('stat-assign')) document.getElementById('stat-assign').innerText = data.stats.submissions || 0;
                    if(document.getElementById('stat-velocity')) document.getElementById('stat-velocity').innerText = (data.stats.velocity || 0) + ' mod/hr';
                    if(document.getElementById('stat-topics')) document.getElementById('stat-topics').innerText = data.stats.topics || 'None';
                }
                
                // 3. Dynamic Badges (Student View Only)
                let badgesHtml = '';
                let streak = data.kpi.streak || 0;
                let comp = data.kpi.completed_courses || data.kpi.completed || 0; 
                let hrs = data.kpi.learning_hours || data.kpi.hours || 0;
                let mods = data.kpi.modules_passed || data.kpi.modules || 0;

                if(streak >= 7) badgesHtml += \"<div style='background:#FF9800; color:white; padding:20px; border-radius:15px; width:220px; text-align:center;'><h1>🔥</h1><h3>Consistent Learner</h3><p>7+ Day Streak</p></div>\";
                if(comp >= 5) badgesHtml += \"<div style='background: var(--primary); color:white; padding:20px; border-radius:15px; width:220px; text-align:center;'><h1>📚</h1><h3>Course Finisher</h3><p>5 Courses</p></div>\";
                if(hrs >= 20) badgesHtml += \"<div style='background: var(--accent); color:white; padding:20px; border-radius:15px; width:220px; text-align:center;'><h1>⏰</h1><h3>Dedicated Learner</h3><p>20+ Hours</p></div>\";
                if(mods >= 10) badgesHtml += \"<div style='background: var(--secondary); color:white; padding:20px; border-radius:15px; width:220px; text-align:center;'><h1>⚡</h1><h3>Fast Learner</h3><p>10+ Modules Passed</p></div>\";
                
                if(document.getElementById('badges-container')) {
                    document.getElementById('badges-container').innerHTML = badgesHtml;
                }

                // 4. Update AI Report Text (Right Column)
                if(genAi && data.report) {
                    let loadingMsg = document.getElementById('ai-loading');
                    if(loadingMsg) loadingMsg.remove();
                    
                    if(aiStatus) {
                        aiStatus.innerHTML = 'REAL-TIME ANALYSIS';
                        aiStatus.style.background = 'var(--bg-light)';
                        aiStatus.style.color = 'var(--secondary)';
                    }
                    
                    aiContent.innerHTML += '<div class=\"generated-report\" style=\"width:100%; text-align:left;\">' + data.report + '</div>';
                }
                
                // 5. Update Chart
                if(data.chart) {
                    updateChart(data.chart);
                }
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                if(genAi) aiContent.innerHTML = '<p class=\"generated-report\" style=\"color:red;\">Failed to connect to AI service.</p>';
            });
    }

    // --- Chart Logic ---
    function updateChart(cData) {
        const ctx = document.getElementById('mainChart').getContext('2d');
        if(chart) chart.destroy();
        
        // Add a premium gradient fill for line charts
        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(74, 175, 80, 0.4)'); // Solid green at top
        gradient.addColorStop(1, 'rgba(74, 175, 80, 0.0)'); // Transparent at bottom

if (cData.type === 'scatter') {
            chart = new Chart(ctx, {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Student', // Cleaned up the generic label
                        data: cData.data, 
                        backgroundColor: '#ff5722',
                        pointRadius: 6,
                        hoverRadius: 8 // Makes it pop a little when you hover
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, 
                    plugins: {
                        // THIS IS THE NEW CODE TO SHOW THE NAME
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let studentName = context.raw.name || 'Unknown Student';
                                    return studentName + ': ' + context.raw.y + '% grade (' + context.raw.x + ' hrs)';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Dedicated Hours' } },
                        y: { title: { display: true, text: 'Avg Grade (%)' }, min: 0, max: 100 }
                    }
                }
            });
        } else {
            chart = new Chart(ctx, {
                type: 'line',
                data: { 
                    labels: cData.labels, 
                    datasets: [{ 
                        label: 'Trend', 
                        data: cData.data, 
                        borderColor: '#4CAF50', 
                        backgroundColor: gradient,
                        borderWidth: 3,
                        pointBackgroundColor: '#2E7D32',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4 // Smooth swooping curve
                    }] 
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // Ensures the chart stays inside the 300px div
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    }

    // Initialize on page load (false = don't generate AI text yet)
    document.addEventListener('DOMContentLoaded', () => fetchData(false));
</script>";

echo $OUTPUT->footer();
?>
