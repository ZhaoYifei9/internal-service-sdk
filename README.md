# Internal Service SDK

供各国家 PHP 业务项目复用的内部 HTTP 客户端，兼容 PHP 7.4。当前包含：

- 固定九行 Canonical Request 的 `X-Internal-*` HMAC-SHA256 签名；
- data-mid 单事件和批量事件上报；
- service-notification FCM 设备注册；
- toolbox-service 飞书 V2 告警和旧自定义消息接口；
- 默认 Swoole 协程 HTTP Transport，以及可替换的 `TransportInterface`。

包不读取环境变量、不访问 Redis 或数据库，也不负责业务协程、事件去重、告警 ID 和 Payload 取数。调用方必须
显式注入 URL、Client ID、Secret、国家和应用信息；Secret 不得写入仓库或日志。

## 安装

私有仓库通过 Composer VCS 安装：

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:ZhaoYifei9/internal-service-sdk.git"
    }
  ],
  "require": {
    "internal-services/service-sdk": "^0.1"
  }
}
```

部署机需要具备该私有仓库的只读权限。

## data-mid

```php
use Internal\ServiceSdk\DataMid\DataMidClient;

$client = new DataMidClient([
    'base_url' => getenv('MID_GATEWAY'),
    'country_code' => getenv('COUNTRY_CODE'),
    'client_id' => getenv('MID_CLIENT_ID'),
    'secret' => getenv('MID_SECRET'),
    'timeout' => 10,
]);

$response = $client->reportEvent([
    'event_type' => 'user.registered',
    'app_id' => '5001',
    'user_id' => '42',
    'phone' => '业务手机号',
    'event_id' => '稳定幂等 ID',
    'timestamp' => time(),
    'data' => ['registered_at' => time()],
]);
```

`reportEvent()` 和 `reportBatch()` 返回 `HttpResponse`。data-mid 的 HTTP 200 批量响应仍可能包含逐条拒绝，调用方在
推进业务断点前必须核对 `accepted/rejected`。

## 通知设备注册

```php
use Internal\ServiceSdk\Notification\NotificationDeviceClient;

$client = new NotificationDeviceClient([
    'base_url' => getenv('NOTIFICATION_SERVICE_URL'),
    'client_id' => getenv('NOTIFICATION_SERVICE_CLIENT_ID'),
    'secret' => getenv('NOTIFICATION_SERVICE_SECRET'),
    'timeout' => 5,
]);

$client->register('install-uuid', [
    'country_code' => 'NG',
    'app_id' => '5001',
    'platform' => 'ANDROID',
    'fcm_token' => '当前 Token',
    'aaid' => '可选 GAID',
    'token_updated_at' => '2026-07-24T12:00:00.000000+01:00',
]);
```

## toolbox 飞书告警

```php
use Internal\ServiceSdk\Toolbox\FeishuAlertClient;

$client = new FeishuAlertClient([
    'base_url' => getenv('FEISHU_BASE_URL'),
    'app_name' => 'country-loan-api',
    'environment' => getenv('APP_ENV'),
    'timeout' => 10,
]);

$client->sendAlert('NG-NEW-SYSTEM-ERROR', [
    'message' => ['error' => 'example'],
], 1);
```

toolbox 返回 `code=0` 表示已发送，`code=200` 表示规则判定后跳过，两者都属于正常受理。

## 请求上下文与测试

构造函数支持注入 Transport、时钟、Nonce 和 Request ID Resolver。Hyperf 项目可用 Resolver 传递当前请求 ID；
单元测试使用 Fake Transport 验证完整方法、URL、正文和签名，不需要启动公共服务。

```bash
composer install
composer test
```
