# Windows 部署教程（phpEnv）

> 适用场景：在 **Windows 本机或 Windows 服务器** 上用 phpEnv 自建 PHP + MySQL + Nginx/Apache。  
> 官方下载：[https://www.phpenv.cn/download.html](https://www.phpenv.cn/download.html)

> **更省事**：若只想本机试用、不想装面板，直接用 [Windows 绿色包](install-windows.md)（`book-*-windows.zip`）。

> **注意**：这里的 phpEnv 是 Windows 图形化集成环境，**不是** GitHub 上同名的命令行工具 `phpenv`。

---

## 一、环境要求

| 组件 | 要求 |
| --- | --- |
| 操作系统 | Windows 64 位（Win7 需 SP1 + KB3063858 补丁） |
| PHP | **8.3**（软件商店安装） |
| MySQL | 5.7 / 8.0 / MariaDB 均可 |
| Web 服务器 | Nginx 或 Apache（推荐 Nginx） |
| PHP 扩展 | `mbstring`、`pdo_mysql`、`curl`、`gd`、`zip`、`fileinfo` |

---

## 二、安装 phpEnv

1. 打开 [phpEnv 下载页](https://www.phpenv.cn/download.html)，下载 **phpEnv（Windows 64 位）** 压缩包或安装版。
2. 解压/安装到 **纯英文、无空格** 的路径，例如：
   ```
   D:\phpEnv
   ```
   路径里不要出现中文，也不要放在 `C:\Program Files` 这类权限受限目录。
3. 启动 phpEnv 主程序，确认 Nginx（或 Apache）、MySQL 服务能正常启动（状态栏无红色报错）。

---

## 三、安装 PHP 8.3 与扩展

1. 打开 phpEnv → **软件商店**（或 **PHP 版本管理**）。
2. 找到 **PHP 8.3**，点击安装，等待下载完成。
3. 进入该 PHP 版本的 **配置 → php.ini**，确认以下扩展已启用（去掉行首分号 `;`）：

   ```ini
   extension=curl
   extension=gd
   extension=mbstring
   extension=mysqli
   extension=pdo_mysql
   extension=zip
   ```

   `fileinfo` 在 PHP 8.3 中通常默认开启，可在 php.ini 中搜索 `fileinfo` 确认未被禁用。

4. 同一 php.ini 中，建议为大文件上传调整（书籍文件可能较大）：

   ```ini
   upload_max_filesize = 512M
   post_max_size = 512M
   max_execution_time = 600
   memory_limit = 256M
   date.timezone = Asia/Shanghai
   ```

5. 保存后，在 phpEnv 中 **重启 PHP / Web 服务**。

---

## 四、拉取项目代码

**方式 A：标准发布包（推荐）**

下载 `book-<版本>.zip`，解压到例如 `D:\www\book`。已含框架，无需 submodule。

```bat
cd D:\www\book
copy example.config.php config.php
```

网站根目录指向解压后的 `public`（见第六节）。

**方式 B：Git**

打开 phpEnv 自带的终端，或系统 CMD / PowerShell：

```bash
cd D:\www
git clone <repository-url> book
cd book
git submodule update --init --recursive
cp src/example.config.php src/config.php
```

> `git submodule` 必须执行，否则 Nova 框架代码缺失，网站无法运行。

确保以下目录存在且可写（Windows 下一般默认可写）：

- `runtime`（Git 布局为 `src/runtime`）— 缓存、日志、初始管理员密码
- `uploads`（Git 布局为 `src/uploads`）— 上传临时文件（首次访问会自动创建）

---

## 五、创建 MySQL 数据库

> 安装向导默认可用 **SQLite**，可不建 MySQL。需要 MySQL 时再执行本节。

### 方式 A：phpEnv 内置 phpMyAdmin

1. phpEnv 主界面打开 **phpMyAdmin**。
2. 使用 root 账号登录（默认密码见 phpEnv 文档或安装说明，常见为 `root`）。
3. 执行 SQL：

   ```sql
   CREATE DATABASE `book` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'book'@'localhost' IDENTIFIED BY '你的强密码';
   GRANT ALL PRIVILEGES ON `book`.* TO 'book'@'localhost';
   FLUSH PRIVILEGES;
   ```

### 方式 B：phpEnv MySQL 命令行

在 phpEnv 终端中连接 MySQL 后执行上述 SQL 即可。

> 不需要手工建表，安装向导会自动建表。

---

## 六、添加网站

1. phpEnv → **网站** → **添加网站**。
2. 填写：

   | 字段 | 示例值 |
   | --- | --- |
   | 域名 | `book.local`（本地开发）或你的真实域名 |
   | 网站目录 | 标准 zip：`D:\www\book\public`；Git：`D:\www\book\src\public` |
   | PHP 版本 | **PHP 8.3** |
   | Web 服务器 | Nginx |

   **网站根目录必须指向 `public`**，不是项目根目录。

3. 保存后，phpEnv 会自动写入 hosts（如 `127.0.0.1 book.local`）。

---

## 七、配置 URL 重写（伪静态）

Book 使用 Nova 框架的单入口路由，必须配置重写规则。

1. 在 phpEnv **网站列表** 中，找到刚创建的站点 → **URL 重写**（或 **伪静态**）。
2. 选择 **Nginx**，填入：

   ```nginx
   location / {
       rewrite ^(.*)$ /index.php/$1 last;
   }
   ```

3. 保存并重启 Web 服务。

若使用 Apache，在站点目录或虚拟主机配置中加入：

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ index.php/$1 [L]
</IfModule>
```

---

## 八、运行安装向导

1. 浏览器访问 `http://book.local`（或你配置的域名）。
2. 系统自动跳转到 **安装向导**，填写：

   | 项目 | 填写说明 |
   | --- | --- |
   | 数据库类型 | SQLite（默认）或 MySQL |
   | 数据库主机 | MySQL 填 `127.0.0.1`；SQLite 忽略 |
   | 数据库端口 | MySQL 填 `3306`；SQLite 忽略 |
   | 数据库账号 / 密码 | MySQL 用第五节账号；SQLite 可留空 |
   | 数据库库名 | MySQL 填 `book`；SQLite 保持默认 |
   | WebDAV 地址 | 与静读天下 App 完全一致，如 `https://dav.jianguoyun.com/dav/` |
   | WebDAV 账号 | 坚果云填邮箱 |
   | WebDAV 密码 | 坚果云填 **应用密码**（不是登录密码） |
   | 系统名称 | 随意，如「我的书库」 |

3. 提交成功后，页面会显示：
   - 管理员用户名：`admin`
   - 随机初始密码（同时写入 `src/runtime/admin_password.txt`）

4. 用返回的凭据登录，**立即修改密码**。

---

## 九、可选：Calibre 封面微服务

非 EPUB 格式（MOBI / AZW / AZW3）的封面提取需要 Calibre 微服务。Windows 本机可选：

1. 安装 Docker Desktop for Windows。
2. 在项目目录执行：

   ```bash
   cd src/calibre/ebook-service
   docker compose up -d
   ```

3. 登录 Book 后台 → **Calibre 配置**，填入 `http://127.0.0.1:8080` 并测试连接。

不需要处理 MOBI/AZW 封面时可跳过。

---

## 十、常见问题

**打开网站 404 / No input file specified**

- 确认网站根目录是 `...\book\public`（标准 zip）或 `...\book\src\public`（Git），不是项目根。
- 确认 URL 重写规则已保存并重启 Web 服务。

**502 Bad Gateway**

- PHP 8.3 是否已安装并在站点中选中。
- phpEnv 中 PHP / Nginx 服务是否正常运行。

**数据库连接失败**

- phpEnv 本机部署时，主机填 `127.0.0.1`，不要填 `localhost`（某些环境下 socket 行为不一致）。
- 确认 MySQL 服务已启动，账号密码与第五节一致。

**页面空白 / 500 错误**

- 查看 `src/runtime/log/` 下的日志。
- 确认 `git submodule update --init --recursive` 已执行。

**WebDAV 同步后没有书**

- 确认静读天下 App 已成功同步，WebDAV 上存在 `Apps/Books/` 目录。
- 安装向导中的 WebDAV 地址、账号必须与 App 端 **完全一致**。

---

## 参考链接

- phpEnv 官方下载：[https://www.phpenv.cn/download.html](https://www.phpenv.cn/download.html)
- phpEnv 官方文档（看云）：[https://www.kancloud.cn/liu1040063186/phpenv](https://www.kancloud.cn/liu1040063186/phpenv)
