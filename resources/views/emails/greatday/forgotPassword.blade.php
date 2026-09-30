<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: oklch(56.63% 0.085 214.15);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .content {
            padding: 30px;
        }

        .lang-section {
            margin-bottom: 50px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .lang-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .lang-title {
            background: linear-gradient(135deg, oklch(56.63% 0.085 214.15) 0%, oklch(66.63% 0.085 214.15) 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 16px;
        }

        .lang-badge {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.3);
            padding: 4px 10px;
            border-radius: 3px;
            margin-right: 8px;
            font-size: 12px;
            font-weight: bold;
        }

        .info-box {
            background-color: #f0f4ff;
            padding: 15px;
            border-left: 4px solid oklch(56.63% 0.085 214.15);
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box p {
            margin: 5px 0;
        }

        .button {
            display: inline-block;
            padding: 14px 35px;
            background: oklch(56.63% 0.085 214.15);
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
        }

        .url-box {
            word-break: break-all;
            background-color: #f0f0f0;
            padding: 12px;
            font-size: 11px;
            border-radius: 4px;
            border: 1px solid #ddd;
            color: #555;
        }

        .footer {
            margin-top: 20px;
            padding: 20px;
            font-size: 12px;
            color: #666;
            text-align: center;
            background-color: #f9f9f9;
            border-top: 1px solid #e0e0e0;
        }

        .footer-section {
            margin: 10px 0;
            padding: 10px;
            background-color: white;
            border-radius: 4px;
        }

        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ff6600;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            line-height: 1.8;
        }

        .warning-icon {
            color: #ff6600;
            font-weight: bold;
            font-size: 18px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #ddd, transparent);
            margin: 25px 0;
        }

        h2 {
            color: oklch(56.63% 0.085 214.15);
            margin-top: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Attendance</h1>
        </div>

        <div class="content">
            <!-- Indonesian Version -->
            <div class="lang-section">
                <div class="lang-title">
                    <span class="lang-badge">ID</span> Bahasa Indonesia
                </div>

                <h2>Reset Password Akun Anda</h2>

                <p>Halo <strong>{{ $username }}</strong>,</p>

                <p>Kami menerima permintaan untuk mereset password akun Attendance Anda.</p>

                <div class="info-box">
                    <p><strong>&#128231; Email:</strong> {{ $email }}</p>
                </div>

                <p>Untuk mereset password Anda, silakan klik tombol di bawah ini:</p>

                <center>
                    <a href="{{ $verificationUrl }}" class="button">&#128274; Reset Password</a>
                </center>

                <div class="divider"></div>

                <p style="font-size: 13px; color: #666;">Atau copy dan paste link berikut ke browser Anda:</p>
                <p class="url-box">{{ $verificationUrl }}</p>

                <p style="margin-top: 20px;"><strong>&#9200; Link reset password akan kadaluarsa
                        pada:</strong><br>{{ $expiredAt }} WIB</p>

                <div class="warning">
                    <span class="warning-icon">&#9888;</span>
                    <strong>Penting:</strong> Jika Anda tidak meminta reset password, silakan abaikan email ini dan
                    password Anda akan tetap aman. Link ini hanya dapat digunakan satu kali dan akan kedaluwarsa dalam
                    24 jam.
                </div>

                <div class="divider"></div>

                <p style="font-size: 13px; color: #666;">Jika Anda tidak meminta reset password, mohon segera hubungi HR
                    untuk keamanan akun Anda.</p>

                <p>Salam,<br><strong>Tim Attendance</strong></p>
            </div>

            <!-- English Version -->
            <div class="lang-section">
                <div class="lang-title">
                    <span class="lang-badge">EN</span> English
                </div>

                <h2>Reset Your Account Password</h2>

                <p>Hello <strong>{{ $username }}</strong>,</p>

                <p>We received a request to reset the password for your Attendance account.</p>

                <div class="info-box">
                    <p><strong>&#128231; Email:</strong> {{ $email }}</p>
                </div>

                <p>To reset your password, please click the button below:</p>

                <center>
                    <a href="{{ $verificationUrl }}" class="button">&#128274; Reset Password</a>
                </center>

                <div class="divider"></div>

                <p style="font-size: 13px; color: #666;">Or copy and paste the following link into your browser:</p>
                <p class="url-box">{{ $verificationUrl }}</p>

                <p style="margin-top: 20px;"><strong>&#9200; Password reset link will expire
                        on:</strong><br>{{ $expiredAt }} WIB</p>

                <div class="warning">
                    <span class="warning-icon">&#9888;</span>
                    <strong>Important:</strong> If you did not request a password reset, please ignore this email and
                    your password will remain secure. This link can only be used once and will expire in 24 hours.
                </div>

                <div class="divider"></div>

                <p style="font-size: 13px; color: #666;">If you did not request a password reset, please contact HR
                    immediately for your account security.</p>

                <p>Best regards,<br><strong>Attendance Team</strong></p>
            </div>

            <!-- Chinese Version -->
            <div class="lang-section">
                <div class="lang-title">
                    <span class="lang-badge">CN</span> 中文
                </div>

                <h2>重置您的账户密码</h2>

                <p>您好 <strong>{{ $username }}</strong>，</p>

                <p>我们收到了重置您的 Attendance 账户密码的请求。</p>

                <div class="info-box">
                    <p><strong>&#128231; 邮箱：</strong>{{ $email }}</p>
                </div>

                <p>要重置您的密码，请点击下面的按钮：</p>

                <center>
                    <a href="{{ $verificationUrl }}" class="button">&#128274; 重置密码</a>
                </center>

                <div class="divider"></div>

                <p style="font-size: 13px; color: #666;">或者将以下链接复制并粘贴到浏览器中：</p>
                <p class="url-box">{{ $verificationUrl }}</p>

                <p style="margin-top: 20px;"><strong>&#9200; 密码重置链接将于以下时间过期：</strong><br>{{ $expiredAt }} WIB</p>

                <div class="warning">
                    <span class="warning-icon">&#9888;</span>
                    <strong>重要提示：</strong>如果您没有请求重置密码，请忽略此邮件，您的密码将保持安全。此链接只能使用一次，并将在24小时后过期。
                </div>

                <div class="divider"></div>

                <p style="font-size: 13px; color: #666;">如果您没有请求重置密码，请立即联系人力资源部以确保账户安全。</p>

                <p>此致，<br><strong>Attendance 团队</strong></p>
            </div>
        </div>

        <div class="footer">
            <div class="footer-section">
                <strong>[ID] Indonesia:</strong> Email ini dikirim secara otomatis, mohon tidak membalas email ini. Jika
                Anda memiliki pertanyaan, silakan hubungi HR.
            </div>
            <div class="footer-section">
                <strong>[EN] English:</strong> This email is sent automatically, please do not reply to this email. If
                you have any questions, please contact HR.
            </div>
            <div class="footer-section">
                <strong>[CN] 中文:</strong> 此邮件为自动发送，请勿回复。如有疑问，请联系人力资源部。
            </div>
        </div>
    </div>
</body>

</html>