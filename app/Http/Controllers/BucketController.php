<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class BucketController extends Controller
{
    /**
     * Display all buckets for the authenticated user
     */
    public function index()
    {
        try {
            \Log::info('BucketController@index - User ID: ' . auth()->id());
            
            $buckets = auth()->user()->buckets()->latest()->get();
            
            \Log::info('BucketController@index - Buckets count: ' . $buckets->count());
            
            return Inertia::render('Buckets/Index', [
                'buckets' => $buckets->map(function ($bucket) {
                    try {
                        return [
                            'id' => $bucket->id,
                            'name' => $bucket->name,
                            'provider' => $bucket->provider,
                            'bucket_name' => $bucket->bucket_name,
                            'is_active' => $bucket->is_active,
                            'last_connected_at' => $bucket->last_connected_at?->diffForHumans(),
                            'created_at' => $bucket->created_at->format('M d, Y'),
                        ];
                    } catch (\Exception $e) {
                        \Log::error('Error mapping bucket ID ' . $bucket->id . ': ' . $e->getMessage());
                        throw $e;
                    }
                }),
            ]);
        } catch (\Exception $e) {
            \Log::error('BucketController@index error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            
            // Return empty buckets array with error message
            return Inertia::render('Buckets/Index', [
                'buckets' => [],
                'error' => 'Failed to load buckets. Please check the logs.',
            ]);
        }
    }

    /**
     * Show form to create a new bucket
     */
    public function create(Request $request)
    {
        return Inertia::render('Buckets/Create', [
            'provider' => $request->get('provider', 'r2')
        ]);
    }

    /**
     * Store a new bucket configuration
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|in:s3,r2',
            'bucket_name' => 'required|string|max:255',
            'region' => 'required_if:provider,s3|nullable|string',
            'access_key_id' => 'required|string',
            'secret_access_key' => 'required|string',
            'endpoint' => 'required_if:provider,r2|nullable|url',
            'public_url' => 'nullable|url',
        ]);

        // Check if name is unique for this user
        if (auth()->user()->buckets()->where('name', $validated['name'])->exists()) {
            return back()->withErrors(['name' => 'You already have a bucket with this name.']);
        }

        // Create the bucket
        $bucket = auth()->user()->buckets()->create($validated);

        // Test the connection
        try {
            $this->testBucketConnection($bucket);
            $bucket->update(['last_connected_at' => now()]);
            
            return redirect()->route('buckets.index')
                ->with('success', 'Bucket connected successfully!');
        } catch (\Exception $e) {
            // Delete the bucket if connection fails
            $bucket->delete();
            
            return back()->withErrors(['connection' => 'Failed to connect: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Test bucket connection
     */
    public function test(Bucket $bucket)
    {
        // Ensure user owns this bucket
        $this->authorize('view', $bucket);

        try {
            $this->testBucketConnection($bucket);
            $bucket->update(['last_connected_at' => now()]);
            
            return back()->with('success', 'Connection successful!');
        } catch (\Exception $e) {
            \Log::error('Bucket test failed', [
                'bucket_id' => $bucket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors(['connection' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle bucket connection status
     */
    public function activate(Bucket $bucket)
    {
        $this->authorize('update', $bucket);

        if ($bucket->is_active) {
            // Disconnect the bucket
            $bucket->update(['is_active' => false]);
            return back()->with('success', 'Bucket disconnected successfully!');
        } else {
            // Connect the bucket
            $bucket->update([
                'is_active' => true,
                'last_connected_at' => now()
            ]);
            return back()->with('success', 'Bucket connected successfully!');
        }
    }

    /**
     * Rename a bucket
     */
    public function rename(Request $request, Bucket $bucket)
    {
        $this->authorize('update', $bucket);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Check if name is unique for this user (excluding current bucket)
        if (auth()->user()->buckets()->where('name', $validated['name'])->where('id', '!=', $bucket->id)->exists()) {
            return back()->withErrors(['name' => 'You already have a bucket with this name.']);
        }

        $bucket->update(['name' => $validated['name']]);

        return back()->with('success', 'Bucket renamed successfully!');
    }

    /**
     * Delete a bucket configuration
     */
    public function destroy(Bucket $bucket)
    {
        $this->authorize('delete', $bucket);

        $bucket->delete();

        return redirect()->route('buckets.index')
            ->with('success', 'Bucket removed successfully!');
    }

    /**
     * Test bucket connection helper
     */
    private function testBucketConnection(Bucket $bucket)
    {
        $client = new S3Client($bucket->getClientConfig());
        
        // Try to list objects (with limit of 1) to test connection
        $client->listObjects([
            'Bucket' => $bucket->bucket_name,
            'MaxKeys' => 1,
        ]);
    }
}
