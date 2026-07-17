# Windows 绿色包部署教程

> 适用场景：Windows 本机，不想装 phpEnv / 面板，下载解压就能用。  
> 发布包：`dist/book-<版本>-windows.zip`（例如 `book-1.0.7-windows.zip`）  
> 内置 TinyPHP：PHP 8.3 + Nginx，无控制面板、无服务注册。

> 若你更习惯图形化集成环境，也可改用 [phpEnv 安装教程](install-phpenv.md)。

---

## 一、环境要求

| 组件 | 要求 |
| --- | --- |
| 操作系统 | Windows 64 位 |
| 本包自带 | PHP 8.3、Nginx、必要扩展 |
| 数据库 | **推荐 SQLite**（本包不含 MySQL；选 MySQL 需自备服务） |
| 端口 | 本机 `80` 未被占用（访问 `http://localhost`） |

---

## 二、获取与解压

1. 从 [GitHub Releases](https://github.com/AnkioTomas/book/releases) 或项目 `dist/` 下载 `book-<版本>-windows.zip`。
2. 解压到**纯英文、无空格**路径，例如：

   ```
   D:\book
   ```

   不要放进 `C:\Program Files`。

3. 解压后进入目录，核心在 `tinyphp/`：

   ```
   tinyphp/
   ├── start.bat          ← 启动
   ├── stop.bat           ← 停止
   ├── status.bat         ← 查看进程
   ├── install_ext.ps1    ← 按需装 PHP 扩展
   ├── public.conf        ← Nginx 站点根（start 时自动写）
   ├── rewrite.conf       ← 伪静态
   ├── www/               ← Book 源码（Web 根在 www/public）
   ├── core/              ← PHP + Nginx 二进制
   └── logs/              ← 日志（启动后生成）
   ```

---

## 三、启动 / 停止

1. 双击 `tinyphp/start.bat`。
2. 脚本会拉起 PHP-CGI（xxfpm）与 Nginx，并打开浏览器访问 `http://localhost`。
3. 关闭启动窗口**不会**停服务；要停就双击 `stop.bat`。
4. `status.bat` 可查看 Nginx / PHP 进程是否在跑。

> 若 80 端口被 IIS、其它 Nginx、占用，先关掉冲突程序，或改 `tinyphp/core/nginx` 配置里的监听端口后再启动。

---

## 四、安装向导

1. 浏览器打开 `http://localhost`。
2. 安装向导中建议：

   | 项目 | 填写说明 |
   | --- | --- |
   | 数据库类型 | **SQLite**（默认，无需另装数据库） |
   | 数据库主机/端口/账号 | 选 SQLite 时忽略 |
   | WebDAV 地址 / 账号 / 密码 | 与静读天下 App **完全一致** |
   | 系统名称 | 随意 |

3. 安装成功后记录 `admin` 与初始密码（亦写入 `tinyphp/www/runtime/admin_password.txt`）。
4. 登录后立刻 **修改密码**。

若坚持用 MySQL：先在本机装好 MySQL/MariaDB，建空库，安装向导里改选 MySQL 并填连接信息。

---

## 五、日常操作

| 操作 | 做法 |
| --- | --- |
| 启动 | `start.bat` |
| 停止 | `stop.bat` |
| 看状态 | `status.bat` |
| 看日志 | `tinyphp/logs/` |
| 加 PHP 扩展 | PowerShell 执行 `.\install_ext.ps1 "redis"`，再 stop → start |

---

## 六、可选：Calibre 封面微服务

非 EPUB 封面提取需要 Docker Desktop：

```bat
cd tinyphp\www\calibre\ebook-service
docker compose up -d
```

后台 → **Calibre 配置** → `http://127.0.0.1:8080` → 测试连接。

---

## 七、常见问题

**打开 localhost 无法访问**

- 是否已运行 `start.bat`，`status.bat` 里 Nginx / php-cgi 是否在。
- 80 端口是否被占用。
- 看 `tinyphp/logs/` 下 Nginx / PHP 错误日志。

**安装报 runtime 不可写**

- 确认解压目录对当前用户可写。
- 手动创建 `tinyphp\www\runtime` 后再试。

**页面 404 / 路由异常**

- 网站入口必须是 `www/public`（`start.bat` 会自动写入 `public.conf`）。
- 不要把站点根改成 `www/`。

**选 MySQL 连不上**

- 本包不带 MySQL。确认本机 MySQL 已启动，主机填 `127.0.0.1`。
- 或改回 SQLite。

**WebDAV 同步无书**

- App 端需先同步出 `Apps/Books/`。
- 地址、账号、密码与 App 完全一致（坚果云用应用密码）。
