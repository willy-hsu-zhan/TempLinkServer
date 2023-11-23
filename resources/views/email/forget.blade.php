<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>修改密碼連結</title>
</head>
<body>
<h1>好疫罩會員密碼修改</h1>

<p>
    請點擊下面的連結：
    <a href="https://nuucsieweb.ddns.net:5001/regist?token={{ $user->email_token }}">
        按我修改密碼 https://nuucsieweb.ddns.net:5001/regist?token={{ $user->email_token }}
    </a>
</p>

<p>
    如果這不是您本人的操作，請忽略此郵件。
</p>
</body>
</html>