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
        if (str_contains($coverData,"Not Found")) return;
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
     * 格式转换并保存到文件（流式下载，不占内存）
     * 
     * @param string $bookPath 电子书文件路径
     * @param string $targetFormat 目标格式（epub, mobi, azw3, pdf, txt）
     * @param string $savePath 保存路径
     * @throws RuntimeException
     */
    public function convertToFile(string $bookPath, string $targetFormat, string $savePath): void
    {
        $this->validateFile($bookPath);
        
        $allowedFormats = ['epub', 'mobi', 'azw3', 'pdf', 'txt'];
        $targetFormat = strtolower($targetFormat);
        
        if (!in_array($targetFormat, $allowedFormats)) {
            throw new RuntimeException("不支持的目标格式: $targetFormat");
        }
        
        $url = $this->baseUrl . '/convert';
        
        // 打开目标文件用于写入
        $fp = fopen($savePath, 'wb');
        if (!$fp) {
            throw new RuntimeException("无法创建文件: $savePath");
        }
        
        // 用于捕获响应头
        $responseHeaders = [];
        $httpCode = 0;
        
        try {
            $postData = [
                'file' => new \CURLFile($bookPath, mime_content_type($bookPath) ?: 'application/octet-stream', basename($bookPath)),
                'target' => $targetFormat
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                // 关键：直接写入文件，不占内存
                CURLOPT_FILE => $fp,
                // 捕获响应头
                CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseHeaders, &$httpCode) {
                    $len = strlen($header);
                    // 解析 HTTP 状态码
                    if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                        $httpCode = (int)$m[1];
                    }
                    // 解析响应头
                    if (strpos($header, ':') !== false) {
                        [$key, $value] = explode(':', $header, 2);
                        $responseHeaders[trim($key)] = trim($value);
                    }
                    return $len;
                }
            ]);
            
            $result = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            fclose($fp);
            
            // 检查错误
            if ($result === false) {
                @unlink($savePath);
                throw new RuntimeException("请求失败: $error");
            }
            
            // 检查 HTTP 状态码
            if ($httpCode >= 400) {
                // 读取错误响应（文件内容是错误信息）
                $errorBody = file_get_contents($savePath);
                @unlink($savePath);
                
                // 尝试解析 JSON 错误
                $json = json_decode($errorBody, true);
                if (isset($json['error'])) {
                    throw new RuntimeException($json['error']);
                }
                throw new RuntimeException("HTTP $httpCode: $errorBody");
            }
            
            // 检查是否写入成功
            if (!file_exists($savePath) || filesize($savePath) === 0) {
                @unlink($savePath);
                throw new RuntimeException('转换失败：输出文件为空');
            }
            
        } catch (\Exception $e) {
            if (is_resource($fp)) {
                fclose($fp);
            }
            @unlink($savePath);
            throw $e;
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

