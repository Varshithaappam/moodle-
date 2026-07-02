<?php
class block_intelligent_stats extends block_base {
    
    public function init() {
        $this->title = get_string('performancemetrics', 'block_intelligent_stats');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        global $USER, $CFG, $PAGE;
        
        // 1. ROLE DETECTION
        $syscontext = context_system::instance();
        $is_admin = is_siteadmin(); 
        $is_faculty = false;

        if (!$is_admin) {
            $sys_roles = get_user_roles($syscontext, $USER->id, true);
            if ($sys_roles) {
                foreach ($sys_roles as $role) {
                    if ($role->shortname === 'manager') { $is_admin = true; break; }
                    if (in_array($role->shortname, ['editingteacher', 'teacher', 'externalteacher', 'coursecreator'])) {
                        $is_faculty = true; break;
                    }
                }
            }
        }

        if (!$is_admin && !$is_faculty) {
            $courses = enrol_get_users_courses($USER->id, true);
            foreach ($courses as $c) {
                $coursecontext = context_course::instance($c->id);
                $course_roles = get_user_roles($coursecontext, $USER->id, true);
                if ($course_roles) {
                    foreach ($course_roles as $role) {
                        if (in_array($role->shortname, ['editingteacher', 'teacher', 'externalteacher', 'coursecreator'])) {
                            $is_faculty = true; break 2; 
                        }
                    }
                }
            }
        }

        // 2. CONNECT TO FLASK BACKEND
        $role_str = $is_admin ? 'admin' : ($is_faculty ? 'faculty' : 'student');
        $flask_url = "http://localhost:5000/api/dashboard";        

        $payload = json_encode(["userid" => $USER->id, "role" => $role_str, "courseid" => 0, "generate_ai" => false]);
        $ch = curl_init($flask_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'ngrok-skip-browser-warning: true']);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $kpi = isset($data['kpi']) ? $data['kpi'] : [];
        
        // --- FIX FOR THE 'ROUND()' ERROR IN PHP ---
        // Safely extract variables and prevent string values from breaking the round() function
        $val_comp   = $kpi['completed'] ?? 0;
        $val_mods   = $kpi['modules'] ?? 0; 
        $val_hours  = isset($kpi['hours']) ? round((float)$kpi['hours']) : 0;
        $val_certs  = $kpi['certificates'] ?? 0;
        $val_streak = $kpi['streak'] ?? 0;
        $val_acc    = $kpi['accuracy'] ?? '0%';
        $val_assign = $kpi['assignment_rate'] ?? '0%';


        // 3. UI RENDERING
        $this->content = new stdClass;
        $html = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        
        // CSS & SCROLLABLE MODAL
        $html .= '<style>
            .act-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
            .modal-content { background:#fff; margin:10% auto; padding:20px; width:90%; max-width:400px; border-radius:10px; position:relative; box-shadow:0 5px 15px rgba(0,0,0,0.3); }
            #mList { max-height: 250px; overflow-y: auto; margin-top: 10px; padding-right: 5px; }
            .heat-row { border-bottom: 1px solid #eee; padding: 8px 0; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
            .heat-row b { color: #008b8b; display: block; font-size: 12px; }
        </style>
        <div id="actModal" class="act-modal">
            <div class="modal-content">
                <span onclick="document.getElementById(\'actModal\').style.display=\'none\'" style="float:right; cursor:pointer; font-size:20px; font-weight:bold;">&times;</span>
                <h4 id="mDate" style="margin-top:0;">Activity Details</h4>
                <div id="mList"></div>
            </div>
        </div>';
        
        // KPI CARDS (Language Supported & Role Based)
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 25px;">';

        if ($is_admin) {
            $html .= $this->card(get_string('activeusers', 'block_intelligent_stats'), $val_comp);
            $html .= $this->card(get_string('platformhrs', 'block_intelligent_stats'), $val_hours);
            $html .= $this->card(get_string('certificatedissued', 'block_intelligent_stats'), $val_certs);
        } else if ($is_faculty) {
            $html .= $this->card(get_string('learnersuccess', 'block_intelligent_stats'), $val_acc);
            $html .= $this->card(get_string('assignmentsubmission', 'block_intelligent_stats'), $val_assign);
            $html .= $this->card(get_string('studenthours', 'block_intelligent_stats'), $val_hours);
        } else {
            $html .= $this->card(get_string('coursescompleted', 'block_intelligent_stats'), $val_comp);
            $html .= $this->card(get_string('modulescompleted', 'block_intelligent_stats'), $val_mods);
            $html .= $this->card(get_string('hoursspent', 'block_intelligent_stats'), $val_hours);
            $html .= $this->card(get_string('streak', 'block_intelligent_stats'), $val_streak . ' ' . get_string('days', 'block_intelligent_stats'));
$html .= '<a href="'.$CFG->wwwroot.'/local/intelligentdashboard/planner.php" style="text-decoration:none;">
                <div style="background:#008b8b; border:1px solid #008b8b; padding:15px; border-radius:10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align:center; color:#fff;">
                    <h5 style="margin:0 0 8px 0; font-size:14px; font-weight:700; color:#fff; text-transform:uppercase;">AI Agent Planner</h5>
                    <div style="font-size: 20px;">🚀</div>
                </div>
              </a>';
        }
        $html .= '</div>';

        // 4. CHARTS & HEATMAP (STUDENTS ONLY)
        if (!$is_admin && !$is_faculty) {
            
            // Prepare Time Analytics Chart Data safely
            $processed_labels = [];
            $processed_data = [];
            $hours_breakdown_raw = $kpi['hours_breakdown'] ?? [];
            
            foreach ($hours_breakdown_raw as $item) {
                if (isset($item['name']) && strtolower($item['name']) !== 'accelara') {
                    $processed_labels[] = $item['name'];
                    $processed_data[] = round(((float)$item['percentage'] / 100) * (float)$val_hours);
                }
            }

            $html .= '
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div style="background:#ffffff; border:1px solid #95a5a6; border-radius:12px; padding:20px; text-align:center;">
                    <h4 style="margin-bottom:15px; text-transform:uppercase;">' . get_string('timeanalytics', 'block_intelligent_stats') . '</h4>
                    <canvas id="intelligentHoursPieChart" style="max-height:200px;"></canvas>
                </div>
                <div style="background:#ffffff; border:1px solid #95a5a6; border-radius:12px; padding:20px;">
                    <h4 style="margin-bottom:15px; text-transform:uppercase;">' . get_string('activityheatmap', 'block_intelligent_stats') . '</h4>
                    <div id="intelligentActivityHeatmapContainer" style="display: grid; grid-template-columns: repeat(10, 1fr); gap: 4px;"></div>
                </div>
            </div>';

            $js_labels = json_encode($processed_labels);
            $js_values = json_encode($processed_data);
            $js_heat = json_encode($kpi['activity_heatmap'] ?? []);

            $html .= "<script>
            document.addEventListener('DOMContentLoaded', function() {
                
                // Initialize Pie Chart
                var pieCanvas = document.getElementById('intelligentHoursPieChart');
                if (pieCanvas) {
                    new Chart(pieCanvas.getContext('2d'), {
                        type: 'pie',
                        data: { labels: {$js_labels}, datasets: [{ data: {$js_values}, backgroundColor: ['#008b8b', '#00b5b5', '#a3d9d9', '#f1f2f6', '#4facfe'] }] },
                        options: { plugins: { tooltip: { callbacks: { label: function(c) { return c.label + ': ' + c.raw + ' hours'; } } } } }
                    });
                }

                // Initialize Heatmap
                var container = document.getElementById('intelligentActivityHeatmapContainer');
                var logData = {$js_heat} || {};
                
                // Add Heatmap Legend
                var legend = document.createElement('div');
                legend.style.cssText = 'display:flex; justify-content:center; gap:8px; margin-top:12px; font-size:11px; color:#555; grid-column: span 10;';
                legend.innerHTML = 
                    '<div style=\"display:flex; align-items:center; gap:4px;\"><div style=\"width:12px;height:12px;background:#f1f2f6;border:1px solid #95a5a6;border-radius:2px;\"></div> 0</div>' +
                    '<div style=\"display:flex; align-items:center; gap:4px;\"><div style=\"width:12px;height:12px;background:#a3d9d9;border:1px solid #95a5a6;border-radius:2px;\"></div> 1-3</div>' +
                    '<div style=\"display:flex; align-items:center; gap:4px;\"><div style=\"width:12px;height:12px;background:#00b5b5;border:1px solid #95a5a6;border-radius:2px;\"></div> 4-7</div>' +
                    '<div style=\"display:flex; align-items:center; gap:4px;\"><div style=\"width:12px;height:12px;background:#008b8b;border:1px solid #95a5a6;border-radius:2px;\"></div> >7</div>';
                container.parentNode.appendChild(legend);

                // Build Heatmap Grid
                for (var i = 29; i >= 0; i--) {
                    var d = new Date(); d.setDate(d.getDate() - i);
                    var dStr = d.toISOString().split('T')[0];
                    var count = logData[dStr] || 0;
                    
                    // Intensity Color Logic
                    var color = '#f1f2f6'; 
                    if (count >= 1 && count <= 3) color = '#a3d9d9';
                    else if (count >= 4 && count <= 7) color = '#00b5b5';
                    else if (count > 7) color = '#008b8b';

                    var cell = document.createElement('div');
                    cell.style.cssText = 'aspect-ratio:1; border-radius:4px; border:1px solid #95a5a6; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:11px; background:' + color;
                    if (count > 7) cell.style.color = '#fff';
                    cell.innerText = d.getDate();
                    
                    cell.onclick = function(date) { return function() {
                        document.getElementById('actModal').style.display = 'block';
                        document.getElementById('mDate').innerText = 'Date: ' + date;
                        document.getElementById('mList').innerHTML = '<div style=\"padding:10px;\">Loading data...</div>';
                        
                        // Fetch using Public IP
                        fetch('http://localhost:5000/api/activities?user_id={$USER->id}&date=' + date)
                            .then(r => r.json())
                            .then(data => {
                                if(data.length === 0) {
                                    document.getElementById('mList').innerHTML = '<div style=\"padding:15px; color:#777;\">No activities found on this day.</div>';
                                } else {
                                    document.getElementById('mList').innerHTML = data.map(a => '<div class=\"heat-row\"><div><b>'+a.course+'</b>'+a.activity+'</div><small style=\"color:#888;\">'+a.time+'</small></div>').join('');
                                }
                            }).catch(err => {
                                document.getElementById('mList').innerHTML = '<div style=\"padding:15px; color:red;\">Error loading data.</div>';
                            });
                    }; }(dStr);
                    
                    container.appendChild(cell);
                }
            });
            </script>";
        }

        $this->content->text = $html;
        return $this->content;
    }

    private function card($title, $value) { 
        return '<div style="background:#ffffff; border:1px solid #95a5a6; padding:15px; border-radius:10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align:center;">
                    <h5 style="margin:0 0 8px 0; font-size:14px; font-weight:700; color:#2c3e50; text-transform:uppercase;">' . $title . '</h5>
                    <h2 style="margin:0; color:#008b8b; font-weight:bold; font-size:26px;">' . $value . '</h2>
                </div>'; 
    }
    
    public function applicable_formats() {
        return array('my' => true); 
    }
}
