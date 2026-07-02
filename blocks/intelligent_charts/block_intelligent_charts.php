<?php
defined('MOODLE_INTERNAL') || die();

class block_intelligent_charts extends block_base {
    
    public function init() {
        $this->title = get_string('pluginname', 'block_intelligent_charts');
    }

    public function specialization() {
        $this->title = get_string('pluginname', 'block_intelligent_charts');
    }

    public function get_title() {
        return get_string('pluginname', 'block_intelligent_charts');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        global $USER, $DB;
        
        // --- 1. PRECISE ROLE DETECTION ---
        $is_admin = is_siteadmin(); 
        $is_faculty = false;
        $faculty_course_ids = [];

        if (!$is_admin) {
            $my_courses = enrol_get_users_courses($USER->id, true, 'id');
            foreach ($my_courses as $course) {
                $coursecontext = context_course::instance($course->id);
                if (has_capability('moodle/course:update', $coursecontext, $USER->id) || 
                    has_capability('moodle/course:manageactivities', $coursecontext, $USER->id)) {
                    
                    $is_faculty = true;
                    $faculty_course_ids[] = (int)$course->id;
                }
            }
        }

        // --- 2. TARGET ID ISOLATE LAYER (PER COURSE RESTRICTIONS) ---
        $target_student_ids = [];

        if ($is_faculty && !empty($faculty_course_ids)) {
            $target_shortnames = ['student', 'paidstudent'];
            list($role_sql, $role_params) = $DB->get_in_or_equal($target_shortnames, SQL_PARAMS_NAMED, 'strole');
            $allowed_role_ids = $DB->get_fieldset_sql("SELECT id FROM {role} WHERE shortname $role_sql", $role_params);

            if (!empty($allowed_role_ids)) {
                $course_contexts_ids = [];
                foreach ($faculty_course_ids as $course_id) {
                    $coursecontext = context_course::instance($course_id);
                    $course_contexts_ids[] = $coursecontext->id;
                }

                if (!empty($course_contexts_ids)) {
                    list($role_id_sql, $role_id_params) = $DB->get_in_or_equal($allowed_role_ids, SQL_PARAMS_NAMED, 'roleid');
                    list($ctx_sql, $ctx_params) = $DB->get_in_or_equal($course_contexts_ids, SQL_PARAMS_NAMED, 'ctx');
                    
                    $sql = "SELECT DISTINCT u.id FROM {user} u
                            JOIN {role_assignments} ra ON ra.userid = u.id
                            WHERE ra.roleid $role_id_sql AND ra.contextid $ctx_sql
                              AND u.deleted = 0 AND u.suspended = 0 AND u.id != :currentuser";
                    
                    $params = array_merge($ctx_params, $role_id_params, ['currentuser' => $USER->id]);
                    $students = $DB->get_records_sql($sql, $params);
                    if ($students) {
                        foreach ($students as $s) { $target_student_ids[] = (int)$s->id; }
                    }
                }
            }
        }

        // --- 3. API Connection Link ---
        $role_str = $is_admin ? 'admin' : ($is_faculty ? 'faculty' : 'student');
        $payload_data = ["userid" => $USER->id, "role" => $role_str, "courseid" => 0, "generate_ai" => false];
        if ($is_faculty) {
            $payload_data["target_student_ids"] = array_values(array_unique($target_student_ids));
        }

        //$ch = curl_init("http://localhost:5000/api/dashboard");
        $ch = curl_init("http://13.126.114.242:5000/api/dashboard");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $api_data = ($response && is_string($response)) ? $response : '{"chart": null}';

        $this->content = new stdClass;
        
        // Heading Text Context Configurations
        if ($is_admin) {
            $chart_title = get_string('admintitle', 'block_intelligent_charts');
            $chart_desc = get_string('admindesc', 'block_intelligent_charts');
            $x_title = get_string('timelinemonths', 'block_intelligent_charts');
            $y_title = get_string('totalcombinedtime', 'block_intelligent_charts');
        } else if ($is_faculty) {
            $chart_title = get_string('facultytitle', 'block_intelligent_charts');
            $chart_desc = get_string('facultydesc', 'block_intelligent_charts');
            $x_title = get_string('dedicationhours', 'block_intelligent_charts');
            $y_title = get_string('quizaveragegrade', 'block_intelligent_charts');
        } else {
            $chart_title = get_string('studenttitle', 'block_intelligent_charts');
            $chart_desc = get_string('studentdesc', 'block_intelligent_charts');
            $x_title = get_string('timelinemonths', 'block_intelligent_charts');
            $y_title = get_string('performancemetricsscore', 'block_intelligent_charts');
        }

        $this->content->text = '
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <div style="background:#ffffff; padding:20px; border-radius:12px; border:1px solid #95a5a6; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h4 style="font-size:18px; font-weight:700; color:#2c3e50; margin:0 0 15px 0; text-transform:uppercase; letter-spacing:0.03em;">' . $chart_title . '</h4>
            <p style="font-size:12px; color:#777; margin-bottom:15px;">' . $chart_desc . '</p>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="mainChart"></canvas>
            </div>
        </div>            
        <script>
        (function() {
            const apiRes = ' . $api_data . ';
            const ctx = document.getElementById("mainChart")?.getContext("2d");
            
            if(ctx && apiRes && apiRes.chart) {
                const chartInfo = apiRes.chart;
                let displayDatasets = [];

                if ("' . $role_str . '" === "student") {
                    // STUDENT VIEW: Three distinct shades of teal lines
                    displayDatasets = [
                        {
                            label: "' . get_string('courseprogression', 'block_intelligent_charts') . '",
                            data: chartInfo.progression_data || [],
                            borderColor: "#005A5B", // Deep Pine Teal
                            backgroundColor: "transparent",
                            borderWidth: 3, tension: 0.2, pointRadius: 4, pointBackgroundColor: "#005A5B"
                        },
                        {
                            label: "' . get_string('assessmentgrades', 'block_intelligent_charts') . '",
                            data: chartInfo.grades_data || [],
                            borderColor: "#2E8B85", // Signature Mid Sea Teal
                            backgroundColor: "transparent",
                            borderWidth: 3, tension: 0.2, pointRadius: 4, pointBackgroundColor: "#2E8B85"
                        },
                        {
                            label: "' . get_string('platformengagement', 'block_intelligent_charts') . '",
                            data: chartInfo.engagement_data || [],
                            borderColor: "#008B8B", // Bright Dark Cyan/Teal
                            backgroundColor: "transparent",
                            borderWidth: 3, tension: 0.2, pointRadius: 4, pointBackgroundColor: "#008B8B"
                        }
                    ];
                } else if ("' . $role_str . '" === "faculty") {
                    // FACULTY VIEW: Scatter mapping using transparent mid-teal elements
                    displayDatasets = [{
                        label: "' . get_string('students', 'block_intelligent_charts') . '",
                        data: chartInfo.data || [],
                        borderColor: "#2E8B85",
                        backgroundColor: "rgba(46, 139, 133, 0.4)",
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }];
                } else {
                    // ADMIN VIEW: Single platform timeline line using soft filled teal area shadow
                    displayDatasets = [{
                        label: "' . get_string('platformusage', 'block_intelligent_charts') . '",
                        data: chartInfo.data || [],
                        borderColor: "#005A5B", // Deep Pine Teal
                        backgroundColor: "rgba(0, 90, 91, 0.08)", // Transparent soft teal shadow tint
                        borderWidth: 3, fill: true, tension: 0.2, pointRadius: 4
                    }];
                }

                new Chart(ctx, {
                    type: chartInfo.type,
                    data: {
                        labels: chartInfo.labels || [],
                        datasets: displayDatasets
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { 
                                display: ("' . $role_str . '" !== "admin"),
                                position: "top"
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        if ("' . $role_str . '" === "faculty") {
                                            const rawPoint = context.dataset.data[context.dataIndex];
                                            const username = rawPoint && rawPoint.name ? rawPoint.name : "' . get_string('unknownuser', 'block_intelligent_charts') . '";
                                            return "' . get_string('username', 'block_intelligent_charts') . ': " + username + " (Hours: " + context.parsed.x + ", Grade: " + context.parsed.y + "%)";
                                        }
                                        let val = context.parsed.y !== undefined ? context.parsed.y : context.parsed;
                                        return context.dataset.label + ": " + val;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { 
                                grid: { color: "#f9f9f9" },
                                title: { display: true, text: "' . $y_title . '", color: "#666", font: { size: 12, weight: "bold" } }
                            },
                            x: { 
                                grid: { display: false },
                                title: { display: true, text: "' . $x_title . '", color: "#666", font: { size: 12, weight: "bold" } }
                            }
                        }
                    }
                });
            } else {
                document.getElementById("mainChart").parentNode.innerHTML = 
                "<div style=\"text-align:center; padding-top:100px; color:#aaa;\">' . get_string('notrackingmetrics', 'block_intelligent_charts') . '</div>";
            }
        })();
        </script>';

        return $this->content;
    }
    
    public function applicable_formats() { return array('my' => true); }
}
