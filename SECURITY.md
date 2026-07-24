# Security

请勿在 Issue、测试、示例或日志中提交真实 Secret、Token、私钥、手机号、设备标识或客户数据。

安全问题请通过 GitHub Repository 的 Security Advisory 私密报告；不要创建公开 Issue。调用方必须从部署环境注入
内部服务 URL、Client ID 和 Secret，并确保异常及日志不会输出签名密钥、FCM Token 或完整业务 Payload。
