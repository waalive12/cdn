# Cloudflare DNS 自定义主机名解析系统

一套完整的 PHP 系统，用于管理和解析 Cloudflare 自定义主机名。支持批量生成二级域名、随机/固定前缀解析、DNS 状态监控等功能。

## 功能特性

✅ **Cloudflare 账户集成**
- 支持 API Token 认证
- 管理多个域名的 DNS 解析
- 一键同步 Cloudflare 自定义主机名

✅ **自定义主机名管理**
- 创建/删除自定义主机名
- CNAME 解析到 dns.ptdns360.com
- DCV 委派验证支持
- SSL/TLS 证书自动管理

✅ **域名解析模式**
- 随机二级域名生成
- 固定前缀模式
- 批量生成（如一次生成 5-100 个）
- 一键复制多个域名

✅ **DNS 监控**
- 实时检测 CNAME 和 TXT 记录状态
- 自动验证解析有效性
- 历史检查日志

✅ **批量管理**
- 批量添加域名
- 批量检查 NS 修改状态
- 批量删除自定义主机名

## 快速开始

### 1. 安装

访问：`http://your-domain.com/install/`

按照向导完成：
- 数据库配置（主机、用户、密码、数据库名）
- 管理员账号创建

### 2. 登录后台

访问：`http://your-domain.com/admin/`

使用安装时创建的管理员账号登录

### 3. 配置 Cloudflare 账户

1. 进入「设置」→「Cloudflare 账户"
2. 填写 API Token 和 Zone ID
3. 点击「测试连接"
4. 连接成功后保存配置

### 4. 创建自定义主机名

1. 进入「自定义主机名"页面
2. 输入主机名（如：dns.example.com）
3. 选择解析模式：
   - 随机模式：自动生成随机前缀
   - 固定模式：使用指定前缀
4. 创建成功

### 5. 生成二级域名

1. 在主机名列表中选择一个主机名
2. 点击「生成域名"
3. 输入数量（1-100）
4. 选择模式（随机/固定）
5. 一键复制所有生成的域名

### 6. 监控 DNS 解析

进入「DNS 监控"页面查看：
- 所有自定义主机名的状态
- 生成的二级域名的 CNAME/TXT 状态
- 最近的检查历史

## 目录结构

```
cloudflare-dns-parser/
├── install/              # 安装程序
│   └── install.php
├── config/               # 配置文件
│   ├── database.php
│   └── config.php
├── api/                  # API 和核心逻辑
│   ├── CloudflareAPI.php
│   ├── CustomHostnameManager.php
│   ├── DNSChecker.php
│   └── endpoints/
├── admin/                # 后台管理
│   ├── auth/
│   ├── login.php
│   ├── dashboard.php
│   ├── hostnames.php
│   ├── domains.php
│   ├── monitoring.php
│   └── settings.php
├── public/               # 公共资源
│   ├── assets/
│   │   ├── css/
│   │   └── js/
│   └── index.php
├── db/                   # 数据库
│   └── schema.sql
└── temp/                 # 临时文件
```

## 数据库结构

### admin_users
- id: 管理员 ID
- username: 用户名
- password: 密码（MD5）
- created_at: 创建时间

### cloudflare_accounts
- id: 账户 ID
- api_token: API Token
- zone_id: Zone ID
- zone_name: 区域名称
- api_email: 邮箱（备用）
- api_key: API Key（备用）
- verified: 是否已验证
- created_at: 创建时间

### custom_hostnames
- id: 主机名 ID
- cf_account_id: 关联的 Cloudflare 账户
- hostname: 主机名
- mode: 解析模式（random/fixed）
- prefix: 前缀（固定模式下使用）
- custom_origin: 自定义源
- ssl_status: SSL 状态
- dcv_token: DCV 验证信息
- verification_status: 验证状态
- is_active: 是否激活
- created_at: 创建时间

### generated_domains
- id: 域名 ID
- hostname_id: 关联的主机名 ID
- subdomain: 二级域名前缀
- full_domain: 完整域名
- txt_record: TXT 记录值
- cname_target: CNAME 目标
- status: 状态（pending/active/failed）
- last_check: 最后检查时间
- created_at: 创建时间

### dns_check_logs
- id: 日志 ID
- domain_id: 域名 ID
- check_type: 检查类型（cname/txt）
- expected_value: 期望值
- actual_value: 实际值
- result: 检查结果（success/failed）
- created_at: 创建时间

## API 端点

### 账户管理
- `POST /api/endpoints/account/add` - 添加 Cloudflare 账户
- `GET /api/endpoints/account/list` - 获取账户列表
- `POST /api/endpoints/account/test` - 测试账户连接
- `DELETE /api/endpoints/account/delete` - 删除账户

### 主机名管理
- `POST /api/endpoints/hostname/create` - 创建主机名
- `GET /api/endpoints/hostname/list` - 获取主机名列表
- `GET /api/endpoints/hostname/sync` - 从 Cloudflare 同步
- `DELETE /api/endpoints/hostname/delete` - 删除主机名

### 域名管理
- `POST /api/endpoints/domain/generate` - 生成二级域名
- `GET /api/endpoints/domain/list` - 获取域名列表
- `POST /api/endpoints/domain/check` - 检查 DNS 状态
- `DELETE /api/endpoints/domain/delete` - 删除域名

### 监控
- `GET /api/endpoints/monitor/status` - 获取监控状态
- `GET /api/endpoints/monitor/logs` - 获取检查日志
- `POST /api/endpoints/monitor/check_all` - 检查所有域名

## 核心实现原理

### 自定义主机名 TXT 返回机制

1. **用户侧流程**
   ```
   test123.example.com 需要验证
   ↓
   查询 CNAME 记录
   ↓
   发现指向 dns.ptdns360.com
   ↓
   ACME 去查询 dns.ptdns360.com 的 TXT 记录
   ```

2. **系统侧实现**
   - 本系统作为权威 DNS 服务器
   - 在 dns.ptdns360.com 配置 NS 指向本系统
   - 接收 DNS 查询请求时：
     1. 解析查询的域名
     2. 从数据库查找对应记录
     3. 返回 TXT 或 CNAME 记录
   - 支持通配符 DNS 记录

3. **配置示例**
   ```dns
   ; dns.ptdns360.com 的 NS 记录
   dns.ptdns360.com NS ns1.your-system.com
   dns.ptdns360.com NS ns2.your-system.com
   ```

### DCV 委派验证

```
_acme-challenge.test123.example.com CNAME test123.example.com.162ade8b49dfe146.dcv.cloudflare.com
↓
ACME 查询 test123.example.com.162ade8b49dfe146.dcv.cloudflare.com
↓
返回 Cloudflare 验证 Token
↓
验证成功，颁发 SSL 证书
```

## 安全考虑

- 后台使用 Session 保护
- API Token 加密存储
- 支持 IP 白名单（可选）
- 操作日志记录（可选）

## 常见问题

**Q: 如何修改 dns.ptdns360.com 的指向？**
A: 编辑 `config/config.php` 中的 `DNS_RELAY_SERVER` 配置项

**Q: 支持自动续期 SSL 证书吗？**
A: 支持。系统会定期检查 SSL 状态并自动续期

**Q: 如何批量导入域名？**
A: 支持 CSV 导入，在「域名管理"页面有导入功能

**Q: 可以同时管理多个 Cloudflare 账户吗？**
A: 可以。系统支持无限数量的账户

## 许可证

MIT

## 技术支持

如有问题，请提交 Issue 或联系开发者。
