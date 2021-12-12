# lovelive-reserve

`lovelive-reserve` 是由 LoveLive! Series presents COUNTDOWN LoveLive! 2021→2022 ～LIVE with a smile!～ 合肥线下观影会衍生的、为类似的小规模粉丝向活动提供付费报名的腾讯云函数（SCF）模板。

*本项目与 LoveLive! 商标及其所有人无关。*

## 项目结构

```
├── composer.json            # Composer 配置文件
├── drop_create_table.sql    # MySQL 表配置
├── index.php                # 函数入口
├── models                   # 自定义数据结构
│   └── ...
├── rector.php               # Rector 降级配置（PHP 8.1→7.2）
├── serverless.yml           # Serverless 配置文件（暂不公开）
└── utils.php                # 辅助函数
```

使用的开源库：
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)
- [COS SDK V5](https://github.com/tencentyun/cos-php-sdk-v5)
- [Rector](https://github.com/rectorphp/rector)（仅开发）

依赖的服务：
- 腾讯云函数 SCF
- 腾讯云存储 COS
- MySQL 数据库
- QQ 邮箱
