<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PULSE API Engine · Interactive Async Console</title>
    <meta name="description" content="High-performance asynchronous API console and developer dashboard for PULSE Cycle Engine backend.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-secondary: #111827;
            --bg-card: rgba(17, 24, 39, 0.75);
            --bg-glass: rgba(31, 41, 55, 0.4);
            --border-color: rgba(255, 255, 255, 0.08);
            --border-focus: rgba(99, 102, 241, 0.5);
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --accent-primary: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.25);
            --accent-secondary: #06b6d4;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --accent-amber: #f59e0b;
            --code-bg: #090d16;
            --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(6, 182, 212, 0.12) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(244, 63, 94, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
        }

        /* Header / Navbar */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            background: rgba(11, 15, 25, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-primary);
        }

        .brand-logo {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            color: white;
            box-shadow: 0 0 15px var(--accent-glow);
        }

        .brand-name {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #ffffff, #9ca3af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-badge {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .token-pill {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .token-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-rose);
            box-shadow: 0 0 8px rgba(244, 63, 94, 0.5);
            transition: all 0.3s ease;
        }

        .token-status-dot.active {
            background: var(--accent-emerald);
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
        }

        .btn-sm {
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .btn-sm:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Layout */
        .app-container {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 1.5rem;
            padding: 1.5rem 2rem;
            flex: 1;
            max-width: 1700px;
            margin: 0 auto;
            width: 100%;
        }

        @media (max-width: 1024px) {
            .app-container {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar: Endpoint Catalog */
        .sidebar-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: calc(100vh - 120px);
            position: sticky;
            top: 5rem;
        }

        .sidebar-header {
            padding: 1.2rem;
            border-bottom: 1px solid var(--border-color);
            background: rgba(0, 0, 0, 0.2);
        }

        .search-box {
            width: 100%;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.6rem 0.9rem;
            color: var(--text-primary);
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-box:focus {
            border-color: var(--accent-primary);
        }

        .endpoint-list {
            overflow-y: auto;
            flex: 1;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .endpoint-category-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 700;
            padding: 0.6rem 0.6rem 0.2rem;
        }

        .endpoint-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            text-align: left;
            width: 100%;
            color: var(--text-secondary);
            font-size: 0.82rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .endpoint-item:hover {
            background: var(--bg-glass);
            color: var(--text-primary);
            border-color: rgba(255, 255, 255, 0.05);
            transform: translateX(2px);
        }

        .endpoint-item.active {
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.4);
            color: #ffffff;
        }

        .method-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.2rem 0.45rem;
            border-radius: 4px;
            font-family: var(--font-mono);
            min-width: 44px;
            text-align: center;
        }

        .method-get { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .method-post { background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); }
        .method-put { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .method-delete { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }

        .endpoint-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
            flex: 1;
        }

        /* Main Console Panel */
        .console-panel {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .request-card, .response-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .request-bar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .select-method {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            font-family: var(--font-mono);
            outline: none;
            cursor: pointer;
        }

        .url-input {
            flex: 1;
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1.2rem;
            font-size: 0.95rem;
            font-family: var(--font-mono);
            outline: none;
            transition: all 0.2s;
        }

        .url-input:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 15px var(--accent-glow);
        }

        .btn-send {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.75rem;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px var(--accent-glow);
        }

        .btn-send:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-send:active {
            transform: translateY(0);
        }

        .btn-send.loading {
            opacity: 0.8;
            pointer-events: none;
        }

        /* Tabs */
        .tabs-header {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            margin: 1.2rem 0 1rem;
            padding-bottom: 0.5rem;
        }

        .tab-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            color: #ffffff;
            background: rgba(99, 102, 241, 0.2);
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* JSON Textarea / Editor */
        .code-textarea {
            width: 100%;
            height: 160px;
            background: var(--code-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            color: #38bdf8;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            line-height: 1.5;
            resize: vertical;
            outline: none;
        }

        .code-textarea:focus {
            border-color: var(--border-focus);
        }

        /* Response Viewer */
        .response-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .response-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            font-family: var(--font-mono);
        }

        .status-2xx { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-4xx { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-5xx { background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }
        .status-idle { background: rgba(156, 163, 175, 0.1); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.2); }

        .time-badge {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: var(--font-mono);
        }

        .response-body-pre {
            background: var(--code-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.2rem;
            color: #f1f5f9;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            line-height: 1.5;
            max-height: 480px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Quick Action Chips */
        .quick-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.8rem;
        }

        .chip-btn {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 0.3rem 0.75rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .chip-btn:hover {
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.4);
            color: #ffffff;
        }

        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: rgba(17, 24, 39, 0.95);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            z-index: 100;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Syntax highlighting colors */
        .json-key { color: #38bdf8; }
        .json-string { color: #4ade80; }
        .json-number { color: #f59e0b; }
        .json-boolean { color: #ec4899; }
        .json-null { color: #94a3b8; }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <nav class="navbar">
        <a href="/" class="brand">
            <div class="brand-logo">P</div>
            <div>
                <span class="brand-name">PULSE AI Engine</span>
                <span class="brand-badge">SPA Console</span>
            </div>
        </a>

        <div class="nav-actions">
            <div class="token-pill">
                <div class="token-status-dot" id="tokenDot"></div>
                <span id="tokenLabel">Bearer Auth: Active (Auto-Managed)</span>
            </div>
            <button class="btn-sm" onclick="setCustomToken()">Set Token</button>
            <button class="btn-sm" onclick="testHealthPing()">Health Check</button>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="app-container">

        <!-- Sidebar Catalog -->
        <aside class="sidebar-card">
            <div class="sidebar-header">
                <input type="text" class="search-box" id="endpointSearch" placeholder="Filter endpoints..." oninput="filterEndpoints(this.value)">
            </div>

            <div class="endpoint-list" id="endpointList">
                <div class="endpoint-category-title">Unified Engine Endpoints</div>
                <button class="endpoint-item active" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/engine/overview', null, 'Combined Cycle Overview (Summary, Biometrics, Discrepancy)')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/cycle-engine/engine/overview</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/engine/sync', null, 'Sync Cycle Dashboard & DB Persistence')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/cycle-engine/engine/sync</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/ai-summary', null, 'AI Cycle Summary')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/ai-summary</span>
                </button>

                <div class="endpoint-category-title">Calendar & Period</div>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/cycle-calendar/month?month=' + new Date().toISOString().slice(0, 7), null, 'Monthly Cycle Predictions')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/cycle-calendar/month</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/cycle-calendar/next-period', null, 'Next Period Forecast')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/cycle-calendar/next-period</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('POST', '/api/v1/cycle-calendar-inputs', {'start_date': new Date().toISOString().slice(0, 10), 'end_date': new Date(Date.now() + 86400000*4).toISOString().slice(0, 10), 'is_day_n': false}, 'Save Period Start/End Inputs')">
                    <span class="method-badge method-post">POST</span>
                    <span class="endpoint-name">/cycle-calendar-inputs</span>
                </button>

                <div class="endpoint-category-title">Biometrics (BBT & OPK)</div>
                <button class="endpoint-item" onclick="loadEndpoint('POST', '/api/v1/bbt/logs', {'temperature_f': 97.6, 'flags': []}, 'Log BBT Temperature')">
                    <span class="method-badge method-post">POST</span>
                    <span class="endpoint-name">/bbt/logs</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/bbt/ui', null, 'Fetch BBT Graph & UI Data')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/cycle-engine/bbt/ui</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('POST', '/api/v1/cycle-engine/opk/log', {'result': 'peak', 'intensity_ratio': 1.25, 'test_line_value': 0.8, 'control_line_value': 0.64}, 'Log OPK / LH Surge Test')">
                    <span class="method-badge method-post">POST</span>
                    <span class="endpoint-name">/cycle-engine/opk/log</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/opk/ui', null, 'Fetch OPK Window & History')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/cycle-engine/opk/ui</span>
                </button>

                <div class="endpoint-category-title">TTC & Marketplace</div>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/ttc/sync-overview', null, 'TTC Conception Overview & Surge Sync')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/ttc/sync-overview</span>
                </button>
                <button class="endpoint-item" onclick="loadEndpoint('GET', '/api/v1/marketplace/slate?metro_id=1', null, 'Provider Slate Discovery')">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint-name">/marketplace/slate</span>
                </button>
            </div>
        </aside>

        <!-- Main Console -->
        <main class="console-panel">

            <!-- Request Card -->
            <div class="request-card">
                <form id="apiForm" onsubmit="executeRequest(event)">
                    <div class="request-bar">
                        <select id="requestMethod" class="select-method">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                        <input type="text" id="requestUrl" class="url-input" value="/api/v1/cycle-engine/engine/overview" placeholder="/api/v1/...">
                        <button type="submit" id="sendBtn" class="btn-send">
                            <span id="btnIcon">⚡</span>
                            <span id="btnText">Send Request</span>
                        </button>
                    </div>

                    <div class="quick-chips">
                        <span style="font-size:0.75rem; color:var(--text-muted); align-self:center;">Presets:</span>
                        <button type="button" class="chip-btn" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/engine/overview', null)">Overview Engine</button>
                        <button type="button" class="chip-btn" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/engine/sync', null)">Sync DB</button>
                        <button type="button" class="chip-btn" onclick="loadEndpoint('GET', '/api/v1/cycle-calendar/month', null)">Calendar Month</button>
                        <button type="button" class="chip-btn" onclick="loadEndpoint('GET', '/api/v1/ttc/sync-overview', null)">TTC Overview</button>
                        <button type="button" class="chip-btn" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/bbt/ui', null)">BBT Graph</button>
                        <button type="button" class="chip-btn" onclick="loadEndpoint('GET', '/api/v1/cycle-engine/opk/ui', null)">OPK UI</button>
                    </div>

                    <!-- Tabs: Headers & Body -->
                    <div class="tabs-header">
                        <button type="button" class="tab-btn active" onclick="switchTab('bodyTab')">JSON Body</button>
                        <button type="button" class="tab-btn" onclick="switchTab('headersTab')">Headers</button>
                        <button type="button" class="tab-btn" onclick="formatJsonInput()">Format JSON</button>
                        <button type="button" class="tab-btn" onclick="clearBody()">Clear</button>
                    </div>

                    <div id="bodyTab" class="tab-content active">
                        <textarea id="requestPayload" class="code-textarea" placeholder='{"key": "value"}'></textarea>
                    </div>

                    <div id="headersTab" class="tab-content">
                        <textarea id="requestHeaders" class="code-textarea" placeholder="Accept: application/json&#10;Content-Type: application/json"></textarea>
                    </div>
                </form>
            </div>

            <!-- Response Card -->
            <div class="response-card">
                <div class="response-header">
                    <div class="response-meta">
                        <span id="responseStatus" class="status-badge status-idle">Ready</span>
                        <span id="responseTime" class="time-badge">0 ms</span>
                        <span id="responseSize" class="time-badge">0 KB</span>
                    </div>
                    <div>
                        <button class="btn-sm" onclick="copyResponse()">Copy JSON</button>
                    </div>
                </div>

                <pre id="responseBody" class="response-body-pre">Click "Send Request" to execute an asynchronous API call with zero page reloads.</pre>
            </div>

        </main>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <span id="toastIcon">✓</span>
        <span id="toastMsg">Action completed successfully</span>
    </div>

    <script>
        // Default Sanctum test token if available or fallback
        let authToken = localStorage.getItem('pulse_bearer_token') || 'test_token';

        function updateTokenStatus() {
            const dot = document.getElementById('tokenDot');
            const label = document.getElementById('tokenLabel');
            if (authToken) {
                dot.classList.add('active');
                label.innerText = 'Bearer Auth: ' + (authToken === 'test_token' ? 'Default Sanctum' : 'Custom Token');
            } else {
                dot.classList.remove('active');
                label.innerText = 'Bearer Auth: None';
            }
        }
        updateTokenStatus();

        function setCustomToken() {
            const token = prompt('Enter Sanctum Bearer Token:', authToken);
            if (token !== null) {
                authToken = token.trim();
                localStorage.setItem('pulse_bearer_token', authToken);
                updateTokenStatus();
                showToast('Authentication token updated');
            }
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            const target = document.getElementById(tabId);
            if (target) target.classList.add('active');
            event.target.classList.add('active');
        }

        function loadEndpoint(method, url, body = null, desc = '') {
            document.getElementById('requestMethod').value = method;
            document.getElementById('requestUrl').value = url;
            
            if (body) {
                document.getElementById('requestPayload').value = JSON.stringify(body, null, 2);
            } else {
                document.getElementById('requestPayload').value = '';
            }

            // Highlight active endpoint in sidebar
            document.querySelectorAll('.endpoint-item').forEach(item => item.classList.remove('active'));
            if (event && event.currentTarget && event.currentTarget.classList) {
                event.currentTarget.classList.add('active');
            }

            // Auto execute GET requests smoothly
            if (method === 'GET') {
                executeRequest();
            }
        }

        function formatJsonInput() {
            try {
                const val = document.getElementById('requestPayload').value.trim();
                if (val) {
                    const parsed = JSON.parse(val);
                    document.getElementById('requestPayload').value = JSON.stringify(parsed, null, 2);
                    showToast('JSON formatted');
                }
            } catch (e) {
                showToast('Invalid JSON in request body', '⚠️');
            }
        }

        function clearBody() {
            document.getElementById('requestPayload').value = '';
        }

        async function executeRequest(e) {
            if (e) e.preventDefault();

            const method = document.getElementById('requestMethod').value;
            const url = document.getElementById('requestUrl').value.trim();
            const payloadRaw = document.getElementById('requestPayload').value.trim();
            const sendBtn = document.getElementById('sendBtn');
            const btnIcon = document.getElementById('btnIcon');
            const btnText = document.getElementById('btnText');
            const statusBadge = document.getElementById('responseStatus');
            const timeBadge = document.getElementById('responseTime');
            const sizeBadge = document.getElementById('responseSize');
            const responseBody = document.getElementById('responseBody');

            if (!url) {
                showToast('Please enter a valid endpoint URL', '⚠️');
                return;
            }

            // UI Loading state
            sendBtn.classList.add('loading');
            btnIcon.innerHTML = '<div class="spinner"></div>';
            btnText.innerText = 'Calling API...';
            statusBadge.className = 'status-badge status-idle';
            statusBadge.innerText = 'Fetching...';

            const startTime = performance.now();

            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (authToken) {
                headers['Authorization'] = `Bearer ${authToken}`;
            }

            const customHeadersRaw = document.getElementById('requestHeaders').value.trim();
            if (customHeadersRaw) {
                customHeadersRaw.split('\n').forEach(line => {
                    const parts = line.split(':');
                    if (parts.length >= 2) {
                        headers[parts[0].trim()] = parts.slice(1).join(':').trim();
                    }
                });
            }

            const options = {
                method: method,
                headers: headers
            };

            if (method !== 'GET' && method !== 'HEAD' && payloadRaw) {
                try {
                    options.body = JSON.stringify(JSON.parse(payloadRaw));
                } catch (err) {
                    options.body = payloadRaw;
                }
            }

            try {
                const res = await fetch(url, options);
                const elapsed = Math.round(performance.now() - startTime);
                timeBadge.innerText = `${elapsed} ms`;

                const text = await res.text();
                const bytes = new Blob([text]).size;
                sizeBadge.innerText = bytes > 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${bytes} B`;

                // Update Status Pill
                statusBadge.innerText = `${res.status} ${res.statusText || (res.ok ? 'OK' : 'Error')}`;
                if (res.status >= 200 && res.status < 300) {
                    statusBadge.className = 'status-badge status-2xx';
                } else if (res.status >= 400 && res.status < 500) {
                    statusBadge.className = 'status-badge status-4xx';
                } else {
                    statusBadge.className = 'status-badge status-5xx';
                }

                // Render JSON Syntax
                try {
                    const parsed = JSON.parse(text);
                    responseBody.innerHTML = syntaxHighlight(JSON.stringify(parsed, null, 2));
                } catch (e) {
                    responseBody.innerText = text || '(Empty Response Body)';
                }

            } catch (networkErr) {
                const elapsed = Math.round(performance.now() - startTime);
                timeBadge.innerText = `${elapsed} ms`;
                statusBadge.className = 'status-badge status-5xx';
                statusBadge.innerText = 'Network Error';
                responseBody.innerText = `Fetch Error: ${networkErr.message}`;
            } finally {
                sendBtn.classList.remove('loading');
                btnIcon.innerHTML = '⚡';
                btnText.innerText = 'Send Request';
            }
        }

        function syntaxHighlight(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                var cls = 'json-number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'json-key';
                    } else {
                        cls = 'json-string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
        }

        async function testHealthPing() {
            showToast('Running background health checks...', '🚀');
            const res = await fetch('/up', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                showToast('Backend health check passed (200 OK)', '✓');
            } else {
                showToast('Backend health check returned ' + res.status, '⚠️');
            }
        }

        function copyResponse() {
            const body = document.getElementById('responseBody').innerText;
            if (!body) return;
            navigator.clipboard.writeText(body).then(() => {
                showToast('Response JSON copied to clipboard', '📋');
            });
        }

        function showToast(msg, icon = '✓') {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerText = msg;
            document.getElementById('toastIcon').innerText = icon;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function filterEndpoints(query) {
            query = query.toLowerCase();
            document.querySelectorAll('.endpoint-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                if (text.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Shortcut: Ctrl+Enter or Cmd+Enter sends request
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                executeRequest();
            }
        });
    </script>
</body>
</html>
