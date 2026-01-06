"""
Calibre CLI 封装层
"""

import subprocess
import re
import shutil
import os
from typing import Dict, Any


class CalibreError(Exception):
    """Calibre 调用错误，包含详细信息"""
    
    def __init__(self, message: str, cmd: list = None, returncode: int = None, 
                 stdout: str = None, stderr: str = None):
        self.message = message
        self.cmd = cmd
        self.returncode = returncode
        self.stdout = stdout
        self.stderr = stderr
        super().__init__(self._format())
    
    def _format(self) -> str:
        parts = [self.message]
        if self.returncode is not None:
            parts.append(f"退出码: {self.returncode}")
        if self.stderr:
            parts.append(f"错误: {self.stderr[:200]}")  # 截断避免太长
        return " | ".join(parts)


class Calibre:
    """Calibre CLI 统一封装"""
    
    TIMEOUT = 60
    
    def __init__(self):
        self._ebook_meta = shutil.which('ebook-meta')
        self._ebook_convert = shutil.which('ebook-convert')
        
        if not self._ebook_meta:
            raise RuntimeError('ebook-meta 未找到，请安装 Calibre')
    
    def version(self) -> str:
        try:
            result = self._run([self._ebook_meta, '--version'])
            return result.stdout.strip()
        except Exception:
            return 'unknown'
    
    def extract_cover(self, book_path: str, cover_path: str) -> None:
        """提取封面，失败抛异常"""
        if not os.path.exists(book_path):
            raise CalibreError(f"文件不存在: {book_path}")
        
        self._run([self._ebook_meta, book_path, '--get-cover', cover_path])
        
        if not os.path.exists(cover_path):
            raise CalibreError("封面提取失败：电子书可能没有内嵌封面")
    
    def read_meta(self, book_path: str) -> Dict[str, Any]:
        """读取元数据，失败抛异常"""
        if not os.path.exists(book_path):
            raise CalibreError(f"文件不存在: {book_path}")
        
        result = self._run([self._ebook_meta, book_path])
        return self._parse_meta_output(result.stdout)
    
    def write_meta(self, book_path: str, title: str = None, authors: list = None,
                   cover: str = None, **kwargs) -> None:
        """写入元数据，失败抛异常"""
        cmd = [self._ebook_meta, book_path]
        
        if title:
            cmd.extend(['--title', title])
        if authors:
            cmd.extend(['--authors', ' & '.join(authors)])
        if cover:
            cmd.extend(['--cover', cover])
        
        field_map = {
            'publisher': '--publisher', 'language': '--language',
            'isbn': '--isbn', 'tags': '--tags', 'series': '--series',
            'series_index': '--index', 'comments': '--comments', 'pubdate': '--date',
        }
        
        for key, flag in field_map.items():
            if key in kwargs and kwargs[key]:
                value = kwargs[key]
                if isinstance(value, list):
                    value = ', '.join(value)
                cmd.extend([flag, str(value)])
        
        self._run(cmd)
    
    def convert(self, input_path: str, output_path: str, options: list = None) -> None:
        """格式转换，失败抛异常"""
        if not self._ebook_convert:
            raise CalibreError('ebook-convert 未找到')
        
        if not os.path.exists(input_path):
            raise CalibreError(f"输入文件不存在: {input_path}")
        
        cmd = [self._ebook_convert, input_path, output_path]
        if options:
            cmd.extend(options)
        
        self._run(cmd, timeout=600)
        
        if not os.path.exists(output_path):
            raise CalibreError("转换失败：输出文件未生成")
    
    def _run(self, cmd: list, timeout: int = None) -> subprocess.CompletedProcess:
        """执行命令，失败抛带详情的异常"""
        timeout = timeout or self.TIMEOUT
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
            
            if result.returncode != 0:
                # 特殊处理：无封面
                if '--get-cover' in cmd and 'No cover' in (result.stderr or ''):
                    raise CalibreError("电子书没有内嵌封面", cmd, result.returncode, 
                                       result.stdout, result.stderr)
                # 其他错误
                raise CalibreError("命令执行失败", cmd, result.returncode,
                                   result.stdout, result.stderr)
            
            return result
            
        except subprocess.TimeoutExpired:
            raise CalibreError(f"命令超时 ({timeout}秒)，文件可能过大", cmd)
        except FileNotFoundError:
            raise CalibreError(f"命令未找到: {cmd[0]}", cmd)
    
    def _parse_meta_output(self, output: str) -> Dict[str, Any]:
        """
        解析 ebook-meta 的文本输出
        
        输出格式示例：
        Title               : Some Book
        Author(s)           : John Doe
        Publisher           : Publisher Name
        Languages           : eng
        """
        meta = {}
        
        # 字段映射
        field_map = {
            'Title': 'title',
            'Author(s)': 'authors',
            'Publisher': 'publisher',
            'Languages': 'language',
            'Published': 'pubdate',
            'Identifiers': 'identifiers',
            'Tags': 'tags',
            'Series': 'series',
            'Comments': 'comments',
        }
        
        for line in output.split('\n'):
            if ':' not in line:
                continue
            
            # 分割键值
            parts = line.split(':', 1)
            if len(parts) != 2:
                continue
            
            key = parts[0].strip()
            value = parts[1].strip()
            
            if not value:
                continue
            
            # 映射字段名
            field = field_map.get(key)
            if not field:
                continue
            
            # 特殊处理
            if field == 'authors':
                # "John Doe & Jane Doe" → ["John Doe", "Jane Doe"]
                meta[field] = [a.strip() for a in value.split('&')]
            elif field == 'tags':
                meta[field] = [t.strip() for t in value.split(',')]
            elif field == 'identifiers':
                # "isbn:123, amazon:456" → {"isbn": "123", "amazon": "456"}
                ids = {}
                for pair in value.split(','):
                    if ':' in pair:
                        k, v = pair.split(':', 1)
                        ids[k.strip()] = v.strip()
                meta[field] = ids
            elif field == 'series':
                # "Series Name #3" → series, series_index
                match = re.match(r'(.+?)\s*#(\d+)', value)
                if match:
                    meta['series'] = match.group(1).strip()
                    meta['series_index'] = int(match.group(2))
                else:
                    meta[field] = value
            else:
                meta[field] = value
        
        return meta

