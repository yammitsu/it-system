<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SystemSetting;
use Exception;

class FileService
{
    protected $maxFileSize;
    protected $allowedExtensions;
    protected $virusScanEnabled;

    public function __construct()
    {
        $this->maxFileSize = $this->getSetting('file', 'max_upload_size', 104857600); // 100MB
        $this->allowedExtensions = $this->getSetting('file', 'allowed_extensions', ['zip', 'pdf']);
        $this->virusScanEnabled = $this->getSetting('file', 'virus_scan_enabled', true);
    }

    /**
     * 課題ファイルアップロード
     */
    public function uploadTaskFile(UploadedFile $file, int $languageId): array
    {
        // ファイルサイズチェック
        if ($file->getSize() > $this->maxFileSize) {
            throw new Exception('ファイルサイズが制限を超えています。');
        }

        // 拡張子チェック
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception('許可されていないファイル形式です。');
        }

        // ファイル名生成
        $originalName = $file->getClientOriginalName();
        $fileName = $this->generateFileName($originalName);

        // 保存パス生成
        $path = "tasks/{$languageId}/" . date('Y/m');

        // ファイル保存
        $storedPath = $file->storeAs($path, $fileName, 'public');

        if (!$storedPath) {
            throw new Exception('ファイルの保存に失敗しました。');
        }

        // ウイルススキャン（実装は省略）
        if ($this->virusScanEnabled) {
            $this->scanFile($storedPath);
        }

        return [
            'path' => $storedPath,
            'name' => $originalName,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * ファイル削除
     */
    public function deleteFile(string $path): bool
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * ファイル存在確認
     */
    public function fileExists(string $path): bool
    {
        return Storage::disk('public')->exists($path);
    }

    /**
     * ファイルダウンロード
     */
    public function downloadFile(string $path, string $name = null)
    {
        if (!$this->fileExists($path)) {
            throw new Exception('ファイルが見つかりません。');
        }

        return Storage::disk('public')->download($path, $name);
    }

    /**
     * ファイル名生成
     */
    protected function generateFileName(string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        // 日本語などのマルチバイト文字を含む場合の処理
        $safeName = Str::slug($name);
        if (empty($safeName)) {
            $safeName = 'file';
        }

        // タイムスタンプとランダム文字列を付加
        $timestamp = date('YmdHis');
        $random = Str::random(5);

        return "{$safeName}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * ウイルススキャン（ダミー実装）
     */
    protected function scanFile(string $path): bool
    {
        // 実際の実装では、ClamAVなどのウイルススキャンエンジンと連携
        // ここではダミー実装
        return true;
    }

    /**
     * システム設定取得
     */
    protected function getSetting(string $category, string $key, $default = null)
    {
        $setting = SystemSetting::where('category', $category)
            ->where('key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        // 型に応じて値を変換
        switch ($setting->type) {
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $setting->value;
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }

    /**
     * ファイルサイズを人間が読める形式に変換
     */
    public function humanFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}