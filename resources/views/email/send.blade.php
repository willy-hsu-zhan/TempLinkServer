<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>註冊確認連結</title>
</head>
<body>
<h2>感謝您在好疫罩進行註冊！</h2>

<h3>請點擊[連結]完成註冊：</h3>
<h3>
    <a href="<?php
      echo "https://nuucsie.ddns.net/api/mail/verify/$line_access_token/$lineID/$email_token";
    ?>">
        [連結]
        <br>
        <?php
          echo "https://nuucsie.ddns.net/api/mail/verify/$line_access_token/$lineID/$email_token";
        ?>
    </a>
</h3>

<h3>
    如果這不是您本人的操作，請忽略此郵件。
</h3>
</body>
</html>