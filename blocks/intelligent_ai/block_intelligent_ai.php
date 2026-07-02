<?php
defined('MOODLE_INTERNAL') || die();

class block_intelligent_ai extends block_base {
    
    public function init() {
        $this->title = get_string('pluginname', 'block_intelligent_ai');
    }

    public function specialization() {
        $this->title = get_string('pluginname', 'block_intelligent_ai');
    }

    /**
     * Step C: Explicitly override the UI header renderer
     * This forces Moodle's UI to output this text word-for-word, bypassing the database cache entirely.
     */
    public function get_title() {
        return get_string('pluginname', 'block_intelligent_ai');
    }

    public function get_content() {
        if ($this->content !== null) { return $this->content; }
        
        global $USER;
        
        // --- 1. Detect Role ---
        $syscontext = context_system::instance();
        $is_admin = is_siteadmin();
        $is_faculty = false;
        $roles = get_user_roles($syscontext, $USER->id, true);
        foreach ($roles as $role) {
            if (in_array($role->shortname, ['editingteacher', 'teacher', 'manager'])) {
                $is_faculty = true; break;
            }
        }
        $role_str = $is_admin ? 'admin' : ($is_faculty ? 'faculty' : 'student');
        
        // Fetch translated role string
        $translated_role = get_string($role_str, 'block_intelligent_ai');

        $this->content = new stdClass;

        // Pre-fetch language strings for the JS engine to prevent translation mismatches
        $str_analyzing = get_string('analyzing', 'block_intelligent_ai');
        $str_noinsights = get_string('noinsights', 'block_intelligent_ai');
        $str_connectionerror = get_string('connectionerror', 'block_intelligent_ai');
        $str_runanalysis = get_string('runanalysis', 'block_intelligent_ai');
        
        // --- 2. Build UI (White & Teal Theme Aligned with Dashboard Blocks) ---
        $html = '<div class="ai-box" style="background:#ffffff; padding:25px; border-radius:12px; border:1px solid #95a5a6; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;">';
        $html .= '<h4 style="font-size:18px; font-weight:700; color:#2c3e50; margin:0 0 15px 0; text-transform:uppercase; letter-spacing:0.03em;">' . get_string('aiinsightanalyst', 'block_intelligent_ai') . '</h4>';
        
        $html .= '<div id="block-ai-content" style="font-size:14px; color:#2c3e50; margin:15px 0; line-height: 1.6;">';
        $html .= '<p id="block-ai-text" style="margin-bottom:15px; color:#5f6a6a;">' . get_string('generateinsights', 'block_intelligent_ai') . ' <strong>' . $translated_role . '</strong></p>';
        $html .= '<button id="block-ai-btn" onclick="fetchBlockAI(\''.$role_str.'\')" style="background:#008b8b; color:#ffffff; border:none; padding:12px 15px; border-radius:8px; cursor:pointer; width:100%; font-weight:bold; text-transform:uppercase; font-size:14px; letter-spacing:0.05em; transition: background 0.3s;">' . get_string('runanalysis', 'block_intelligent_ai') . '</button>';
        $html .= '</div></div>';

        // --- 3. JS Engine ---
        $html .= "
        <script>
            function fetchBlockAI(role) {
                let contentBox = document.getElementById('block-ai-content');
                let btn = document.getElementById('block-ai-btn');
                if (!btn || !contentBox) return;
                
                btn.innerText = " . json_encode($str_analyzing) . ";
                btn.style.background = '#005A5B';
                btn.disabled = true;

                fetch('/moodle/local/intelligentdashboard/index.php?fetch_data=1&generate_ai=1&role=' + role)
                    .then(response => response.json())
                    .then(data => {
                        if(data.report) {
                            contentBox.innerHTML = formatAIReport(data.report);
                        } else {
                            contentBox.innerHTML = '<p style=\"color:#666; padding-top:10px;\">' + " . json_encode($str_noinsights) . " + '</p>';
                        }
                    })
                    .catch(err => {
                        contentBox.innerHTML = '<p style=\"color:#d32f2f; padding-top:10px;\">' + " . json_encode($str_connectionerror) . " + '</p>';
                        btn.innerText = " . json_encode($str_runanalysis) . ";
                        btn.style.background = '#008b8b';
                        btn.disabled = false;
                    });
            }

            // Client-side lightweight formatter to match layout structure perfectly
            function formatAIReport(text) {
                if (!text) return '';
                
                let lines = text.split('\\n');
                let parsedHtml = '<div style=\"padding-top: 10px;\">';
                let insideList = false;

                lines.forEach(line => {
                    let trimmed = line.trim();
                    if (!trimmed) return;

                    // Parse Section Headings (e.g., 'Performance Summary:', 'Critical Gaps:')
                    if (trimmed.endsWith(':') && !trimmed.startsWith('•') && trimmed.length < 40) {
                        if (insideList) {
                            parsedHtml += '</ul>';
                            insideList = false;
                        }
                        parsedHtml += '<h5 style=\"font-size: 14px; font-weight: 700; color: #2c3e50; margin: 20px 0 8px 0; text-transform: none; letter-spacing: 0.02em;\">' + trimmed + '</h5>';
                    } 
                    // Parse Bullet Points (e.g., '• Student performance indicates...')
                    else if (trimmed.startsWith('•') || trimmed.startsWith('-')) {
                        if (!insideList) {
                            parsedHtml += '<ul style=\"list-style-type: disc; padding-left: 20px; margin: 0 0 15px 0;\">';
                            insideList = true;
                        }
                        let content = trimmed.substring(1).trim();
                        parsedHtml += '<li style=\"margin-bottom: 8px; color: #444; font-size: 13.5px; line-height: 1.6;\">' + content + '</li>';
                    } 
                    // Standard Paragraphs
                    else {
                        if (insideList) {
                            parsedHtml += '</ul>';
                            insideList = false;
                        }
                        parsedHtml += '<p style=\"margin-bottom: 12px; color: #444; font-size: 13.5px;\">' + trimmed + '</p>';
                    }
                });

                if (insideList) {
                    parsedHtml += '</ul>';
                }

                parsedHtml += 'div>';
                return parsedHtml;
            }
        </script>";

        $this->content->text = $html;
        return $this->content;
    }
    
    public function applicable_formats() {
        return array('my' => true, 'course' => true); 
    }
}
