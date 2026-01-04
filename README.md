# Book 书籍管理系统

> **基于静读天下 App WebDAV 同步的在线书籍管理系统**

一个专为 **静读天下（Moon+ Reader）** 用户设计的 Web 端书库管理系统，通过 WebDAV 同步静读天下的书籍，提供 PC 端友好的书籍管理界面。

---

## 📢 使用前必读

```
┌─────────────────────────────────────────────────────────┐
│  ⚠️  本系统不是独立的书籍管理系统！                        │
│                                                         │
│  必须配合静读天下 App 使用：                              │
│  1. 在手机上安装静读天下（Moon+ Reader）                  │
│  2. 配置 WebDAV 服务器（坚果云/Nextcloud 等）             │
│  3. 在静读天下中至少同步一次书籍                          │
│  4. 将相同的 WebDAV 配置填入本系统                       │
│                                                         │
│  没有静读天下同步的 WebDAV 数据，本系统将无法工作！        │
└─────────────────────────────────────────────────────────┘
```

---
## 核心功能

### 📱 静读天下集成

- **WebDAV 双向同步**：与静读天下共享同一个 WebDAV 书库
- **元数据同步**：自动读取静读天下的书籍分类、收藏、系列、评分
- **跨设备管理**：在 PC 端管理，手机 App 自动同步变化

### 📚 书籍管理

- **浏览与搜索**：按书名、作者、系列、分类、收藏夹筛选
- **批量操作**：批量设置分类、收藏夹、系列
- **元数据编辑**：
    - 手动编辑书名、作者、简介、封面
    - 豆瓣自动获取元数据（评分、出版信息等）
    - 系列管理（支持系列编号排序）
    - 自定义分类标签和收藏夹
    - 5星评分系统

### 📤 文件上传

除了从静读天下同步，也支持直接在 Web 端上传：

- 拖拽上传 / 批量选择上传
- 大文件分片上传（2MB/片）
- 支持格式：EPUB, MOBI, AZW, AZW3, PDF, TXT
- 上传后自动发布到 WebDAV

### 🔍 智能搜索

- 全文搜索书名和作者
- 多条件组合筛选
- 分页浏览（支持 20/50/100 条/页）

## 技术栈

- **后端**：PHP 8.3+、Nova 框架、MySQL 5.7+
- **前端**：MDUI 2.x、jQuery、原生 JavaScript
- **存储**：WebDAV
- **外部 API**：豆瓣图书 API

## 系统要求

### 服务端

- PHP >= 8.3
- MySQL >= 5.7 或 MariaDB >= 10.2
- Nginx / Apache（支持 URL 重写）

### 必需服务

- **WebDAV 服务器**（必需，不是可选！）
    - 坚果云（推荐，国内访问快）
    - Nextcloud（自建方案）
    - Synology NAS / 群晖
    - 阿里云盘 WebDAV 网关

### 客户端

- **静读天下 App**（Android ）
    - 版本要求：支持 WebDAV 同步的任意版本


## 快速开始（3 步走）

### 第一步：准备 WebDAV 服务

推荐使用**坚果云**（国内访问快，配置简单）：

1. 注册坚果云账号：https://www.jianguoyun.com/
2. 获取应用密码：账户信息 → 安全选项 → 添加应用密码
3. 记录以下信息：
   ```
   WebDAV 地址: https://dav.jianguoyun.com/dav/
   用户名: 你的邮箱
   密码: 应用密码（不是登录密码！）
   ```

### 第二步：配置静读天下

下载并安装静读天下：
- Android: https://www.moondownload.com/download.html


在 App 中配置 WebDAV 同步并上传书籍。

### 第三步：部署本系统

参考下方「快速安装」部分部署本系统，并填入相同的 WebDAV 配置。

---

## 静读天下详细配置

### 1. 在静读天下 App 中配置 WebDAV

打开静读天下 App → 设置选项 → 通过WebDAV同步：

```
服务器地址: https://dav.jianguoyun.com/dav/  (坚果云示例)
用户名: your_email@example.com
密码: your_app_password  (应用密码，非登录密码)
同步文件夹: Apps/Books/  (默认路径即可，不要修改)
勾选【同步我的书架】
```

**推荐的 WebDAV 服务提供商：**

- **坚果云**（国内，免费 1GB/月上传流量）：https://www.jianguoyun.com/
- **Nextcloud**（自建，无限制）
- **Synology NAS**（群晖 WebDAV）
- **阿里云盘**（通过 WebDAV 网关）

### 2. 在静读天下中同步书籍

1. 将电子书导入静读天下（支持 EPUB/MOBI/AZW/PDF/TXT）
2. 添加书籍元数据（分类、收藏夹、系列、评分等）
3. 书架页面，点击「同步到云端」将书籍上传到 WebDAV
4. 确认 WebDAV 服务器上出现 `Apps/Books/` 目录

### 3. 配置本系统

编辑 `src/config.php`，填入**与静读天下完全相同的 WebDAV 配置**：

```php
'webdav' => [
    // 唯一设备标识，随便填一个不重复的字符串即可
    'deviceId' => 'web_manager_001',
    
    // ⚠️ 必须与静读天下 App 中的地址完全一致
    'url' => 'https://dav.jianguoyun.com/dav/',
    
    // ⚠️ 坚果云使用邮箱作为用户名
    'username' => 'your_email@example.com',
    
    // ⚠️ 坚果云必须使用「应用密码」，不是登录密码
    'password' => 'your_app_password',
],
```

**⚠️ 常见错误：**
- ❌ 地址末尾忘记 `/dav/`
- ❌ 坚果云使用了登录密码而不是应用密码
- ❌ 地址或用户名与 App 中不一致

### 4. 点击「同步」按钮

首次访问本系统后，点击右上角的「同步」按钮，系统会：

1. 连接到 WebDAV 服务器
2. 扫描 `Apps/Books/` 目录下的所有书籍
3. 读取静读天下生成的元数据文件
4. 导入书籍信息到数据库
5. 在 Web 端展示你的书库


## 快速安装

### 1. 下载源码包

```bash
git clone <repository-url> book
cd book
```

### 3. 配置应用

编辑 `src/config.php`：

```php
<?php
return [
    'debug' => false,  // 生产环境关闭调试
    'timezone' => 'Asia/Shanghai',
    'domain' => ['your-domain.com'],
    
    // 数据库配置
    'db' => [
        'host' => 'localhost',
        'type' => 'mysql',
        'port' => 3306,
        'username' => 'book',
        'password' => 'your_password',
        'db' => 'book',
        'charset' => 'utf8mb4',
    ],
    
    // WebDAV 配置（必需！与静读天下保持一致）
    'webdav' => [
        'deviceId' => 'web_server_001',  // 唯一设备标识，随意填写
        'url' => 'https://dav.jianguoyun.com/dav/',  // 坚果云 WebDAV 地址
        'username' => 'your_email@example.com',  // 坚果云邮箱
        'password' => 'your_app_password',  // 坚果云应用密码
    ],
    
    // 登录配置
    'login' => [
        'allowedLoginCount' => 1,
        'loginCallback' => '/',
        'systemName' => '我的书库',
        'ssoEnable' => false,
    ],
];
```

### 4. 配置 Web 服务器

1. 伪静态

```nginx
  rewrite ^(.*)$ /index.php/$1 last;
```

2. 工作目录

```
/public
```



### 7. 访问系统并同步数据

打开浏览器访问：`http://your-domain.com`

**首次使用流程：**

1. 系统会引导你注册管理员账号
2. 登录后点击右上角「同步」按钮
3. 系统从 WebDAV 拉取静读天下的书籍数据
4. 刷新页面，你的书库就出现了

**⚠️ 如果同步后没有数据：**
- 确认静读天下已同步书籍到 WebDAV
- 检查 WebDAV 配置是否正确
- 查看错误日志：`tail -f /var/log/php-fpm/error.log`
- 手动测试 WebDAV：`curl -u "user:pass" https://dav.jianguoyun.com/dav/Apps/Books/`

## 使用说明

### 典型工作流程

```
手机静读天下 → 添加书籍 → 同步到 WebDAV
                                ↓
                         本系统点击「同步」
                                ↓
                         Web 端查看/编辑
                                ↓
                         修改保存后自动更新
                                ↓
                  手机静读天下再次同步 → 获取最新变化
```

### 书籍导入方式

**方式一：静读天下同步（推荐）**

1. 在静读天下 App 中导入电子书
2. 在 App 中编辑书籍信息（分类、收藏、评分等）
3. 点击 App 的「立即同步」上传到 WebDAV
4. 在本系统点击「同步」按钮拉取最新数据

**方式二：Web 端直接上传**

- 拖拽 EPUB/MOBI/AZW/PDF/TXT 文件到页面
- 或点击「导入」按钮选择文件
- 上传后自动发布到 WebDAV
- 静读天下下次同步时会自动下载

### 编辑书籍信息

1. 点击表格中的「编辑」图标
2. 手动填写或点击「搜索」按钮从豆瓣获取信息
3. 支持字段：
   - 书名、作者、简介
   - 分类、收藏夹、系列名称、系列编号
   - 评分（0-5星）

### 批量操作

1. 勾选要批量操作的书籍
2. 点击「批量操作」按钮
3. 填写要统一设置的字段（分类/收藏/系列）
4. 点击「批量更新」

### 搜索筛选

- **搜索**：支持书名和作者模糊搜索
- **筛选**：按系列、分类、收藏夹精确筛选
- **重置**：一键清空所有搜索条件

## Docker 部署（推荐）

### 1. 创建 docker-compose.yml

```yaml
version: '3.8'

services:
  mysql:
    image: mysql:8.0
    container_name: book-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: book
      MYSQL_USER: book
      MYSQL_PASSWORD: book_password
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - book-net

  app:
    image: php:8.3-fpm
    container_name: book-app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./src:/var/www
      - ./uploads:/var/www/uploads
    networks:
      - book-net
    depends_on:
      - mysql

  nginx:
    image: nginx:alpine
    container_name: book-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - ./src:/var/www
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    networks:
      - book-net
    depends_on:
      - app

volumes:
  mysql_data:

networks:
  book-net:
```

### 2. Nginx 配置文件（nginx.conf）

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/public;
    index index.php;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php/$1 last;
        }
        
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

### 3. 启动服务

```bash
docker-compose up -d
```

访问 `http://localhost`

## 生产环境部署清单

- [ ] 关闭调试模式（`debug => false`）
- [ ] 配置 HTTPS（使用 Let's Encrypt）
- [ ] 设置强密码（数据库、WebDAV、管理员）
- [ ] 配置定时任务（如有需要）
- [ ] 设置数据库备份
- [ ] 限制上传文件大小（根据服务器性能）
- [ ] 配置 Redis 缓存（可选，提升性能）
- [ ] 设置日志轮转

## 故障排查

### 静读天下相关问题

**1. 点击「同步」按钮没有数据**

检查：
- ✅ 静读天下是否已配置 WebDAV？
- ✅ 静读天下是否至少同步过一次？
- ✅ WebDAV 服务器上是否存在 `Apps/Books/` 目录？
- ✅ 本系统 `src/config.php` 的 WebDAV 配置是否与 App 一致？

解决：
```bash
# 手动测试 WebDAV 连接
curl -u "username:password" https://dav.jianguoyun.com/dav/Apps/Books/
```

**2. 静读天下同步报错**

常见原因：
- 用户名密码错误（注意坚果云需要使用应用密码）
- WebDAV 地址末尾缺少 `/dav/`
- 网络问题（检查是否能访问 WebDAV 服务器）

**3. 修改后静读天下看不到变化**

工作流：
1. 在本系统修改书籍信息
2. 本系统自动更新到 WebDAV
3. 打开静读天下 App
4. 手动点击「立即同步」
5. 静读天下会下载最新元数据

**4. 书籍封面不显示**

- 检查 WebDAV 是否允许访问图片文件
- 检查网络代理设置（`/proxy/` 路径用于代理外部图片）

### 上传失败

1. 检查 PHP 配置：
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   max_execution_time = 300
   ```

2. 检查目录权限：
   ```bash
   chmod -R 777 src/app/cache
   chmod -R 777 uploads  # 如果有上传目录
   ```

### WebDAV 同步失败

1. 检查 `src/config.php` 中的 WebDAV 配置
2. 确认 WebDAV 服务器可访问
3. 验证用户名密码正确
4. 查看 PHP 错误日志：`tail -f /var/log/php8.3-fpm.log`

### 数据库连接失败

1. 检查数据库服务是否运行
2. 验证 `src/config.php` 中的数据库配置
3. 测试连接：
   ```bash
   mysql -h localhost -u book -p
   ```

### 页面 404

1. 检查 Nginx/Apache URL 重写配置
2. 确认 `src/public/index.php` 存在
3. 查看 Web 服务器错误日志

## 常见问题（FAQ）

### Q1: 为什么必须使用静读天下？

**A**: 本系统设计初衷是为静读天下用户提供 PC 端管理界面。静读天下的 WebDAV 同步功能会自动生成特定格式的元数据文件，本系统解析这些文件来展示书籍信息。

### Q2: 可以不用静读天下，只用 Web 端吗？

**A**: 技术上可以（通过 Web 端上传功能），但会失去：
- 手机端阅读体验（静读天下是优秀的阅读器）
- 阅读进度、书签、笔记的云端同步
- 跨设备无缝切换阅读

### Q3: 支持其他阅读器吗？

**A**: 目前不支持。本系统专门解析静读天下的数据格式。如需支持其他阅读器，需要：
1. 该阅读器支持 WebDAV 同步
2. 了解其元数据格式
3. 修改本系统的解析逻辑

### Q4: WebDAV 服务器推荐哪个？

**国内用户推荐：**
- **坚果云**：免费 1GB/月上传流量，国内访问快，配置简单
- **阿里云盘 WebDAV**：容量大，需要第三方网关

**折腾爱好者：**
- **Nextcloud**：自建方案，完全掌控数据
- **Synology NAS**：家中有群晖的最佳选择

### Q5: 静读天下的 WebDAV 同步路径是什么？

默认路径：`Apps/Books/`

你可以在静读天下 App 中查看/修改同步路径：
设置 → 备份/恢复 → WebDAV 设置 → 同步文件夹

### Q6: 数据安全吗？

- 书籍文件存储在你的 WebDAV 服务器（不经过本系统）
- 本系统数据库只存储书籍元数据（书名、作者、分类等）
- 建议定期备份数据库和 WebDAV 数据

## 相关资源

- **静读天下官网**: https://www.moondownload.com/
- **静读天下论坛**: https://www.mobileread.com/forums/forumdisplay.php?f=238
- **坚果云 WebDAV 配置**: https://help.jianguoyun.com/?p=2064
- **Nextcloud 官网**: https://nextcloud.com/

## 技术架构

```
┌─────────────────┐
│  静读天下 App    │
│  (手机阅读)      │
└────────┬────────┘
         │ WebDAV 同步
         ↓
┌─────────────────┐      ┌─────────────────┐
│  WebDAV Server  │◄────►│  Book Web 系统  │
│  (坚果云/NC等)   │      │  (书籍管理)      │
└─────────────────┘      └────────┬────────┘
                                  │
                         ┌────────┴────────┐
                         │  MySQL 数据库    │
                         │  (元数据存储)    │
                         └─────────────────┘
```

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request。

代码风格：
- PHP: PSR-12
- JavaScript: ES6+
- 提交信息：清晰描述改动内容

特别欢迎：
- 支持更多阅读器的 WebDAV 格式
- UI/UX 改进
- 性能优化建议

---

**⚠️ 重要声明**：

1. 本系统依赖静读天下 App 的 WebDAV 同步功能，不是独立的书籍管理系统
2. 为个人/小团队使用设计，不适合大规模并发场景
3. 使用前必须先配置好静读天下的 WebDAV 同步
4. 建议定期备份数据库和 WebDAV 数据

如有问题请提交 Issue，附上：
- 静读天下版本号
- WebDAV 服务提供商
- 错误截图和日志
