<?php
/** Temporary customer uploads (step 3 of the order form). Files are stored OUTSIDE the web root. */
require __DIR__ . '/../../app/bootstrap.php';
$u = current_user();
if (!$u || $u['role'] !== 'customer') json_out(['ok' => false, 'error' => 'Please log in.'], 401);
if (!is_post()) json_out(['ok' => false, 'error' => 'POST required.'], 405);
csrf_check(true);
$uid = (int)$u['id'];
$dir = tmp_dir($uid);

if (post('delete') !== '') {                                  // remove an uploaded file
    $t = post('delete');
    if (valid_token($t)) { @unlink("$dir/$t.bin"); @unlink("$dir/$t.json"); }
    json_out(['ok' => true]);
}
if (!rate_limit('upload:' . $uid, 60, 600)) json_out(['ok' => false, 'error' => 'Too many uploads. Please wait a few minutes.'], 429);
if (empty($_FILES['file'])) json_out(['ok' => false, 'error' => 'No file received. The file may exceed the server upload limit.'], 400);
[$ext, $mime, $err] = validate_upload($_FILES['file'], ORDER_FILE_EXT);
if ($err) json_out(['ok' => false, 'error' => $err], 422);

ensure_dir($dir);
$token = bin2hex(random_bytes(16));
if (!move_uploaded_file($_FILES['file']['tmp_name'], "$dir/$token.bin")) json_out(['ok' => false, 'error' => 'Could not store the file.'], 500);
$pages = analyze_file("$dir/$token.bin", $ext);
$meta = ['name' => mb_substr(basename($_FILES['file']['name']), 0, 150), 'mime' => $mime, 'size' => (int)$_FILES['file']['size'], 'pages' => $pages, 'ext' => $ext];
file_put_contents("$dir/$token.json", json_encode($meta));
json_out(['ok' => true, 'token' => $token] + $meta);
