<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\FileVersion;
use App\Models\FileAccessLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use ZipArchive;
use Exception;

class AdvancedFileService extends FileService
{
    /**
     * ファイルの暗号化アップロード
     */
    public function uploadEncrypted(UploadedFile $file, string $directory, array $metadata = []): array
    {
        // 基本的なアップロード処理
        $result = parent::uploadTaskFile($file, $directory);
        
        // ファイル暗号化
        $encryptedPath = $this->encryptFile($result['path']);
        
        // バージョン管理
        $version = $this->createFileVersion($encryptedPath, $metadata);
        
        // CDN配信設定（実装は環境依存）
        $cdnUrl = $this->configureCdn($encryptedPath);
        
        return array_merge($result, [
            'encrypted_path' => $encryptedPath,
            'version_id' => $version->id,
            'cdn_url' => $cdnUrl,
        ]);
    }

    /**
     * ファイル暗号化
     */
    public function encryptFile(string $path): string
    {
        $content = Storage::get($path);
        $encrypted = Crypt::encryptString($content);
        
        $encryptedPath = str_replace('.', '_encrypted.', $path);
        Storage::put($encryptedPath, $encrypted);
        
        // 元ファイルを削除
        Storage::delete($path);
        
        return $encryptedPath;
    }

    /**
     * ファイル復号化
     */
    public function decryptFile(string $path): string
    {
        if (!Storage::exists($path)) {
            throw new Exception('ファイルが存在しません。');
        }
        
        $encrypted = Storage::get($path);
        $decrypted = Crypt::decryptString($encrypted);
        
        return $decrypted;
    }

    /**
     * セキュアダウンロード
     */
    public function secureDownload(string $path, User $user, Task $task = null): array
    {
        // アクセス権限確認
        if (!$this->hasAccess($path, $user, $task)) {
            throw new Exception('このファイルへのアクセス権限がありません。');
        }
        
        // アクセスログ記録
        $this->logAccess($path, $user, 'download');
        
        // 一時的な署名付きURL生成
        $signedUrl = $this->generateSignedUrl($path);
        
        // ファイル復号化（必要な場合）
        if ($this->isEncrypted($path)) {
            $content = $this->decryptFile($path);
            $tempPath = 'temp/' . Str::random(40);
            Storage::put($tempPath, $content);
            
            // 一時ファイルは5分後に自動削除
            $this->scheduleDelete($tempPath, 5);
            
            return [
                'url' => Storage::temporaryUrl($tempPath, now()->addMinutes(5)),
                'expires_at' => now()->addMinutes(5),
            ];
        }
        
        return [
            'url' => $signedUrl,
            'expires_at' => now()->addHour(),
        ];
    }

    /**
     * バッチファイルアップロード
     */
    public function batchUpload(array $files, string $directory, array $options = []): array
    {
        $results = [];
        $errors = [];
        
        foreach ($files as $file) {
            try {
                $result = $this->uploadTaskFile($file, $directory);
                $results[] = $result;
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'successful' => $results,
            'failed' => $errors,
            'total' => count($files),
            'success_count' => count($results),
            'error_count' => count($errors),
        ];
    }

    /**
     * ファイルバージョン管理
     */
    public function createFileVersion(string $path, array $metadata = []): FileVersion
    {
        return FileVersion::create([
            'file_path' => $path,
            'version_number' => $this->getNextVersionNumber($path),
            'size' => Storage::size($path),
            'hash' => hash_file('sha256', Storage::path($path)),
            'metadata' => $metadata,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * ファイルバージョン取得
     */
    public function getFileVersions(string $originalPath): array
    {
        return FileVersion::where('file_path', 'like', $originalPath . '%')
            ->orderBy('version_number', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * ファイルロールバック
     */
    public function rollbackToVersion(string $path, int $versionNumber): bool
    {
        $version = FileVersion::where('file_path', $path)
            ->where('version_number', $versionNumber)
            ->first();
        
        if (!$version) {
            throw new Exception('指定されたバージョンが見つかりません。');
        }
        
        // 現在のファイルをバックアップ
        $this->createFileVersion($path, ['rollback_from' => $this->getCurrentVersionNumber($path)]);
        
        // バージョンファイルを復元
        $versionPath = $version->file_path;
        if (Storage::exists($versionPath)) {
            Storage::copy($versionPath, $path);
            return true;
        }
        
        return false;
    }

    /**
     * ZIP圧縮
     */
    public function createZipArchive(array $files, string $zipName): string
    {
        $zip = new ZipArchive();
        $zipPath = "archives/{$zipName}.zip";
        $fullPath = Storage::path($zipPath);
        
        if ($zip->open($fullPath, ZipArchive::CREATE) !== true) {
            throw new Exception('ZIPファイルの作成に失敗しました。');
        }
        
        foreach ($files as $file) {
            if (Storage::exists($file)) {
                $zip->addFile(
                    Storage::path($file),
                    basename($file)
                );
            }
        }
        
        $zip->close();
        
        return $zipPath;
    }

    /**
     * ファイル検索
     */
    public function searchFiles(array $criteria): array
    {
        $query = FileVersion::query();
        
        if (isset($criteria['name'])) {
            $query->where('file_path', 'like', "%{$criteria['name']}%");
        }
        
        if (isset($criteria['date_from'])) {
            $query->where('created_at', '>=', $criteria['date_from']);
        }
        
        if (isset($criteria['date_to'])) {
            $query->where('created_at', '<=', $criteria['date_to']);
        }
        
        if (isset($criteria['size_min'])) {
            $query->where('size', '>=', $criteria['size_min']);
        }
        
        if (isset($criteria['size_max'])) {
            $query->where('size', '<=', $criteria['size_max']);
        }
        
        return $query->get()->toArray();
    }

    /**
     * ファイルアクセス権限確認
     */
    private function hasAccess(string $path, User $user, Task $task = null): bool
    {
        // システム管理者は全アクセス可能
        if ($user->role === 'system_admin') {
            return true;
        }
        
        // 課題ファイルの場合
        if ($task) {
            // 言語が一致しない場合
            if ($task->language_id !== $user->language_id) {
                return false;
            }
            
            // 企業専用課題の場合
            if ($task->company_id && $task->company_id !== $user->company_id) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * アクセスログ記録
     */
    private function logAccess(string $path, User $user, string $action): void
    {
        FileAccessLog::create([
            'file_path' => $path,
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * 署名付きURL生成
     */
    private function generateSignedUrl(string $path): string
    {
        return Storage::temporaryUrl($path, now()->addHour());
    }

    /**
     * ファイルが暗号化されているか確認
     */
    private function isEncrypted(string $path): bool
    {
        return Str::contains($path, '_encrypted');
    }

    /**
     * 次のバージョン番号取得
     */
    private function getNextVersionNumber(string $path): int
    {
        $latest = FileVersion::where('file_path', 'like', $path . '%')
            ->orderBy('version_number', 'desc')
            ->first();
        
        return $latest ? $latest->version_number + 1 : 1;
    }

    /**
     * 現在のバージョン番号取得
     */
    private function getCurrentVersionNumber(string $path): int
    {
        $latest = FileVersion::where('file_path', $path)
            ->orderBy('version_number', 'desc')
            ->first();
        
        return $latest ? $latest->version_number : 0;
    }

    /**
     * 一時ファイルの削除スケジュール
     */
    private function scheduleDelete(string $path, int $minutes): void
    {
        // Jobを使用して削除をスケジュール
        dispatch(function () use ($path) {
            if (Storage::exists($path)) {
                Storage::delete($path);
            }
        })->delay(now()->addMinutes($minutes));
    }

    /**
     * CDN設定
     */
    private function configureCdn(string $path): ?string
    {
        // CDN設定が有効な場合
        if (config('filesystems.cdn.enabled', false)) {
            // CDNへのアップロード処理
            // 実装は使用するCDNサービスに依存
            return config('filesystems.cdn.url') . '/' . $path;
        }
        
        return null;
    }

    /**
     * ファイルプレビュー生成
     */
    public function generatePreview(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        switch (strtolower($extension)) {
            case 'pdf':
                return $this->generatePdfPreview($path);
            case 'doc':
            case 'docx':
                return $this->generateDocumentPreview($path);
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                return $this->generateImagePreview($path);
            default:
                return null;
        }
    }

    /**
     * PDFプレビュー生成
     */
    private function generatePdfPreview(string $path): string
    {
        // PDF -> 画像変換処理（要Imagick等）
        $previewPath = str_replace('.pdf', '_preview.jpg', $path);
        // 実装は省略
        return $previewPath;
    }

    /**
     * ドキュメントプレビュー生成
     */
    private function generateDocumentPreview(string $path): string
    {
        // ドキュメント -> HTML or 画像変換処理
        $previewPath = str_replace(['.doc', '.docx'], '_preview.html', $path);
        // 実装は省略
        return $previewPath;
    }

    /**
     * 画像プレビュー生成
     */
    private function generateImagePreview(string $path): string
    {
        // サムネイル生成処理
        $thumbnailPath = str_replace('.', '_thumb.', $path);
        // 実装は省略
        return $thumbnailPath;
    }

    /**
     * ファイル統計情報取得
     */
    public function getFileStatistics(): array
    {
        $totalSize = FileVersion::sum('size');
        $totalFiles = FileVersion::count();
        $fileTypes = FileVersion::selectRaw('
            SUBSTRING_INDEX(file_path, ".", -1) as extension,
            COUNT(*) as count,
            SUM(size) as total_size
        ')
            ->groupBy('extension')
            ->get();

        return [
            'total_size' => $this->humanFileSize($totalSize),
            'total_files' => $totalFiles,
            'by_type' => $fileTypes->map(function ($type) {
                return [
                    'extension' => $type->extension,
                    'count' => $type->count,
                    'size' => $this->humanFileSize($type->total_size),
                ];
            }),
            'storage_usage' => [
                'used' => $totalSize,
                'limit' => config('filesystems.storage_limit', 1073741824000), // 1TB default
                'percentage' => round(($totalSize / config('filesystems.storage_limit', 1073741824000)) * 100, 2),
            ],
        ];
    }
}