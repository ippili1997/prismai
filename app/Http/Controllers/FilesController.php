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
        $nextToken = $request->get('continuation_token', null);
        $perPage = 100; // Simple pagination limit
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            $params = [
                'Bucket' => $bucket->bucket_name,
                'Prefix' => $prefix,
                'Delimiter' => '/',
                'MaxKeys' => $perPage,
            ];
            
            if ($nextToken) {
                $params['ContinuationToken'] = $nextToken;
            }
            
            $result = $client->listObjectsV2($params);
            
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
            'hasMore' => $result['IsTruncated'] ?? false,
            'nextToken' => $result['NextContinuationToken'] ?? null,
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
    
    public function createFolder(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        $request->validate([
            'folder_name' => 'required|string|max:255',
            'prefix' => 'nullable|string',
        ]);
        
        $folderName = trim($request->get('folder_name'), '/');
        $prefix = $request->get('prefix', '');
        
        // Construct the full folder key (ensure it ends with /)
        $folderKey = $prefix . $folderName . '/';
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            // Create an empty object with the folder key to represent the folder
            $client->putObject([
                'Bucket' => $bucket->bucket_name,
                'Key' => $folderKey,
                'Body' => '',
            ]);
            
            return response()->json(['success' => true, 'message' => 'Folder created successfully']);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create folder: ' . $e->getMessage()], 500);
        }
    }
    
    public function rename(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        $request->validate([
            'old_path' => 'required|string',
            'new_name' => 'required|string|max:255',
            'is_folder' => 'required|boolean',
        ]);
        
        $oldPath = $request->get('old_path');
        $newName = trim($request->get('new_name'), '/');
        $isFolder = $request->get('is_folder');
        
        // Get the parent directory
        $parentPath = dirname($oldPath);
        if ($parentPath === '.' || $parentPath === '/') {
            $parentPath = '';
        } else {
            $parentPath .= '/';
        }
        
        // Construct new path
        $newPath = $parentPath . $newName;
        if ($isFolder && !str_ends_with($newPath, '/')) {
            $newPath .= '/';
        }
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            if ($isFolder) {
                // For folders, we need to copy all objects with the old prefix to the new prefix
                $oldPrefix = $oldPath;
                if (!str_ends_with($oldPrefix, '/')) {
                    $oldPrefix .= '/';
                }
                
                // List all objects with the old prefix
                $objectsToRename = [];
                $continuationToken = null;
                
                do {
                    $params = [
                        'Bucket' => $bucket->bucket_name,
                        'Prefix' => $oldPrefix,
                    ];
                    
                    if ($continuationToken) {
                        $params['ContinuationToken'] = $continuationToken;
                    }
                    
                    $result = $client->listObjectsV2($params);
                    
                    if (!empty($result['Contents'])) {
                        foreach ($result['Contents'] as $object) {
                            $objectsToRename[] = $object['Key'];
                        }
                    }
                    
                    $continuationToken = $result['NextContinuationToken'] ?? null;
                } while ($continuationToken);
                
                // Copy each object to the new location
                foreach ($objectsToRename as $oldKey) {
                    $newKey = str_replace($oldPrefix, $newPath, $oldKey);
                    
                    // Copy object
                    $client->copyObject([
                        'Bucket' => $bucket->bucket_name,
                        'CopySource' => $bucket->bucket_name . '/' . $oldKey,
                        'Key' => $newKey,
                    ]);
                }
                
                // Delete old objects
                if (!empty($objectsToRename)) {
                    $deleteObjects = array_map(function($key) {
                        return ['Key' => $key];
                    }, $objectsToRename);
                    
                    $client->deleteObjects([
                        'Bucket' => $bucket->bucket_name,
                        'Delete' => [
                            'Objects' => $deleteObjects,
                        ],
                    ]);
                }
                
                // Also check if there's a folder marker for the old folder and rename it
                if (!in_array($oldPath, $objectsToRename) && !in_array($oldPrefix, $objectsToRename)) {
                    try {
                        // Try to copy the folder marker if it exists
                        $client->copyObject([
                            'Bucket' => $bucket->bucket_name,
                            'CopySource' => $bucket->bucket_name . '/' . $oldPath,
                            'Key' => $newPath,
                        ]);
                        
                        // Delete the old folder marker
                        $client->deleteObject([
                            'Bucket' => $bucket->bucket_name,
                            'Key' => $oldPath,
                        ]);
                    } catch (\Exception $e) {
                        // Folder marker might not exist, which is fine
                    }
                }
                
                return response()->json(['success' => true, 'message' => 'Folder renamed successfully']);
                
            } else {
                // For files, simple copy and delete
                $client->copyObject([
                    'Bucket' => $bucket->bucket_name,
                    'CopySource' => $bucket->bucket_name . '/' . $oldPath,
                    'Key' => $newPath,
                ]);
                
                $client->deleteObject([
                    'Bucket' => $bucket->bucket_name,
                    'Key' => $oldPath,
                ]);
                
                return response()->json(['success' => true, 'message' => 'File renamed successfully']);
            }
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to rename: ' . $e->getMessage()], 500);
        }
    }
    
    public function getFolderTree(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            
            // List all objects to build folder tree
            $folders = [];
            $continuationToken = null;
            $processedPrefixes = [];
            
            do {
                $params = [
                    'Bucket' => $bucket->bucket_name,
                    'Delimiter' => '/',
                ];
                
                if ($continuationToken) {
                    $params['ContinuationToken'] = $continuationToken;
                }
                
                // First, get root level folders
                $this->addFoldersFromPrefix($client, $bucket->bucket_name, '', $folders, $processedPrefixes, 0);
                
                $continuationToken = null; // We'll handle all folders recursively
            } while (false);
            
            // Sort folders by path
            usort($folders, function($a, $b) {
                return strcmp($a['path'], $b['path']);
            });
            
            return response()->json(['folders' => $folders]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get folder tree: ' . $e->getMessage()], 500);
        }
    }
    
    private function addFoldersFromPrefix($client, $bucketName, $prefix, &$folders, &$processedPrefixes, $level)
    {
        if (in_array($prefix, $processedPrefixes)) {
            return;
        }
        
        $processedPrefixes[] = $prefix;
        
        $result = $client->listObjectsV2([
            'Bucket' => $bucketName,
            'Prefix' => $prefix,
            'Delimiter' => '/',
        ]);
        
        if (isset($result['CommonPrefixes'])) {
            foreach ($result['CommonPrefixes'] as $commonPrefix) {
                $folderPath = $commonPrefix['Prefix'];
                $folderName = basename(rtrim($folderPath, '/'));
                
                $folders[] = [
                    'name' => $folderName,
                    'path' => $folderPath,
                    'level' => $level,
                ];
                
                // Recursively get subfolders
                $this->addFoldersFromPrefix($client, $bucketName, $folderPath, $folders, $processedPrefixes, $level + 1);
            }
        }
    }
    
    public function move(Request $request, Bucket $bucket)
    {
        $this->authorize('view', $bucket);
        
        $request->validate([
            'items' => 'required|array|min:1',
            'destination' => 'nullable|string',
            'current_path' => 'nullable|string',
        ]);
        
        $items = $request->get('items');
        $destination = $request->get('destination', '');
        $currentPath = $request->get('current_path', '');
        
        // Ensure destination ends with / if it's not empty
        if ($destination && !str_ends_with($destination, '/')) {
            $destination .= '/';
        }
        
        try {
            $client = new S3Client($bucket->getClientConfig());
            $errors = [];
            $movedCount = 0;
            
            foreach ($items as $itemPath) {
                try {
                    // Determine if item is a folder
                    $isFolder = str_ends_with($itemPath, '/');
                    
                    if (!$isFolder) {
                        // Check if it's actually a folder by looking for objects with this prefix
                        $objects = $client->listObjectsV2([
                            'Bucket' => $bucket->bucket_name,
                            'Prefix' => $itemPath . '/',
                            'MaxKeys' => 1,
                        ]);
                        
                        $isFolder = !empty($objects['Contents']);
                    }
                    
                    // Get the item name
                    $itemName = basename(rtrim($itemPath, '/'));
                    
                    // Construct new path
                    $newPath = $destination . $itemName;
                    if ($isFolder && !str_ends_with($newPath, '/')) {
                        $newPath .= '/';
                    }
                    
                    // Check if we're trying to move to the same location
                    if ($itemPath === $newPath) {
                        $errors[] = "$itemName: Already in the destination folder";
                        continue;
                    }
                    
                    // Check if we're trying to move a parent folder into its child
                    if ($isFolder && str_starts_with($destination, $itemPath)) {
                        $errors[] = "$itemName: Cannot move a folder into itself";
                        continue;
                    }
                    
                    if ($isFolder) {
                        // Move folder and all its contents
                        $oldPrefix = $itemPath;
                        if (!str_ends_with($oldPrefix, '/')) {
                            $oldPrefix .= '/';
                        }
                        
                        // List all objects with the old prefix
                        $objectsToMove = [];
                        $continuationToken = null;
                        
                        do {
                            $params = [
                                'Bucket' => $bucket->bucket_name,
                                'Prefix' => $oldPrefix,
                            ];
                            
                            if ($continuationToken) {
                                $params['ContinuationToken'] = $continuationToken;
                            }
                            
                            $result = $client->listObjectsV2($params);
                            
                            if (!empty($result['Contents'])) {
                                foreach ($result['Contents'] as $object) {
                                    $objectsToMove[] = $object['Key'];
                                }
                            }
                            
                            $continuationToken = $result['NextContinuationToken'] ?? null;
                        } while ($continuationToken);
                        
                        // Copy each object to the new location
                        foreach ($objectsToMove as $oldKey) {
                            $newKey = str_replace($oldPrefix, $newPath, $oldKey);
                            
                            // Copy object
                            $client->copyObject([
                                'Bucket' => $bucket->bucket_name,
                                'CopySource' => $bucket->bucket_name . '/' . $oldKey,
                                'Key' => $newKey,
                            ]);
                        }
                        
                        // Delete old objects
                        if (!empty($objectsToMove)) {
                            $deleteObjects = array_map(function($key) {
                                return ['Key' => $key];
                            }, $objectsToMove);
                            
                            $client->deleteObjects([
                                'Bucket' => $bucket->bucket_name,
                                'Delete' => [
                                    'Objects' => $deleteObjects,
                                ],
                            ]);
                        }
                        
                        // Handle folder marker
                        if (!in_array($itemPath, $objectsToMove) && !in_array($oldPrefix, $objectsToMove)) {
                            try {
                                // Try to copy the folder marker if it exists
                                $client->copyObject([
                                    'Bucket' => $bucket->bucket_name,
                                    'CopySource' => $bucket->bucket_name . '/' . $itemPath,
                                    'Key' => $newPath,
                                ]);
                                
                                // Delete the old folder marker
                                $client->deleteObject([
                                    'Bucket' => $bucket->bucket_name,
                                    'Key' => $itemPath,
                                ]);
                            } catch (\Exception $e) {
                                // Folder marker might not exist, which is fine
                            }
                        }
                        
                    } else {
                        // Move single file
                        $client->copyObject([
                            'Bucket' => $bucket->bucket_name,
                            'CopySource' => $bucket->bucket_name . '/' . $itemPath,
                            'Key' => $newPath,
                        ]);
                        
                        $client->deleteObject([
                            'Bucket' => $bucket->bucket_name,
                            'Key' => $itemPath,
                        ]);
                    }
                    
                    $movedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = basename($itemPath) . ': ' . $e->getMessage();
                }
            }
            
            if ($movedCount === 0 && !empty($errors)) {
                return response()->json(['error' => 'Failed to move items: ' . implode(', ', $errors)], 500);
            }
            
            $message = "$movedCount item(s) moved successfully";
            if (!empty($errors)) {
                $message .= '. Errors: ' . implode(', ', $errors);
            }
            
            return response()->json(['success' => true, 'message' => $message]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to move items: ' . $e->getMessage()], 500);
        }
    }
}