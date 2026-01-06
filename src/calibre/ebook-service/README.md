# 电子书能力微服务

> 把 Calibre CLI 抽象为 HTTP API，PHP 端零感知 Calibre 存在

## 设计原则

```
✅ 文件进，结果出，无状态
✅ EPUB 优先原生解析（快 10 倍）
✅ 每个请求独立沙箱
✅ Calibre 封装在单一文件
```

## 快速启动

```bash
# 开发环境
docker-compose up

# 生产环境
docker-compose up -d
```

## API 接口

### 健康检查

```http
GET /health
```

**响应**

```json
{
  "status": "ok",
  "calibre": "ebook-meta (calibre 7.x)"
}
```

---

### 提取封面

```http
POST /cover
Content-Type: multipart/form-data

file=@book.epub
```

**响应**

- 成功：`image/jpeg` 二进制
- 失败：`{"error": "无法提取封面"}`

---

### 读取元数据

```http
POST /meta
Content-Type: multipart/form-data

file=@book.epub
```

**响应**

```json
{
  "title": "书名",
  "authors": ["作者1", "作者2"],
  "publisher": "出版社",
  "language": "zh",
  "pubdate": "2024-01-01",
  "identifiers": {
    "isbn": "978-xxx",
    "amazon": "B0xxx"
  },
  "tags": ["科幻", "小说"],
  "comments": "简介...",
  "format": "epub"
}
```

---

### 格式转换

```http
POST /convert
Content-Type: multipart/form-data

file=@book.mobi
target=epub
```

**响应**

- 成功：转换后文件二进制
- 失败：`{"error": "转换失败"}`

**支持的目标格式**

- `epub`
- `mobi`
- `azw3`
- `pdf`
- `txt`

---

## PHP 客户端使用

```php
use app\utils\EbookServiceClient;

$client = new EbookServiceClient('http://localhost:8080');

// 提取封面
$coverData = $client->extractCover('/path/to/book.epub');
file_put_contents('cover.jpg', $coverData);

// 或直接保存
$client->extractCoverToFile('/path/to/book.epub', 'cover.jpg');

// 读取元数据
$meta = $client->readMeta('/path/to/book.mobi');
echo $meta['title'];
echo implode(', ', $meta['authors']);

// 格式转换
$epubData = $client->convert('/path/to/book.mobi', 'epub');
file_put_contents('book.epub', $epubData);

// 或直接保存
$client->convertToFile('/path/to/book.mobi', 'epub', 'book.epub');
```

---

## 内部架构

```
ebook-service/
├── main.py              # Flask 应用入口
├── core/
│   ├── calibre.py       # Calibre CLI 封装（唯一调用点）
│   ├── epub.py          # EPUB 原生解析（不走 Calibre）
│   └── detect.py        # 格式检测
├── Dockerfile
├── docker-compose.yml
└── requirements.txt
```

### 为什么 EPUB 不走 Calibre？

1. **EPUB 本质是 ZIP**，解析很简单
2. **比 CLI 快 10 倍**（省去进程启动开销）
3. **不依赖外部工具**（更稳定）

### 沙箱机制

每个请求创建独立临时目录：

```python
with Sandbox() as sandbox:
    book_path = sandbox.save_upload(file)
    # 处理...
# 自动清理
```

---

## 资源限制

Docker 层已配置：

- CPU：2 核
- 内存：2GB
- 临时文件：500MB tmpfs

**格式转换是 CPU 密集型**，建议生产环境：

- 限制并发转换数
- 或使用异步队列（后续扩展）

---

## 扩展计划（按需）

当前版本**故意不做**的功能：

| 功能 | 原因 |
|------|------|
| 修改元数据 | 需求不明确，先不做 |
| 异步转换 | 当前并发量不需要 |
| capabilities 探测 | YAGNI |
| 结构能力（拆分/合并） | 谁用？|

**等真实需求出现再扩展**。

