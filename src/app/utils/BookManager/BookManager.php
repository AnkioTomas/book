<?php

namespace app\utils\BookManager;

use nova\framework\core\File;
use nova\framework\json\Json;

class BookManager extends BaseManager
{

    public function updateBookList(array $books): bool
    {
        $path = $this->moon . "/books.sync";
        $sync = $this->runtime . "books.sync";
        $data = zlib_encode(Json::encode($books), ZLIB_ENCODING_DEFLATE);
        File::write($sync, $data);
        return $this->client->upload($sync, $path);
    }

    public function deleteBook(string $filename): bool
    {
        $path = $this->path . DS . $this->normalizeFilename($filename);
        return $this->client->delete($path);
    }


    public function uploadBook(string $file, string $filename): bool
    {
        return $this->client->upload($file, $this->path . DS . $this->normalizeFilename($filename));
    }

    public function downloadBook(string $filename, string $localPath): bool
    {
        File::mkDir(dirname($localPath));
        return $this->client->download($this->path . DS . $this->normalizeFilename($filename), $localPath);
    }


    public function bookExists(string $filename): bool
    {
        $path = $this->path . DS . $this->normalizeFilename($filename);
        try {
            $this->client->getResourceInfo($path);
            return true;
        } catch (\RuntimeException $exception) {
            return false;
        }
    }
    public function list(): array
    {
        $path = $this->moon . "/books.sync";
        $sync = $this->runtime . "books.sync";
        if ($this->client->download($path, $sync)) {
            $data = file_get_contents($sync);
            return Json::decode(zlib_decode($data), true);
        }
        return [];
    }

    function delete(string $filename)
    {
        BookManager::getInstance()->deleteBook($filename);
        CoverManager::getInstance()->deleteCover($filename);
        ProgressManager::getInstance()->deleteProgress($filename);
    }

}