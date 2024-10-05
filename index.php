<?php
// Conexión a la base de datos
$pdo = new PDO('mysql:host=localhost', 'root', '');

// Crear la base de datos si no existe
$pdo->exec("CREATE DATABASE IF NOT EXISTS social_natio");
$pdo->exec("USE social_natio");

// Crear la tabla de usuarios
$pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Crear la tabla de publicaciones
$pdo->exec("CREATE TABLE IF NOT EXISTS publicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    contenido TEXT NOT NULL,
    imagen VARCHAR(255) DEFAULT NULL,
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
)");

// Crear la tabla de "me gusta"
$pdo->exec("CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    publicacion_id INT NOT NULL,
    fecha_like TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (publicacion_id) REFERENCES publicaciones(id) ON DELETE CASCADE
)");

// Crear la tabla de amigos (para gestionar solicitudes y bloqueos)
$pdo->exec("CREATE TABLE IF NOT EXISTS amigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    amigo_id INT NOT NULL,
    estado ENUM('pendiente', 'aceptado', 'bloqueado') DEFAULT 'pendiente',
    fecha_amistad TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (amigo_id) REFERENCES usuarios(id) ON DELETE CASCADE
)");

session_start();

// Función para registrar usuario
if (isset($_POST['registro'])) {
    $nombre_usuario = $_POST['nombre_usuario'];
    $email = $_POST['email'];
    $clave = password_hash($_POST['clave'], PASSWORD_BCRYPT);
    
    // Insertar usuario en la base de datos
    $sql = "INSERT INTO usuarios (nombre_usuario, email, clave) VALUES (:nombre_usuario, :email, :clave)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['nombre_usuario' => $nombre_usuario, 'email' => $email, 'clave' => $clave]);
    echo "Registro exitoso, ¡ahora puedes iniciar sesión!";
}

// Función para iniciar sesión
if (isset($_POST['login'])) {
    $nombre_usuario = $_POST['nombre_usuario'];
    $clave = $_POST['clave'];

    // Buscar el usuario
    $sql = "SELECT * FROM usuarios WHERE nombre_usuario = :nombre_usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['nombre_usuario' => $nombre_usuario]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($clave, $usuario['clave'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
        echo "Inicio de sesión exitoso, bienvenido " . $usuario['nombre_usuario'];
    } else {
        echo "Usuario o contraseña incorrectos";
    }
}

// Función para publicar en el muro
if (isset($_POST['publicar'])) {
    if (isset($_SESSION['usuario_id'])) {
        $contenido = $_POST['contenido'];
        $imagen = '';

        // Subir imagen si hay una
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $nombre_imagen = $_FILES['imagen']['name'];
            $ruta = 'uploads/' . $nombre_imagen;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta);
            $imagen = $ruta;
        }

        // Insertar publicación
        $sql = "INSERT INTO publicaciones (usuario_id, contenido, imagen) VALUES (:usuario_id, :contenido, :imagen)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $_SESSION['usuario_id'], 'contenido' => $contenido, 'imagen' => $imagen]);
        echo "Publicación realizada";
    } else {
        echo "Debes iniciar sesión para publicar";
    }
}

// Función para dar me gusta a una publicación
if (isset($_GET['like'])) {
    if (isset($_SESSION['usuario_id'])) {
        $publicacion_id = $_GET['like'];
        $sql = "INSERT INTO likes (usuario_id, publicacion_id) VALUES (:usuario_id, :publicacion_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $_SESSION['usuario_id'], 'publicacion_id' => $publicacion_id]);
        echo "Me gusta agregado";
    }
}

// Función para cambiar el avatar
if (isset($_POST['cambiar_avatar'])) {
    if (isset($_SESSION['usuario_id'])) {
        $avatar = '';

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $nombre_avatar = $_FILES['avatar']['name'];
            $ruta = 'avatars/' . $nombre_avatar;
            move_uploaded_file($_FILES['avatar']['tmp_name'], $ruta);
            $avatar = $ruta;

            // Actualizar avatar en la base de datos
            $sql = "UPDATE usuarios SET avatar = :avatar WHERE id = :usuario_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['avatar' => $avatar, 'usuario_id' => $_SESSION['usuario_id']]);
            echo "Avatar cambiado";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Natio</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Bienvenido a Social Natio</h1>

    <!-- Formulario de registro -->
    <h2>Registro</h2>
    <form method="POST" action="index.php">
        <input type="text" name="nombre_usuario" placeholder="Nombre de usuario" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="clave" placeholder="Contraseña" required>
        <button type="submit" name="registro">Registrar</button>
    </form>

    <!-- Formulario de login -->
    <h2>Iniciar Sesión</h2>
    <form method="POST" action="index.php">
        <input type="text" name="nombre_usuario" placeholder="Nombre de usuario" required>
        <input type="password" name="clave" placeholder="Contraseña" required>
        <button type="submit" name="login">Iniciar Sesión</button>
    </form>

    <!-- Muro de publicaciones -->
    <?php if (isset($_SESSION['usuario_id'])): ?>
        <h2>Muro de Publicaciones</h2>
        <form method="POST" action="index.php" enctype="multipart/form-data">
            <textarea name="contenido" placeholder="Escribe algo..." required></textarea>
            <input type="file" name="imagen">
            <button type="submit" name="publicar">Publicar</button>
        </form>

        <!-- Mostrar publicaciones -->
        <?php
        $sql = "SELECT publicaciones.*, usuarios.nombre_usuario FROM publicaciones JOIN usuarios ON publicaciones.usuario_id = usuarios.id ORDER BY publicaciones.fecha_publicacion DESC";
        $stmt = $pdo->query($sql);
        $publicaciones = $stmt->fetchAll();

        foreach ($publicaciones as $publicacion):
        ?>
            <div class="publicacion">
                <p><strong>@<?= $publicacion['nombre_usuario']; ?>:</strong> <?= $publicacion['contenido']; ?></p>
                <?php if ($publicacion['imagen']): ?>
                    <img src="<?= $publicacion['imagen']; ?>" alt="Imagen de publicación" width="200">
                <?php endif; ?>
                <a href="index.php?like=<?= $publicacion['id']; ?>">Me gusta</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
