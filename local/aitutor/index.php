<?php
require('../../config.php');
require_login();

global $DB, $USER, $PAGE, $OUTPUT;
$userid = $USER->id;
$firstname = $USER->firstname;

$PAGE->set_url('/local/aitutor/index.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_aitutor'));
$PAGE->set_heading(get_string('pluginname', 'local_aitutor'));

echo $OUTPUT->header();
?>

<style>
:root{
    --primary-teal:#14b8a6;
    --primary-teal-dark:#0f766e;
    --primary-teal-light:#ccfbf1;

    --bg-main:#f8fafc;
    --bg-card:#ffffff;
    --bg-hover:#f0fdfa;

    --text-primary:#1f2937;
    --text-secondary:#64748b;

    --border:#d1fae5;
    --shadow:0 10px 25px rgba(20,184,166,.12);
}

body{
    background:#f4f7fb !important;
}

.chat-container{
    max-width:1200px;
    margin:25px auto;
    padding:25px;
    font-family:'SF Pro Display',sans-serif;
}

.nav-tabs-custom{
    display:flex;
    background:#ffffff;
    padding:8px;
    border-radius:14px;
    margin-bottom:25px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
}

.nav-btn{
    flex:1;
    padding:14px 20px;
    border:none;
    background:transparent;
    color:var(--text-secondary);
    border-radius:10px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:.3s;
}

.nav-btn:hover{
    background:var(--bg-hover);
    color:var(--primary-teal);
}

.nav-btn.active{
    background:var(--primary-teal);
    color:#fff;
}

.chat-box{
    height:420px;
    overflow-y:auto;
    background:#fff;
    border-radius:16px;
    padding:25px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    margin-bottom:20px;
}

.quiz-box{
    background:#fff;
    padding:30px;
    border-radius:16px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    margin-bottom:25px;
}

.quiz-box h5{
    font-size:18px;
    color:var(--primary-teal-dark);
    font-weight:700;
    margin-bottom:20px;
    border-bottom:2px solid var(--primary-teal-light);
    padding-bottom:10px;
}

.input-area{
    display:flex;
    gap:15px;
}

.input-area input.form-control{
    flex:1;
    background:#fff !important;
    border:1px solid #cbd5e1 !important;
    border-radius:10px !important;
    padding:14px 18px !important;
    color:var(--text-primary) !important;
}

.input-area input.form-control::placeholder{
    color:#94a3b8 !important;
}

.input-area input.form-control:focus{
    border-color:var(--primary-teal) !important;
    box-shadow:0 0 0 4px rgba(20,184,166,.15) !important;
}

.btn-success{
    background:var(--primary-teal) !important;
    border:none !important;
    color:#fff !important;
    border-radius:10px !important;
    padding:0 24px !important;
}

.btn-success:hover{
    background:var(--primary-teal-dark) !important;
}

.btn-primary{
    background:#e6fffb !important;
    border:1px solid var(--primary-teal) !important;
    color:var(--primary-teal-dark) !important;
    border-radius:10px !important;
    padding:0 24px !important;
}

.btn-primary:hover{
    background:var(--primary-teal) !important;
    color:#fff !important;
}

.msg{
    margin:16px 0;
    display:flex;
}

.user{
    justify-content:flex-end;
}

.bot{
    justify-content:flex-start;
}

.bubble{
    padding:14px 20px;
    border-radius:14px;
    max-width:75%;
    font-size:14px;
    line-height:1.6;
}

.user .bubble{
    background:var(--primary-teal);
    color:#fff;
}

.bot .bubble{
    background:#f8fafc;
    color:var(--text-primary);
    border:1px solid #e2e8f0;
}

.typing{
    color:var(--primary-teal);
    font-style:italic;
}

.quiz-box label{
    display:block;
    margin-bottom:12px;
    padding:14px 16px;
    border:1px solid #e2e8f0;
    border-radius:10px;
    background:#fff;
    color:var(--text-primary);
    cursor:pointer;
    transition:.3s;
}

.quiz-box label:hover{
    border-color:var(--primary-teal);
    background:#f0fdfa;
}

.quiz-box input[type="radio"]{
    accent-color:var(--primary-teal);
}

#adaptive-difficulty{
    color:var(--text-secondary) !important;
}

#adaptive-score{
    color:var(--primary-teal-dark) !important;
    font-size:18px;
    font-weight:700;
}

.chat-box::-webkit-scrollbar{
    width:8px;
}

.chat-box::-webkit-scrollbar-thumb{
    background:var(--primary-teal);
    border-radius:8px;
}

.chat-box::-webkit-scrollbar-track{
    background:#f1f5f9;
}

@media(max-width:768px){
    .nav-tabs-custom{
        flex-direction:column;
    }

    .input-area{
        flex-direction:column;
    }

    .input-area button{
        width:100%;
    }
}
</style>


<div class="chat-container">
    <div class="nav-tabs-custom">
        <button id="btn-chat" onclick="showSection('chat')" class="nav-btn active"><?php echo get_string('aitutor', 'local_aitutor'); ?></button>
        <button id="btn-quiz" onclick="showSection('quiz')" class="nav-btn"><?php echo get_string('quizgenerator', 'local_aitutor'); ?></button>
        <button id="btn-adaptive" onclick="showSection('adaptive')" class="nav-btn"><?php echo get_string('adaptivequiz', 'local_aitutor'); ?></button>
    </div>

    <div id="chat-section" class="section">
        <div id="chat-box" class="chat-box"></div>
        <div class="input-area">
            <input type="text" id="msg" class="form-control" placeholder="Query medical knowledge data stream..." />
            <button onclick="sendMsg()" class="btn btn-success"><?php echo get_string('send', 'local_aitutor'); ?></button>
        </div>
    </div>

    <div id="quiz-section" class="section" style="display:none;">
        <div class="quiz-box">
            <h5>Generate Diagnostic Quiz Workspace</h5>
            <div class="input-area">
                <input type="text" id="quiz-topic" class="form-control" placeholder="Specify research domain (e.g. neuro-informatics)..." />
                <button onclick="generateQuiz()" class="btn btn-primary"><?php echo get_string('generate', 'local_aitutor'); ?></button>
            </div>
            <div id="quiz-output" style="margin-top:25px; color: var(--text-data); line-height: 1.6;"></div>
        </div>
    </div>

    <div id="adaptive-section" class="section" style="display:none;">
        <div class="quiz-box">
            <h5>Adaptive Metric Engine</h5>
            <div class="input-area">
                <input type="text" id="adaptive-topic" class="form-control" placeholder="Specify optimization subject matter..." />
                <button onclick="generateAdaptiveQuiz()" class="btn btn-success"><?php echo get_string('initializesession', 'local_aitutor'); ?></button>
            </div>
            <div id="adaptive-quiz-output" style="margin-top:25px;"></div>
            
            <div style="display: flex; gap: 12px; align-items: center; margin-top: 20px;">
                <button type="button" id="submitAdaptiveBtn" class="btn btn-success" style="display:none;" onclick="submitAdaptiveQuiz()"><?php echo get_string('submitmatrix', 'local_aitutor'); ?></button>
                <button id="retakeBtn" class="btn btn-primary" onclick="retakeQuiz()" style="display:none;"><?php echo get_string('recalibrate', 'local_aitutor'); ?></button>
            </div>
            
            <div id="adaptive-difficulty" style="margin-top:20px; font-weight:700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-data);"></div>
            <div id="adaptive-score" style="margin-top:6px; font-weight:700; font-size: 14px; text-transform: uppercase; color: var(--neon-cyan); text-shadow: 0 0 8px rgba(0, 242, 254, 0.3);"></div>
        </div>
    </div>
</div>

<script>
    const API_PROXY_BASE = "/moodle/api";
    const MOODLE_USER_ID = <?php echo (int)$USER->id; ?>;
    const USER_FIRSTNAME = <?php echo json_encode($firstname); ?>;

    // Run layout initialization as soon as DOM completely loads
    document.addEventListener("DOMContentLoaded", function() {
        initializeChatWorkspace();
    });

    function initializeChatWorkspace() {
        let chat = document.getElementById("chat-box");
        if (!chat) return;

        // Pool of random conversational greeting matrices matching Gemini styles
        const greetingTemplates = [
            `Hi ${USER_FIRSTNAME}, what's the move?`,
            `Hi ${USER_FIRSTNAME}, let's get into it.`,
            `How can I help you today, ${USER_FIRSTNAME}?`,
            `Welcome back, ${USER_FIRSTNAME}. Ready to analyze some data streams?`,
            `Hello ${USER_FIRSTNAME}! What are we building or researching today?`
        ];

        // Pick a truly random template index on initialization
        const randomGreeting = greetingTemplates[Math.floor(Math.random() * greetingTemplates.length)];

        // Render the greeting centered inside the chat area with a premium teal ambient glow effect
        chat.innerHTML = `
            <div id="ai-minimalist-welcome" style="
                display: flex; 
                flex-direction: column; 
                align-items: center; 
                justify-content: center; 
                height: 100%; 
                text-align: center; 
                padding: 40px 20px;
                background: radial-gradient(circle, rgba(20, 184, 166, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
            ">
                <h2 style="
                    font-size: 32px; 
                    font-weight: 700; 
                    margin-bottom: 12px; 
                    font-family: 'SF Pro Display', -apple-system, sans-serif;
                    background: linear-gradient(135deg, var(--primary-teal-dark) 30%, var(--primary-teal) 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    letter-spacing: -0.5px;
                ">
                    ${randomGreeting}
                </h2>
            </div>
        `;
    }

    // Tab Navigation Switch Module
    function showSection(sectionId) {
        document.querySelectorAll('.section').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
        
        const targetSection = document.getElementById(sectionId + '-section');
        const targetButton = document.getElementById('btn-' + sectionId);
        
        if (targetSection) targetSection.style.display = 'block';
        if (targetButton) targetButton.classList.add('active');
    }

    function formatResponse(text) {
        text = text.replace(/[#*]+/g, "");
        text = text.replace(/(Fundamentals of Project Management)/gi, "<b style='color:#fff;'>$1</b><br><br>");
        text = text.replace(/(\d+\.\s)/g, "<br><b style='color:var(--neon-cyan);'>$1</b>");
        text = text.replace(/\n/g, "<br>");
        return text;
    }

    // ================= CHAT PROCESSING =================
    async function sendMsg() {
        let input = document.getElementById("msg");
        let text = input.value.trim();
        if (!text) return;

        let chat = document.getElementById("chat-box");
        // Remove the static greeting framework instantly on first interaction entry
        const minimalistWelcome = document.getElementById("ai-minimalist-welcome");
        if (minimalistWelcome) {
            chat.innerHTML = ""; // Clear out the greeting entirely to free up vertical flow
        }
        
        chat.innerHTML += `
            <div class="msg user">
                <div class="bubble">${text}</div>
            </div>
        `;
        input.value = "";
        chat.innerHTML += `<div class="msg bot typing" id="typing">Awaiting telemetry data reply...</div>`;
        chat.scrollTop = chat.scrollHeight;

        try {
            let res = await fetch(`${API_PROXY_BASE}/v1/chat/completions`, {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({
                    messages: [{ role: "user", content: text }]
                })
            });

            let data = await res.json();
            const typingIndicator = document.getElementById("typing");
            if (typingIndicator) typingIndicator.remove();

            let raw = data.choices?.[0]?.message?.content || "No telemetry matrix received.";
            let reply = formatResponse(raw);

            chat.innerHTML += `
                <div class="msg bot">
                    <div class="bubble">${reply}</div>
                </div>
            `;
        } catch (error) {
            const typingIndicator = document.getElementById("typing");
            if (typingIndicator) typingIndicator.remove();
            chat.innerHTML += `
                <div class="msg bot">
                    <div class="bubble" style="color:#e53e3e;">Critical: Connection exception to AI processing segment.</div>
                </div>
            `;
        }
        chat.scrollTop = chat.scrollHeight;
    }

    // ================= QUIZ PROCESSING =================
    async function generateQuiz() {
        let topic = document.getElementById("quiz-topic").value.trim();
        if (!topic) return;

        let output = document.getElementById("quiz-output");
        output.innerHTML = "<span class='typing'>Compiling query data points...</span>";

        try {
            let res = await fetch(`${API_PROXY_BASE}/generate-quiz`, {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({ topic: topic })
            });

            let data = await res.json();
            let formatted = (data.quiz || "No metrics compiled.")
                .replace(/\n/g, "<br>")
                .replace(/(Q\d+\.)/g, "<br><b style='color:#fff;'>$1</b>")
                .replace(/(Answer:)/g, "<br><b style='color:var(--neon-cyan);'>Answer:</b>");

            output.innerHTML = formatted;
        } catch (error) {
            output.innerHTML = "<span style='color:#e53e3e;'>Failure executing matrix compilation.</span>";
        }
    }

    // ================= ADAPTIVE ENGINE PROCESSING =================
    let adaptiveData = [];

    async function generateAdaptiveQuiz() {
        document.getElementById("adaptive-score").innerHTML = "";
        let btn = document.getElementById("submitAdaptiveBtn");
        btn.style.display = "none";
        btn.disabled = false;

        let topic = document.getElementById("adaptive-topic").value.trim();
        if (!topic) return;

        let output = document.getElementById("adaptive-quiz-output");
        output.innerHTML = "<span class='typing'>Calibrating adaptive evaluation matrices...</span>";
        let savedDifficulty = localStorage.getItem("adaptiveDifficulty");

        try {
            let res = await fetch(`${API_PROXY_BASE}/generate-adaptive-quiz`, {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({
                    topic: topic,
                    difficulty: savedDifficulty || "medium"
                })
            });

            let data = await res.json();
            renderAdaptiveQuiz(data.quiz);

            document.getElementById("adaptive-difficulty").innerHTML = "System Difficulty Rating: " + (data.difficulty || "medium");
            btn.style.display = "block";
            localStorage.setItem("lastTopic", topic);
        } catch(e) {
            output.innerHTML = "<span style='color:#e53e3e;'>System error rendering runtime assessment configuration.</span>";
        }
    }

    function renderAdaptiveQuiz(quiz) {
        let output = document.getElementById("adaptive-quiz-output");
        output.innerHTML = "";
        adaptiveData = [];

        quiz.forEach((q, i) => {
            let html = `<div class="quiz-box" style="background: rgba(5, 11, 20, 0.3) !important; border-color: rgba(0, 242, 254, 0.08) !important;">`;
            html += `<b style="color: #ffffff; font-size: 15px;">Matrix Evaluation ${i + 1}: ${q.question}</b><br><br>`;
            let letters = ["A", "B", "C", "D"];

            q.options.forEach((opt, index) => {
                let letter = letters[index];
                html += `
                    <label>
                        <input type="radio" name="aq${i}" value="${letter}">
                        <span style="color:#fff; font-weight:700; margin-right:4px;">${letter}.</span> ${opt}
                    </label>
                `;
            });

            html += `<div id="afeedback${i}" style="margin-top:12px; font-size:13px; line-height:1.5;"></div>`;
            html += `</div>`;

            adaptiveData.push({
                answer: q.answer,
                explanation: q.explanation
            });
            output.innerHTML += html;
        });

        let btn = document.getElementById("submitAdaptiveBtn");
        btn.style.display = "block";
        btn.disabled = false;
    }

    function submitAdaptiveQuiz() {
        let total = adaptiveData.length;
        let correct = 0;

        adaptiveData.forEach((q, i) => {
            let selected = document.querySelector(`input[name="aq${i}"]:checked`);
            let feedback = document.getElementById(`afeedback${i}`);
            if (!feedback) return;

            if (!selected) {
                feedback.innerHTML = "<span style='color: #ecc94b; font-weight:700;'>⚠️ PROFILE GAP: NOT EVALUATED</span>";
                return;
            }

            if (selected.value === q.answer) {
                correct++;
                feedback.innerHTML = `<span style='color: #38a169; font-weight:700;'>✅ METRIC VALIDATED</span><br><span style='color: var(--text-data);'>${q.explanation}</span>`;
            } else {
                feedback.innerHTML = `<span style='color: #e53e3e; font-weight:700;'>❌ DISCREPANCY REGISTERED</span><br><span style='color: #ffffff;'><b>Expected Node Allocation:</b> ${q.answer}</span><br><span style='color: var(--text-data);'>${q.explanation}</span>`;
            }
        });

        let score = total > 0 ? (correct / total) * 100 : 0;
        document.getElementById("adaptive-score").innerHTML = `Profile Evaluation Accuracy: ${score.toFixed(0)}%`;
        updateAdaptiveDifficulty(score);

        document.getElementById("submitAdaptiveBtn").disabled = true;
        document.getElementById("retakeBtn").style.display = "block";
    }

    function updateAdaptiveDifficulty(score) {
        let difficulty = "medium";
        if (score < 50) difficulty = "easy";
        else if (score >= 80) difficulty = "hard";
        localStorage.setItem("adaptiveDifficulty", difficulty);
    }

    function retakeQuiz() {
        document.getElementById("retakeBtn").style.display = "none";
        generateAdaptiveQuiz();
    }

    // Capture Input Control Form Operations
    document.getElementById("msg").addEventListener("keypress", function(e) {
        if (e.key === "Enter") sendMsg();
    });

    document.getElementById("quiz-topic").addEventListener("keypress", function(e) {
        if (e.key === "Enter") generateQuiz();
    });
</script>

<?php
echo $OUTPUT->footer();
?>
