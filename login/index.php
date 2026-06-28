<?php
session_start();

require_once dirname(__DIR__) . "/config/database.php";

if (!empty($_SESSION["user_id"])) {
    header("Location: ../dashboard/");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$correo = isset($_POST["Correo"]) ? trim($_POST["Correo"]) : "";
$password = isset($_POST["Password"]) ? $_POST["Password"] : "";
$mensaje = "";
$tipoMensaje = "danger";
$formularioEnviado = $_SERVER["REQUEST_METHOD"] === "POST";
$planesMembresia = array(
    "basica" => "Basica",
    "estandar" => "Estandar",
    "premium" => "Premium",
    "vip" => "VIP"
);

function longitudTexto($texto) {
    return function_exists("mb_strlen") ? mb_strlen($texto, "UTF-8") : strlen($texto);
}

function buscarUsuarioPorCorreo($conexion, $correo) {
    $consulta = $conexion->prepare(
        "SELECT u.id, u.nombre, u.correo, u.password, m.codigo AS codigo_membresia, m.nombre AS membresia
         FROM usuarios u
         LEFT JOIN membresias m ON m.id = u.membresia_id
         WHERE LOWER(u.correo) = LOWER(:correo)
         LIMIT 1"
    );

    $consulta->execute(array(":correo" => $correo));

    return $consulta->fetch();
}

if ($formularioEnviado) {
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $mensaje = "La sesion expiro. Recargue la pagina e intente de nuevo.";
    } elseif ($correo === "" || $password === "") {
        $mensaje = "Debe ingresar correo electronico y contrasena.";
    } elseif (longitudTexto($correo) > 255) {
        $mensaje = "El correo es demasiado largo.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Ingrese un correo electronico valido.";
    } elseif (longitudTexto($password) < 8) {
        $mensaje = "La contrasena debe tener al menos 8 caracteres.";
    } else {
        try {
            $conexion = obtenerConexion();
            $usuario = buscarUsuarioPorCorreo($conexion, $correo);

            if ($usuario && !empty($usuario["password"]) && password_verify($password, $usuario["password"])) {
                $codigoMembresia = $usuario["codigo_membresia"];
                $membresia = isset($planesMembresia[$codigoMembresia]) ? $planesMembresia[$codigoMembresia] : $usuario["membresia"];

                session_regenerate_id(true);
                $_SESSION["user_id"] = $usuario["id"];
                $_SESSION["user_name"] = $usuario["nombre"];
                $_SESSION["membership"] = $membresia;

                header("Location: ../dashboard/");
                exit;
            } else {
                $mensaje = "Correo o contrasena incorrectos.";
            }
        } catch (Exception $e) {
            header("Location: error.html");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - PowerFit Gym</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/stilo.css">
  </head>

  <body class="auth-page">
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#loginNavbar" aria-controls="loginNavbar" aria-expanded="false">
            <span class="sr-only">Abrir navegaci&oacute;n</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="../index.html#inicio">PowerFit Gym</a>
        </div>

        <div class="collapse navbar-collapse" id="loginNavbar">
          <ul class="nav navbar-nav navbar-right">
            <li><a href="../index.html#inicio">Inicio</a></li>
            <li><a href="../index.html#programas">Programas</a></li>
            <li><a href="../index.html#membresias">Membres&iacute;as</a></li>
            <li class="active"><a href="../login/">Login</a></li>
            <li><a href="../signup/">Sign Up</a></li>
            <li><a href="../admin/">Admin</a></li>
            <li><a href="../index.html#contacto">Contacto</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="auth-wrap">
      <section class="auth-visual" aria-label="PowerFit Gym">
        <div class="auth-visual-content">
          <p class="auth-kicker">PowerFit Gym</p>
          <h1>Bienvenido de nuevo</h1>
          <p>Acceda a su cuenta para continuar con el control de su membres&iacute;a.</p>
        </div>
      </section>

      <section class="auth-card" aria-labelledby="loginTitle">
        <div class="auth-card-header">
          <h2 id="loginTitle">Login de Clientes</h2>
          <p>Ingrese con su correo electr&oacute;nico y contrase&ntilde;a registrados.</p>
        </div>

        <?php if ($formularioEnviado) : ?>
          <div class="alert alert-<?php echo htmlspecialchars($tipoMensaje, ENT_QUOTES, "UTF-8"); ?>" role="alert">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
          </div>
        <?php endif; ?>

        <form action="index.php" method="POST" data-validate="cliente">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>">

          <div class="form-group">
            <label for="Correo">Correo electr&oacute;nico</label>
            <div class="input-group input-group-lg">
              <span class="input-group-addon"><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span></span>
              <input name="Correo" type="email" maxlength="255" class="form-control" id="Correo" placeholder="Introduce tu correo electr&oacute;nico" value="<?php echo htmlspecialchars($correo, ENT_QUOTES, "UTF-8"); ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="Password">Contrase&ntilde;a</label>
            <div class="input-group input-group-lg">
              <span class="input-group-addon"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span></span>
              <input name="Password" type="password" maxlength="128" class="form-control" id="Password" placeholder="Introduce tu contrase&ntilde;a" required>
            </div>
          </div>

          <button type="submit" class="btn btn-gym btn-lg btn-block">
            <span class="glyphicon glyphicon-log-in" aria-hidden="true"></span>
            Entrar
          </button>
        </form>

        <p class="auth-switch">
          <a href="recuperar.php">&iquest;Olvid&oacute; su contrase&ntilde;a?</a>
        </p>

        <p class="auth-switch auth-switch-secondary">
          &iquest;Todav&iacute;a no tiene cuenta?
          <a href="../signup/">Crear cuenta</a>
        </p>
      </section>
    </main>

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../js/main.js"></script>
  </body>
</html>
