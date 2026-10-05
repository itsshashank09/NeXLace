# Message attachments

`api/send_message.php` accepts text and multipart attachments, checks MIME type with PHP Fileinfo and applies a 2 MB file-size limit. Files are written beneath `uploads/messages/`. `api/get_messages.php` returns attachment metadata to the conversation interface.

The messages table needs `attachment_path` and `attachment_name` fields. `migrate_add_attachments.php` contains an incremental update; it does not replace the missing base schema. Run schema changes only against a development database after inspecting them.

To verify the feature, use two test accounts, open `messages.php` and try a small image and PDF. Then try an oversized file and a disallowed type. Confirm that another user cannot read the conversation or retrieve a private attachment. MIME validation alone does not establish private file access.

The upload directory must be writable by the PHP process. Keep uploads out of Git and configure the web server so uploaded content cannot execute as PHP or another server script.
