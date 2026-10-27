<?php
/**
 * Drop-in replacement for move_uploaded_file() that refuses to store anything
 * that could execute as a script.
 *
 * Rules (the destination name is what gets checked, since that is what the web
 * server will see):
 *  - the final extension must be in the allowlist below;
 *  - no script-like extension may appear anywhere in the name (blocks
 *    "shell.php.jpg", which Apache's AddType/AddHandler would still execute);
 *  - image extensions must really be images (getimagesize on the temp file).
 *
 * Returns false (and logs) when rejected, like move_uploaded_file() on failure.
 */
if (!function_exists('safe_move_uploaded_file')) {
    function safe_move_uploaded_file($from, $to)
    {
        $base = strtolower(basename((string)$to));
        $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif', 'avif',
                    'pdf', 'csv', 'xls', 'xlsx', 'doc', 'docx'];
        $images  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'];

        if (!in_array($ext, $allowed, true)
            || preg_match('/\.(php[0-9]?|phtml|phar|pht|shtml|cgi|pl|py|sh|asp|aspx|jsp|htaccess)(\.|$)/', $base)) {
            error_log('safe_move_uploaded_file: rejected "' . $base . '"');
            return false;
        }
        if (in_array($ext, $images, true) && @getimagesize((string)$from) === false) {
            error_log('safe_move_uploaded_file: "' . $base . '" is not a valid image');
            return false;
        }
        return move_uploaded_file($from, $to);
    }
}

if (!function_exists('safe_old_file_param')) {
    /**
     * Sanitises a client-supplied "old file" value before it is used with
     * unlink()/file_exists(). Only two shapes are accepted, everything else
     * (parent-directory segments, other folders, absolute paths, NUL bytes,
     * URL schemes) becomes an empty string:
     *   - a bare file name:                    "abc123.jpg"
     *   - an upload-folder path as stored:     "user_icon/abc.jpg" or
     *                                          "../distributor/user_icon/abc.jpg"
     */
    function safe_old_file_param($value)
    {
        $v = is_scalar($value) ? (string)$value : '';
        if ($v === '') {
            return '';
        }
        $dirs = 'user_icon|cf_user_icon|outlet_icon|shop_icon|demo_photo|bussiness_logo|offers_img|tp_photo|cp_photo';
        if (preg_match('#^[A-Za-z0-9_][A-Za-z0-9_. \-]*$#', $v)
            || preg_match('#^(\.\./[A-Za-z0-9_\-]+/)?(' . $dirs . ')/[A-Za-z0-9_][A-Za-z0-9_. \-]*$#', $v)) {
            return $v;
        }
        return '';
    }
}
