# Internal Service SDK

供各国家 PHP 业务项目复用的内部 HTTP 客户端，兼容 PHP 7.4。当前包含：

- 固定九行 Canonical Request 的 `X-Internal-*` HMAC-SHA256 签名；
- data-mid 事件类型、稳定事件 ID、事件工厂、订单上下文脱敏、批量结果及高阶上报门面；
- data-mid 单事件和批量事件 HTTP 上报；
- service-notification FCM 设备注册；
- toolbox-service 飞书 V2 告警、告警目录及旧自定义消息兜底；
- AJ 设备与事件 DTO、JSON 客户端及可配置重试；
- 支持服务身份与 Toolbox Actor/Scopes 的原始 Signed HTTP 请求；
- 可替换的 `TransportInterface`，以及可选 Swoole、Guzzle Transport。

包不读取环境变量、不访问 Redis 或数据库。调用方负责注入具体调度器、去重存储、告警 ID、业务 Payload 与运行配置；
SDK 统一调度/去重接口、上报生命周期和协议客户端。Secret 不得写入仓库或日志。

## 安装

包发布在 Packagist，可直接安装：

```bash
composer require internal-services/service-sdk:^0.4
```

无需配置 Composer VCS Repository、GitHub Token 或 SSH Deploy Key。

## data-mid

```php
use Internal\ServiceSdk\DataMid\DataMidClient;
use Internal\ServiceSdk\DataMid\EventFactory;

$client = new DataMidClient([
    'base_url' => getenv('MID_GATEWAY'),
    'country_code' => getenv('COUNTRY_CODE'),
    'client_id' => getenv('MID_CLIENT_ID'),
    'secret' => getenv('MID_SECRET'),
    'timeout' => 10,
]);

$events = new EventFactory('NG');
$event = $events->applicationSubmitted(
    '42',
    '5001',
    '业务手机号',
    'ORDER-001',
    'PRODUCT-01',
    'Quick Loan'
);

$response = $client->reportEvent($event->payload());
```

`EventFactory` 统一生成协议字段、事件时间、稳定 `event_id` 和生产端去重 Key；`EventType`、`EventId`、
`OrderContext` 也可独立使用。`reportEvent()` 和 `reportBatch()` 返回 `HttpResponse`。需要推进批处理断点时使用
`reportBatchResult()`，只有 `BatchReportResult::isComplete($expected)` 为 `true` 才表示全部接收。

推荐业务入口使用 `DataMidReporter`：它统一命名事件、异步派发、生产端去重租约、失败释放和批量完整接收判断。
国家项目用 `CallableDispatcher` 适配协程，以 `DedupStoreInterface` 适配 Redis；SDK 自身不依赖具体框架或缓存实现。

## 内部管理端请求

Toolbox/BFF 调用 data-mid、service-notification 或 service-short-link 管理端时，使用
`SignedHttpClient` 对实际发送的 Method、Path + Query 和原始 Body 字节签名，并通过
`InternalRequestContext` 传递已经由 Toolbox 鉴权的操作人和权限。SDK 不读取 Laravel Request、用户模型或
权限数据，也不会自行授予 Scope。

```php
use Internal\ServiceSdk\Auth\InternalRequestContext;
use Internal\ServiceSdk\Http\GuzzleHttpTransport;
use Internal\ServiceSdk\Http\QueryString;
use Internal\ServiceSdk\Http\SignedHttpClient;

$client = new SignedHttpClient([
    'base_url' => getenv('DATA_MID_BASE_URL'),
    'client_id' => getenv('DATA_MID_CLIENT_ID'),
    'secret' => getenv('DATA_MID_CLIENT_SECRET'),
    'timeout' => 10,
], new GuzzleHttpTransport());

$context = new InternalRequestContext(
    'operator-id',
    'operator@example.com',
    'mid.rules.read,mid.rules.edit',
    'request-id'
);
$path = QueryString::append('/admin/v1/rules', ['country_code' => 'NG']);
$response = $client->request('GET', $path, '', $context);
```

`Idempotency-Key` 等不属于九行 Canonical 的协议 Header 可以通过第五个参数传入；
`X-Internal-*` 和 `X-Request-Id` 禁止从附加 Header 覆盖。

## 通知设备注册

```php
use Internal\ServiceSdk\Notification\DeviceRegistration;
use Internal\ServiceSdk\Notification\NotificationDeviceClient;

$client = new NotificationDeviceClient([
    'base_url' => getenv('NOTIFICATION_SERVICE_URL'),
    'client_id' => getenv('NOTIFICATION_SERVICE_CLIENT_ID'),
    'secret' => getenv('NOTIFICATION_SERVICE_SECRET'),
    'timeout' => 5,
]);

$client->registerDevice(new DeviceRegistration(
    'install-uuid',
    'NG',
    '5001',
    DeviceRegistration::PLATFORM_ANDROID,
    '当前 Token（至少 20 字节）',
    '2026-07-24T12:00:00.000000+01:00',
    '可选 GAID'
));
```

`DeviceRegistration` 在发出请求前统一规范国家、平台和可选 AAID，并校验通知服务的完整字段、长度和
RFC 3339 时间契约。原有 `register($installUuid, $payload)` 作为低层兼容入口继续保留。

## toolbox 飞书告警

```php
use Internal\ServiceSdk\Toolbox\AlertCatalog;
use Internal\ServiceSdk\Toolbox\FeishuAlertClient;
use Internal\ServiceSdk\Toolbox\FeishuNotifier;

$client = new FeishuAlertClient([
    'base_url' => getenv('FEISHU_BASE_URL'),
    'app_name' => 'country-loan-api',
    'environment' => getenv('APP_ENV'),
    'timeout' => 10,
]);

$notifier = new FeishuNotifier($client, new AlertCatalog([
    'SYSTEM_ERROR' => [
        'id' => 'COUNTRY-SYSTEM-ERROR',
        'description' => '系统错误',
    ],
]));
$notifier->notify('SYSTEM_ERROR', [
    'message' => ['error' => 'example'],
], 1);
```

toolbox 返回 `code=0` 表示已发送，`code=200` 表示规则判定后跳过，两者都属于正常受理。V2 调用失败时，
`FeishuNotifier` 可自动调用旧自定义消息接口兜底，且不会把原始业务 Payload 复制到兜底内容。

## AJ 事件客户端

`AjClient` 统一 `/api/device/ma` 与 `/api/event` 的 JSON 请求、超时、响应解析和有限重试；协程项目可注入非阻塞
Sleeper。`AjEvent` 强制调用方显式传入 `clientVersion`，避免复制国家代码时沿用错误值。订单状态映射、首复贷、
风控判断、SQL、Redis 业务幂等和延迟任务仍属于业务项目。

## 请求上下文与测试

构造函数支持注入 Transport、时钟、Nonce 和 Request ID Resolver。Hyperf 项目可用 Resolver 传递当前请求 ID；
单元测试使用 Fake Transport 验证完整方法、URL、正文和签名，不需要启动公共服务。

`ext-swoole` 与 `guzzlehttp/guzzle` 均为按运行时选择的可选依赖：原有 Hyperf/Swoole 项目可以继续使用默认
`SwooleHttpTransport`；Laravel 等项目显式注入 `GuzzleHttpTransport`，安装 SDK 不再强制要求 Swoole 扩展。

```bash
composer install
composer test
```
