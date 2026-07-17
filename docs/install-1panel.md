# Linux 部署教程（1Panel）

> 适用场景：在 **Linux 服务器**（云 VPS、NAS、家用 Linux 主机）上通过 [1Panel](https://1panel.cn/) 面板部署。  
> 1Panel 官方文档：[https://1panel.cn/docs/](https://1panel.cn/docs/)

---

## 一、环境要求

| 组件 | 要求 |
| --- | --- |
| 服务器 | Linux（推荐 Ubuntu 22.04 / Debian 12 / CentOS 7+） |
| 面板 | 1Panel v2 |
| PHP | **8.3**（通过「运行环境」创建） |
| 数据库 | MySQL 8.0 / MariaDB（1Panel 应用商店或已有实例） |
| Web | OpenResty（1Panel 网站模块依赖） |
| PHP 扩展 | `mbstring`、`pdo_mysql`、`curl`、`gd`、`zip`、`fileinfo` |

---

## 二、安装 1Panel

在服务器上执行 1Panel 官方安装命令（以官网最新命令为准）：

```bash
curl -sSL https://resource.fit2cloud.com/1panel/package/quick_start.sh -o quick_start.sh
sudo bash quick_start.sh
```

安装完成后记录面板地址、用户名和密码，浏览器登录 1Panel。

---

## 三、安装 OpenResty

1. 左侧菜单 → **应用商店**。
2. 搜索 **OpenResty**，点击安装。
3. 等待状态变为 **运行中**。

> 1Panel 的「网站」功能依赖 OpenResty，必须先装。

---

## 四、创建 PHP 8.3 运行环境

1. 左侧菜单 → **网站** → **运行环境**。
2. 点击 **创建运行环境**：

   | 字段 | 建议值 |
   | --- | --- |
   | 名称 | `php83-book` |
   | 应用 | PHP |
   | 版本 | **8.3.x**（选最新 8.3） |
   | 扩展 | 勾选或手动添加：`pdo_mysql`、`mysqli`、`mbstring`、`curl`、`gd`、`zip`、`fileinfo` |

3. 创建完成后等待环境就绪（首次拉镜像可能需要几分钟）。

### 调整 PHP 上传限制（建议）

进入该运行环境的 **配置 → php.ini**（或 **设置**），修改：

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 600
memory_limit = 256M
date.timezone = Asia/Shanghai
```

保存并重载 PHP 环境。

---

## 五、创建 MySQL 数据库

> 安装向导默认可用 **SQLite**，不想装 MySQL 可跳过本节。

### 方式 A：1Panel 已有 MySQL 应用

1. **应用商店** 安装 **MySQL** 或 **MariaDB**（若尚未安装）。
2. 左侧 → **数据库** → **创建数据库**：

   | 字段 | 建议值 |
   | --- | --- |
   | 名称 | `book` |
   | 用户名 | `book` |
   | 密码 | 自行设置强密码 |
   | 字符集 | `utf8mb4` |

3. 创建完成后，在数据库详情页查看 **连接地址**（主机名、端口），安装向导要用。

### 方式 B：创建网站时一并创建

在下一步「创建网站」时勾选 **创建数据库**，1Panel 会自动生成库名和账号，记下连接信息即可。

> 不需要手工建表，安装向导会自动建表。

---

## 六、部署项目代码

### 6.1 创建网站

1. **网站** → **创建网站** → 选择 **运行环境**。
2. 填写：

   | 字段 | 建议值 |
   | --- | --- |
   | 类型 | 运行环境 |
   | 运行环境 | 第四节创建的 `php83-book` |
   | 主域名 | `book.example.com`（或服务器 IP） |
   | 代号 | `book`（决定网站目录名） |
   | 创建数据库 | 可选（第五节已建则跳过） |

3. 确认创建。网站根目录一般为：

   ```
   /opt/1panel/www/sites/book/index
   ```

   （`book` 为代号，以面板实际路径为准。）

### 6.2 上传代码

**方式 A：标准发布包（推荐）**

下载 `book-<版本>.zip`，上传到网站 `index` 目录并解压。已含框架代码，**无需** `git submodule`。

```bash
cd /opt/1panel/www/sites/book/index
cp example.config.php config.php   # 若解压后直接是应用根
# 或：cp src/example.config.php src/config.php  （Git 布局）
```

**方式 B：Git**

进入 1Panel 网站目录（**网站 → 配置 → 网站目录 → 打开**），在终端或 SSH 中：

```bash
cd /opt/1panel/www/sites/book/index
# 若目录非空，先清空（注意备份）
git clone <repository-url> .
git submodule update --init --recursive
cp src/example.config.php src/config.php
```

**方式 C：本地上传自打包**

1. 本地打包项目（含 submodule 内容）。
2. 1Panel 文件管理器上传到网站 `index` 目录并解压。
3. SSH 执行对应的 `cp ... config.php`。

> 也可用 Docker 整站部署，见 [Docker 安装教程](install-docker.md)。

### 6.3 设置运行目录

Book 的 Web 入口在 `public/`，必须单独指定：

1. **网站 → 配置 → 网站目录**。
2. **运行目录**：
   - 标准 zip 解压到网站根：`/public`
   - Git / 仓库布局：`/src/public`
3. **运行用户/组**：保持默认（通常为 `1000:1000`），若出现写入失败再按面板提示调整。
4. 点击 **保存并重载**。

标准 zip 目录示例：

```
/opt/1panel/www/sites/book/index/
├── public/              ← 运行目录
├── runtime/             ← 需可写
├── config.php
└── ...
```

### 6.4 目录权限

确保 PHP 进程能写入运行时目录：

```bash
cd /opt/1panel/www/sites/book/index
mkdir -p runtime uploads             # Git 布局用 src/runtime src/uploads
chown -R 1000:1000 runtime uploads
chmod -R 755 runtime uploads
```

若上传或安装报权限错误，按 1Panel 网站目录页显示的用户/组调整 `chown`。

---

## 七、配置伪静态（URL 重写）

1. **网站 → 配置 → 伪静态**。
2. 若下拉列表中没有 Book / Nova 方案，选择 **自定义**，填入：

   ```nginx
   location / {
       rewrite ^(.*)$ /index.php/$1 last;
   }
   ```

3. 点击 **保存并重载**。

若上述规则不生效，改用 **配置文件** 选项卡，在 `server { }` 块内、`location ~ \.php` 之前加入同样的 `location /` 规则，保存并重载。

---

## 八、运行安装向导

1. 浏览器访问 `http://book.example.com`（或服务器 IP）。
2. 若域名未解析，可先在 **网站 → 配置 → 域名** 中绑定 IP，或临时用 IP 访问。
3. 进入安装向导，填写：

   | 项目 | 填写说明 |
   | --- | --- |
   | 数据库类型 | SQLite（默认）或 MySQL |
   | 数据库主机 | MySQL：以 1Panel **数据库 → 连接信息** 为准；SQLite 忽略 |
   | 数据库端口 | MySQL 默认 `3306`；SQLite 忽略 |
   | 数据库账号 / 密码 | MySQL 用第五节账号；SQLite 可留空 |
   | 数据库库名 | MySQL 填 `book`；SQLite 保持默认 |
   | WebDAV 地址 | 与静读天下 App 一致，如 `https://dav.jianguoyun.com/dav/` |
   | WebDAV 账号 / 密码 | 与 App 端相同（坚果云用应用密码） |
   | 系统名称 | 随意 |

4. 安装成功后记录 `admin` 和随机初始密码（亦在 `src/runtime/admin_password.txt`）。
5. 登录后立即 **修改密码**。

---

## 九、HTTPS（可选）

1. **网站 → 配置 → HTTPS**。
2. 选择 **Let's Encrypt** 自动申请，或上传已有证书。
3. 开启 **强制 HTTPS**。
4. 保存并重载。

---

## 十、可选：Calibre 封面微服务

非 EPUB 格式封面提取需要 `ebook-service`：

```bash
cd /opt/1panel/www/sites/book/index/src/calibre/ebook-service
docker compose up -d
```

1Panel 服务器需已安装 Docker。服务默认监听 `8080`。

在 Book 后台 → **Calibre 配置**：

- 若 PHP 与 Docker 在同一台机器：`http://127.0.0.1:8080`
- 若在 1Panel Docker 网络内互通：填容器服务名或内网 IP（以实际 `docker compose ps` 为准）

点击 **测试连接** 确认可用。

---

## 十一、常见问题

**访问首页 404**

- **运行目录** 是否为 `/public`（标准 zip）或 `/src/public`（Git）。
- 伪静态是否已保存并重载 OpenResty。

**502 / 504**

- PHP 运行环境是否处于 **运行中**。
- PHP 版本是否为 8.3，扩展是否齐全。

**数据库连接失败**

- 主机名不要用猜的，以 1Panel **数据库 → 连接信息** 为准。
- 1Panel 容器化 MySQL 时，主机常为 `mysql` 或 `127.0.0.1`，以面板显示为准。

**写入 config.php 或 runtime 失败**

- 检查 `src/runtime`、`src/uploads` 目录权限与运行用户是否一致。

**上传大文件失败**

- 确认 PHP `upload_max_filesize` / `post_max_size` 已改。
- **网站 → 配置 → 配置文件** 中 OpenResty 的 `client_max_body_size` 建议设为 `512m`：

  ```nginx
  client_max_body_size 512m;
  ```

**WebDAV 同步无数据**

- 静读天下 App 需先成功同步到 WebDAV。
- WebDAV 凭据必须与 App 端完全一致。

---

## 参考链接

- 1Panel 官网：[https://1panel.cn/](https://1panel.cn/)
- 1Panel 文档：[https://1panel.cn/docs/](https://1panel.cn/docs/)
- 创建网站：[https://1panel.cn/docs/v2/user_manual/websites/website_create/](https://1panel.cn/docs/v2/user_manual/websites/website_create/)
