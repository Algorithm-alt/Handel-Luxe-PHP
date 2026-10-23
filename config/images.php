<?php
/**
 * Product image gallery: storage, validation and compression.
 *
 * Every product carries 1-6 images. The first image (lowest sort_order) is
 * also written to handel_products.image, which stays the single "cover"
 * column that carts, order items, invoices and the WhatsApp notification
 * already read — so those code paths keep working untouched.
 */
require_once __DIR__ . '/db.php';

const GALLERY_MAX_IMAGES = 6;
const GALLERY_MIN_IMAGES = 1;

/* Source uploads are normalised to this long edge, and the gallery copies
   derived from them are scaled down again. A 12MP phone photo lands around
   250KB for the source and 80KB for the gallery copy. */
const IMAGE_SOURCE_EDGE  = 1600;
const IMAGE_SOURCE_QUALITY = 85;
const IMAGE_GALLERY_EDGE   = 800;
const IMAGE_GALLERY_QUALITY = 82;

const IMAGE_MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

/* The stand-in shown for a product with no image of its own. syncProductCover()
   writes it to handel_products.image once the gallery is empty, and
   ensureProductGallery() seeds it as the single gallery row so the read paths
   always have something to render. */
function placeholderImagePath() {
    return '/images/placeholder.png';
}

function ensureImagesTable() {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->query("CREATE TABLE IF NOT EXISTS handel_product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image VARCHAR(500) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_product_sort (product_id, sort_order),
        FOREIGN KEY (product_id) REFERENCES handel_products(id) ON DELETE CASCADE
    )");
    $done = true;
}

/* Gallery rows for a product, in display order. */
function getProductImages($productId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT id, image, sort_order FROM handel_product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['image'] = image_url($r['image']);
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
    }
    unset($r);
    return $rows;
}

/**
 * Backfill the gallery for a product that has none, seeding it from the
 * legacy single image column so every product always renders at least one
 * image. Called on read paths for products created before the gallery.
 */
function ensureProductGallery($productId) {
    ensureImagesTable();
    $db = getDB();

    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM handel_product_images WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    if ($count > 0) return;

    $stmt = $db->prepare("SELECT image FROM handel_products WHERE id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cover = ($row && !empty($row['image'])) ? $row['image'] : placeholderImagePath();
    $stmt = $db->prepare("INSERT INTO handel_product_images (product_id, image, sort_order) VALUES (?, ?, 0)");
    $stmt->bind_param('is', $productId, $cover);
    $stmt->execute();
    $stmt->close();
}

/* ---------------------------------------------------------------- storage */

function imagesUploadDir() {
    return __DIR__ . '/../public/uploads';
}

function imageHasAlpha($im) {
    if (!imageistruecolor($im)) return false;
    $w = imagesx($im); $h = imagesy($im);
    // Sample a grid rather than every pixel — enough to catch the flat
    // transparent backgrounds logos and packshots actually use.
    for ($x = 0; $x < $w; $x += max(1, (int)($w / 40))) {
        for ($y = 0; $y < $h; $y += max(1, (int)($h / 40))) {
            if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 0) return true;
        }
    }
    return false;
}

/* WebP support is a compile-time option; fall back to JPEG when absent. */
function imageEncodeFormat() {
    static $format = null;
    if ($format !== null) return $format;
    $format = (function_exists('imagewebp') && (imagetypes() & IMG_WEBP)) ? 'webp' : 'jpg';
    return $format;
}

/* Flatten onto white so a transparent PNG does not keep an alpha channel
   through a format that cannot store one. */
function imageFlatten($im) {
    $w = imagesx($im); $h = imagesy($im);
    $flat = imagecreatetruecolor($w, $h);
    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
    imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
    return $flat;
}

/**
 * Resize to fit $maxEdge (never upscaling) and write an encoded copy.
 * Returns true on success.
 */
function imageSaveResized($srcIm, $destPath, $maxEdge, $quality) {
    $sw = imagesx($srcIm); $sh = imagesy($srcIm);
    $scale = min(1, $maxEdge / max($sw, $sh));
    $dw = max(1, (int)round($sw * $scale));
    $dh = max(1, (int)round($sh * $scale));

    $out = ($scale < 1) ? imagecreatetruecolor($dw, $dh) : $srcIm;
    if ($scale < 1) {
        imagecopyresampled($out, $srcIm, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
    }

    $format = imageEncodeFormat();
    if ($format === 'jpg' && imageHasAlpha($out)) {
        $flat = imageFlatten($out);
        $ok = imagejpeg($flat, $destPath, $quality);
        imagedestroy($flat);
    } elseif ($format === 'webp') {
        $ok = imagewebp($out, $destPath, $quality);
    } else {
        $ok = imagejpeg($out, $destPath, $quality);
    }

    if ($scale < 1) imagedestroy($out);
    return (bool)$ok;
}

/* PHP's own UPLOAD_ERR_* code as something an admin can act on. The size
   errors name the php.ini directive responsible, because "the upload failed"
   on its own sends people to check the file rather than the server. */
function uploadErrorMessage($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'the file is larger than this PHP build\'s upload_max_filesize (' . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_FORM_SIZE:
            return 'the file is larger than the form allowed.';
        case UPLOAD_ERR_PARTIAL:
            return 'only part of the file arrived. Check the connection and try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'no file was received.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'the server has no temporary directory configured for uploads.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'the server could not write the file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'a PHP extension stopped the upload.';
        default:
            return 'unknown error (code ' . (int)$code . ').';
    }
}

/**
 * Validate, compress and store one uploaded image.
 *
 * Returns ['source' => '/uploads/…', 'gallery' => '/uploads/…'] or null when
 * the file is rejected, in which case $reason is filled with a sentence
 * naming the check that refused it. Guards mirror the original single-image
 * handler: extension allowlist, real image bytes, upload size cap,
 * is_uploaded_file, plus a randomized server-side filename — nothing
 * user-supplied reaches the filesystem path.
 */
function storeProductImage(array $file, &$reason = null) {
    $reason = null;
    /* Every rejection records why. Collapsing them all into "choose a JPG,
       PNG, GIF or WebP file under 5MB" pointed the admin at the file's
       format even when the cause was server-side (no GD extension, a PHP
       upload limit) or a format the allowlist simply does not cover. */
    $fail = function ($why) use (&$reason) { $reason = $why; return null; };

    /* The error code is checked before tmp_name because PHP reports an
       over-limit file as UPLOAD_ERR_INI_SIZE *with an empty tmp_name* — so
       testing tmp_name first would blame post_max_size for a file that
       actually tripped upload_max_filesize, and send the admin to the wrong
       php.ini line. */
    $err = $file['error'] ?? UPLOAD_ERR_OK;
    if ($err !== UPLOAD_ERR_OK) {
        return $fail('The upload failed: ' . uploadErrorMessage($err));
    }
    if (empty($file['tmp_name'])) {
        return $fail('The upload did not reach the server. It is probably larger than this PHP build\'s post_max_size (' . ini_get('post_max_size') . ').');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('The file was not recognised as a genuine upload.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        return $fail('The uploaded file was empty.');
    }
    if ($size > IMAGE_MAX_UPLOAD_BYTES) {
        return $fail('The file is ' . round($size / 1048576, 1) . 'MB and the limit is '
            . round(IMAGE_MAX_UPLOAD_BYTES / 1048576) . 'MB. Resize it and try again.');
    }

    $allowed = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return $fail($ext === ''
            ? 'The file has no extension, so its format cannot be checked.'
            : 'Files ending in ".' . $ext . '" are not supported. Use JPG, PNG, GIF or WebP.');
    }

    // Confirm the bytes really are an image — a script named "photo.jpg"
    // must never reach a served directory.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return $fail('The file does not contain readable image data. A HEIC or AVIF photo straight off a phone is the usual cause; export it as JPG first.');
    }
    $mimeExt = image_type_to_extension($info[2], false);
    $mimeExt = $mimeExt === 'jpeg' ? 'jpg' : $mimeExt;
    if (!in_array($mimeExt, $allowed, true)) {
        return $fail('Despite the file name, the contents are "' . $mimeExt . '", which is not supported.');
    }

    if (!extension_loaded('gd')) {
        return $fail('This PHP build has no GD extension, so no image can be processed at all. Enable extension=gd in php.ini and restart Apache.');
    }

    $srcIm = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if ($srcIm === false) {
        return $fail('The image could not be decoded. It may be truncated or corrupt.');
    }

    // Normalise orientation before resizing, otherwise portrait photos
    // taken on a phone arrive sideways once EXIF is dropped by re-encoding.
    if (function_exists('exif_read_data') && in_array($mimeExt, ['jpg'], true)) {
        $exif = @exif_read_data($file['tmp_name']);
        $orientation = $exif['Orientation'] ?? 0;
        if ($orientation >= 3) {
            if ($orientation === 3 || $orientation === 4) $srcIm = imagerotate($srcIm, 180, 0);
            elseif ($orientation === 5 || $orientation === 6) $srcIm = imagerotate($srcIm, -90, 0);
            elseif ($orientation === 7 || $orientation === 8) $srcIm = imagerotate($srcIm, 90, 0);
        }
    }

    $format = imageEncodeFormat();
    $ext_out = $format === 'webp' ? 'webp' : 'jpg';
    $dir = imagesUploadDir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        imagedestroy($srcIm);
        return $fail('The uploads folder could not be created at ' . $dir . '. Check that the web server can write to the project folder.');
    }

    $base = bin2hex(random_bytes(12));
    $sourcePath  = $dir . '/' . $base . '-src.' . $ext_out;
    $galleryPath = $dir . '/' . $base . '.' . $ext_out;

    $okSource  = imageSaveResized($srcIm, $sourcePath,  IMAGE_SOURCE_EDGE,  IMAGE_SOURCE_QUALITY);
    $okGallery = $okSource && imageSaveResized($srcIm, $galleryPath, IMAGE_GALLERY_EDGE, IMAGE_GALLERY_QUALITY);
    imagedestroy($srcIm);

    if (!$okGallery) {
        @unlink($sourcePath);
        return $fail('The image could not be written to ' . $dir . '. Check that the folder is writable by the web server.');
    }
    if (!$okSource) {
        // Without a source copy, the gallery copy becomes the only artefact.
        @unlink($galleryPath);
        return $fail('The image could not be written to ' . $dir . '. Check that the folder is writable by the web server.');
    }

    @chmod($sourcePath, 0644);
    @chmod($galleryPath, 0644);

    return [
        'source'  => '/uploads/' . basename($sourcePath),
        'gallery' => '/uploads/' . basename($galleryPath),
    ];
}

/**
 * True when PHP threw the whole request body away before any code ran.
 *
 * A POST whose body exceeds post_max_size arrives with $_POST *and* $_FILES
 * both empty. PHP only warns in the error log, so the handler sees a form
 * that looks untouched and reports a validation error about fields the admin
 * did fill in. Content-Length is still present, which is what makes this
 * detectable at all.
 */
function oversizedPostDetected() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $len > 0 && empty($_POST) && empty($_FILES);
}

function oversizedPostError() {
    return 'The request was too large for this PHP build and was discarded before it reached the app, '
        . 'so none of the form fields or images arrived. post_max_size is ' . ini_get('post_max_size')
        . ' and upload_max_filesize is ' . ini_get('upload_max_filesize')
        . '. Raise both in php.ini (post_max_size must be the larger of the two) and restart Apache.';
}

/* Uploaded files for a field, normalised into a list of single-file arrays. */
function normaliseUploadedFiles($field) {
    if (empty($_FILES[$field])) return [];
    $f = $_FILES[$field];
    $out = [];
    if (is_array($f['name'])) {
        $count = count($f['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $out[] = [
                'name' => $f['name'][$i],
                'type' => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error' => $f['error'][$i],
                'size' => $f['size'][$i],
            ];
        }
    } elseif (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $out[] = $f;
    }
    return $out;
}

/* Persist a gallery row. */
function insertProductImage($productId, $path, $sortOrder) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO handel_product_images (product_id, image, sort_order) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $productId, $path, $sortOrder);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Delete a gallery row and its files, refusing ids that belong to another
 * product so one product's edit can never strip another's images.
 */
function deleteProductImage($productId, $imageId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT image FROM handel_product_images WHERE id = ? AND product_id = ?");
    $stmt->bind_param('ii', $imageId, $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;

    $stmt = $db->prepare("DELETE FROM handel_product_images WHERE id = ? AND product_id = ?");
    $stmt->bind_param('ii', $imageId, $productId);
    $stmt->execute();
    $stmt->close();

    removeUploadedImageFiles($row['image']);
    return true;
}

/* Remove the gallery copy and its -src partner from disk. Only ever
   touches files inside public/uploads. */
function removeUploadedImageFiles($galleryPath) {
    if (!$galleryPath || strpos($galleryPath, '/uploads/') !== 0) return;
    $dir = imagesUploadDir();
    $galleryFile = $dir . '/' . basename($galleryPath);
    @unlink($galleryFile);

    $ext = pathinfo($galleryFile, PATHINFO_EXTENSION);
    $base = substr(basename($galleryFile), 0, -(strlen($ext) + 1));
    @unlink($dir . '/' . $base . '-src.' . $ext);
}

/* Keep handel_products.image (the cover) in step with the gallery order. */
function syncProductCover($productId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT image FROM handel_product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cover = $row ? $row['image'] : placeholderImagePath();
    $stmt = $db->prepare("UPDATE handel_products SET image = ? WHERE id = ?");
    $stmt->bind_param('si', $cover, $productId);
    $stmt->execute();
    $stmt->close();
}

function countProductImages($productId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM handel_product_images WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count;
}

/* Highest sort_order in use, so appended images queue up after existing ones. */
function nextImageSortOrder($productId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) AS m FROM handel_product_images WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $m = (int)$stmt->get_result()->fetch_assoc()['m'];
    $stmt->close();
    return $m + 1;
}

/* Resolve one gallery id to a row, or null when it is not this product's.
   Lets a batch be planned in full before any part of it is carried out. */
function lookupProductImage($productId, $imageId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT image FROM handel_product_images WHERE id = ? AND product_id = ?");
    $stmt->bind_param('ii', $imageId, $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/* Gallery rows holding the stand-in rather than a real image. There is at most
   one, and only ever as the sole row of an image-less product, because the
   first real image to arrive displaces it. */
function countPlaceholderGalleryRows($productId) {
    ensureImagesTable();
    $db = getDB();
    $placeholder = placeholderImagePath();
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM handel_product_images WHERE product_id = ? AND image = ?");
    $stmt->bind_param('is', $productId, $placeholder);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count;
}

/* Drop the stand-in once real images are arriving. Matches the placeholder path
   only, so it can never delete a real image or unlink its files. */
function removePlaceholderGalleryRows($productId) {
    ensureImagesTable();
    $db = getDB();
    $placeholder = placeholderImagePath();
    $stmt = $db->prepare("DELETE FROM handel_product_images WHERE product_id = ? AND image = ?");
    $stmt->bind_param('is', $productId, $placeholder);
    $stmt->execute();
    $removed = $stmt->affected_rows;
    $stmt->close();
    return $removed;
}

/**
 * Apply a submitted batch of uploads/removals to a product.
 *
 * Nothing is written until the whole batch is known to be acceptable, so a
 * request that breaks the 1-6 rule changes nothing at all. Returns
 * ['added' => n, 'removed' => n, 'rejected' => n] or throws RuntimeException
 * with a message suitable for the admin UI.
 */
function applyProductImages($productId, array $files, array $removeIds, $requireMinimum = false) {
    ensureImagesTable();

    $existing = countProductImages($productId);

    /* Stand-in rows are counted apart from real ones. They must not occupy one
       of the 6 slots, because the batch that adds a real image displaces the
       stand-in — but they do still count as something to display, so editing
       the text of an image-less product is not blocked by the 1-image floor. */
    $standIns = countPlaceholderGalleryRows($productId);
    $realExisting = $existing - $standIns;

    /* Resolve the removals without performing any of them. Ids naming another
       product's image, or nothing at all, must not count towards the room a
       batch frees up — otherwise a request full of junk ids looks like it
       makes space it does not. A stand-in is skipped for the same reason: real
       images displace it automatically, so removing it frees no slot and there
       is nothing there for the admin to delete. */
    $removals = [];
    $seen = [];
    foreach ($removeIds as $rid) {
        $rid = (int)$rid;
        if ($rid <= 0 || isset($seen[$rid])) continue;
        $seen[$rid] = true;
        $row = lookupProductImage($productId, $rid);
        if ($row !== null && $row['image'] !== placeholderImagePath()) $removals[] = $rid;
    }

    /* The ceiling is enforced here, before anything is written, rather than
       once the batch has been applied: a check that fired with rows already
       inserted would have to undo them, which is the partial-failure shape
       this function exists to avoid. count($files) is used instead of the
       number that will survive validation so the message can name what the
       admin actually submitted. */
    if ($realExisting - count($removals) + count($files) > GALLERY_MAX_IMAGES) {
        throw new RuntimeException('A product can have at most ' . GALLERY_MAX_IMAGES . ' images. You have ' . $realExisting . ' and tried to add ' . count($files) . '.');
    }

    /* Write the uploads first and leave the database untouched until the batch
       is known to be acceptable. Removing before validating is what let a
       refused edit destroy a product's only image: the row was gone and the
       files were unlinked by the time the 1-image floor was consulted, so the
       error the admin saw ("Please add at least 1 image") described a state
       the request had itself caused, and the original upload could not be
       recovered. */
    $stored = [];
    $rejected = 0;
    $reasons = [];
    foreach ($files as $file) {
        $result = storeProductImage($file, $reason);
        if ($result === null) {
            $rejected++;
            if ($reason !== null && !in_array($reason, $reasons, true)) $reasons[] = $reason;
            continue;
        }
        $stored[] = $result;
    }

    /* Displayable rows once the batch lands. A stand-in counts here, because it
       is still something to show; it is excluded from the ceiling above, which
       is about real images only. */
    $projected = $existing - count($removals) + count($stored);
    $discardStored = function () use ($stored) {
        // Also unlinks each stored file's -src partner.
        foreach ($stored as $s) removeUploadedImageFiles($s['gallery']);
    };

    /* Every uploaded file was refused. The admin clearly intended to change
       the gallery, so this must surface as an error even when the product
       already had enough images to keep $projected above the minimum —
       otherwise an edit reports "success" while the new images never appear. */
    if (count($files) > 0 && $rejected > 0 && count($stored) === 0) {
        $discardStored();
        throw new RuntimeException('No image was saved. '
            . ($reasons ? implode(' ', $reasons) : 'The uploaded file(s) were rejected.'));
    }

    if ($requireMinimum && $projected < GALLERY_MIN_IMAGES) {
        $discardStored();
        /* Report what actually refused the file. The old wording blamed the
           file's format unconditionally, so a missing GD extension or a PHP
           upload limit sent the admin off to re-export a perfectly good JPG. */
        if ($rejected > 0 && $reasons) {
            throw new RuntimeException('No image was saved. ' . implode(' ', $reasons));
        }
        throw new RuntimeException('Please add at least 1 image for this product.');
    }

    /* Accepted, so apply it. The stand-in goes before the real images land: it
       sits at sort_order 0, and since the cover is the lowest sort_order,
       leaving it in place would keep it as the product's cover no matter what
       the admin uploaded next — which is exactly how a freshly uploaded image
       ended up losing to a placeholder. Additions land before removals leave,
       so the gallery is never momentarily empty even when the two balance
       out. */
    if ($stored) removePlaceholderGalleryRows($productId);

    $added = 0;
    foreach ($stored as $s) {
        insertProductImage($productId, $s['gallery'], nextImageSortOrder($productId));
        $added++;
    }

    $removed = 0;
    foreach ($removals as $rid) {
        if (deleteProductImage($productId, $rid)) $removed++;
    }

    if ($added > 0 || $removed > 0) syncProductCover($productId);

    return ['added' => $added, 'removed' => $removed, 'rejected' => $rejected];
}
