<?php
defined('MOODLE_INTERNAL') || die();

class block_custom_ai_chat extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_custom_ai_chat');
    }

    public function get_content() {
        global $COURSE;
        if ($this->content !== null) { return $this->content; }

        $this->content = new stdClass();
        $courseid = $COURSE->id;

        $html = '
        <div id="custom-chat-wrapper" style="padding: 5px;">
            <div id="custom-chat-output" style="height: 240px; overflow-y: auto; border: 1px solid #e0e0e0; background: #ffffff; padding: 15px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; color: #333; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                <p style="color: #888; margin: 0; font-style: italic; font-size: 12px;">Ask questions about this course...</p>
            </div>
            
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="text" id="custom-chat-input" placeholder="Type your question..." style="flex-grow: 1; height: 38px; background: #f9f9f9; border: 1px solid #ddd; color: #333; border-radius: 6px; padding: 0 12px; font-size: 13px; outline: none;">
                
                <button id="custom-chat-send-btn" style="background: #2E8B85; color: #ffffff; border: none; height: 38px; padding: 0 16px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 600; text-transform: uppercase;">Ask</button>
            </div>
        </div>';

        $html .= "
        <script>
        (function() {
            const sendBtn = document.getElementById('custom-chat-send-btn');
            const chatInput = document.getElementById('custom-chat-input');
            const chatOutput = document.getElementById('custom-chat-output');
            const targetCourseId = " . json_encode($courseid) . ";

            async function handleQuerySubmission() {
                const queryText = chatInput.value.trim();
                if (!queryText) return;

                // User Bubble (Teal Accent)
                chatOutput.innerHTML += `<div style='margin-bottom: 12px; text-align: right;'><span style='background: #e0f2f1; color: #2E8B85; padding: 8px 12px; border-radius: 12px 12px 0px 12px; display: inline-block; max-width: 85%; text-align: left; font-size: 13px;'><b>You:</b> \${queryText}</span></div>`;
                chatInput.value = '';
                chatOutput.scrollTop = chatOutput.scrollHeight;

                const loadingId = 'loader_' + Date.now();
                chatOutput.innerHTML += `<div id='\${loadingId}' style='margin-bottom: 10px; color: #888; font-size: 12px;'>AI is thinking...</div>`;

                try {
//                    const response = await fetch('http://localhost:5000/api/course-chatbot', {
 const response = await fetch('http://13.126.114.242:5000/api/course-chatbot', {  
                      method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ question: queryText, courseid: targetCourseId })
                    });
                    const data = await response.json();
                    document.getElementById(loadingId).remove();
                    
                    // AI Response Bubble (Neutral Light Gray)
                    chatOutput.innerHTML += `<div style='margin-bottom: 12px; text-align: left;'><span style='background: #f4f4f4; color: #333; padding: 8px 12px; border-radius: 12px 12px 12px 0px; display: inline-block; max-width: 85%; font-size: 13px;'><b>AI:</b> \${data.answer}</span></div>`;
                } catch (error) {
                    document.getElementById(loadingId).remove();
                    chatOutput.innerHTML += `<div style='margin-bottom: 10px; color: #d32f2f; font-size: 12px;'>Error: Connection failed.</div>`;
                }
                chatOutput.scrollTop = chatOutput.scrollHeight;
            }

            sendBtn.addEventListener('click', handleQuerySubmission);
            chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleQuerySubmission(); });
        })();
        </script>";

        $this->content->text = $html;
        return $this->content;
    }

    public function applicable_formats() {
        return array('all' => true);
    }
}
