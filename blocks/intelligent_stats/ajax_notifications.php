<?php
define('AJAX_SCRIPT', true);
// Connect to Moodle Core securely
require_once('../../config.php');
require_login(); // Crucial: Only logged-in users get data

global $DB, $USER;
$now = time();
$response = ['actions' => '', 'info' => '', 'ann' => '', 'count' => 0];

// 1. Fetch ACTIONS (Due Soon)
$sql_actions = "SELECT name, timestart FROM {event} WHERE courseid IN (SELECT courseid FROM {user_enrolments} ue JOIN {enrol} e ON ue.enrolid = e.id WHERE ue.userid = ?) AND timestart > ? ORDER BY timestart ASC LIMIT 5";
$actions = $DB->get_records_sql($sql_actions, array($USER->id, $now));

if ($actions) {
    foreach ($actions as $act) {
        $response['actions'] .= '<div style="padding: 10px; border-left: 4px solid #f44336; border-bottom: 1px solid #eee; background: white;"><b style="color:#d32f2f; font-size:12px;">DUE SOON</b><br><span style="font-size:13px; color:#333;">'.$act->name.'</span><br><small style="color:#888;">'.date('M d, g:i A', $act->timestart).'</small></div>';
    }
    $response['count'] = count($actions);
} else {
    $response['actions'] = '<div style="padding:20px; text-align:center; color:#888;">No pending actions! 🎉</div>';
}

// 2. Fetch INFO (Updates)
$sql_info = "SELECT subject, timecreated FROM {notifications} WHERE useridto = ? ORDER BY timecreated DESC LIMIT 5";
$info_alerts = $DB->get_records_sql($sql_info, array($USER->id));

if ($info_alerts) {
    foreach ($info_alerts as $inf) {
        $response['info'] .= '<div style="padding: 10px; border-left: 4px solid #2196F3; border-bottom: 1px solid #eee; background: white;"><span style="font-size:13px; color:#333;">'.strip_tags($inf->subject).'</span><br><small style="color:#888;">'.date('M d', $inf->timecreated).'</small></div>';
    }
} else {
    $response['info'] = '<div style="padding:20px; text-align:center; color:#888;">No new updates.</div>';
}

// 3. Fetch ANNOUNCEMENTS (News Forums)
$sql_ann = "SELECT d.name, d.timemodified FROM {forum_discussions} d JOIN {forum} f ON d.forum = f.id WHERE f.type = 'news' ORDER BY d.timemodified DESC LIMIT 5";
$announcements = $DB->get_records_sql($sql_ann);

if ($announcements) {
    foreach ($announcements as $ann) {
        $response['ann'] .= '<div style="padding: 10px; border-left: 4px solid #4CAF50; border-bottom: 1px solid #eee; background: white;"><span style="font-size:13px; color:#333;">'.$ann->name.'</span><br><small style="color:#888;">'.date('M d', $ann->timemodified).'</small></div>';
    }
} else {
    $response['ann'] = '<div style="padding:20px; text-align:center; color:#888;">No recent announcements.</div>';
}

echo json_encode($response);
die();
