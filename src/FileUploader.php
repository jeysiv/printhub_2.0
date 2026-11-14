<?php
namespace PrintHub;

/**
 * FileUploader  -  safely saves the file a customer wants printed.
 *
 * Safety rules:  1) size limit  2) only allowed file types (checked by extension AND real content)
 *                3) the saved name is random, so nobody can overwrite or guess files.
 */
class FileUploader
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    // allowed extension => allowed real MIME types
    private const ALLOWED = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];

    private string $folder;

    public function __construct(string $folder)
    {
        $this->folder = rtrim($folder, '/\\');
    }

    /**
     * Save one uploaded file.
     * Returns ['name' => original name, 'path' => 'uploads/xxxx.pdf'],
     * or null when the customer did not choose a file (a file is optional).
     */
    public function save(array $file): ?array
    {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($code === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(['The file is too large. The limit is 5 MB.']);
        }
        if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException(['The file could not be uploaded. Please try again.']);
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new ValidationException(['The file is too large. The limit is 5 MB.']);
        }

        $originalName = substr(basename($file['name']), 0, 200);
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::ALLOWED)) {
            throw new ValidationException(['Only PDF, JPG, PNG, or DOCX files are allowed.']);
        }

        // Look at the real content of the file, not just its name
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, self::ALLOWED[$extension], true)) {
            throw new ValidationException(['The file content does not match its type.']);
        }

        $newName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $this->folder . '/' . $newName)) {
            throw new ValidationException(['The file could not be saved. Please try again.']);
        }

        return ['name' => $originalName, 'path' => 'uploads/' . $newName];
    }

    /** Remove a saved file (used when something fails after the upload, or an order is deleted). */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $full = $this->folder . '/' . basename($path); // basename() blocks "../" tricks
        if (is_file($full)) {
            unlink($full);
        }
    }
}
