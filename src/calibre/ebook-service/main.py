#!/usr/bin/env python3
"""
电子书能力微服务 - 极简版
职责：封装 Calibre CLI，对外提供 HTTP API
原则：文件进，结果出，无状态
"""

import os
import tempfile
import shutil
from pathlib import Path
from flask import Flask, request, jsonify, send_file
from werkzeug.utils import secure_filename

from core.calibre import Calibre, CalibreError
from core.epub import EpubHandler
from core.detect import detect_format

app = Flask(__name__)
app.config['MAX_CONTENT_LENGTH'] = 100 * 1024 * 1024  # 100MB

calibre = Calibre()
epub_handler = EpubHandler()


class Sandbox:
    """每个请求一个沙箱，用完即毁"""
    
    def __init__(self):
        self.path = tempfile.mkdtemp(prefix='ebook_')
    
    def __enter__(self):
        return self
    
    def __exit__(self, *args):
        shutil.rmtree(self.path, ignore_errors=True)
    
    def save_upload(self, file_storage, filename=None):
        """保存上传文件到沙箱"""
        name = filename or secure_filename(file_storage.filename)
        path = os.path.join(self.path, name)
        file_storage.save(path)
        return path
    
    def temp_path(self, filename):
        """获取沙箱内临时文件路径"""
        return os.path.join(self.path, filename)


def error_response(message, code=400):
    return jsonify({'error': message}), code


@app.route('/health', methods=['GET'])
def health():
    """健康检查"""
    return jsonify({'status': 'ok', 'calibre': calibre.version()})


@app.route('/cover', methods=['POST'])
def extract_cover():
    """提取封面"""
    if 'file' not in request.files:
        return error_response('缺少 file 参数')
    
    file = request.files['file']
    if not file.filename:
        return error_response('文件名为空')
    
    with Sandbox() as sandbox:
        book_path = sandbox.save_upload(file)
        cover_path = sandbox.temp_path('cover.jpg')
        fmt = detect_format(book_path)
        
        try:
            if fmt == 'epub':
                epub_handler.extract_cover(book_path, cover_path)
            else:
                calibre.extract_cover(book_path, cover_path)
        except CalibreError as e:
            return error_response(str(e), 500)
        except Exception as e:
            return error_response(f'提取封面失败: {e}', 500)
        
        if not os.path.exists(cover_path):
            return error_response('封面提取失败：文件未生成', 500)
        
        with open(cover_path, 'rb') as f:
            cover_data = f.read()
    
    from io import BytesIO
    return send_file(BytesIO(cover_data), mimetype='image/jpeg', download_name='cover.jpg')


@app.route('/meta', methods=['POST'])
def read_meta():
    """读取元数据"""
    if 'file' not in request.files:
        return error_response('缺少 file 参数')
    
    file = request.files['file']
    if not file.filename:
        return error_response('文件名为空')
    
    with Sandbox() as sandbox:
        book_path = sandbox.save_upload(file)
        fmt = detect_format(book_path)
        
        try:
            if fmt == 'epub':
                meta = epub_handler.read_meta(book_path)
            else:
                meta = calibre.read_meta(book_path)
        except CalibreError as e:
            return error_response(str(e), 500)
        except Exception as e:
            return error_response(f'读取元数据失败: {e}', 500)
        
        meta['format'] = fmt
    
    return jsonify(meta)


@app.route('/convert', methods=['POST'])
def convert():
    """格式转换"""
    if 'file' not in request.files:
        return error_response('缺少 file 参数')
    
    file = request.files['file']
    target = request.form.get('target', 'epub').lower()
    
    if not file.filename:
        return error_response('文件名为空')
    
    allowed_targets = {'epub', 'mobi', 'azw3', 'pdf', 'txt'}
    if target not in allowed_targets:
        return error_response(f'不支持的目标格式: {target}')
    
    with Sandbox() as sandbox:
        book_path = sandbox.save_upload(file)
        stem = Path(file.filename).stem
        output_name = f'{stem}.{target}'
        output_path = sandbox.temp_path(output_name)
        
        try:
            calibre.convert(book_path, output_path)
        except CalibreError as e:
            return error_response(str(e), 500)
        except Exception as e:
            return error_response(f'转换失败: {e}', 500)
        
        with open(output_path, 'rb') as f:
            output_data = f.read()
    
    from io import BytesIO
    mime_types = {
        'epub': 'application/epub+zip',
        'mobi': 'application/x-mobipocket-ebook',
        'azw3': 'application/vnd.amazon.ebook',
        'pdf': 'application/pdf',
        'txt': 'text/plain',
    }
    
    return send_file(
        BytesIO(output_data),
        mimetype=mime_types.get(target, 'application/octet-stream'),
        download_name=output_name
    )


if __name__ == '__main__':
    # 开发模式
    app.run(host='0.0.0.0', port=8080, debug=True)

