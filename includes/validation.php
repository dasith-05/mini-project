<?php
/**
 * Input validation for signup, login, and item reports.
 */
function validate_signup(array $post): array {
    $err = [];
    $name = trim($post['name'] ?? '');
    $student_id = trim($post['student_id'] ?? '');
    $contact = trim($post['contact'] ?? '');
    $password = $post['password'] ?? '';

    if ($name === '') {
        $err[] = 'Full name is required.';
    } elseif (strlen($name) > (defined('NAME_MAX') ? NAME_MAX : 100)) {
        $err[] = 'Name is too long.';
    }

    if ($student_id === '') {
        $err[] = 'Student ID is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $student_id)) {
        $err[] = 'Student ID can only contain letters, numbers, hyphens and underscores.';
    } elseif (strlen($student_id) > (defined('STUDENT_ID_MAX') ? STUDENT_ID_MAX : 50)) {
        $err[] = 'Student ID is too long.';
    }

    if ($contact === '') {
        $err[] = 'Contact number is required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $contact)) {
        $err[] = 'Please enter a valid contact number (7–20 digits).';
    } elseif (strlen($contact) > (defined('CONTACT_MAX') ? CONTACT_MAX : 20)) {
        $err[] = 'Contact number is too long.';
    }

    $minPass = defined('PASSWORD_MIN') ? PASSWORD_MIN : 8;
    if (strlen($password) < $minPass) {
        $err[] = "Password must be at least {$minPass} characters.";
    }

    return $err;
}

function validate_login(array $post): array {
    $err = [];
    $student_id = trim($post['student_id'] ?? '');
    $password = $post['password'] ?? '';

    if ($student_id === '' || $password === '') {
        $err[] = 'Student ID and password are required.';
    }

    return $err;
}

function validate_item(array $post): array {
    $err = [];
    $title = trim($post['title'] ?? '');
    $description = trim($post['description'] ?? '');
    $item_type = $post['item_type'] ?? '';

    if ($title === '') {
        $err[] = 'Item title is required.';
    } elseif (strlen($title) > (defined('ITEM_TITLE_MAX') ? ITEM_TITLE_MAX : 200)) {
        $err[] = 'Title is too long.';
    }

    if (strlen($description) > (defined('ITEM_DESC_MAX') ? ITEM_DESC_MAX : 1000)) {
        $err[] = 'Description is too long.';
    }

    if (!in_array($item_type, ['lost', 'found'], true)) {
        $err[] = 'Please choose Lost or Found.';
    }

    return $err;
}

function validate_otp_verify(array $post): array {
    $err = [];
    $otp = trim($post['entered_otp'] ?? '');
    if ($otp === '') {
        $err[] = 'OTP is required.';
    } elseif (!preg_match('/^\d{4,8}$/', $otp)) {
        $err[] = 'OTP must be 4–8 digits.';
    }
    return $err;
}

function validate_profile_update(array $post, array $files): array {
    $err = [];
    $name = trim($post['name'] ?? '');
    $contact = trim($post['contact'] ?? '');

    if ($name === '') {
        $err[] = 'Full name is required.';
    } elseif (strlen($name) > (defined('NAME_MAX') ? NAME_MAX : 100)) {
        $err[] = 'Name is too long.';
    }

    if ($contact === '') {
        $err[] = 'Contact number is required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $contact)) {
        $err[] = 'Please enter a valid contact number (7–20 digits).';
    } elseif (strlen($contact) > (defined('CONTACT_MAX') ? CONTACT_MAX : 20)) {
        $err[] = 'Contact number is too long.';
    }

    if (!empty($files['profile_picture']['name'])) {
        $f = $files['profile_picture'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $err[] = 'File upload error.';
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            if (!in_array($mime, $allowed, true)) {
                $err[] = 'Only JPG, PNG and WebP images are allowed.';
            }
            if ($f['size'] > 2 * 1024 * 1024) { // 2MB limit
                $err[] = 'Image size must be less than 2MB.';
            }
        }
    }

    return $err;
}
