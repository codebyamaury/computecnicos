<?php
$root_dir = __DIR__;

function process_dir($dir) {
    global $root_dir;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            if ($file === '.git' || $file === 'vendor' || $file === 'node_modules') continue;
            process_dir($path);
        } else {
            if (preg_match('/\.(php|js|html)$/', $file)) {
                if ($file === 'update_urls.php') continue;
                process_file($path);
            }
        }
    }
}

function process_file($filepath) {
    if (!is_file($filepath) || !is_readable($filepath)) return;
    $content = file_get_contents($filepath);
    $original = $content;0'
    
    // href="xxx.php" (ignoring URLs starting with http)
    $content = preg_replace('/href="((?!http|mailto|tel)[^"]*?)\.php(\?[^"]*)?"/', 'href="$1$2"', $content);
    $content = preg_replace("/href='((?!http|mailto|tel)[^']*?)\.php(\?[^']*)?'/", "href='$1$2'", $content);
    
    // action="xxx.php"
    $content = preg_replace('/action="((?!http)[^"]*?)\.php(\?[^"]*)?"/', 'action="$1$2"', $content);
    $content = preg_replace("/action='((?!http)[^']*?)\.php(\?[^']*)?'/", "action='$1$2'", $content);
    
    // header("Location: ...")
    $content = preg_replace("/header\(\\s*['\"]Location:\\s*((?!http)[^'\"]*?)\.php(\?[^'\"]*)?['\"]\\s*\)/", "header('Location: $1$2')", $content);
    
    // window.location.href = '...'
    $content = preg_replace("/window\\.location\\.href\\s*=\\s*['\"]((?!http)[^'\"]*?)\\.php(\\?[^'\"]*)?['\"]/", "window.location.href = '$1$2'", $content);
    $content = preg_replace("/window\\.location\\s*=\\s*['\"]((?!http)[^'\"]*?)\\.php(\\?[^'\"]*)?['\"]/", "window.location = '$1$2'", $content);

    // index cleanups
    $content = preg_replace('/href="(\.\.\/)*index"/', 'href="/"', $content);
    $content = preg_replace("/href='(\.\.\/)*index'/", "href='/'", $content);
    $content = preg_replace('/action="(\.\.\/)*index"/', 'action="/"', $content);
    $content = preg_replace("/action='(\.\.\/)*index'/", "action='/'", $content);
    $content = preg_replace("/header\('Location: (\.\.\/)*index'\)/", "header('Location: /')", $content);
    $content = str_replace("window.location.href = 'index'", "window.location.href = '/'", $content);
    $content = str_replace("window.location.href = '../index'", "window.location.href = '/'", $content);
    
    // Header logic
    $content = str_replace("\$current == 'index.php'", "(\$current == 'index.php' || \$current == 'index' || \$current == '' || \$current == '/')", $content);
    $content = str_replace("\$current == 'productos.php'", "(\$current == 'productos.php' || \$current == 'productos')", $content);
    $content = str_replace("\$current == 'servicios.php'", "(\$current == 'servicios.php' || \$current == 'servicios')", $content);
    $content = str_replace("\$current == 'contacto.php'", "(\$current == 'contacto.php' || \$current == 'contacto')", $content);

    // Revert index to index.php if absolutely necessary? No, logic works.

    if ($content !== $original) {
        file_put_contents($filepath, $content);
        echo "Updated $filepath\n";
    }
}

process_dir($root_dir);
echo "Done!\n";
