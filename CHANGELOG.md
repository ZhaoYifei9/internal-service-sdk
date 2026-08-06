# Changelog

## Unreleased

- 增加 `user.asset.synced` 命名事件与高阶上报入口，幂等同步已有用户资产，并明确区分业务逻辑 `app_id` 与实际安装 `package_app_id`；该事件不表示新注册。

## 0.4.0 - 2026-07-24

- 增加 `DataMidReporter`、可注入调度器与生产端去重租约，统一命名事件、失败释放和批量结果判断。
- 增加飞书 `AlertCatalog`、`FeishuNotifier` 与结构化结果，统一 V2 告警和旧接口安全兜底。
- 增加 AJ 设备/事件 DTO 与可配置重试客户端；国家必须显式提供 `clientVersion`。
- 增加支持原始 Body、稳定 Query、Actor/Scopes、Request ID 和附加 Header 的 `SignedHttpClient`。
- 增加 `InternalRequestContext` 与 `QueryString`，统一 Toolbox/BFF 管理端代理的签名输入。
- 增加可选 `GuzzleHttpTransport`，并将 Swoole 从 Composer 强制依赖调整为按运行时选择。
- `SignedJsonClient` 改为复用 Signed HTTP 核心，保持现有 data-mid 与通知设备调用接口兼容。

## 0.3.1 - 2026-07-24

- 将规范仓库迁至 `ZhaoYifei9/internal-service-sdk`，准备通过 Packagist 公开分发。
- 安装方式收敛为标准 Composer 包，不再要求业务仓库配置 VCS 地址或 GitHub 凭证。
- 对公开历史和示例完成隐私清理，Git 元数据统一使用 GitHub noreply 邮箱。

## 0.3.0 - 2026-07-24

- 增加通知设备注册强类型契约，统一字段规范、平台常量及调用前校验。
- 增加 `registerDevice()` 高层入口，并保留 `register()` 低层兼容调用。

## 0.2.0 - 2026-07-24

- 将 data-mid 稳定事件类型和确定性事件 ID 生成器纳入 SDK。
- 增加事件工厂与 PreparedEvent，统一用户、设备、借款和逾期事件 Payload。
- 增加订单消息上下文白名单及银行卡末四位脱敏。
- 增加批量上报结果解析，统一完整接收判断和首条拒绝原因读取。

## 0.1.1 - 2026-07-24

- 兼容 toolbox 旧飞书接口成功时返回空响应。
- 包元数据与 PHP 命名空间保持组织中立。

## 0.1.0 - 2026-07-24

- 提供统一九行 Canonical Request HMAC-SHA256 签名。
- 提供 data-mid 单事件与批量事件 HTTP 客户端。
- 提供 service-notification 设备注册客户端。
- 提供 toolbox-service 飞书 V2 告警与自定义消息客户端。
- 提供可替换 Transport 和默认 Swoole 协程 HTTP 实现，兼容 PHP 7.4。
