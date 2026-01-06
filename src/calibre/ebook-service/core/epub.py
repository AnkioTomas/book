"""
EPUB 原生解析

为什么不用 Calibre？
1. EPUB 本质是 ZIP，解析很简单
2. 比调用 CLI 快 10 倍
3. 不依赖外部工具

原则：能不调 Calibre 就不调
"""

import zipfile
import os
from xml.etree import ElementTree as ET
from typing import Optional, Dict, Any


class EpubHandler:
    """EPUB 原生解析器"""
    
    # OPF 命名空间
    NS = {
        'opf': 'http://www.idpf.org/2007/opf',
        'dc': 'http://purl.org/dc/elements/1.1/',
        'dcterms': 'http://purl.org/dc/terms/',
    }
    
    def extract_cover(self, epub_path: str, cover_path: str) -> bool:
        """
        提取封面
        
        策略：
        1. 找 meta cover 引用
        2. 找 manifest 中 properties="cover-image"
        3. 找文件名包含 cover 的图片
        """
        try:
            with zipfile.ZipFile(epub_path, 'r') as zf:
                opf_path, opf_root = self._parse_opf(zf)
                if opf_root is None:
                    return False
                
                opf_dir = os.path.dirname(opf_path)
                
                # 方法1：meta cover 引用
                cover_href = self._find_cover_by_meta(opf_root, opf_dir)
                
                # 方法2：manifest properties
                if not cover_href:
                    cover_href = self._find_cover_by_properties(opf_root, opf_dir)
                
                # 方法3：文件名猜测
                if not cover_href:
                    cover_href = self._find_cover_by_filename(zf)
                
                if not cover_href:
                    return False
                
                # 提取封面
                try:
                    cover_data = zf.read(cover_href)
                    with open(cover_path, 'wb') as f:
                        f.write(cover_data)
                    return True
                except KeyError:
                    # 尝试加上 opf_dir 前缀
                    full_path = os.path.join(opf_dir, cover_href).replace('\\', '/')
                    cover_data = zf.read(full_path)
                    with open(cover_path, 'wb') as f:
                        f.write(cover_data)
                    return True
                    
        except Exception:
            return False
    
    def read_meta(self, epub_path: str) -> Optional[Dict[str, Any]]:
        """
        读取元数据
        
        直接解析 OPF 文件
        """
        try:
            with zipfile.ZipFile(epub_path, 'r') as zf:
                _, opf_root = self._parse_opf(zf)
                if opf_root is None:
                    return None
                
                meta = {}
                
                # 查找 metadata 节点
                metadata = opf_root.find('opf:metadata', self.NS)
                if metadata is None:
                    metadata = opf_root.find('metadata')
                
                if metadata is None:
                    return meta
                
                # 标题
                title = self._find_dc(metadata, 'title')
                if title is not None and title.text:
                    meta['title'] = title.text.strip()
                
                # 作者（可能有多个）
                creators = self._find_all_dc(metadata, 'creator')
                if creators:
                    meta['authors'] = [
                        c.text.strip() for c in creators 
                        if c.text
                    ]
                
                # 出版商
                publisher = self._find_dc(metadata, 'publisher')
                if publisher is not None and publisher.text:
                    meta['publisher'] = publisher.text.strip()
                
                # 语言
                language = self._find_dc(metadata, 'language')
                if language is not None and language.text:
                    meta['language'] = language.text.strip()
                
                # 出版日期
                date = self._find_dc(metadata, 'date')
                if date is not None and date.text:
                    meta['pubdate'] = date.text.strip()
                
                # ISBN 和其他标识符
                identifiers = self._find_all_dc(metadata, 'identifier')
                if identifiers:
                    ids = {}
                    for ident in identifiers:
                        if ident.text:
                            # 尝试识别类型
                            scheme = ident.get('{http://www.idpf.org/2007/opf}scheme', '')
                            text = ident.text.strip()
                            
                            if 'isbn' in scheme.lower() or text.startswith('urn:isbn:'):
                                ids['isbn'] = text.replace('urn:isbn:', '')
                            elif 'amazon' in scheme.lower() or 'asin' in scheme.lower():
                                ids['amazon'] = text
                            elif text.startswith('urn:'):
                                parts = text.split(':', 2)
                                if len(parts) >= 3:
                                    ids[parts[1]] = parts[2]
                    
                    if ids:
                        meta['identifiers'] = ids
                
                # 描述
                description = self._find_dc(metadata, 'description')
                if description is not None and description.text:
                    meta['comments'] = description.text.strip()
                
                # 主题/标签
                subjects = self._find_all_dc(metadata, 'subject')
                if subjects:
                    meta['tags'] = [
                        s.text.strip() for s in subjects
                        if s.text
                    ]
                
                return meta
                
        except Exception:
            return None
    
    def _parse_opf(self, zf: zipfile.ZipFile):
        """
        解析 OPF 文件
        
        Returns:
            (opf_path, opf_root) 或 (None, None)
        """
        # 从 container.xml 找 OPF 路径
        try:
            container = zf.read('META-INF/container.xml')
            container_root = ET.fromstring(container)
            
            # 查找 rootfile
            for rf in container_root.iter():
                if rf.tag.endswith('rootfile'):
                    opf_path = rf.get('full-path')
                    if opf_path:
                        opf_content = zf.read(opf_path)
                        opf_root = ET.fromstring(opf_content)
                        return opf_path, opf_root
        except Exception:
            pass
        
        # 回退：查找任何 .opf 文件
        for name in zf.namelist():
            if name.endswith('.opf'):
                try:
                    opf_content = zf.read(name)
                    opf_root = ET.fromstring(opf_content)
                    return name, opf_root
                except Exception:
                    continue
        
        return None, None
    
    def _find_dc(self, metadata, name):
        """查找 Dublin Core 元素"""
        # 带命名空间
        elem = metadata.find(f'dc:{name}', self.NS)
        if elem is not None:
            return elem
        
        # 不带命名空间
        for child in metadata:
            if child.tag.endswith(name):
                return child
        
        return None
    
    def _find_all_dc(self, metadata, name):
        """查找所有 Dublin Core 元素"""
        results = []
        
        # 带命名空间
        results.extend(metadata.findall(f'dc:{name}', self.NS))
        
        # 不带命名空间
        for child in metadata:
            if child.tag.endswith(name) and child not in results:
                results.append(child)
        
        return results
    
    def _find_cover_by_meta(self, opf_root, opf_dir) -> Optional[str]:
        """通过 meta cover 找封面"""
        metadata = opf_root.find('opf:metadata', self.NS)
        if metadata is None:
            metadata = opf_root.find('metadata')
        
        if metadata is None:
            return None
        
        # 找 <meta name="cover" content="cover-image-id"/>
        cover_id = None
        for meta in metadata.iter():
            if meta.tag.endswith('meta'):
                if meta.get('name') == 'cover':
                    cover_id = meta.get('content')
                    break
        
        if not cover_id:
            return None
        
        # 在 manifest 中找对应 item
        manifest = opf_root.find('opf:manifest', self.NS)
        if manifest is None:
            manifest = opf_root.find('manifest')
        
        if manifest is None:
            return None
        
        for item in manifest.iter():
            if item.tag.endswith('item'):
                if item.get('id') == cover_id:
                    return item.get('href')
        
        return None
    
    def _find_cover_by_properties(self, opf_root, opf_dir) -> Optional[str]:
        """通过 properties="cover-image" 找封面"""
        manifest = opf_root.find('opf:manifest', self.NS)
        if manifest is None:
            manifest = opf_root.find('manifest')
        
        if manifest is None:
            return None
        
        for item in manifest.iter():
            if item.tag.endswith('item'):
                props = item.get('properties', '')
                if 'cover-image' in props:
                    return item.get('href')
        
        return None
    
    def _find_cover_by_filename(self, zf: zipfile.ZipFile) -> Optional[str]:
        """通过文件名猜测封面"""
        candidates = []
        
        for name in zf.namelist():
            lower = name.lower()
            # 必须是图片
            if not any(lower.endswith(ext) for ext in ['.jpg', '.jpeg', '.png', '.gif']):
                continue
            
            # 包含 cover 关键词
            if 'cover' in lower:
                candidates.append((0, name))  # 最高优先级
            elif 'front' in lower:
                candidates.append((1, name))
            elif 'title' in lower:
                candidates.append((2, name))
        
        if candidates:
            candidates.sort(key=lambda x: x[0])
            return candidates[0][1]
        
        return None

