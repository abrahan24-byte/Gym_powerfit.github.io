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
$token = isset($_GET["token"]) ? trim($_GET["token"]) : (isset($_POST["token"]) ? trim($_POST["token"]) : "");
$password = isset($_POST["Password"]) ? $_POST["Password"] : "";
$confirmarPassword = isset($_POST["ConfirmarPassword"]) ? $_POST["ConfirmarPassword"] : "";
$mensaje = "";
$tipoMensaje = "danger";
$tokenValido = false;
$passwordActualizada = false;
$formularioEnviado = $_SERVER["REQUEST_METHOD"] === "POST";
$reset = false;

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function longitudTexto($texto) {
    return function_exists("mb_strlen") ? mb_strlen($texto, "UTF-8") : strlen($texto);
}

function buscarResetValido($conexion, $token) {
    $consulta = $conexion->prepare(
        "SELECT pr.id, pr.usuario_id, u.nombre
         FROM password_resets pr
         INNER JOIN usuarios u ON u.id = pr.usuario_id
         WHERE pr.token = :token
           AND pr.usado = 0
           AND pr.fecha_expiracion >= NOW()
         LIMIT 1"
    );
    $consulta->execute(array(":token" => $token));

    return $consulta->fetch();
}

try {
    $conexion = obtenerConexion();

    if ($token !== "") {
        $reset = buscarResetValido($conexion, $token);
        $tokenValido = $reset !== false;
    }

    if (!$tokenValido) {
        $mensaje = "El enlace de recuperacion no es valido o ya expiro.";
    } elseif ($formularioEnviado) {
        if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
            $mensaje = "La sesion expiro. Recargue la pagina e intente de nuevo.";
        } elseif ($password === "" || $confirmarPassword === "") {
            $mensaje = "Ingrese y confirme su nueva contrasena.";
        } elseif (longitudTexto($password) < 8) {
            $mensaje = "La contrasena debe tener al menos 8 caracteres.";
        } elseif ($password !== $confirmarPassword) {
            $mensaje = "Las contrasenas no coinciden.";
        } else {
            $conexion->beginTransaction();

            $consultaUsuario = $conexion->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
            $consultaUsuario->execute(array(
                ":password" => password_hash($password, PASSWORD_DEFAULT),
                ":id" => $reset["usuario_id"]
            ));

            $consultaReset = $conexion->prepare("UPDATE password_resets SET usado = 1 WHERE id = :id");
            $consultaReset->execute(array(":id" => $reset["id"]));

            $conexion->commit();

            $mensaje = "Contrasena actualizada correctamente. Ahora puede iniciar sesion.";
            $tipoMensaje = "success";
            $passwordActualizada = true;
        }
    }
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    header("Location: error.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contrase&ntilde;a - PowerFit Gym</title>

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
          <h1>Nueva contrase&ntilde;a</h1>
          <p>Cree una contrase&ntilde;a segura para volver a entrar a su cuenta.</p>
        </div>
      </section>

      <section class="auth-card" aria-labelledby="resetTitle">
        <div class="auth-card-header">
          <h2 id="resetTitle">Restablecer contrase&ntilde;a</h2>
          <p>La nueva contrase&ntilde;a debe tener al menos 8 caracteres.</p>
        </div>

        <?php if ($mensaje !== "") : ?>
          <div class="alert alert-<?php echo escapar($tipoMensaje); ?>" role="alert">
            <?php echo escapar($mensaje); ?>
          </div>
        <?php endif; ?>

        <?php if ($tokenValido && !$passwordActualizada) : ?>
          <form action="restablecer.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrfToken); ?>">
            <input type="hidden" name="token" value="<?php echo escapar($token); ?>">

            <div class="form-group">
              <label for="Password">Nueva contrase&ntilde;a</label>
              <div class="input-group input-group-lg">
                <span class="input-group-addon"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span></span>
                <input name="Password" type="password" maxlength="128" class="form-control" id="Password" placeholder="Crea una nueva contrase&ntilde;a" required>
              </div>
            </div>

            <div class="form-group">
              <label for="ConfirmarPassword">Confirmar contrase&ntilde;a</label>
              <div class="input-group input-group-lg">
                <span class="input-group-addon"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span></span>
                <input name="ConfirmarPassword" type="password" maxlength="128" class="form-control" id="ConfirmarPassword" placeholder="Repite la contrase&ntilde;a" required>
              </div>
            </div>

            <button type="submit" class="btn btn-gym btn-lg btn-block">
              <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>
              Guardar contrase&ntilde;a
            </button>
          </form>
        <?php endif; ?>

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
