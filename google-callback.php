<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Core/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? 'TU_CLIENT_ID'); // Reemplaza por tu Client ID o usa env
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? 'TU_CLIENT_SECRET'); // Reemplaza por tu Client Secret o usa env
$client->setRedirectUri(base_url() . '/google-callback.php');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['access_token'])) {
        $client->setAccessToken($token['access_token']);
        $oauth = new Google\Service\Oauth2($client);
        $userInfo = $oauth->userinfo->get();
        $email = $userInfo->email;
        $nombre = $userInfo->name;
        $foto = $userInfo->picture ?? null;
        try {
            // Buscar usuario por email
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                // Usuario no registrado: crear cuenta automáticamente con Google
                $foto_final = $foto;
                if (!empty($foto)) {
                    $profilesDir = __DIR__ . '/uploads/profiles/';
                    if (!is_dir($profilesDir)) {
                        @mkdir($profilesDir, 0777, true);
                    }
                    // Usar nombre de archivo por email hash para evitar colisiones
                    $filename = 'profile_' . md5($email) . '.jpg';
                    $targetPath = $profilesDir . $filename;
                    try {
                        $imgData = @file_get_contents($foto);
                        if ($imgData !== false) {
                            @file_put_contents($targetPath, $imgData);
                            $foto_final = 'uploads/profiles/' . $filename;
                        }
                    } catch (Exception $e) {
                        $foto_final = $foto;
                    }
                }

                // Generar contraseña aleatoria (requerida por NOT NULL)
                $randomPass = bin2hex(random_bytes(8));
                $hash = password_hash($randomPass, PASSWORD_DEFAULT);

                // Insertar nuevo usuario con datos básicos de Google
                $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, telefono, direccion, password, foto) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$nombre, $email, null, null, $hash, $foto_final]);

                // Recuperar el usuario recién creado
                $stmt2 = $pdo->prepare('SELECT id, nombre, email, rol, foto FROM usuarios WHERE email = ?');
                $stmt2->execute([$email]);
                $usuarioNuevo = $stmt2->fetch();

                // Guardar sesión y mensaje de éxito
                $_SESSION['usuario'] = [
                    'id' => $usuarioNuevo['id'],
                    'nombre' => $usuarioNuevo['nombre'],
                    'email' => $usuarioNuevo['email'],
                    'rol' => $usuarioNuevo['rol'],
                    'foto' => $usuarioNuevo['foto']
                ];
                $_SESSION['login_success'] = '¡Bienvenido, ' . ($usuarioNuevo['nombre'] ?? 'usuario') . '! Tu cuenta fue creada con Google y has iniciado sesión.';
                header('Location: index.php');
                exit;
            } else {
                // Usuario existe - actualizar foto y nombre con los datos de Google
                // Intentar descargar y guardar la foto localmente para mayor fiabilidad
                $foto_final = $foto;
                if (!empty($foto)) {
                    $profilesDir = __DIR__ . '/uploads/profiles/';
                    if (!is_dir($profilesDir)) {
                        @mkdir($profilesDir, 0777, true);
                    }
                    $targetPath = $profilesDir . 'profile_' . $usuario['id'] . '.jpg';
                    // Si hay una foto local anterior distinta, eliminarla
                    $oldFoto = $usuario['foto'] ?? '';
                    $oldLocalPath = (!empty($oldFoto) && strpos($oldFoto, 'uploads/profiles/') === 0) ? (__DIR__ . '/' . $oldFoto) : '';
                    $newRelative = 'uploads/profiles/' . 'profile_' . $usuario['id'] . '.jpg';
                    if ($oldLocalPath && $oldFoto !== $newRelative && is_file($oldLocalPath)) {
                        @unlink($oldLocalPath);
                    }
                    try {
                        $imgData = @file_get_contents($foto);
                        if ($imgData !== false) {
                            @file_put_contents($targetPath, $imgData);
                            // Guardar ruta relativa para uso en HTML
                            $foto_final = 'uploads/profiles/' . 'profile_' . $usuario['id'] . '.jpg';
                        }
                    } catch (Exception $e) {
                        // Si falla la descarga, usamos la URL de Google directamente
                        $foto_final = $foto;
                    }
                }

                $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?, foto=? WHERE id=?');
                $stmt->execute([$nombre, $foto_final, $usuario['id']]);
                $usuario['nombre'] = $nombre;
                $usuario['foto'] = $foto_final;
                
                // Guardar datos en sesión
                $_SESSION['usuario'] = [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'email' => $usuario['email'],
                    'rol' => $usuario['rol'],
                    'foto' => $usuario['foto']
                ];
                // Mensaje de éxito para mostrar toast al volver a la página principal
                $_SESSION['login_success'] = '¡Bienvenido, ' . ($usuario['nombre'] ?? 'usuario') . '! Has iniciado sesión con Google.';
                // Redirigir a la página principal
                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            echo 'Error al iniciar sesión: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        echo 'Error al obtener el token de acceso.';
    }
} else {
    echo 'No autorizado';
}