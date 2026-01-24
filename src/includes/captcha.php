<?php
function generateCaptcha() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $captcha = '';
    for ($i = 0; $i < 6; $i++) {
        $captcha .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha'] = strtolower($captcha);
    return $captcha;
}

function verifyCaptcha($input) {
    if (empty($_SESSION['captcha'])) return false;
    $valid = strtolower(trim($input)) === $_SESSION['captcha'];
    unset($_SESSION['captcha']);
    return $valid;
}

function renderCaptchaImage() {
    $captcha = generateCaptcha();
    
    $width = 150;
    $height = 50;
    $img = imagecreatetruecolor($width, $height);
    
    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $bg);
    
    for ($i = 0; $i < 100; $i++) {
        $color = imagecolorallocate($img, random_int(200, 255), random_int(200, 255), random_int(200, 255));
        imagesetpixel($img, random_int(0, $width), random_int(0, $height), $color);
    }
    
    for ($i = 0; $i < 5; $i++) {
        $color = imagecolorallocate($img, random_int(150, 200), random_int(150, 200), random_int(150, 200));
        imageline($img, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $color);
    }
    
    $textColor = imagecolorallocate($img, 0, 0, 0);
    $fontSize = 5;
    $x = 15;
    
    for ($i = 0; $i < strlen($captcha); $i++) {
        $y = random_int(15, 30);
        imagestring($img, $fontSize, $x, $y, $captcha[$i], $textColor);
        $x += 20 + random_int(-3, 3);
    }
    
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    imagepng($img);
    imagedestroy($img);
}
