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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            background-color: rgba(255,255,255,0.3);
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
                
                <h2>Selamat Datang!</h2>
                
                <p>Halo <strong>{{ $username }}</strong>,</p>
                
                <p>Terima kasih telah melakukan registrasi di Attendance. Akun Anda telah berhasil dibuat.</p>
                
                <div class="info-box">
                    <p><strong>&#128231; Email:</strong> {{ $email }}</p>
                </div>
                
                <p>Untuk mengaktifkan akun Anda dan mensetup username serta password, silakan klik tombol verifikasi di bawah ini:</p>
                
                <center>
                    <a href="{{ $verificationUrl }}" class="button">&#10003; Verifikasi Akun</a>
                </center>
                
                <div class="divider"></div>
                
                <p style="font-size: 13px; color: #666;">Atau copy dan paste link berikut ke browser Anda:</p>
                <p class="url-box">{{ $verificationUrl }}</p>
                
                <p style="margin-top: 20px;"><strong>&#9200; Link verifikasi akan kadaluarsa pada:</strong><br>{{ $expiredAt }} WIB</p>
                
                <div class="warning">
                    <span class="warning-icon">&#9888;</span> 
                    <strong>Penting:</strong> Setelah verifikasi, Anda akan mensetup <strong>username</strong> dan <strong>password</strong> baru Anda. Harap catat dan simpan dengan aman karena informasi ini diperlukan untuk login.
                </div>
                
                <div class="divider"></div>
                
                <p style="font-size: 13px; color: #666;">Jika Anda tidak melakukan registrasi, silakan abaikan email ini.</p>
                
                <p>Salam,<br><strong>Tim Attendance</strong></p>
            </div>

            <!-- English Version -->
            <div class="lang-section">
                <div class="lang-title">
                    <span class="lang-badge">EN</span> English
                </div>
                
                <h2>Welcome!</h2>
                
                <p>Hello <strong>{{ $username }}</strong>,</p>
                
                <p>Thank you for registering on Attendance. Your account has been successfully created.</p>
                
                <div class="info-box">
                    <p><strong>&#128231; Email:</strong> {{ $email }}</p>
                </div>
                
                <p>To activate your account and setup your username and password, please click the verification button below:</p>
                
                <center>
                    <a href="{{ $verificationUrl }}" class="button">&#10003; Verify Account</a>
                </center>
                
                <div class="divider"></div>
                
                <p style="font-size: 13px; color: #666;">Or copy and paste the following link into your browser:</p>
                <p class="url-box">{{ $verificationUrl }}</p>
                
                <p style="margin-top: 20px;"><strong>&#9200; Verification link will expire on:</strong><br>{{ $expiredAt }} WIB</p>
                
                <div class="warning">
                    <span class="warning-icon">&#9888;</span> 
                    <strong>Important:</strong> After verification, you will setup your new <strong>username</strong> and <strong>password</strong>. Please write them down and keep them secure as this information is required for login.
                </div>
                
                <div class="divider"></div>
                
                <p style="font-size: 13px; color: #666;">If you did not register, please ignore this email.</p>
                
                <p>Best regards,<br><strong>Attendance Team</strong></p>
            </div>

            <!-- Chinese Version -->
            <div class="lang-section">
                <div class="lang-title">
                    <span class="lang-badge">CN</span> 中文
                </div>
                
                <h2>欢迎！</h2>
                
                <p>您好 <strong>{{ $username }}</strong>，</p>
                
                <p>感谢您在 Attendance 注册。您的账户已成功创建。</p>
                
                <div class="info-box">
                    <p><strong>&#128231; 邮箱：</strong>{{ $email }}</p>
                </div>
                
                <p>要激活您的账户并设置用户名和密码，请点击下面的验证按钮：</p>
                
                <center>
                    <a href="{{ $verificationUrl }}" class="button">&#10003; 验证账户</a>
                </center>
                
                <div class="divider"></div>
                
                <p style="font-size: 13px; color: #666;">或者将以下链接复制并粘贴到浏览器中：</p>
                <p class="url-box">{{ $verificationUrl }}</p>
                
                <p style="margin-top: 20px;"><strong>&#9200; 验证链接将于以下时间过期：</strong><br>{{ $expiredAt }} WIB</p>
                
                <div class="warning">
                    <span class="warning-icon">&#9888;</span> 
                    <strong>重要提示：</strong>验证后，您将设置新的<strong>用户名</strong>和<strong>密码</strong>。请记录并妥善保管，因为登录时需要这些信息。
                </div>
                
                <div class="divider"></div>
                
                <p style="font-size: 13px; color: #666;">如果您没有注册，请忽略此邮件。</p>
                
                <p>此致，<br><strong>Attendance 团队</strong></p>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-section">
                <strong>[ID] Indonesia:</strong> Email ini dikirim secara otomatis, mohon tidak membalas email ini. Jika Anda memiliki pertanyaan, silakan hubungi HR.
            </div>
            <div class="footer-section">
                <strong>[EN] English:</strong> This email is sent automatically, please do not reply to this email. If you have any questions, please contact HR.
            </div>
            <div class="footer-section">
                <strong>[CN] 中文:</strong> 此邮件为自动发送，请勿回复。如有疑问，请联系人力资源部。
            </div>
        </div>
    </div>
</body>
</html>