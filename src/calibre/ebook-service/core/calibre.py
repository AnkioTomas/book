"""
Calibre CLI 封装层

原则：
1. 所有 Calibre 调用只在这里
2. 统一错误处理
3. 未来换工具，只改这一个文件
"""

import subprocess
import re
import shutil
from typing import Optional, Dict, Any


class CalibreError(Exception):
    """Calibre 调用错误"""
    pass


class Calibre:
    """Calibre CLI 统一封装"""
    
    TIMEOUT = 60  # 秒
    
    def __init__(self):
        # 检查 Calibre 是否可用
        self._ebook_meta = shutil.which('ebook-meta')
        self._ebook_convert = shutil.which('ebook-convert')
        
        if not self._ebook_meta:
            raise RuntimeError('ebook-meta 未找到，请安装 Calibre')
    
    def version(self) -> str:
        """获取 Calibre 版本"""
        try:
            result = self._run([self._ebook_meta, '--version'])
            return result.stdout.strip()
        except Exception:
            return 'unknown'
    
    def extract_cover(self, book_path: str, cover_path: str) -> bool:
        """
        提取封面
        
        Args:
            book_path: 电子书路径
            cover_path: 封面输出路径
        
        Returns:
            是否成功
        """
        try:
            self._run([
                self._ebook_meta,
                book_path,
                '--get-cover', cover_path
            ])
            return True
        except CalibreError:
            return False
    
    def read_meta(self, book_path: str) -> Optional[Dict[str, Any]]:
        """
        读取元数据
        
        Args:
            book_path: 电子书路径
        
        Returns:
            元数据字典，失败返回 None
        """
        try:
            result = self._run([self._ebook_meta, book_path])
            return self._parse_meta_output(result.stdout)
        except CalibreError:
            return None
    
    def write_meta(
        self,
        book_path: str,
        title: str = None,
        authors: list = None,
        cover: str = None,
        **kwargs
    ) -> bool:
        """
        写入元数据
        
        Args:
            book_path: 电子书路径
            title: 标题
            authors: 作者列表
            cover: 封面图片路径
            **kwargs: 其他元数据
        
        Returns:
            是否成功
        """
        cmd = [self._ebook_meta, book_path]
        
        if title:
            cmd.extend(['--title', title])
        
        if authors:
            # Calibre 用 & 分隔多作者
            cmd.extend(['--authors', ' & '.join(authors)])
        
        if cover:
            cmd.extend(['--cover', cover])
        
        # 其他常见字段
        field_map = {
            'publisher': '--publisher',
            'language': '--language',
            'isbn': '--isbn',
            'tags': '--tags',
            'series': '--series',
            'series_index': '--index',
            'comments': '--comments',
            'pubdate': '--date',
        }
        
        for key, flag in field_map.items():
            if key in kwargs and kwargs[key]:
                value = kwargs[key]
                if isinstance(value, list):
                    value = ', '.join(value)
                cmd.extend([flag, str(value)])
        
        try:
            self._run(cmd)
            return True
        except CalibreError:
            return False
    
    def convert(
        self,
        input_path: str,
        output_path: str,
        options: list = None
    ) -> bool:
        """
        格式转换
        
        Args:
            input_path: 输入文件
            output_path: 输出文件（扩展名决定目标格式）
            options: 额外选项列表
        
        Returns:
            是否成功
        """
        if not self._ebook_convert:
            raise CalibreError('ebook-convert 未找到')
        
        cmd = [self._ebook_convert, input_path, output_path]
        
        if options:
            cmd.extend(options)
        
        try:
            # 转换可能很慢，用更长的超时
            self._run(cmd, timeout=600)
            return True
        except CalibreError:
            return False
    
    def _run(
        self,
        cmd: list,
        timeout: int = None
    ) -> subprocess.CompletedProcess:
        """
        执行命令
        
        统一处理：超时、错误、编码
        """
        timeout = timeout or self.TIMEOUT
        
        try:
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=timeout
            )
            
            # ebook-meta 有时返回非零但实际成功，检查 stderr
            if result.returncode != 0:
                # 忽略某些"警告"类错误
                if 'cover' in ' '.join(cmd) and 'No cover' in result.stderr:
                    raise CalibreError('No cover found')
                # 其他错误
                if result.stderr.strip():
                    raise CalibreError(result.stderr.strip())
            
            return result
            
        except subprocess.TimeoutExpired:
            raise CalibreError(f'命令超时 ({timeout}s)')
        except FileNotFoundError:
            raise CalibreError(f'命令未找到: {cmd[0]}')
    
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

