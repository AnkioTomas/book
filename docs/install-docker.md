# Docker 部署教程

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
