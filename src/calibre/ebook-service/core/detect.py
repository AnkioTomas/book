"""
电子书格式检测

不信任文件扩展名，用 magic bytes 判断
"""

import os


def detect_format(file_path: str) -> str:
    """
    检测电子书格式
    
    Returns:
        格式名：epub, mobi, azw3, pdf, txt, unknown
    """
    ext = os.path.splitext(file_path)[1].lower()
    
    try:
        with open(file_path, 'rb') as f:
            header = f.read(64)
    except Exception:
        return 'unknown'
    
    # EPUB: ZIP 格式，且包含 mimetype
    if header[:2] == b'PK':
        # 检查是否真的是 EPUB
        if b'mimetype' in header or ext == '.epub':
            return 'epub'
        return 'zip'
    
    # PDF: %PDF
    if header[:4] == b'%PDF':
        return 'pdf'
    
    # MOBI/AZW: PalmDOC 格式
    # 检查 PDB header
    if len(header) >= 64:
        # PDB type at offset 60-64
        pdb_type = header[60:68]
        
        if b'BOOKMOBI' in pdb_type:
            # AZW3 和 MOBI 都是 BOOKMOBI
            # AZW3 通常有 KF8 标记，但检测复杂
            # 简单处理：用扩展名区分
            if ext in ['.azw3', '.azw']:
                return 'azw3'
            return 'mobi'
        
        if b'TEXtREAd' in pdb_type:
            return 'prc'
    
    # TXT: 无特殊标记，靠扩展名
    if ext == '.txt':
        return 'txt'
    
    # FB2: XML 格式
    if header.startswith(b'<?xml') and b'FictionBook' in header:
        return 'fb2'
    
    # 回退到扩展名
    ext_map = {
        '.epub': 'epub',
        '.mobi': 'mobi',
        '.azw': 'azw3',
        '.azw3': 'azw3',
        '.pdf': 'pdf',
        '.txt': 'txt',
        '.fb2': 'fb2',
        '.cbz': 'cbz',
        '.cbr': 'cbr',
    }
    
    return ext_map.get(ext, 'unknown')

