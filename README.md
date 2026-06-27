# Book 书籍管理系统

> **基于静读天下 App WebDAV 同步的 Web 端书库管理系统**

一个为 **静读天下（Moon+ Reader）** 用户设计的 PC 端书库管理后台：手机 App 通过 WebDAV 同步书籍和元数据，本系统在 Web 端提供搜索、批量编辑、豆瓣抓取、AI 智能识别、统计分析、在线阅读等能力，并把改动同步回 WebDAV，形成双向闭环。

---

## 使用前必读

本系统**不是独立的书库管理系统**，必须配合静读天下 App + WebDAV 一起使用：

1. 在手机上安装静读天下（Moon+ Reader）
2. 配置一个可用的 WebDAV 服务（坚果云 / Nextcloud / 群晖 等）
3. 在静读天下中至少完成一次同步，让 WebDAV 上出现 `Apps/Books/` 目录
4. 在本系统的安装向导中填入**完全相同**的 WebDAV 凭据

没有静读天下生成的 WebDAV 数据，本系统是空的。

---

## 功能概览

- **WebDAV 双向同步**：与静读天下共享同一个书库，每小时自动增量同步，也可手动触发
- **元数据管理**：分类、标签、收藏、系列（带编号）、5 星评分、已读状态
- **AI 智能识别**：接入 OpenRouter / ChatGPT 等 LLM，自动检索豆瓣补全书名、作者、简介、封面、评分、分类、标签
- **AI 智能分类**：批量让 AI 根据书籍信息自动判断分类和标签
- **豆瓣搜索**：手动搜索豆瓣，获取书名、作者、简介、封面、出版信息
- **统计面板**：藏书总量、已读/在读/未读、分类分布、评分分布、近 12 月入库趋势
- **批量操作**：批量改分类 / 标签 / 系列、批量已读标记、批量删除、批量封面刮削、删除重复
- **Web 端上传**：拖拽 / 多选，大文件分片，支持 EPUB / MOBI / AZW / AZW3 / PDF / TXT，上传后自动入库并发布到 WebDAV
- **在线阅读器**（Foliate.js）：支持 EPUB / MOBI / AZW / AZW3 / PDF
- **阅读进度同步**：与静读天下共享同一份进度文件，双向 last-write-wins 仲裁
- **Web 安装向导**：首次部署通过浏览器填表完成配置，无需手动编辑配置文件

---

## 界面预览

<p align="center">
  <img src="docs/screenshots/img.png" width="900" /><br/>
  <img src="docs/screenshots/img_1.png" width="900" /><br/>
  <sub><b>统计面板</b> — 藏书概览、分类分布、评分分布、入库趋势、最近添加 / 最近阅读 / 从未翻开</sub>
</p>

<p align="center">
  <img src="docs/screenshots/img_2.png" width="900" /><br/>
  <sub><b>继续阅读</b> — 当前阅读进度卡片 + 最近阅读书籍网格</sub>
</p>

<p align="center">
  <img src="docs/screenshots/img_3.png" width="900" /><br/>
  <sub><b>书库管理</b> — 封面卡片视图，搜索、导入、同步、批量操作</sub>
</p>

<p align="center">
  <img src="docs/screenshots/img_4.png" width="900" /><br/>
  <sub><b>右键菜单</b> — 下载、编辑、AI 识别、AI 分类、删除、刮削封面、标记已读</sub>
</p>

<p align="center">
  <img src="docs/screenshots/img_5.png" width="900" /><br/>
  <sub><b>分类筛选</b> — 按分类 / 系列 / 标签分组浏览</sub>
</p>

<p align="center">
  <img src="docs/screenshots/img_6.png" width="900" /><br/>
  <sub><b>Calibre 配置</b> — 微服务地址设置与连接测试</sub>
</p>

<p align="center">
  <img src="docs/screenshots/img_7.png" width="900" /><br/>
  <sub><b>后台任务</b> — WebDAV 同步日志，增量同步全过程可追溯</sub>
</p>

<p align="center">
  <img src="docs/screenshots/img_8.png" width="900" /><br/>
  <sub><b>AI 分类</b> — 后台任务日志，AI 自动检索豆瓣并写入分类和标签</sub>
</p>

---

## 系统要求

| 组件 | 版本 / 说明 |
| --- | --- |
| PHP | >= 8.3，需要 `mbstring`、`pdo_mysql`、`curl`、`gd`、`zip`、`fileinfo` |
| MySQL / MariaDB | MySQL >= 5.7 或 MariaDB >= 10.2，字符集 `utf8mb4` |
| Web 服务器 | Nginx 或 Apache（必须支持 URL 重写） |
| WebDAV | 坚果云 / Nextcloud / 群晖 / 阿里云盘网关，任选其一 |
| 静读天下 App | Android，支持 WebDAV 同步的版本即可 |
| Docker（可选） | 仅当需要 MOBI/AZW 格式转换、封面提取时使用 |

---

## 部署形态

本系统是一个普通 PHP 项目，**核心服务不强制 Docker**，但配套依赖建议容器化：

### 必需

| 服务 | 用途 | 推荐镜像 |
| --- | --- | --- |
| MySQL / MariaDB | 业务数据库 | `mysql:8.0` 或 `mariadb:11` |
| PHP-FPM | 运行 PHP 8.3 | `php:8.3-fpm` |
| Nginx | 静态资源 + 反向代理到 PHP-FPM | `nginx:alpine` |

> 用 1Panel / 宝塔 / Docker Compose 直接装现成 LNMP 也可以，本质就是一个 PHP 8.3 + MySQL 的环境。

### 可选

- **`ebook-service` 容器**：位于 `src/calibre/ebook-service/`，封装 Calibre CLI 提供 HTTP 接口，用于：
  - MOBI / AZW / AZW3 等非 EPUB 格式的封面提取
  - 格式转换（如需要）

  启动方式：

  ```bash
  cd src/calibre/ebook-service
  docker compose up -d
  ```

  服务监听 `8080` 端口，安装完成后可在系统「Calibre 配置」页面填入地址并测试连接。

  不需要 Calibre 能力时，**这个容器可以完全不装**，系统只会失去非 EPUB 格式的封面提取功能。

---

## 安装步骤

### 1. 拉代码

```bash
git clone <repository-url> book
cd book
git submodule update --init --recursive
```

### 2. 创建数据库

登录 MySQL，建一个空库 + 一个专用账号：

```sql
CREATE DATABASE `book` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'book'@'%' IDENTIFIED BY '改成你自己的强密码';
GRANT ALL PRIVILEGES ON `book`.* TO 'book'@'%';
FLUSH PRIVILEGES;
```

> 不需要手工建表，系统首次启动会自动建表并升级 schema。

### 3. 准备配置文件

```bash
cp src/example.config.php src/config.php
```

> `config.php` 已被 `.gitignore` 排除，不会被提交。后续配置由安装向导自动写入。

### 4. 配置 Nginx

工作目录指向 `src/public`，并加 URL 重写：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/book/src/public;
    index index.php;

    location / {
        rewrite ^(.*)$ /index.php/$1 last;
    }

    location ~ \.php(/|$) {
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        fastcgi_pass   127.0.0.1:9000;   # 容器化部署改成 php-fpm:9000
        fastcgi_index  index.php;
        include        fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param  PATH_INFO       $fastcgi_path_info;
    }
}
```

确保 `src/runtime` 目录对 PHP 进程可写（缓存、日志、初始密码都写这里）。

### 5. 运行安装向导

打开 `http://your-domain.com`，系统检测到未安装会自动跳转到安装页面。

在安装向导中填写：

- **数据库连接**：主机、端口、账号、密码、库名
- **WebDAV 配置**：服务器地址、账号、密码、设备 ID
- **系统名称**：显示在页面标题的名称

提交后系统会：

1. 测试数据库连接
2. 写入配置到 `config.php`
3. 自动建表并生成管理员账号
4. 返回初始管理员用户名和密码

用返回的凭据登录即可。

> 三个最常见的 WebDAV 配错：
> - 地址少了末尾 `/dav/`
> - 坚果云填了登录密码而不是"应用密码"
> - App 端和本系统的地址 / 账号不一致

---

## 用户名密码

本系统**没有注册页**，管理员账号在安装时自动生成：

- 用户名固定：`admin`
- 密码：随机 16 位十六进制串，安装完成后会显示在页面上，同时写入 `src/runtime/admin_password.txt`

登录后立刻去**右上角用户菜单 → 修改密码**，把初始随机密码换成你自己的。限制：
- 新密码最少 8 位
- 新用户名只能是 5–10 位的小写字母数字
- 修改成功会强制踢下线，需要用新凭据重新登录

> 忘记密码的最快做法：直接 `DROP TABLE` 用户相关表让系统重新生成 admin，或者手动用 `password_hash()` 在 MySQL 里改 `password` 字段。

如果有自建 SSO（OIDC），在 `config.php` 中将 `login.ssoEnable` 改为 `true`，并填写 `ssoProviderUrl`、`ssoClientId`、`ssoClientSecret`。

---

## AI 功能配置（可选）

系统集成了 AI 能力，用于：

- **AI 智能填充**：编辑书籍时一键让 AI 检索豆瓣，补全书名、作者、简介、封面、评分、分类、标签（SSE 实时推送进度，结果预填到表单供人工核对）
- **AI 批量识别**：选中多本书提交后台任务，AI 逐本检索并直接写库
- **AI 批量分类**：选中多本书提交后台任务，AI 自动判断分类和标签

在 `config.php` 中配置 AI 服务商：

```php
'ai' => [
    'currentProvider' => 'OpenRouter',   // 或 'ChatGPT'
    'providers' => [
        'openrouter' => [
            'api_key'   => 'sk-or-v1-xxx',
            'api_url'   => 'https://openrouter.ai/api',
            'api_model' => 'qwen/qwen3.6-flash',
            'proxy'     => '',
        ],
        'chatgpt' => [
            'api_key'   => '',
            'api_url'   => '',
            'api_model' => '',
            'proxy'     => '',
        ],
    ],
],
```

不配置 AI 不影响系统其他功能，仅 AI 相关按钮不可用。

---

## 静读天下侧配置（一次就够）

打开静读天下 App → 设置 → 通过 WebDAV 同步：

```
服务器地址 : https://dav.jianguoyun.com/dav/    （以坚果云为例）
用户名     : your_email@example.com
密码       : 应用密码（不是登录密码）
同步文件夹 : Apps/Books/                        （保持默认，不要改）
勾选【同步我的书架】
```

在 App 侧执行一次「立即同步」，确认 WebDAV 上出现 `Apps/Books/` 目录后，再回到本系统等待自动同步或手动点击「同步」按钮拉数据。

---

## 典型工作流

```
手机静读天下 → 加书 / 改元数据 → 同步到 WebDAV
                                      │
                                      ▼
                      本系统自动同步（每小时）或手动触发
                                      │
                                      ▼
              Web 端搜索 / 批量编辑 / AI 识别 / 豆瓣抓取 / 统计
                                      │
                                      ▼
                         手机静读天下下次同步拉走更新
```

---

## 故障排查

**同步后没有任何书：**
- 确认手机端真的成功同步了（WebDAV 上有 `Apps/Books/<书名>.epub`）
- 用 curl 验证 WebDAV 凭据：`curl -u "user:pass" https://dav.jianguoyun.com/dav/Apps/Books/`
- 检查 `src/runtime/log/` 下的错误日志

**登录页一直报错：**
- 验证码识别有误，刷新一下
- 查看 `src/runtime/log/` 是否记录了密码错误 / 验证码错误

**封面提取失败：**
- 非 EPUB 格式需要 `ebook-service` 容器
- 在系统「Calibre 配置」页面测试连接

**AI 功能不工作：**
- 确认 `config.php` 中 `ai.currentProvider` 和对应的 `api_key`、`api_url`、`api_model` 都已填写
- 检查网络是否能访问 AI 服务商的 API 地址

**数据库连不上：**
- Docker 部署时 `db.host` 必须是容器名（如 `mysql`），不能写 `localhost`
- 确认账号有 `book` 库的全部权限

---

## 目录结构

```
book/
├── src/
│   ├── app/
│   │   ├── ai/                 # AI Agent、工具集、任务（元数据填充、分类）
│   │   ├── controller/         # 控制器（书籍、上传、豆瓣、Calibre）
│   │   ├── database/           # 数据模型与 DAO（BookModel、ReadingProgressModel）
│   │   ├── task/               # 后台任务（同步、封面刮削、AI 识别/分类）
│   │   ├── utils/              # 工具类（豆瓣搜索、BookManager、安装向导）
│   │   ├── static/             # 前端资源（JS、Foliate 阅读器、UI 组件）
│   │   └── Application.php     # 应用入口，路由注册，定时任务注册
│   ├── calibre/
│   │   └── ebook-service/      # 可选 Calibre 微服务（docker compose）
│   ├── nova/                   # Nova 框架 + 插件（submodule）
│   ├── public/                 # Web 入口，Nginx root 指向这里
│   ├── runtime/                # 缓存、日志、admin_password.txt
│   ├── example.config.php      # 配置模板，复制为 config.php 使用
│   └── config.php              # 实际配置（.gitignore 排除）
├── tests/                      # 测试目录
├── dist/                       # 发布包
├── docs/                       # 文档资源
├── nginx.conf                  # Nginx rewrite 参考
├── package.json                # 项目元信息
├── nova.phar                   # CLI 工具
└── README.md
```

---

## 技术栈

- **后端**：PHP 8.3 + Nova 框架 + MySQL
- **前端**：MDUI 2.x + Foliate.js（在线阅读器）
- **AI**：OpenRouter / ChatGPT（通过 nova-ai 插件，Agent + Tool Calling 架构）
- **存储**：WebDAV（坚果云 / Nextcloud / 群晖 ……）
- **可选**：Calibre（封装在 `ebook-service` Python 微服务里）

---

## 许可证

MIT License

## 贡献

欢迎 Issue / PR：
- PHP：PSR-12 / `php-cs-fixer.dist.php`
- JS：ES6+
- Commit：写清楚"为什么改"，不只是"改了什么"
