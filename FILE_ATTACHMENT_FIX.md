# File Attachment Fix - Complete Guide

## Problem
When sending images/files, only text like "[Attachment: images.jpeg]" appears instead of the actual file.

## Solution Applied

### 1. Database Setup

**For fresh installation:**
- Import `database/nexlace_complete.sql` in phpMyAdmin (includes attachment columns)

**For existing database:**
Run this SQL in phpMyAdmin to add attachment support:
```sql
ALTER TABLE messages 
ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL AFTER message,
ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path;
```

### 2. Files Updated

#### ✅ api/send_message.php
- Now handles both JSON and FormData inputs
- Supports file uploads up to 2MB
- Saves files to `uploads/messages/` directory
- Stores file path and name in database

#### ✅ api/get_messages.php
- Fetches `attachment_path` and `attachment_name` from database
- Includes attachment data in API response

#### ✅ messages.php (Frontend)
- Already has code to display images and files
- Shows images inline with preview
- Shows other files as downloadable attachments with icons

### 3. How It Works

**When user attaches a file:**
1. File is uploaded to `uploads/messages/`
2. Database stores:
   - `attachment_path`: `uploads/messages/unique_filename.ext`
   - `attachment_name`: `original_filename.ext`
3. Message text can be empty (file only) or include text

**When messages are displayed:**
- **Images**: Show inline with thumbnail (clickable to open full size)
- **Files**: Show as download link with file icon and name

### 4. Testing Steps

1. **First, run the SQL script above in phpMyAdmin**
2. Go to http://localhost/NeXLace/messages.php
3. Open any conversation
4. Click the attachment button (📎)
5. Select an image or file (max 2MB)
6. Optionally add a message
7. Click send

**Expected Result:**
- Images appear as thumbnails in the chat
- Files appear as downloadable links with icons
- No more "[Attachment: filename]" text

### 5. File Size Limit
- Maximum: 2MB per file
- Error shown if file exceeds limit

### 6. Supported File Types
All file types are supported. Images (.jpg, .jpeg, .png, .gif, .webp, .svg) display inline.

## Troubleshooting

### If attachments still don't work:
1. ✅ Check that SQL was run successfully: `DESCRIBE messages;` should show `attachment_path` and `attachment_name` columns
2. ✅ Check folder permissions: `uploads/messages/` folder should exist and be writable
3. ✅ Check browser console for errors (F12)
4. ✅ Verify file size is under 2MB

### If folder doesn't exist:
The code auto-creates it, but you can manually create:
```
cd c:\xampp\htdocs\NeXLace
mkdir uploads\messages
```

## Current Status
- ✅ Backend ready (send_message.php)
- ✅ API ready (get_messages.php)
- ✅ Frontend ready (messages.php)
- ⚠️ Database needs SQL update (run the ALTER TABLE command)
