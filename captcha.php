<?php 
session_start(); 

// Generate 5-digit random number
$text = rand(10000, 99999); 
$_SESSION["vercode"] = $text; 

$height = 100; 
$width = 260;   

// Create image with white background
$image_p = imagecreatetruecolor($width, $height); 
$white = imagecolorallocate($image_p, 255, 255, 255); 
$black = imagecolorallocate($image_p, 0, 0, 0);
$lightGray = imagecolorallocate($image_p, 230, 230, 230);
imagefill($image_p, 0, 0, $white);

// Add light noise dots (very subtle)
for ($i = 0; $i < 30; $i++) {
    imagesetpixel($image_p, rand(0, $width), rand(0, $height), $lightGray);
}

// Add few subtle lines
for ($i = 0; $i < 3; $i++) {
    $lineColor = imagecolorallocate($image_p, 240, 240, 240);
    imageline($image_p, rand(0, $width), rand(0, $height), 
              rand(0, $width), rand(0, $height), $lineColor);
}

// Strong dark colors for text - easy to read
$textColors = array(
    imagecolorallocate($image_p, 0, 0, 0),          // Black
    imagecolorallocate($image_p, 0, 51, 153),       // Dark blue
    imagecolorallocate($image_p, 102, 0, 51),       // Dark purple
    imagecolorallocate($image_p, 0, 102, 51)        // Dark green
);

// Draw each digit with good size and contrast
$text_arr = str_split($text);
$start_x = 5;
$digit_width = 51;
$font_size = 50; // Large font size

// Try to use TTF font if available, otherwise fall back to GD built-in
$font_file = 'C:\\Windows\\Fonts\\arial.ttf'; // Windows Arial font
$use_ttf = file_exists($font_file);

foreach ($text_arr as $index => $digit) {
    $x = $start_x + ($index * $digit_width);
    $y = 85; // Y position for TTF font baseline
    
    // Random dark color for each digit
    $color = $textColors[array_rand($textColors)];
    
    if ($use_ttf) {
        // Use TrueType font for much larger, better looking digits
        $y_offset = rand(-2, 2);
        imagettftext($image_p, $font_size, 0, $x, $y + $y_offset, $color, $font_file, $digit);
    } else {
        // Fallback: use built-in font (smaller)
        $y_offset = rand(-3, 3);
        imagestring($image_p, 5, $x, $y/3 + $y_offset, $digit, $color);
    }
}

// Add a clear border
imagerectangle($image_p, 0, 0, $width - 1, $height - 1, $black);

header('Content-Type: image/jpeg');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagejpeg($image_p, null, 90); 
imagedestroy($image_p);
?>
