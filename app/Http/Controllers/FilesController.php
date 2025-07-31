<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FilesController extends Controller
{
    public function index(Request $request)
    {
        $activeBucket = $request->user()->buckets()->where('is_active', true)->first();
        
        if (!$activeBucket) {
            return redirect()->route('buckets.index')
                ->with('error', 'Please activate a bucket first to browse files.');
        }

        $prefix = $request->get('prefix', '');
        $files = [];
        $folders = [];
        
        try {
            $client = new S3Client($activeBucket->getClientConfig());
            
            $result = $client->listObjectsV2([
                'Bucket' => $activeBucket->bucket_name,
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
            $activeBucket->update(['last_connected_at' => now()]);
            
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
            'activeBucket' => $activeBucket->only(['id', 'name', 'provider']),
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
            
            // Generate a pre-signed URL for download (valid for 5 minutes)
            $cmd = $client->getCommand('GetObject', [
                'Bucket' => $bucket->bucket_name,
                'Key' => $key,
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
            
            $client->deleteObject([
                'Bucket' => $bucket->bucket_name,
                'Key' => $key,
            ]);
            
            return back()->with('success', 'File deleted successfully.');
            
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete file: ' . $e->getMessage());
        }
    }
    
    public function getUploadUrl(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string',
        ]);
        
        $activeBucket = $request->user()->buckets()->where('is_active', true)->first();
        
        if (!$activeBucket) {
            return response()->json(['error' => 'No active bucket found'], 400);
        }
        
        $prefix = $request->get('prefix', '');
        $filename = $request->get('filename');
        $contentType = $request->get('content_type');
        
        // Construct the full key
        $key = $prefix . $filename;
        
        try {
            $client = new S3Client($activeBucket->getClientConfig());
            
            // Generate a pre-signed URL for upload (valid for 10 minutes)
            $cmd = $client->getCommand('PutObject', [
                'Bucket' => $activeBucket->bucket_name,
                'Key' => $key,
                'ContentType' => $contentType,
            ]);
            
            $presignedUrl = $client->createPresignedRequest($cmd, '+10 minutes')->getUri();
            
            \Log::info('Generated pre-signed URL', [
                'bucket' => $activeBucket->bucket_name,
                'key' => $key,
                'content_type' => $contentType,
                'url_host' => parse_url((string) $presignedUrl, PHP_URL_HOST),
            ]);
            
            return response()->json([
                'upload_url' => (string) $presignedUrl,
                'key' => $key,
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate upload URL: ' . $e->getMessage()], 500);
        }
    }
}