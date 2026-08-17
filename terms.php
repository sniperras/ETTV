<?php
// terms.php - Terms of Service for ET TV Display System
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - ET TV Display</title>
    <link rel="icon" type="image/png" href="/img/ethiopian_logo.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
        }

        .header p {
            opacity: 0.8;
            margin-top: 5px;
            font-size: 14px;
        }

        .container {
            flex: 1;
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .card h2 {
            color: #1a1a2e;
            font-size: 22px;
            margin-bottom: 20px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .card h3 {
            color: #333;
            font-size: 18px;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        .card p {
            color: #555;
            line-height: 1.8;
            margin-bottom: 15px;
            font-size: 15px;
        }

        .card ul {
            list-style: none;
            padding: 0;
            margin: 15px 0 20px 0;
        }

        .card ul li {
            padding: 10px 15px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            color: #444;
            line-height: 1.6;
            font-size: 14px;
        }

        .card ul li strong {
            color: #1a1a2e;
        }

        .card ul li.accept {
            border-left-color: #28a745;
            background: #f0fff4;
        }

        .footer {
            background: #1a1a2e;
            color: white;
            padding: 30px 20px;
            text-align: center;
            margin-top: auto;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
            margin: 0 15px;
            font-size: 14px;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .footer p {
            opacity: 0.7;
            font-size: 13px;
            margin-top: 10px;
        }

        .last-updated {
            color: #999;
            font-size: 13px;
            font-style: italic;
            margin-top: 10px;
        }

        .btn-back {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .btn-back:hover {
            background: #5a6fd6;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }

            .card {
                padding: 25px;
            }

            .card h2 {
                font-size: 20px;
            }

            .card p {
                font-size: 14px;
            }

            .footer a {
                display: inline-block;
                margin: 5px 10px;
            }
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>📋 Terms of Service</h1>
        <p>ET TV Display System - Ethiopian Airlines</p>
    </div>

    <div class="container">
        <div class="card">
            <h2>Terms of Service</h2>
            <p><strong>Last Updated:</strong> June 2026</p>

            <p>
                Welcome to the ET TV Display System ("the System"), operated by Ethiopian Airlines ("we", "our", "us").
                By accessing or using the System, you agree to be bound by these Terms of Service ("Terms"). If you do
                not agree to these Terms, please do not use the System.
            </p>

            <h3>1. Acceptance of Terms</h3>
            <p>
                By using the ET TV Display System, you acknowledge that you have read, understood, and agree to be bound
                by these Terms. These Terms apply to all users of the System, including administrators, content creators,
                and viewers.
            </p>

            <h3>2. Description of Service</h3>
            <p>
                The ET TV Display System is a digital signage solution designed to display content on television screens
                within Ethiopian Airlines facilities. The System allows authorized administrators to:
            </p>
            <ul>
                <li><strong>Create and Manage Content:</strong> Upload and organize images, videos, audio files, PDF documents, and website embeds.</li>
                <li><strong>Control Display Order:</strong> Arrange content in a specific sequence for display.</li>
                <li><strong>Monitor Display Status:</strong> View active content and system performance.</li>
                <li><strong>User Management:</strong> Administer user accounts and permissions.</li>
            </ul>

            <h3>3. User Accounts</h3>
            <p>
                To access certain features of the System, you must create an account. You are responsible for:
            </p>
            <ul>
                <li><strong>Account Security:</strong> Maintaining the confidentiality of your username and password.</li>
                <li><strong>Account Activity:</strong> All activities that occur under your account.</li>
                <li><strong>Accurate Information:</strong> Providing accurate and complete information during registration.</li>
                <li><strong>Unauthorized Access:</strong> Immediately notifying us of any unauthorized use of your account.</li>
            </ul>

            <h3>4. User Conduct</h3>
            <p>You agree to use the System in accordance with the following guidelines:</p>
            <ul>
                <li><strong>Compliance with Laws:</strong> You will not use the System for any illegal or unauthorized purpose.</li>
                <li><strong>Content Responsibility:</strong> You are solely responsible for the content you upload, publish, or display through the System.</li>
                <li><strong>No Interference:</strong> You will not interfere with or disrupt the System or its servers.</li>
                <li><strong>No Abuse:</strong> You will not use the System to harass, threaten, or defame others.</li>
                <li><strong>No Malicious Content:</strong> You will not upload viruses, malware, or other harmful code.</li>
            </ul>

            <h3>5. Content Guidelines</h3>
            <p>
                By uploading content to the System, you represent and warrant that:
            </p>
            <ul>
                <li><strong>Ownership:</strong> You own or have the necessary licenses, rights, consents, and permissions to use and authorize us to display the content.</li>
                <li><strong>Accuracy:</strong> The content is accurate, complete, and not misleading.</li>
                <li><strong>Appropriate Content:</strong> The content does not violate any applicable laws or regulations.</li>
                <li><strong>No Infringement:</strong> The content does not infringe any third-party intellectual property rights.</li>
            </ul>

            <h3>6. Intellectual Property</h3>
            <p>
                The System and its original content, features, and functionality are and will remain the exclusive property
                of Ethiopian Airlines. The System is protected by copyright, trademark, and other laws of both Ethiopia
                and foreign countries. Our trademarks and trade dress may not be used in connection with any product or
                service without the prior written consent of Ethiopian Airlines.
            </p>

            <h3>7. Content Ownership and License</h3>
            <p>
                You retain all rights to the content you upload to the System. By uploading content, you grant us a
                non-exclusive, worldwide, royalty-free license to:
            </p>
            <ul>
                <li><strong>Display:</strong> Display your content on the ET TV Display System.</li>
                <li><strong>Distribute:</strong> Distribute your content to television screens within Ethiopian Airlines facilities.</li>
                <li><strong>Modify:</strong> Make technical modifications necessary to display your content properly.</li>
                <li><strong>Archive:</strong> Store and archive your content for system operation purposes.</li>
            </ul>

            <h3>8. Termination</h3>
            <p>
                We may terminate or suspend your account immediately, without prior notice or liability, for any reason
                whatsoever, including without limitation if you breach these Terms. Upon termination, your right to use
                the System will cease immediately. If you wish to terminate your account, you may simply discontinue using
                the System or contact us to request account deletion.
            </p>

            <h3>9. Disclaimer of Warranties</h3>
            <p>
                THE SYSTEM IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
                INCLUDING BUT NOT LIMITED TO IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND
                NON-INFRINGEMENT. WE DO NOT WARRANT THAT THE SYSTEM WILL BE UNINTERRUPTED, SECURE, OR ERROR-FREE.
            </p>

            <h3>10. Limitation of Liability</h3>
            <p>
                TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL ETHIOPIAN AIRLINES BE LIABLE FOR ANY
                INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING WITHOUT LIMITATION, LOSS OF
                PROFITS, DATA, USE, GOODWILL, OR OTHER INTANGIBLE LOSSES, RESULTING FROM:
            </p>
            <ul>
                <li>Your use or inability to use the System.</li>
                <li>Unauthorized access to or alteration of your transmissions or data.</li>
                <li>Statements or conduct of any third party on the System.</li>
                <li>Any other matter relating to the System.</li>
            </ul>

            <h3>11. Indemnification</h3>
            <p>
                You agree to defend, indemnify, and hold harmless Ethiopian Airlines and its employees, contractors,
                directors, and agents from and against any and all claims, damages, obligations, losses, liabilities,
                costs, or debt, and expenses (including but not limited to attorney's fees) arising from:
            </p>
            <ul>
                <li>Your use of and access to the System.</li>
                <li>Your violation of any term of these Terms.</li>
                <li>Your violation of any third-party right, including without limitation any copyright, property, or privacy right.</li>
                <li>Any claim that your content caused damage to a third party.</li>
            </ul>

            <h3>12. Changes to Terms</h3>
            <p>
                We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision
                is material, we will try to provide at least 30 days' notice prior to any new terms taking effect. What
                constitutes a material change will be determined at our sole discretion. By continuing to access or use
                our System after those revisions become effective, you agree to be bound by the revised terms.
            </p>

            <h3>13. Governing Law</h3>
            <p>
                These Terms shall be governed and construed in accordance with the laws of Ethiopia, without regard to
                its conflict of law provisions. Our failure to enforce any right or provision of these Terms will not be
                considered a waiver of those rights. If any provision of these Terms is held to be invalid or unenforceable
                by a court, the remaining provisions of these Terms will remain in effect.
            </p>

            <h3>14. Contact Us</h3>
            <p>
                If you have any questions about these Terms, please contact us at:
            </p>
            <ul>
                <li><strong>Email:</strong> legal@ettv.ethiopianairlines.com</li>
                <li><strong>Address:</strong> Ethiopian Airlines Group, Bole International Airport, Addis Ababa, Ethiopia</li>
            </ul>

            <div class="last-updated">
                These Terms of Service were last updated on June 2026.
            </div>
        </div>

        <div style="text-align: center;">
            <a href="/" class="btn-back">← Back to TV Display</a>
        </div>
    </div>

    <div class="footer">
        <div>
            <a href="/privacy.php">Privacy Policy</a>
            <a href="/terms.php">Terms of Service</a>
            <a href="/">TV Display</a>
            <a href="/admin/login.php">Admin Login</a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Ethiopian Airlines. All rights reserved.</p>
    </div>

</body>

</html>