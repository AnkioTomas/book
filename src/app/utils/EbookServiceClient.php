<?php
/**
 * 电子书能力服务客户端
 * 
 * PHP 端唯一与 ebook-service 交互的入口
 * 
 * 使用示例：
 *   $client = new EbookServiceClient();
 *   $cover = $client->extractCover('/path/to/book.epub');
 *   $meta = $client->readMeta('/path/to/book.mobi');
 */

namespace app\utils;

use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpException;
use RuntimeException;

class EbookServiceClient
{
    private string $baseUrl;
    private int $timeout;
    
    /**
     * @param string $baseUrl 服务地址，默认本地 Docker
     * @param int $timeout 超时时间（秒）
     */
    public function __construct(
        string $baseUrl = 'http://localhost:8080',
        int $timeout = 600
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }
    
    /**
     * 健康检查
     * 
     * @return array{status: string, calibre: string}
     * @throws RuntimeException
     */
    public function health(): array
    {
        return $this->get('/health');
    }
    
    /**
     * 提取封面
     * 
     * @param string $bookPath 电子书文件路径
     * @return string 封面图片二进制数据
     * @throws RuntimeException
     */
    public function extractCover(string $bookPath): string
    {
        $this->validateFile($bookPath);
        
        $response = $this->postFile('/cover', $bookPath);
        
        // 检查是否返回图片
        if (empty($response)) {
            throw new RuntimeException('无法提取封面');
        }
        
        return $response;
    }
    
    /**
     * 提取封面并保存到文件
     * 
     * @param string $bookPath 电子书文件路径
     * @param string $savePath 封面保存路径
     * @throws RuntimeException
     */
    public function extractCoverToFile(string $bookPath, string $savePath): void
    {
        $coverData = $this->extractCover($bookPath);
        if (file_put_contents($savePath, $coverData) === false) {
            throw new RuntimeException("无法写入文件: $savePath");
        }
    }
    
    /**
     * 读取元数据
     * 
     * @param string $bookPath 电子书文件路径
     * @return array 元数据
     * @throws RuntimeException
     */
    public function readMeta(string $bookPath): array
    {
        $this->validateFile($bookPath);
        
        $response = $this->postFile('/meta', $bookPath, true);
        
        if (!is_array($response)) {
            throw new RuntimeException('无法读取元数据');
        }
        
        return $response;
    }
    
    /**
     * 格式转换
     * 
     * @param string $bookPath 电子书文件路径
     * @param string $targetFormat 目标格式（epub, mobi, azw3, pdf, txt）
     * @return string 转换后文件的二进制数据
     * @throws RuntimeException
     */
    public function convert(string $bookPath, string $targetFormat): string
    {
        $this->validateFile($bookPath);
        
        $allowedFormats = ['epub', 'mobi', 'azw3', 'pdf', 'txt'];
        $targetFormat = strtolower($targetFormat);
        
        if (!in_array($targetFormat, $allowedFormats)) {
            throw new RuntimeException("不支持的目标格式: $targetFormat");
        }
        
        $response = $this->postFile('/convert', $bookPath, false, [
            'target' => $targetFormat
        ]);
        
        if (empty($response)) {
            throw new RuntimeException('转换失败');
        }
        
        return $response;
    }
    
    /**
     * 格式转换并保存到文件
     * 
     * @param string $bookPath 电子书文件路径
     * @param string $targetFormat 目标格式
     * @param string $savePath 保存路径
     * @throws RuntimeException
     */
    public function convertToFile(string $bookPath, string $targetFormat, string $savePath): void
    {
        $data = $this->convert($bookPath, $targetFormat);
        if (file_put_contents($savePath, $data) === false) {
            throw new RuntimeException("无法写入文件: $savePath");
        }
    }
    
    /**
     * 验证文件存在
     */
    private function validateFile(string $path): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException("文件不存在: $path");
        }
        
        if (!is_readable($path)) {
            throw new RuntimeException("文件不可读: $path");
        }
    }
    
    /**
     * GET 请求
     */
    private function get(string $endpoint): array
    {
        try {
            $response = HttpClient::init($this->baseUrl)
                ->timeout($this->timeout)
                ->get()
                ->send($endpoint);
            
            return json_decode($response->getBody(), true) ?? [];
        } catch (HttpException $e) {
            throw new RuntimeException('服务请求失败: ' . $e->getMessage());
        }
    }
    
    /**
     * POST 文件
     * 
     * @param string $endpoint 端点
     * @param string $filePath 文件路径
     * @param bool $expectJson 是否期望 JSON 响应
     * @param array $extraFields 额外表单字段
     * @return mixed
     */
    private function postFile(
        string $endpoint,
        string $filePath,
        bool $expectJson = false,
        array $extraFields = []
    ) {
        try {
            // 构建 multipart/form-data 数据
            // CURLFile 会让 cURL 自动使用 multipart/form-data
            $postData = array_merge(
                ['file' => new \CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', basename($filePath))],
                $extraFields
            );
            
            $response = HttpClient::init($this->baseUrl)
                ->timeout($this->timeout)
                ->setOption(CURLOPT_POST, true)
                ->setOption(CURLOPT_POSTFIELDS, $postData)
                ->send($endpoint);
            
            $httpCode = $response->getHttpCode();
            $body = $response->getBody();
            $contentType = $response->getHeaders()['Content-Type'] ?? '';
            
            // JSON 响应：检查错误字段
            if (str_contains($contentType, 'application/json')) {
                $json = json_decode($body, true);
                
                if (isset($json['error'])) {
                    throw new RuntimeException($json['error']);
                }
                
                if ($expectJson) {
                    return $json;
                }
            }
            
            // HTTP 错误码
            if ($httpCode >= 400) {
                throw new RuntimeException("HTTP $httpCode: $body");
            }
            
            return $body;
            
        } catch (HttpException $e) {
            throw new RuntimeException('服务请求失败: ' . $e->getMessage());
        }
    }
}

