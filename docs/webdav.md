# WebDAV 配置说明

本系统与静读天下共用同一套 WebDAV。选一种方式拿到「地址 / 账号 / 密码」，**App 端和本系统必须填完全相同的三项**。

同步目录保持默认：`Apps/Books/`（不要改）。

---

## 一、公开 WebDAV 服务

适合不想自己搭服务、手机与 PC 都能直连公网的场景。

### 1. 坚果云（推荐，开箱即用）

| 项 | 值 |
| --- | --- |
| 地址 | `https://dav.jianguoyun.com/dav/` |
| 账号 | 坚果云**注册邮箱**（不要填手机号） |
| 密码 | **应用密码**（不是登录密码） |

生成应用密码：

1. 打开 [坚果云官网](https://www.jianguoyun.com/) 并登录
2. 右上角账户 → **账户信息** → **安全选项**
3. **第三方应用管理** → **添加应用密码** → 起个名字 → 生成
4. 立刻复制保存（只显示一次）

常见踩坑：

- 地址少了末尾 `/dav/`
- 填了登录密码而不是应用密码
- 免费账号有上传/下载流量月配额，书多时可能触发限流（属服务商限制，不是本系统 bug）

### 2. Nextcloud / 群晖 / 威联通 等自建网盘

凡是提供标准 WebDAV 的网盘/NAS 都可用。地址以各产品文档为准，常见形态：

| 产品 | 地址示例 |
| --- | --- |
| Nextcloud | `https://你的域名/remote.php/dav/files/用户名/` |
| 群晖 Drive | `https://你的NAS地址:5006/` 或面板里给出的 WebDAV 地址 |
| 威联通 | 面板「WebDAV」服务给出的地址 |

账号密码用该网盘本身的登录凭据（或应用专用密码，若产品支持）。

### 3. 其它网盘经网关暴露（可选）

阿里云盘、123 盘等**多数没有官方稳定 WebDAV**。若要用它们：

1. 自建 [OpenList](https://github.com/OpenListTeam/OpenList) / AList
2. 在其中挂载对应网盘
3. 用网关的 WebDAV 入口，一般是：`http(s)://你的主机:端口/dav/`
4. 账号密码用 OpenList/AList 的用户（并确认已开启 WebDAV 读写权限）

这类方案多一层转发，出问题先查网关，再查本系统。

---

## 二、本机最小化启动 WebDAV（Windows / macOS）

适合本地试验、或手机与电脑同一局域网。用 [rclone](https://rclone.org/) 一条命令把本地目录暴露成 WebDAV，不必装 Nextcloud。

> 手机上的静读天下要连到这台电脑：地址必须填**局域网 IP**（如 `http://192.168.1.8:8080/`），不能填 `127.0.0.1`。本系统若跑在同一台电脑，可用 `http://127.0.0.1:8080/`；若跑在别的机器，同样填这台电脑的局域网 IP。

### 共同步骤

1. 建一个空目录当 WebDAV 根，例如：
   - Windows：`D:\webdav`
   - macOS：`~/webdav`
2. 安装 rclone（见下）
3. 启动服务后，用 curl 验证：

```bash
curl -u "book:bookpass" http://127.0.0.1:8080/
```

返回目录列表或 `200` 即通。

4. 静读天下 / 本系统填写：

```
服务器地址 : http://<电脑局域网IP>:8080/
用户名     : book
密码       : bookpass
同步文件夹 : Apps/Books/
```

首次在 App 里「立即同步」后，根目录下会出现 `Apps/Books/`。

### Windows

1. 从 [rclone 下载页](https://rclone.org/downloads/) 取 Windows amd64 zip，解压得到 `rclone.exe`
2. 在 PowerShell 里（按你的路径改）：

```powershell
mkdir D:\webdav -Force
cd <rclone.exe所在目录>
.\rclone.exe serve webdav D:\webdav --addr 0.0.0.0:8080 --user book --pass bookpass
```

3. 若手机连不上：Windows 防火墙放行 8080 入站，或临时关防火墙验证。
4. 停服：在该窗口 `Ctrl+C`。

可选：把上述命令写成 `start-webdav.bat`，双击启动。

### macOS

```bash
# 安装（二选一）
brew install rclone
# 或：https://rclone.org/downloads/ 下载 macOS 包

mkdir -p ~/webdav
rclone serve webdav ~/webdav --addr 0.0.0.0:8080 --user book --pass bookpass
```

首次监听非本机地址时，系统可能弹「允许传入网络连接」——选允许。停服：`Ctrl+C`。

查本机局域网 IP：

```bash
ipconfig getifaddr en0
# 或
ifconfig | grep "inet "
```

### 注意

- `--addr 0.0.0.0:8080` 表示局域网可达；只绑 `127.0.0.1` 则手机访问不到。
- 本机方案默认是 **HTTP**。仅建议在家庭/办公局域网使用；不要把未加密 WebDAV 直接暴露到公网。
- 电脑休眠或关掉终端窗口后服务就停了；要长期跑请用开机脚本 / 计划任务 / `launchd`（自行扩展，本文不展开）。
- 账号密码示例 `book` / `bookpass` 务必改成自己的。

---

## 三、填进本系统

安装向导或侧栏 **WebDAV 配置** 中，与静读天下填**同一套**：

| 字段 | 说明 |
| --- | --- |
| 主机域 / 地址 | 含协议与路径，如 `https://dav.jianguoyun.com/dav/` |
| 账户 | 与 App 一致 |
| 密码 | 与 App 一致（坚果云用应用密码） |

改完后可点「测试连接」。确认 WebDAV 上已有 `Apps/Books/` 后，在书库页点「同步」或等整点自动同步。

验证凭据（把地址和账号换成你的）：

```bash
curl -u "邮箱或用户名:密码" https://dav.jianguoyun.com/dav/Apps/Books/
```

---

## 四、故障排查（最短路径）

| 现象 | 先查 |
| --- | --- |
| 同步后没有书 | App 是否已成功同步；WebDAV 上有无 `Apps/Books/` |
| 401 / 认证失败 | 坚果云是否误用登录密码；账号是否邮箱 |
| 连不上本机 WebDAV | 是否填了局域网 IP；防火墙；rclone 是否在跑 |
| App 与 Web 数据不一致 | 两边地址/账号/密码是否一字不差 |
