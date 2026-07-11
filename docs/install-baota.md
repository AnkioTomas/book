# Linux 部署教程（宝塔面板）

> 适用场景：在 **Linux 服务器** 上通过 [宝塔面板（BT Panel）](https://www.bt.cn/) 部署。  
> 官方文档：[https://docs.bt.cn/](https://docs.bt.cn/)

---

## 一、环境要求

| 组件 | 要求 |
| --- | --- |
| 服务器 | Linux（推荐 Ubuntu 22.04 / Debian 12 / CentOS 7+） |
| 面板 | 宝塔 Linux 面板 7.x / 8.x / 10.x |
| PHP | **8.3** |
| 数据库 | MySQL 5.7+ / MySQL 8.0 / MariaDB |
| Web | Nginx（推荐）或 Apache |
| PHP 扩展 | `mbstring`、`pdo_mysql`、`curl`、`gd`、`zip`、`fileinfo` |

---

## 二、安装宝塔面板

SSH 登录服务器，执行宝塔官方安装脚本（以 [宝塔官网](https://www.bt.cn/new/download.html) 最新命令为准）：

```bash
# 示例（请优先使用官网当前提供的命令）
url=https://download.bt.cn/install/install_lts.sh
if [ -f /usr/bin/curl ]; then
  curl -sSO "$url"
else
  wget -O install_lts.sh "$url"
fi
bash install_lts.sh
```

安装完成后终端会输出：

- 面板地址（形如 `http://服务器IP:8888/xxxxxxxx`）
- 用户名
- 密码

浏览器登录，按提示安装 **LNMP 推荐套件**（或手动安装下面三个）。

---

## 三、安装运行环境

左侧 **软件商店**，安装并启动：

| 软件 | 版本建议 |
| --- | --- |
| Nginx | 最新稳定版 |
| MySQL | 8.0 或 5.7 |
| PHP | **8.3** |

### 安装 PHP 扩展

1. **软件商店** → 已安装的 **PHP 8.3** → **设置**。
2. **安装扩展** 中启用：

   - `fileinfo`
   - `mysqli`
   - `pdo_mysql`
   - `mbstring`
   - `curl`
   - `gd`
   - `zip`

3. **配置修改 → php.ini**，建议调整：

   ```ini
   upload_max_filesize = 512M
   post_max_size = 512M
   max_execution_time = 600
   memory_limit = 256M
   date.timezone = Asia/Shanghai
   ```

4. **服务** 页签 → **重载配置** / **重启** PHP。

---

## 四、创建数据库

1. 左侧 **数据库** → **添加数据库**。

   | 字段 | 建议值 |
   | --- | --- |
   | 数据库名 | `book` |
   | 用户名 | `book` |
   | 密码 | 自行设置强密码 |
   | 访问权限 | 本地服务器（`localhost`） |

2. 记下数据库名、用户名、密码，安装向导要用。

> 不需要手工建表，安装向导会自动建表。

---

## 五、添加网站

1. 左侧 **网站** → **添加站点**。

   | 字段 | 建议值 |
   | --- | --- |
   | 域名 | `book.example.com`（无域名可填服务器 IP） |
   | 根目录 | `/www/wwwroot/book.example.com`（默认即可） |
   | FTP / 数据库 | 数据库已在第四节创建，此处可不勾选 |
   | PHP 版本 | **PHP-83** |

2. 提交创建。

---

## 六、部署项目代码

### 6.1 上传代码

**方式 A：Git（推荐）**

SSH 进入网站根目录：

```bash
cd /www/wwwroot/book.example.com
git clone <repository-url> .
git submodule update --init --recursive
cp src/example.config.php src/config.php
```

**方式 B：宝塔文件管理器**

1. 本地打包项目（确保 submodule 内容已包含）。
2. **文件** → 进入 `/www/wwwroot/book.example.com` → 上传并解压。
3. SSH 或终端执行 `cp src/example.config.php src/config.php`。

### 6.2 设置运行目录（必做）

Book 的 Web 入口在 `src/public`：

1. **网站** → 对应站点 → **设置**。
2. **网站目录** → **运行目录** 选择 **`/src/public`**。
3. 取消勾选「防跨站攻击(open_basedir)」若导致无法读写（仅当报 open_basedir 错误时）。
4. 保存。

最终目录结构：

```
/www/wwwroot/book.example.com/
├── src/
│   ├── public/       ← 运行目录
│   ├── runtime/      ← 需可写
│   ├── config.php
│   └── ...
└── ...
```

### 6.3 目录权限

SSH 执行（域名目录按实际替换）：

```bash
cd /www/wwwroot/book.example.com
mkdir -p src/runtime src/uploads
chown -R www:www src/runtime src/uploads
chmod -R 755 src/runtime src/uploads
```

宝塔默认 Web 用户为 `www`，若写入失败可在 **网站目录** 页查看并统一所有者。

---

## 七、配置伪静态

1. **网站 → 设置 → 伪静态**。
2. 下拉没有 Book / Nova 方案时，选择 **自定义**，粘贴：

   ```nginx
   location / {
       rewrite ^(.*)$ /index.php/$1 last;
   }
   ```

3. 保存。

若保存后整站 502，检查是否与宝塔默认 `location` 冲突；可改用：

```nginx
if (!-e $request_filename) {
    rewrite ^(.*)$ /index.php/$1 last;
}
```

---

## 八、上传大小限制（建议）

大体积书籍上传需同时调整 Nginx 与 PHP：

1. **网站 → 设置 → 配置文件**，在 `server { }` 内增加：

   ```nginx
   client_max_body_size 512m;
   ```

2. 确认第三节 php.ini 中 `upload_max_filesize` / `post_max_size` 已为 `512M`。
3. 保存并重载 Nginx、PHP。

---

## 九、运行安装向导

1. 浏览器访问 `http://book.example.com`（或服务器 IP）。
2. 未备案域名可能被云厂商拦截，需完成备案或使用 IP 访问。
3. 进入安装向导，填写：

   | 项目 | 填写说明 |
   | --- | --- |
   | 数据库主机 | `127.0.0.1` |
   | 数据库端口 | `3306` |
   | 数据库账号 | `book` |
   | 数据库密码 | 第四节设置的密码 |
   | 数据库库名 | `book` |
   | WebDAV 地址 | 与静读天下 App 一致，如 `https://dav.jianguoyun.com/dav/` |
   | WebDAV 账号 / 密码 | 与 App 端相同（坚果云用应用密码） |
   | 系统名称 | 随意 |

4. 安装成功后会显示：
   - 管理员：`admin`
   - 随机初始密码（同时写入 `src/runtime/admin_password.txt`）
5. 登录后立即 **修改密码**。

---

## 十、HTTPS（可选）

1. **网站 → 设置 → SSL**。
2. 选择 **Let's Encrypt** 申请免费证书，或粘贴已有证书。
3. 开启 **强制 HTTPS**。
4. 保存。

---

## 十一、可选：Calibre 封面微服务

非 EPUB（MOBI / AZW / AZW3）封面提取需要 `ebook-service`：

1. 宝塔 **软件商店** 安装 **Docker 管理器**（若尚未安装）。
2. SSH 执行：

   ```bash
   cd /www/wwwroot/book.example.com/src/calibre/ebook-service
   docker compose up -d
   ```

3. Book 后台 → **Calibre 配置** → 填入 `http://127.0.0.1:8080` → **测试连接**。

不需要 MOBI/AZW 封面能力可跳过。

---

## 十二、常见问题

**404 / No input file specified**

- **运行目录** 是否为 `/src/public`。
- 伪静态是否已保存；PHP 版本是否为 8.3。

**502 Bad Gateway**

- PHP 8.3 是否安装并已在站点中选中。
- **软件商店 → PHP 8.3 → 服务** 是否运行中。

**数据库连接失败**

- 主机填 `127.0.0.1`，不要用容器名（宝塔 MySQL 通常在本机）。
- 数据库用户访问权限是否为「本地服务器」。

**500 / 空白页**

- 查看 `src/runtime/log/` 日志。
- 确认 `git submodule update --init --recursive` 已执行。

**安装或上传报权限错误**

- `chown -R www:www src/runtime src/uploads`
- 检查 **网站目录** 是否误开 open_basedir 限制。

**上传大文件失败**

- 同时检查 php.ini 与 Nginx `client_max_body_size`（见第八节）。

**WebDAV 同步无书**

- 静读天下 App 需先同步到 WebDAV，且存在 `Apps/Books/` 目录。
- WebDAV 凭据必须与 App 端完全一致。

---

## 参考链接

- 宝塔官网：[https://www.bt.cn/](https://www.bt.cn/)
- 宝塔文档：[https://docs.bt.cn/](https://docs.bt.cn/)
- 站点伪静态配置：[https://docs.bt.cn/10.0/user-guide/site/php/site-config/pseudo-static](https://docs.bt.cn/10.0/user-guide/site/php/site-config/pseudo-static)
