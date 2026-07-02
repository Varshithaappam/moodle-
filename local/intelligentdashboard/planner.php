<?php
// Original file: planner.php
require('../../config.php');
require_login();

global $DB, $USER, $PAGE, $OUTPUT;
$userid = $USER->id;
$syscontext = context_system::instance();

// Reuse the role detection from index.php
$is_admin = is_siteadmin(); 
$is_faculty = false;

if (!$is_admin) {
    $sys_roles = get_user_roles($syscontext, $userid, true);
    if ($sys_roles) {
        foreach ($sys_roles as $role) {
            if ($role->shortname === 'manager') { $is_admin = true; break; }
            if (in_array($role->shortname, ['editingteacher', 'teacher', 'externalteacher', 'coursecreator'])) {
                $is_faculty = true;
                break;
            }
        }
    }
}

// ==========================================
// LOCALIZATION STRING INGESTION SAFETY WRAPPER
// ==========================================
$str_pluginname         = 'Intelligent Dashboard';
$str_studyagentplanner  = 'My Study Agent & Planner';
$str_backtodashboard    = 'Back to Main Dashboard';
$str_plannersubtitle    = 'Select a course and adjust your availability to build your personal learning schedule.';
$str_goaltracking       = 'MY GOAL TRACKING & PROGRESS FORECAST';
$str_calculatingtimeline = 'Calculating my personal timeline...';
$str_analyzingpace      = 'Analyzing historical pace calculations from your course log store.';
$str_studysettings      = 'My Study Settings';
$str_coursecontext      = 'Select Course Context';
$str_dailystudyhours    = 'Daily Study Allocation (Hours)';
$str_dailytarget        = 'Target Daily Task Pace';
$str_customschedule     = 'My Custom Schedule';
$str_planning           = 'PLANNING';
$str_planningdots       = 'PLANNING...';
$str_ready              = 'READY';
$str_finishby           = 'On Track to Finish By:';
$str_coursecompleted    = 'Course Completed! 🎉';
$str_activitytype       = 'Activity Type';
$str_criticaldue        = '🚨 DUE ≤ 3 DAYS';
$str_estimatedtime      = 'Estimated Time';
$str_nopendingtasks     = 'No pending tasks remaining for this course! Great job. 🎉';
$str_connectionerror    = 'Connection error with the AI planning agent engine.';

try {
    $str_pluginname         = get_string('pluginname', 'local_intelligentdashboard');
    $str_studyagentplanner  = get_string('studyagentplanner', 'local_intelligentdashboard');
    $str_backtodashboard    = get_string('backtodashboard', 'local_intelligentdashboard');
    $str_plannersubtitle    = get_string('plannersubtitle', 'local_intelligentdashboard');
    $str_goaltracking       = get_string('goaltracking', 'local_intelligentdashboard');
    $str_calculatingtimeline = get_string('calculatingtimeline', 'local_intelligentdashboard');
    $str_analyzingpace      = get_string('analyzingpace', 'local_intelligentdashboard');
    $str_studysettings      = get_string('studysettings', 'local_intelligentdashboard');
    $str_coursecontext      = get_string('coursecontext', 'local_intelligentdashboard');
    $str_dailystudyhours    = get_string('dailystudyhours', 'local_intelligentdashboard');
    $str_dailytarget        = get_string('dailytarget', 'local_intelligentdashboard');
    $str_customschedule     = get_string('customschedule', 'local_intelligentdashboard');
    $str_planning           = get_string('planning', 'local_intelligentdashboard');
    $str_planningdots       = get_string('planningdots', 'local_intelligentdashboard');
    $str_ready              = get_string('ready', 'local_intelligentdashboard');
    $str_finishby           = get_string('finishby', 'local_intelligentdashboard');
    $str_coursecompleted    = get_string('coursecompleted', 'local_intelligentdashboard');
    $str_activitytype       = get_string('activitytype', 'local_intelligentdashboard');
    $str_criticaldue        = get_string('criticaldue', 'local_intelligentdashboard');
    $str_estimatedtime      = get_string('estimatedtime', 'local_intelligentdashboard');
    $str_nopendingtasks     = get_string('nopendingtasks', 'local_intelligentdashboard');
    $str_connectionerror    = get_string('connectionerror', 'local_intelligentdashboard');
} catch (Exception $e) {
    // Graceful execution fallback onto initialized strings if lang packs aren't compiled yet
}

// 1. Get selected course from dropdown (defaults to 0 / All)
$selected_courseid = optional_param('courseid', 0, PARAM_INT);
$courses = enrol_get_users_courses($userid, true);

// If no course is selected, default to the first enrolled course
if ($selected_courseid == 0 && !empty($courses)) {
    $selected_courseid = reset($courses)->id;
}

// 2. Fetch specific pending tasks using SAFE, universal LEFT JOINs
$pending_tasks = [];
if ($selected_courseid) {
    // SQL dynamically filters completed tasks, passed quizzes, submitted assignments, and excluded modules
    $sql = "
        SELECT cm.id, m.name as modname,
               COALESCE(a.name, q.name, r.name, p.name, 'Learning Module') as instancename,
               COALESCE(a.duedate, q.timeclose, NULL) as deadline
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id
        LEFT JOIN {assign} a ON m.name = 'assign' AND cm.instance = a.id
        LEFT JOIN {quiz} q ON m.name = 'quiz' AND cm.instance = q.id
        LEFT JOIN {resource} r ON m.name = 'resource' AND cm.instance = r.id
        LEFT JOIN {page} p ON m.name = 'page' AND cm.instance = p.id
        WHERE cm.course = ? 
          AND m.name NOT IN ('attendance', 'bigbluebuttonbn', 'customcert', 'feedback', 'glossary', 'label') 
          AND cm.id NOT IN (
              SELECT coursemoduleid FROM {course_modules_completion} WHERE userid = ? AND completionstate = 1
          )
          AND (m.name != 'quiz' OR cm.instance NOT IN (
              SELECT quiz FROM {quiz_grades} WHERE userid = ? GROUP BY quiz HAVING MAX(grade) >= 5
          ))
          AND (m.name != 'assign' OR cm.id NOT IN (
              SELECT cm_sub.id FROM {assign} a 
              JOIN {assign_submission} s ON s.assignment = a.id 
              JOIN {course_modules} cm_sub ON cm_sub.instance = a.id 
              WHERE s.userid = ? AND s.status = 'submitted'
          ))";
        
    try {
        $activities = $DB->get_records_sql($sql, [$selected_courseid, $userid, $userid, $userid]);
        
        if ($activities) {
            foreach ($activities as $act) {
                $due_in_days = null;
                if (!empty($act->deadline)) {
                    $due_in_days = round(($act->deadline - time()) / 86400); 
                }
                
                $pending_tasks[] = [
                    "name" => $act->instancename ?: 'Course Activity',
                    "type" => $act->modname,
                    "due_days" => $due_in_days
                ];
            }
        }
    } catch (Exception $e) {
        // Silently catch DB errors so the page doesn't 500 crash
    }
}

$modules_remaining = count($pending_tasks);

// 3. Velocity and Failures logic
$timespent = $DB->get_field_sql("SELECT SUM(timespent) FROM {block_dedication} WHERE userid = ?", [$userid]);
$hours_spent = $timespent ? round($timespent / 3600, 2) : 15.0;

$modules_passed = $DB->count_records_sql("
    SELECT COUNT(*) FROM {course_modules_completion} cmc 
    JOIN {course_modules} cm ON cmc.coursemoduleid = cm.id 
    WHERE cmc.userid = ? AND cmc.completionstate = 1", [$userid]);
    
$velocity = $hours_spent > 0 ? round($modules_passed / $hours_spent, 2) : 0.5;
if ($velocity <= 0) $velocity = 0.5;

// Fetch failures by looking at the highest score. If the highest score is < 70%, it's a failure.
$failures = [];
$quiz_records = $DB->get_records_sql("
    SELECT q.id, q.name, (MAX(qg.grade) / q.grade) * 100 as score 
    FROM {quiz_grades} qg 
    JOIN {quiz} q ON qg.quiz = q.id 
    WHERE qg.userid = ? 
    GROUP BY q.id, q.name, q.grade
    HAVING (MAX(qg.grade) / q.grade) < 0.7", [$userid]);

if ($quiz_records) {
    foreach ($quiz_records as $qr) {
        $failures[] = ["name" => $qr->name, "score" => round($qr->score, 1)];
    }
}

// ==========================================
// PAGE SETUP & THEME STYLES
// ==========================================
$PAGE->set_url(new moodle_url('/local/intelligentdashboard/planner.php'));
$PAGE->set_context($syscontext);
$PAGE->set_title($str_studyagentplanner);
$PAGE->set_heading($str_studyagentplanner);

echo $OUTPUT->header();

echo "
<style>
/* ==========================================================================
   CLEAN WHITE & TEAL THEME OVERHAUL
   ========================================================================== */
:root {
    --bg-body: #f8fafc;
    --panel-clinical: #ffffff;
    --brand-teal: #14b8a6;
    --brand-teal-dark: #0f766e;
    --text-main: #1e293b;
    --text-muted: #64748b;
    --border-subtle: #cbd5e1;
    --accent-light: #e6f4f4;
}

body { background-color: var(--bg-body) !important; color: var(--text-main); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }

.planner-container { max-width: 1400px; margin: 10px auto; padding: 0 24px 50px 24px; }

.back-btn { 
    display: inline-flex; align-items: center; text-decoration: none !important; 
    color: var(--brand-teal) !important; font-weight: 500; font-size: 14px; margin-bottom: 20px; 
}
.back-btn:hover { color: var(--brand-teal-dark) !important; }

.planner-heading { color: var(--text-main) !important; font-weight: 700; font-size: 32px; margin-bottom: 6px; }
.planner-subheading { color: var(--text-muted); font-size: 15px; margin-bottom: 25px; }

/* --- ASYMMETRIC GRID SYSTEM --- */
.planner-layout {
    display: grid;
    grid-template-columns: 380px 1fr; /* Rigid narrow side panel, fluid main panel */
    gap: 30px;
    align-items: start;
}

/* --- PREDICTION BANNER --- */
.prediction-banner { 
    background: var(--brand-teal-dark) !important; color: #ffffff !important; 
    padding: 24px 30px; border-radius: 10px; margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(15, 118, 110, 0.15);
}
.prediction-banner p { margin: 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.9; }
.prediction-banner h2 { margin: 6px 0 4px 0; font-size: 26px; font-weight: 700; color: #ffffff !important; }
.prediction-banner #adaptive-advice { font-size: 14px; opacity: 0.85; text-transform: none; letter-spacing: normal; font-weight: normal; }

/* --- WHITE CARDS --- */
.card-panel { 
    background: var(--panel-clinical) !important; 
    border-radius: 10px; padding: 25px; 
    box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 1px 2px rgba(0,0,0,0.04);
    border: 1px solid var(--border-subtle) !important; 
}

.panel-title {
    margin-top: 0; margin-bottom: 20px;
    color: var(--brand-teal-dark) !important; font-size: 20px; font-weight: 600;
}

/* --- FORMS --- */
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-main); margin-bottom: 8px; }
select.form-control, input.form-control { 
    width: 100%;
    height: 42px;
    padding: 8px 12px;
    background-color: #ffffff !important;
    border: 1px solid var(--border-subtle) !important;
    color: var(--text-main) !important;
    border-radius: 6px !important;
    font-size: 14px;
}
select.form-control:focus, input.form-control:focus { 
    border-color: var(--brand-teal) !important;
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1) !important;
    outline: none;
}

/* --- TASK TILES --- */
.task-item { 
    display: flex; align-items: center; justify-content: space-between;
    background: #ffffff !important; 
    padding: 16px 20px; border-radius: 8px; margin-bottom: 12px; 
    border: 1px solid var(--border-subtle); 
    border-left: 4px solid var(--brand-teal) !important;
    transition: all 0.3s ease;
}
.task-item:hover { border-color: #cbd5e1; background: #f8fafc !important; }

/* DYNAMIC PRIORITY STYLING */
.task-item.critical { border-left: 4px solid #ef4444 !important; background: #fef2f2 !important; }
.task-item.remediation { border-left: 4px solid #f59e0b !important; background: #fffbeb !important; }

.task-details { flex-grow: 1; }
.task-details h4 { margin: 0 0 4px 0; font-size: 15px; font-weight: 600; color: var(--text-main); }
.task-details span { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.02em; }

.task-time { 
    font-size: 13px; font-weight: 600; color: var(--brand-teal) !important; 
    background: var(--accent-light) !important; 
    padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(20, 184, 166, 0.15);
    display: inline-flex; align-items: center; gap: 4px;
}

/* Dropdown layout safety stack fallback */
@media (max-width: 1024px) {
    .planner-layout { grid-template-columns: 1fr; }
}
</style>
";

echo "<div class='planner-container'>";
echo "<a href='" . $CFG->wwwroot . "/my/' class='back-btn'>← " . htmlspecialchars($str_backtodashboard, ENT_QUOTES, 'UTF-8') . "</a>";
echo "<h1 class='planner-heading'>🚀 " . htmlspecialchars($str_studyagentplanner, ENT_QUOTES, 'UTF-8') . "</h1>";
echo "<p class='planner-subheading'>" . htmlspecialchars($str_plannersubtitle, ENT_QUOTES, 'UTF-8') . "</p>";

echo "
<div class='prediction-banner'>
    <p>" . htmlspecialchars($str_goaltracking, ENT_QUOTES, 'UTF-8') . "</p>
    <h2 id='projected-date'>" . htmlspecialchars($str_calculatingtimeline, ENT_QUOTES, 'UTF-8') . "</h2>
    <p id='adaptive-advice'>" . htmlspecialchars($str_analyzingpace, ENT_QUOTES, 'UTF-8') . "</p>
</div>

<div class='planner-layout'>
    <div class='card-panel'>
        <h3 class='panel-title'>" . htmlspecialchars($str_studysettings, ENT_QUOTES, 'UTF-8') . "</h3>
        
        <div class='form-group'>
            <label>" . htmlspecialchars($str_coursecontext, ENT_QUOTES, 'UTF-8') . "</label>
            <select class='form-control' onchange=\"window.location.href='?courseid=' + this.value\">";
            foreach ($courses as $c) {
                $selected = ($c->id == $selected_courseid) ? "selected" : "";
                echo "<option value='{$c->id}' {$selected}>" . htmlspecialchars($c->fullname, ENT_QUOTES, 'UTF-8') . "</option>";
            }
echo "      </select>
        </div>

        <div class='form-group'>
            <label>" . htmlspecialchars($str_dailystudyhours, ENT_QUOTES, 'UTF-8') . "</label>
            <input type='number' id='hours_per_day' class='form-control' value='1.5' min='0.5' max='8' step='0.5' onchange='optimizeSchedule()'>
        </div>
        
        <div class='form-group'>
            <label>" . htmlspecialchars($str_dailytarget, ENT_QUOTES, 'UTF-8') . "</label>
            <input type='number' id='daily_target' class='form-control' value='2' min='1' max='10' onchange='optimizeSchedule()'>
        </div>
    </div>
    
    <div class='card-panel'>
        <div style='display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;'>
            <h3 style='margin:0; color: var(--text-main); font-size: 20px; font-weight: 600;'>" . htmlspecialchars($str_customschedule, ENT_QUOTES, 'UTF-8') . "</h3>
            <span id='status-label' style='display: none !important;'>" . htmlspecialchars($str_planning, ENT_QUOTES, 'UTF-8') . "</span>
        </div>
        
        <div id='itinerary-container' class='task-list'>
        </div>
    </div>
</div>
</div>";

// Process the arrays in PHP first
$json_failures = json_encode($failures);
$json_pending_tasks = json_encode($pending_tasks);
?>

<script>
    const userVelocity = <?php echo $velocity; ?>;
    const modulesRemaining = <?php echo $modules_remaining; ?>;
    const userFailures = <?php echo $json_failures; ?>;
    const pendingTasks = <?php echo $json_pending_tasks; ?>;

    // Localized response strings for the JavaScript execution environment
    const langStrings = {
        planningdots: <?php echo json_encode($str_planningdots); ?>,
        ready: <?php echo json_encode($str_ready); ?>,
        finishby: <?php echo json_encode($str_finishby); ?>,
        coursecompleted: <?php echo json_encode($str_coursecompleted); ?>,
        activitytype: <?php echo json_encode($str_activitytype); ?>,
        criticaldue: <?php echo json_encode($str_criticaldue); ?>,
        estimatedtime: <?php echo json_encode($str_estimatedtime); ?>,
        nopendingtasks: <?php echo json_encode($str_nopendingtasks); ?>,
        connectionerror: <?php echo json_encode($str_connectionerror); ?>
    };

    function optimizeSchedule() {
        let hrsAvailable = document.getElementById('hours_per_day').value;
        let targetPace = document.getElementById('daily_target').value;
        let itineraryBox = document.getElementById('itinerary-container');
        let statusLabel = document.getElementById('status-label');
        
        statusLabel.innerText = langStrings.planningdots;
        statusLabel.style.color = '#d97706';

        // Fire request to Flask
        fetch('http://13.126.114.242:5000/ai-agent-planner', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'ngrok-skip-browser-warning': 'true'
            },
            body: JSON.stringify({
                velocity: userVelocity,
                hours_per_day: hrsAvailable,
                modules_remaining: modulesRemaining,
                failures: userFailures,
                pending_tasks: pendingTasks, 
                student_daily_target: targetPace
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                console.error('Planner Core Error:', data.error);
                return;
            }

            document.getElementById('projected-date').innerText = data.modules_remaining > 0 ? 
                langStrings.finishby + ' ' + data.finish_date : langStrings.coursecompleted;
            document.getElementById('adaptive-advice').innerText = data.advice;
            
            statusLabel.innerText = data.status === 'Optimized' ? langStrings.ready : data.status.toUpperCase();
            if(data.status === 'Optimized') {
                statusLabel.style.color = 'var(--brand-teal)';
            } else {
                statusLabel.style.color = '#ef4444';
            }

            // Map new tasks
            itineraryBox.innerHTML = '';
            if(data.daily_tasks && data.daily_tasks.length > 0) {
                data.daily_tasks.forEach((task, index) => {
                    let cssClass = '';
                    if (task.priority === 'remedial') cssClass = 'remediation';
                    if (task.priority === 'critical') cssClass = 'critical';
                    
                    let priorityBadge = task.priority === 'critical' ? 
                        `<span style='background:#ef4444; color:white; padding:2px 8px; border-radius:4px; font-size:10px; margin-left:12px; font-weight:bold;'>${langStrings.criticaldue}</span>` : "";
                    
                    // Render Task (Without Checkbox)
                    let taskHtml = `
                        <div class='task-item ${cssClass}' id='task-wrapper-${index}'>
                            <div class='task-details'>
                                <h4>${task.task} ${priorityBadge}</h4>
                                <span>${langStrings.activitytype}: ${task.type}</span>
                            </div>
                            <div class='task-time'>⏰ ${task.estimated}</div>
                        </div>
                    `;
                    itineraryBox.innerHTML += taskHtml;
                });
            } else {
                itineraryBox.innerHTML = `<p style="color: var(--text-muted); text-align:center; padding: 30px; font-size: 14px;">${langStrings.nopendingtasks}</p>`;
            }
        })
        .catch(err => {
            console.error('Network Scheduler Error:', err);
            itineraryBox.innerHTML = `<p style="color: #ef4444; text-align: center; padding: 20px; font-weight: 600;">${langStrings.connectionerror}</p>`;
        });
    }

    document.addEventListener('DOMContentLoaded', optimizeSchedule);
</script>

<?php
echo $OUTPUT->footer();
?>
