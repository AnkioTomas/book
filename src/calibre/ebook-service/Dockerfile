# 电子书能力微服务 Docker 镜像
#
# 基于 linuxserver/calibre（已包含所有 Calibre 工具）
# 只添加 Python Web 层

FROM linuxserver/calibre:latest

# 安装 Python 依赖
COPY requirements.txt /app/requirements.txt
RUN pip3 install --no-cache-dir -r /app/requirements.txt

# 复制应用代码
COPY . /app
WORKDIR /app

# 创建非 root 用户运行服务
RUN useradd -m -u 1000 ebook || true

# 暴露端口
EXPOSE 5000

# 健康检查
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:5000/health || exit 1

# 启动命令
# 生产环境用 gunicorn，4 worker，60s 超时
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "--workers", "4", "--timeout", "600", "main:app"]

