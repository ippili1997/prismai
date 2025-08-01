<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FilesController extends Controller
{
    public function index(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        // Check if bucket is active (validated/connected)
        if (!$bucket->is_active) {
            return redirect()->route('buckets.index')
                ->with('error', 'This bucket is not active. Please test the connection first.');
        }

        $prefix = $request->get('prefix', '');
        $files = [];
        $folders = [];
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            $result = $client->listObjectsV2([
                'Bucket' => $bucket->bucket_name,
                'Prefix' => $prefix,
                'Delimiter' => '/',
                'MaxKeys' => 1000,
            ]);
            
            // Process folders (common prefixes)
            if (isset($result['CommonPrefixes'])) {
                foreach ($result['CommonPrefixes'] as $commonPrefix) {
                    $folderPath = $commonPrefix['Prefix'];
                    $folderName = rtrim(str_replace($prefix, '', $folderPath), '/');
                    if ($folderName) {
                        $folders[] = [
                            'name' => $folderName,
                            'path' => $folderPath,
                            'type' => 'folder',
                        ];
                    }
                }
            }
            
            // Process files
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $key = $object['Key'];
                    
                    // Skip if it's the prefix itself (folder marker)
                    if ($key === $prefix || $key === $prefix . '/') {
                        continue;
                    }
                    
                    $fileName = str_replace($prefix, '', $key);
                    
                    // Skip if it's in a subfolder
                    if (strpos($fileName, '/') !== false) {
                        continue;
                    }
                    
                    $files[] = [
                        'name' => $fileName,
                        'path' => $key,
                        'size' => $object['Size'],
                        'last_modified' => $object['LastModified']->format('Y-m-d H:i:s'),
                        'type' => 'file',
                    ];
                }
            }
            
            // Update last connected timestamp
            $bucket->update(['last_connected_at' => now()]);
            
        } catch (\Exception $e) {
            return redirect()->route('buckets.index')
                ->with('error', 'Failed to connect to bucket: ' . $e->getMessage());
        }
        
        // Build breadcrumb
        $breadcrumb = [];
        if ($prefix) {
            $parts = explode('/', rtrim($prefix, '/'));
            $currentPath = '';
            foreach ($parts as $part) {
                if ($part) {
                    $currentPath .= $part . '/';
                    $breadcrumb[] = [
                        'name' => $part,
                        'path' => $currentPath,
                    ];
                }
            }
        }
        
        return Inertia::render('Files/Index', [
            'files' => $files,
            'folders' => $folders,
            'activeBucket' => $bucket->only(['id', 'name', 'provider']),
            'currentPath' => $prefix,
            'breadcrumb' => $breadcrumb,
        ]);
    }
    
    public function download(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        $key = $request->get('key');
        if (!$key) {
            return back()->with('error', 'No file specified.');
        }
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            // Get the filename from the key
            $filename = basename($key);
            
            // Generate a pre-signed URL for download (valid for 5 minutes)
            $cmd = $client->getCommand('GetObject', [
                'Bucket' => $bucket->bucket_name,
                'Key' => $key,
                'ResponseContentDisposition' => 'attachment; filename="' . $filename . '"',
            ]);
            
            $presignedUrl = $client->createPresignedRequest($cmd, '+5 minutes')->getUri();
            
            return redirect()->away((string) $presignedUrl);
            
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to download file: ' . $e->getMessage());
        }
    }
    
    public function destroy(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        $key = $request->get('key');
        if (!$key) {
            return back()->with('error', 'No file specified.');
        }
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            // Check if this is a folder by looking for objects with this prefix
            $isFolderKey = str_ends_with($key, '/');
            
            // List all objects with this prefix to check if it's a folder
            $objects = $client->listObjectsV2([
                'Bucket' => $bucket->bucket_name,
                'Prefix' => $isFolderKey ? $key : $key . '/',
                'MaxKeys' => 1,
            ]);
            
            $isFolder = $isFolderKey || (!empty($objects['Contents']) && count($objects['Contents']) > 0);
            
            if ($isFolder) {
                // Delete all objects within the folder
                $prefix = $isFolderKey ? $key : $key . '/';
                
                // List all objects to delete
                $objectsToDelete = [];
                $continuationToken = null;
                
                do {
                    $params = [
                        'Bucket' => $bucket->bucket_name,
                        'Prefix' => $prefix,
                    ];
                    
                    if ($continuationToken) {
                        $params['ContinuationToken'] = $continuationToken;
                    }
                    
                    $result = $client->listObjectsV2($params);
                    
                    if (!empty($result['Contents'])) {
                        foreach ($result['Contents'] as $object) {
                            $objectsToDelete[] = ['Key' => $object['Key']];
                        }
                    }
                    
                    $continuationToken = $result['NextContinuationToken'] ?? null;
                } while ($continuationToken);
                
                // Delete all objects if any found
                if (!empty($objectsToDelete)) {
                    $client->deleteObjects([
                        'Bucket' => $bucket->bucket_name,
                        'Delete' => [
                            'Objects' => $objectsToDelete,
                        ],
                    ]);
                }
                
                // Also try to delete the folder marker itself
                if ($isFolderKey) {
                    try {
                        $client->deleteObject([
                            'Bucket' => $bucket->bucket_name,
                            'Key' => $key,
                        ]);
                    } catch (\Exception $e) {
                        // Ignore if folder marker doesn't exist
                    }
                }
                
                return back()->with('success', 'Folder and all its contents deleted successfully.');
            } else {
                // Single file deletion
                $client->deleteObject([
                    'Bucket' => $bucket->bucket_name,
                    'Key' => $key,
                ]);
                
                return back()->with('success', 'File deleted successfully.');
            }
            
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }
    
    public function getViewUrl(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        $request->validate([
            'key' => 'required|string',
        ]);
        
        $key = $request->get('key');
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            // Get object metadata to check file size for videos
            $headObject = $client->headObject([
                'Bucket' => $bucket->bucket_name,
                'Key' => $key,
            ]);
            
            $size = $headObject['ContentLength'];
            $contentType = $headObject['ContentType'] ?? 'application/octet-stream';
            
            // Check file size limit for videos (100MB)
            $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
            $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
            
            if (in_array($ext, $videoExtensions) && $size > 100 * 1024 * 1024) {
                return response()->json(['error' => 'Video files larger than 100MB cannot be previewed'], 400);
            }
            
            // For text/code files, fetch the content directly if they're small enough
            $textExtensions = ['txt', 'log', 'md', 'js', 'ts', 'jsx', 'tsx', 'css', 'scss', 'sass', 
                              'html', 'xml', 'json', 'php', 'py', 'java', 'c', 'cpp', 'h', 
                              'sql', 'sh', 'yml', 'yaml', 'env', 'gitignore'];
            
            if (in_array($ext, $textExtensions) && $size < 1024 * 1024) { // 1MB limit for text files
                try {
                    $result = $client->getObject([
                        'Bucket' => $bucket->bucket_name,
                        'Key' => $key,
                    ]);
                    
                    $content = (string) $result['Body'];
                    
                    return response()->json([
                        'view_url' => null,
                        'content' => $content,
                        'size' => $size,
                        'content_type' => $contentType,
                        'is_text' => true,
                    ]);
                } catch (\Exception $e) {
                    // Fall back to pre-signed URL if direct fetch fails
                }
            }
            
            // Generate a pre-signed URL for viewing (valid for 30 minutes)
            $cmd = $client->getCommand('GetObject', [
                'Bucket' => $bucket->bucket_name,
                'Key' => $key,
            ]);
            
            $presignedUrl = $client->createPresignedRequest($cmd, '+30 minutes')->getUri();
            
            return response()->json([
                'view_url' => (string) $presignedUrl,
                'size' => $size,
                'content_type' => $contentType,
                'is_text' => false,
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate view URL: ' . $e->getMessage()], 500);
        }
    }
    
    public function getUploadUrl(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        // Increase execution time for this endpoint
        set_time_limit(300); // 5 minutes
        
        $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string',
        ]);
        
        if (!$bucket->is_active) {
            return response()->json(['error' => 'Bucket is not active'], 400);
        }
        
        $prefix = $request->get('prefix', '');
        $filename = $request->get('filename');
        $contentType = $request->get('content_type');
        
        // Construct the full key
        $key = $prefix . $filename;
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            // Generate a pre-signed URL for upload (valid for 10 minutes)
            $params = [
                'Bucket' => $bucket->bucket_name,
                'Key' => $key,
                'ContentType' => $contentType,
            ];
            
            // R2 doesn't support ACL parameter in pre-signed URLs
            if ($bucket->provider === 's3') {
                $params['ACL'] = 'private';
            }
            
            $cmd = $client->getCommand('PutObject', $params);
            
            $request = $client->createPresignedRequest($cmd, '+10 minutes');
            $presignedUrl = (string) $request->getUri();
            
            \Log::info('Generated pre-signed URL', [
                'bucket' => $bucket->bucket_name,
                'key' => $key,
                'content_type' => $contentType,
                'method' => $request->getMethod(),
                'url' => $presignedUrl,
            ]);
            
            return response()->json([
                'upload_url' => $presignedUrl,
                'key' => $key,
                'method' => $request->getMethod(),
                'headers' => $request->getHeaders(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate upload URL: ' . $e->getMessage()], 500);
        }
    }
    
    public function getFolderDownloadUrls(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        $request->validate([
            'folder_path' => 'required|string',
        ]);
        
        $folderPath = $request->get('folder_path');
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            // List all objects in the folder
            $files = [];
            $continuationToken = null;
            
            do {
                $params = [
                    'Bucket' => $bucket->bucket_name,
                    'Prefix' => $folderPath,
                ];
                
                if ($continuationToken) {
                    $params['ContinuationToken'] = $continuationToken;
                }
                
                $result = $client->listObjectsV2($params);
                
                if (!empty($result['Contents'])) {
                    foreach ($result['Contents'] as $object) {
                        $key = $object['Key'];
                        
                        // Skip folder markers
                        if (str_ends_with($key, '/')) {
                            continue;
                        }
                        
                        // Generate pre-signed URL for each file (valid for 1 hour)
                        $cmd = $client->getCommand('GetObject', [
                            'Bucket' => $bucket->bucket_name,
                            'Key' => $key,
                        ]);
                        
                        $presignedUrl = $client->createPresignedRequest($cmd, '+60 minutes')->getUri();
                        
                        // Get relative path for ZIP structure
                        $relativePath = str_replace($folderPath, '', $key);
                        
                        $files[] = [
                            'path' => $relativePath,
                            'url' => (string) $presignedUrl,
                            'size' => $object['Size'],
                        ];
                    }
                }
                
                $continuationToken = $result['NextContinuationToken'] ?? null;
            } while ($continuationToken);
            
            return response()->json([
                'files' => $files,
                'folder_name' => basename(rtrim($folderPath, '/')),
                'total_size' => array_sum(array_column($files, 'size')),
                'file_count' => count($files),
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate download URLs: ' . $e->getMessage()], 500);
        }
    }
}