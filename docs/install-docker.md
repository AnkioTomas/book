# Docker 部署教程

Book 提供两种 Docker 部署方式：

| 方式 | 适合 | 说明 |
| --- | --- | --- |
| 单容器镜像（推荐） | NAS（unRAID / 群晖 / 威联通）、不支持 compose 的环境 | 一个容器、一个端口、一个数据目录，内置 Workerman |
| Compose 包 | 已有 Docker Compose，想用 Nginx + PHP-FPM | 下文「一」到「六」节 |

## 单容器镜像

```bash
docker run -d --name book \
  -p 9528:9528 \
  -v /mnt/user/appdata/book:/data \
  --restart unless-stopped \
  ghcr.io/ankiotomas/book:latest
```

浏览器打开 `http://<NAS地址>:9528`，按安装向导完成配置（见「四、安装向导」）。

- **端口**：容器内固定 `9528`，主机端口可以随意映射（例如 `-p 8080:9528`）。
- **数据目录** `/data`：`config.php`、`runtime/`（SQLite 库、缓存、日志）、`uploads/`（上传的书）全部在这里。升级时删除容器、拉取新镜像、挂载同一目录重建即可，配置和数据不会丢。
- **数据库**：默认 SQLite；镜像已包含 `pdo_mysql`，也可以连接外部 MySQL。
- **升级**：请拉取新版本镜像重建容器，不要使用后台的「在线更新」。在线更新只会改动容器内的代码，重建容器后就会还原。

unRAID 用户在 Docker 页面「Add Container」：Repository 填 `ghcr.io/ankiotomas/book:latest`，添加一个 Port（Container Port `9528`）和一个 Path（Container Path `/data`，Host Path 例如 `/mnt/user/appdata/book`）。

### 从源码构建

镜像由 `Dockerfile.workerman` 构建，代码依赖 submodule，克隆时必须带 `--recursive`：

```bash
git clone --recursive https://github.com/AnkioTomas/book.git
cd book
docker build -f Dockerfile.workerman -t book .
docker run -d --name book -p 9528:9528 -v /path/to/book-data:/data --restart unless-stopped book
```

已有仓库补拉 submodule：`git submodule update --init --recursive`。

查看日志：`docker logs -f book`。

---

# Compose 包

> 适用场景：已安装 Docker / Docker Compose，希望一键跑起 Book，不想自己配 PHP + Nginx。  
> 发布包：`dist/book-<版本>-docker.zip`（例如 `book-1.0.7-docker.zip`）

---

## 一、环境要求

| 组件 | 要求 |
| --- | --- |
| Docker | 20+ |
| Docker Compose | v2（`docker compose`） |
| 端口 | 主机 `9528` 未被占用 |
| 数据库 | **包内默认 SQLite**（镜像未装 `pdo_mysql`，不要选 MySQL） |

---

## 二、获取发布包

从 [GitHub Releases](https://github.com/AnkioTomas/book/releases) 或项目 `dist/` 目录下载：

```
book-<版本>-docker.zip
```

解压后目录结构：

```
book-<版本>-docker/
├── Dockerfile
├── docker-compose.yml
├── nginx.conf
├── php.ini
└── src/                 ← 业务代码（含 example.config.php）
```

---

## 三、启动

```bash
cd book-<版本>-docker   # 以实际解压目录名为准
docker compose up -d --build
```

Nginx 监听容器内 `80` 端口并将 PHP 请求转发给独立的 PHP-FPM
容器，主机端口仍为 `9528`：

```
http://localhost:9528
```

查看日志：

```bash
docker compose logs -f
```

停止：

```bash
docker compose down
```

---

## 四、安装向导

1. 浏览器打开 `http://localhost:9528`。
2. 进入安装向导后：
   - **数据库类型**：选 **SQLite**（保持默认即可）
   - 主机 / 端口 / 账号：选 SQLite 时忽略
   - **WebDAV**：与静读天下 App 完全一致
3. 安装成功后记录 `admin` 与初始密码，登录后立刻改密码。

> `./src` 已挂载进容器。安装写入的 `config.php`、SQLite 库文件、`runtime/` 日志都会落在宿主机 `src/` 下，重建容器不会丢配置。

---

## 五、可选：Calibre 封面微服务

非 EPUB（MOBI / AZW / AZW3）封面提取需要另起 `ebook-service`：

```bash
cd src/calibre/ebook-service
docker compose up -d
```

在 Book 后台 → **Calibre 配置** 填写可达地址（同机常见 `http://127.0.0.1:8080` 或 compose 网络内服务名），点 **测试连接**。

---

## 六、常见问题

**9528 端口冲突**

改 `docker-compose.yml` 中 Nginx 的 `ports`，例如 `"9529:80"`，再 `docker compose up -d`。

**选了 MySQL 安装失败**

当前 PHP-FPM 镜像只装了 SQLite 相关扩展。重新安装并选 SQLite，或自行改 Dockerfile 加 `pdo_mysql` 并另起 MySQL 容器。

**改代码不生效**

`src` 是 volume 挂载；若修改了 `Dockerfile`、`php.ini` 或 PHP 扩展，需要 `--build` 重建。修改 `nginx.conf` 后需要重启 Nginx 容器。

**权限 / runtime 写失败**

确保宿主机 `src/runtime` 对容器用户可写；必要时：

```bash
mkdir -p src/runtime && chmod -R 755 src/runtime
```

**WebDAV 同步无书**

静读天下需先同步出 `Apps/Books/`；凭据必须与 App 端完全一致。
