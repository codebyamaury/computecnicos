<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Core/bootstrap.php';
// Sesión manejada por bootstrap (DB handler)
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
                // En Vercel no guardamos fotos localmente (filesystem efímero)
                // Usaremos directamente la URL provista por Google.
                $foto_final = $foto;

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
                // Crear token persistente para mantener sesion activa (30 dias)
                $rememberMe->createToken($usuarioNuevo['id']);
                header('Location: index.php');
                exit;
            } else {
                // Usuario existe - actualizar foto y nombre con los datos de Google
                // Intentar descargar y guardar la foto localmente para mayor fiabilidad
                $foto_final = $foto;
                // En Vercel no podemos descargar la foto y guardarla en local (filesystem efímero).
                // Pasamos directamente el enlace de Google a la base de datos.
                $foto_final = $foto;

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
                // Crear token persistente para mantener sesion activa (30 dias)
                $rememberMe->createToken($usuario['id']);
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