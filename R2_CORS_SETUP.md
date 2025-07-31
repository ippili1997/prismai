# R2 CORS Configuration for File Uploads

To allow direct browser uploads to R2, you need to configure CORS settings on your R2 bucket.

## Steps to Configure CORS:

1. **Go to Cloudflare Dashboard**
   - Navigate to R2 > Your Bucket > Settings

2. **Add CORS Policy**
   - Click on "CORS Policy" 
   - Add the following configuration:

```json
[
  {
    "AllowedOrigins": [
      "http://localhost:8000",
      "http://127.0.0.1:8000",
      "https://yourdomain.com"
    ],
    "AllowedMethods": [
      "GET",
      "PUT",
      "POST",
      "DELETE",
      "HEAD"
    ],
    "AllowedHeaders": [
      "*"
    ],
    "ExposeHeaders": [
      "ETag"
    ],
    "MaxAgeSeconds": 3600
  }
]
```

3. **Important Notes**:
   - Replace `https://yourdomain.com` with your actual domain in production
   - For development, include both `localhost:8000` and `127.0.0.1:8000`
   - The `AllowedHeaders: ["*"]` allows all headers including Content-Type

4. **Save the Configuration**

## Testing the Upload:

After configuring CORS:
1. Refresh your browser page
2. Open the browser console (F12)
3. Try uploading a file again
4. Check for any CORS errors in the console

## Common Issues:

1. **CORS Error**: If you see "CORS policy" errors, double-check your allowed origins
2. **403 Forbidden**: Check that your R2 API credentials have write permissions
3. **Network Error**: Ensure your R2 endpoint URL is correct in the bucket configuration