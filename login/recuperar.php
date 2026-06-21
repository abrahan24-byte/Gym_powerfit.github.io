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
$mensaje = "";
$tipoMensaje = "danger";
$enlaceRecuperacion = "";
$formularioEnviado = $_SERVER["REQUEST_METHOD"] === "POST";

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function longitudTexto($texto) {
    return function_exists("mb_strlen") ? mb_strlen($texto, "UTF-8") : strlen($texto);
}

function buscarUsuarioRecuperacion($conexion, $correo) {
    $consulta = $conexion->prepare("SELECT id, nombre, correo FROM usuarios WHERE LOWER(correo) = LOWER(:correo) LIMIT 1");
    $consulta->execute(array(":correo" => $correo));

    return $consulta->fetch();
}

if ($formularioEnviado) {
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $mensaje = "La sesion expiro. Recargue la pagina e intente de nuevo.";
    } elseif ($correo === "") {
        $mensaje = "Ingrese su correo electronico.";
    } elseif (longitudTexto($correo) > 255) {
        $mensaje = "El correo es demasiado largo.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Ingrese un correo electronico valido.";
    } else {
        try {
            $conexion = obtenerConexion();
            $usuario = buscarUsuarioRecuperacion($conexion, $correo);

            if ($usuario) {
                $token = bin2hex(random_bytes(32));
                $consulta = $conexion->prepare(
                    "INSERT INTO password_resets (usuario_id, token, fecha_expiracion)
                     VALUES (:usuario_id, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
                );
                $consulta->execute(array(
                    ":usuario_id" => $usuario["id"],
                    ":token" => $token
                ));

                $baseUrl = dirname($_SERVER["SCRIPT_NAME"]);
                $enlaceRecuperacion = $baseUrl . "/restablecer.php?token=" . urlencode($token);
            }

            $mensaje = "Si el correo existe, se genero un enlace para restablecer la contrasena.";
            $tipoMensaje = "success";
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
    <title>Recuperar contrase&ntilde;a - PowerFit Gym</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/stilo.css">
  </head>

  <body class="auth-page">
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <div class="navbar-header">
          <a class="navbar-brand" href="../index.html#inicio">PowerFit Gym</a>
        </div>
      </div>
    </nav>

    <main class="auth-wrap">
      <section class="auth-visual" aria-label="PowerFit Gym">
        <div class="auth-visual-content">
          <p class="auth-kicker">PowerFit Gym</p>
          <h1>Recupere su acceso</h1>
          <p>Solicite un enlace para crear una nueva contrase&ntilde;a de usuario.</p>
        </div>
      </section>

      <section class="auth-card" aria-labelledby="recoverTitle">
        <div class="auth-card-header">
          <h2 id="recoverTitle">Recuperar contrase&ntilde;a</h2>
          <p>Ingrese el correo electr&oacute;nico registrado en su cuenta.</p>
        </div>

        <?php if ($formularioEnviado) : ?>
          <div class="alert alert-<?php echo escapar($tipoMensaje); ?>" role="alert">
            <?php echo escapar($mensaje); ?>
          </div>
        <?php endif; ?>

        <?php if ($enlaceRecuperacion !== "") : ?>
          <div class="reset-link-box">
            <p>Enlace de prueba:</p>
            <a href="<?php echo escapar($enlaceRecuperacion); ?>"><?php echo escapar($enlaceRecuperacion); ?></a>
          </div>
        <?php endif; ?>

        <form action="recuperar.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo escapar($csrfToken); ?>">

          <div class="form-group">
            <label for="Correo">Correo electr&oacute;nico</label>
            <div class="input-group input-group-lg">
              <span class="input-group-addon"><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span></span>
              <input name="Correo" type="email" maxlength="255" class="form-control" id="Correo" placeholder="Introduce tu correo electr&oacute;nico" value="<?php echo escapar($correo); ?>" required>
            </div>
          </div>

          <button type="submit" class="btn btn-gym btn-lg btn-block">
            <span class="glyphicon glyphicon-send" aria-hidden="true"></span>
            Generar enlace
          </button>
        </form>

        <p class="auth-switch">
          <a href="../login/">Volver al login</a>
        </p>
      </section>
    </main>

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../js/main.js"></script>
  </body>
</html>
