<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>註冊確認連結</title>
</head>
<body>
<h1>感謝您在好疫罩進行註冊！</h1>

<p>
    請點擊下面的連結完成註冊：
    <a href="https://nuucsieweb.ddns.net:5001/regist?token={{ $user->email_token }}">
        按我驗證 https://nuucsieweb.ddns.net:5001/regist?token={{ $user->email_token }}
    </a>
</p>

<p>
    如果這不是您本人的操作，請忽略此郵件。
</p>
</body>
</html>