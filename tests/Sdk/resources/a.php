<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.
function a_test($str)
{
    echo "\nHi: $str";
    var_dump(debug_backtrace());
}

$foo = 'friend';
a_test($foo);
