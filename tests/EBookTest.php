<?php

namespace tests;

use app\utils\EbookServiceClient;
use nova\commands\test\TestCase;
use function nova\framework\dump;

class EBookTest extends TestCase
{
    private EbookServiceClient $client;
    private string $demoDir;
    private string $tmpDir;
    
    // 支持的电子书格式
    private array $supportedFormats = ['epub', 'mobi', 'azw3', 'pdf'];
    public function test()
    {
       $this->setUp();
       $this->testHealth();
       $this->testExtractAllCovers();
       $this->testReadAllMeta();
       $this->testConvertAllToMobi();
    }

    protected function setUp(): void
    {
        $this->client = new EbookServiceClient("http://192.168.100.200:8080");
        $this->demoDir = __DIR__ . '/demo';
        $this->tmpDir = __DIR__ . '/tmp';
        
        // 确保 tmp 目录存在
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0755, true);
        }
    }
    
    /**
     * 扫描 demo 目录获取所有电子书
     */
    private function scanBooks(): array
    {
        $books = [];
        $files = scandir($this->demoDir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $this->supportedFormats)) {
                $books[] = [
                    'path' => $this->demoDir . '/' . $file,
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'ext' => $ext
                ];
            }
        }
        
        return $books;
    }
    
    /**
     * 测试健康检查
     */
    function testHealth(): void
    {
        echo "\n=== 健康检查 ===\n";
        
        $result = $this->client->health();
        print_r($result);

        $this->checkString('ok', $result['status']);
        
        echo "✅ 服务正常\n";
    }
    
    /**
     * 测试所有电子书的封面提取
     */
    function testExtractAllCovers(): void
    {
        echo "\n=== 封面提取测试 ===\n";
        
        $books = $this->scanBooks();
        
        foreach ($books as $book) {
            echo "处理: {$book['name']}.{$book['ext']} ... ";
            
            try {
                $savePath = $this->tmpDir . '/' . $book['name'] . '_cover.jpg';
                $this->client->extractCoverToFile($book['path'], $savePath);
                $size = round(filesize($savePath) / 1024, 2);
                echo "✅ 封面已保存 ({$size} KB)\n";
            } catch (\Exception $e) {
                echo "❌ {$e->getMessage()}\n";
            }
        }
        
        echo "\n封面保存目录: {$this->tmpDir}\n";
    }
    
    /**
     * 测试所有电子书的元数据读取
     */
    function testReadAllMeta(): void
    {
        echo "\n=== 元数据读取测试 ===\n";
        
        $books = $this->scanBooks();
        $allMeta = [];
        
        foreach ($books as $book) {
            echo "\n📖 {$book['name']}.{$book['ext']}\n";
            
            try {
                $meta = $this->client->readMeta($book['path']);
                $allMeta[$book['name']] = $meta;
                
                // 显示关键信息
                echo "   标题: " . ($meta['title'] ?? '未知') . "\n";
                echo "   作者: " . ($meta['author'] ?? '未知') . "\n";

            } catch (\Exception $e) {
                echo "   ❌ 错误: {$e->getMessage()}\n";
                $allMeta[$book['name']] = ['error' => $e->getMessage()];
            }
        }
        
        // 保存所有元数据到 JSON
        $metaPath = $this->tmpDir . '/all_meta.json';
        file_put_contents($metaPath, json_encode($allMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "\n元数据已保存: {$metaPath}\n";
    }
    
    /**
     * 测试格式转换（EPUB -> MOBI）
     */
    function testConvertAllToMobi(): void
    {
        echo "\n=== 格式转换测试 (-> MOBI) ===\n";
        
        $books = $this->scanBooks();
        
        foreach ($books as $book) {
            // 跳过已经是 mobi 的
            if ($book['ext'] === 'mobi') {
                echo "{$book['name']}: 跳过（已是 MOBI）\n";
                continue;
            }
            
            echo "转换: {$book['name']}.{$book['ext']} -> mobi ... ";
            
            try {
                $savePath = $this->tmpDir . '/' . $book['name'] . '.mobi';
                $this->client->convertToFile($book['path'], 'mobi', $savePath);
                $size = round(filesize($savePath) / 1024 / 1024, 2);
                echo "✅ ({$size} MB)\n";
            } catch (\Exception $e) {
                echo "❌ {$e->getMessage()}\n";
            }
        }
        
        echo "\n转换文件保存目录: {$this->tmpDir}\n";
    }
}