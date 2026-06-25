<?php
// privacy.php - Privacy Policy for ET TV Display System
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - ET TV Display</title>
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
            border-left: 4px solid #667eea;
            color: #444;
            line-height: 1.6;
            font-size: 14px;
        }

        .card ul li strong {
            color: #1a1a2e;
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
        <h1>🔒 Privacy Policy</h1>
        <p>ET TV Display System - Ethiopian Airlines</p>
    </div>

    <div class="container">
        <div class="card">
            <h2>Privacy Policy</h2>
            <p><strong>Last Updated:</strong> June 2026</p>

            <p>
                Ethiopian Airlines ("we", "our", "us") is committed to protecting your privacy. This Privacy Policy
                explains how we collect, use, disclose, and safeguard your information when you use the ET TV Display
                System ("the System"). Please read this privacy policy carefully. If you do not agree with the terms of
                this privacy policy, please do not access the System.
            </p>

            <h3>1. Information We Collect</h3>
            <p>
                We may collect information about you in a variety of ways. The information we may collect on the System includes:
            </p>
            <ul>
                <li><strong>Personal Data:</strong> Personally identifiable information, such as your username, and role, that you voluntarily give to us when you register for the System.</li>
                <li><strong>Usage Data:</strong> Information about your interactions with the System, including content creation, content management, order changes, and timestamps of activities.</li>
            </ul>

            <h3>2. How We Use Your Information</h3>
            <p>We use the information we collect to:</p>
            <ul>
                <li><strong>Operate and Maintain:</strong> Manage the System, including content display, order management, and user administration.</li>
                <li><strong>Improve Services:</strong> Analyze usage patterns to enhance the System's performance and user experience.</li>
                <li><strong>Security:</strong> Monitor and protect against unauthorized access, fraud, and other security incidents.</li>
                <li><strong>Communication:</strong> Send administrative emails, system updates, and security alerts.</li>
                <li><strong>Audit and Compliance:</strong> Maintain audit trails of user activities for security and compliance purposes.</li>
            </ul>

            <h3>3. Data Security</h3>
            <p>
                We have implemented appropriate technical and organizational security measures designed to protect the
                security of any personal information we process. However, please also remember that we cannot guarantee
                that the internet itself is 100% secure. Although we will do our best to protect your personal information,
                transmission of personal information to and from our System is at your own risk. You should only access
                the System within a secure environment.
            </p>

            <h3>4. Data Retention</h3>
            <p>
                We will retain your personal information only for as long as is necessary for the purposes set out in
                this Privacy Policy. We will retain and use your information to the extent necessary to comply with our
                legal obligations, resolve disputes, and enforce our policies.
            </p>

            <h3>5. Your Rights</h3>
            <p>
                Depending on your location, you may have the following rights regarding your personal information:
            </p>
            <ul>
                <li><strong>Access:</strong> Request access to the personal information we hold about you.</li>
                <li><strong>Correction:</strong> Request correction of inaccurate or incomplete information.</li>
                <li><strong>Deletion:</strong> Request deletion of your personal information, subject to legal obligations.</li>
                <li><strong>Restriction:</strong> Request restriction of processing your personal information.</li>
                <li><strong>Objection:</strong> Object to the processing of your personal information.</li>
                <li><strong>Data Portability:</strong> Request transfer of your personal information to another organization.</li>
            </ul>

            <h3>6. Cookies and Tracking</h3>
            <p>
                The System may use cookies and similar tracking technologies to enhance your experience. Cookies are
                small data files stored on your device. You can control the use of cookies through your browser settings.
                However, disabling cookies may affect the functionality of the System.
            </p>

            <h3>7. Third-Party Services</h3>
            <p>
                The System may contain links to third-party websites or services. We are not responsible for the privacy
                practices or content of such third parties. We encourage you to review the privacy policies of any
                third-party sites you visit.
            </p>

            <h3>8. Children's Privacy</h3>
            <p>
                The System is not intended for use by individuals under the age of 13. We do not knowingly collect
                personal information from children under 13. If we become aware that we have collected personal
                information from a child under 13, we will take steps to delete such information.
            </p>

            <h3>9. Updates to This Privacy Policy</h3>
            <p>
                We may update this Privacy Policy from time to time. We will notify you of any changes by posting the
                new Privacy Policy on this page. We encourage you to review this Privacy Policy periodically for any
                changes. Changes to this Privacy Policy are effective when they are posted on this page.
            </p>

            <h3>10. Contact Us</h3>
            <p>
                If you have any questions about this Privacy Policy, please contact us at:
            </p>
            <ul>
                <li><strong>Email:</strong> privacy@ettv.ethiopianairlines.com</li>
                <li><strong>Address:</strong> Ethiopian Airlines Group, Bole International Airport, Addis Ababa, Ethiopia</li>
            </ul>

            <div class="last-updated">
                This Privacy Policy was last updated on June 2026.
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