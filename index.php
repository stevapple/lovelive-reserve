<?php

require_once 'vendor/autoload.php';
require_once 'utils.php';

use PHPMailer\PHPMailer\PHPMailer;
use Qcloud\Cos;

function main_handler(object $event, object $context) {
    // 0. 加载环境变量
    // 0.1 时区信息
    $timezone = new DateTimeZone($_ENV['TIMEZONE']);
    $timezone_desc = $_ENV['TIMEZONE_NICENAME'];
    // 0.2 时间限制
    $timelimit = $_ENV['TIMELIMIT_EXPR'];
    $timelimit_desc = $_ENV['TIMELIMIT_NICENAME'];
    // 0.3 数量限制
    $max_quantity = intval($_ENV['MAX_QUANTITY']);
    $capacity = intval($_ENV['RESERVE_CAPACITY']);
    // 0.4 活动信息
    $entity = $_ENV['ENTITY_NAME'];
    $event_shortname = $_ENV['EVENT_SHORTNAME'];
    $event_name = $_ENV['EVENT_FULLNAME'];
    // 0.5 MySQL 配置
    $mysql_host = $_ENV['MYSQL_HOSTNAME'];
    $mysql_username = $_ENV['MYSQL_USERNAME'];
    $mysql_password = $_ENV['MYSQL_PASSWORD'];
    $database = $_ENV['MYSQL_DATABASE'];
    // 0.6 COS 配置
    $cos_credential = [
        'secretId' => $_ENV['COS_SECRETID'],
        'secretKey' => $_ENV['COS_SECRETKEY']
    ];
    $cos_region = $_ENV['COS_REGION'];
    $cos_bucket = $_ENV['COS_BUCKETNAME'];
    // 0.7 邮箱设置
    $mail_username = $_ENV['EMAIL_USERNAME'];
    $mail_password = $_ENV['EMAIL_PASSWORD'];
    $mail_address = $mail_username .'@qq.com';

    // 1. 从 event 输入提取信息
    $qq = $event->pathParameters->qq;
    $req_body = json_decode($event->body);
    $email = $req_body->email;
    $quantity = $req_body->quantity;

    // 2. 验证输入
    if (!is_numeric($qq) || !is_int($quantity)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !($quantity >= 1 && $quantity <= $max_quantity)) {
        return error(400, '非法参数');
    }

    // 3. 建立 MySQL 数据连接，更改事务隔离等级
    $mysqli = new mysqli($mysql_host, $mysql_username, $mysql_password, $database);
    $mysqli->query('SET session transaction isolation level read uncommitted');

    // 4. 释放超时未付款的席位
    $day_before = (new DateTime('now', $timezone))->modify("-$timelimit");
    $day_after = (new DateTime('now', $timezone))->modify("+$timelimit");
    // 4.1 设置时区
    $set = $mysqli->prepare('SET time_zone = ?');
    $set->bind_param('s', $timezone->getName());
    $set->execute();
    // 4.2 更新过期信息
    $update = $mysqli->prepare('UPDATE reserve SET expired = true WHERE `time` < ?');
    $update->bind_param('s', $day_before->format('Y-m-d H:i:s'));
    $update->execute();

    // 5. 检查该 QQ 号是否已报名
    $query = $mysqli->prepare('SELECT `time` FROM reserve WHERE qq = ?');
    $query->bind_param('s', $qq);
    $query->bind_result($time_str);
    $query->execute();
    if ($query->fetch()) {
        $time = new DateTime($time_str, $timezone);
        $template = <<<response
QQ号%s已于%s提交过申请，请勿重复报名。
如未收到收款码邮件，请联系%s。
response;
        $response = sprintf($template, $qq, $time->format('Y年m月d日H:i'), $mail_address);
        return failed($response);
    }

    // 6. 查询数据库，检查当前人数是否超额
    $row = $mysqli->query('SELECT SUM(quantity) FROM reserve WHERE (paid OR NOT expired) AND NOT flagged')->fetch_row();
    $reserved = intval($row[0]);
    if ($reserved + $quantity > $capacity)
        return failed('剩余席位不足，请稍后再试。');

    // 7. 通过 COS SDK 生成预签名的收款码链接
    $cos = new Cos\Client([
        'scheme' => 'https',
        'region' => $cos_region,
        'credentials'=> $cos_credential
    ]);
    $paycode = $cos->getObjectUrl($cos_bucket, "payment/code_$quantity.jpg", "+$timelimit");

    // 8. 向服务器提交一个增加请求
    $mysqli->autocommit(false);
    $insert = $mysqli->prepare('INSERT INTO reserve (qq, email, quantity) VALUES (?,?,?)');
    $insert->bind_param('ssi', $qq, $email, $quantity);
    $insert->execute();

    // 9. 发送邮件
    $mail = new PHPMailer(true);
    // 9.1 配置 SMTP 服务器 
    $mail->CharSet = 'UTF-8';
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = 'smtp.qq.com';
    $mail->SMTPAuth = true;
    $mail->Username = $mail_username;
    $mail->Password = $mail_password;
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;
    // 9.2 构造邮件正文
    $mail->isHTML(true);
    $mail->Subject = $event_shortname .'报名成功';
    $mail->AltBody = '点击以下链接获取收款码，有效期'. $timelimit_desc ."，付款时请备注报名所使用的QQ号：\n". $paycode;
    $template = <<<email
你已成功报名%s！<br />
请在%s内通过邮件内的收款码及时支付报名费用。<br />
付款时请备注QQ号%s，付款后请留意入群邀请。<br />
如有任何疑问，请回复邮件。<br />
<img src="%s" alt="收款码" />
email;
    $mail->Body = sprintf($template, htmlentities($event_name), $timelimit_desc, $qq, $paycode);
    // 9.3 发送邮件
    $mail->setFrom($mail_address, $entity);
    $mail->addAddress($email);
    $mail->addReplyTo($mail_address);
    $mail->send();

    // 10. 提交事务，关闭数据库连接
    $mysqli->commit();
    $mysqli->close();

    // 11. 构造返回请求
    $template = <<<response
报名成功！包含收款码的邮件已经发送到%s，请注意查收。
付款时请备注QQ号%s，付款后请留意入群邀请。
请务必在%s%s前完成付款，否则预订席位将被释放，且无法再次报名。
response;
    $response = sprintf($template, $email, $qq, $timezone_desc, $day_after->format('Y年m月d日H:i'));

    // 12. 返回结果
    return success($response);
}
