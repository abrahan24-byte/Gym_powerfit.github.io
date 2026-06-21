<?php
session_start();

require_once dirname(__DIR__) . "/config/database.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$planesMembresia = array(
    "basica" => "Basica",
    "estandar" => "Estandar",
    "premium" => "Premium",
    "vip" => "VIP"
);
$membresiaInicial = isset($_GET["membresia"]) ? trim($_GET["membresia"]) : "";

$nombre = isset($_POST["Nombre"]) ? trim($_POST["Nombre"]) : "";
$correo = isset($_POST["Correo"]) ? trim($_POST["Correo"]) : "";
$password = isset($_POST["Password"]) ? $_POST["Password"] : "";
$membresia = isset($_POST["Membresia"]) ? trim($_POST["Membresia"]) : $membresiaInicial;
$mensaje = "";
$tipoMensaje = "danger";
$formularioEnviado = $_SERVER["REQUEST_METHOD"] === "POST";

if (!isset($planesMembresia[$membresia])) {
    $membresia = "";
}

function longitudTexto($texto) {
    return function_exists("mb_strlen") ? mb_strlen($texto, "UTF-8") : strlen($texto);
}

function correoRegistrado($conexion, $correo) {
    $consulta = $conexion->prepare("SELECT id FROM usuarios WHERE LOWER(correo) = LOWER(:correo) LIMIT 1");
    $consulta->execute(array(":correo" => $correo));

    return $consulta->fetch() !== false;
}

function obtenerMembresiaId($conexion, $codigo) {
    $consulta = $conexion->prepare("SELECT id FROM membresias WHERE codigo = :codigo LIMIT 1");
    $consulta->execute(array(":codigo" => $codigo));
    $membresia = $consulta->fetch();

    return $membresia ? $membresia["id"] : false;
}

if ($formularioEnviado) {
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $mensaje = "La sesion expiro. Recargue la pagina e intente de nuevo.";
    } elseif ($nombre === "" || $correo === "" || $password === "" || $membresia === "") {
        $mensaje = "Debe ingresar nombre, correo electronico, contrasena y membresia.";
    } elseif (longitudTexto($nombre) > 100) {
        $mensaje = "El nombre es demasiado largo.";
    } elseif (longitudTexto($correo) > 255) {
        $mensaje = "El correo es demasiado largo.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Ingrese un correo electronico valido.";
    } elseif (longitudTexto($password) < 8) {
        $mensaje = "La contrasena debe tener al menos 8 caracteres.";
    } elseif (!isset($planesMembresia[$membresia])) {
        $mensaje = "Seleccione una membresia valida.";
    } else {
        try {
            $conexion = obtenerConexion();

            if (correoRegistrado($conexion, $correo)) {
                $mensaje = "Ese correo electronico ya esta registrado.";
            } else {
                $membresiaId = obtenerMembresiaId($conexion, $membresia);

                if (!$membresiaId) {
                    $mensaje = "La membresia seleccionada no existe.";
                } else {
                    $consulta = $conexion->prepare(
                        "INSERT INTO usuarios (nombre, correo, password, membresia_id, estado, fecha_vencimiento)
                         VALUES (:nombre, :correo, :password, :membresia_id, 'activa', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY))"
                    );
                    $consulta->execute(array(
                        ":nombre" => $nombre,
                        ":correo" => $correo,
                        ":password" => password_hash($password, PASSWORD_DEFAULT),
                        ":membresia_id" => $membresiaId
                    ));

                    $mensaje = "Usuario registrado correctamente en la membresia " . $planesMembresia[$membresia] . ". Ahora puede iniciar sesion.";
                    $tipoMensaje = "success";
                    $nombre = "";
                    $correo = "";
                    $password = "";
                }
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
    <title>Sign Up - PowerFit Gym</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/stilo.css">
  </head>

  <body class="auth-page">
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#signupNavbar" aria-controls="signupNavbar" aria-expanded="false">
            <span class="sr-only">Abrir navegaci&oacute;n</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="../index.html#inicio">PowerFit Gym</a>
        </div>

        <div class="collapse navbar-collapse" id="signupNavbar">
          <ul class="nav navbar-nav navbar-right">
            <li><a href="../index.html#inicio">Inicio</a></li>
            <li><a href="../index.html#programas">Programas</a></li>
            <li><a href="../index.html#membresias">Membres&iacute;as</a></li>
            <li><a href="../login/">Login</a></li>
            <li class="active"><a href="../signup/">Sign Up</a></li>
            <li><a href="../index.html#contacto">Contacto</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="auth-wrap">
      <section class="auth-visual auth-visual-signup" aria-label="PowerFit Gym">
        <div class="auth-visual-content">
          <p class="auth-kicker">PowerFit Gym</p>
          <h1>Cree su cuenta</h1>
          <p>Registre sus datos para empezar a gestionar su membres&iacute;a.</p>
        </div>
      </section>

      <section class="auth-card" aria-labelledby="signupTitle">
        <div class="auth-card-header">
          <h2 id="signupTitle">Registro de Clientes</h2>
          <p>Complete el formulario y seleccione su membres&iacute;a.</p>
        </div>

        <?php if ($formularioEnviado) : ?>
          <div class="alert alert-<?php echo htmlspecialchars($tipoMensaje, ENT_QUOTES, "UTF-8"); ?>" role="alert">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
          </div>
        <?php endif; ?>

        <form action="index.php" method="POST" data-validate="cliente">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>">

          <div class="form-group">
            <label for="Nombre">Nombre</label>
            <div class="input-group input-group-lg">
              <span class="input-group-addon"><span class="glyphicon glyphicon-user" aria-hidden="true"></span></span>
              <input name="Nombre" type="text" maxlength="100" class="form-control" id="Nombre" placeholder="Introduce tu nombre" value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, "UTF-8"); ?>" required>
            </div>
          </div>

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
              <input name="Password" type="password" maxlength="128" class="form-control" id="Password" placeholder="Crea una contrase&ntilde;a" required>
            </div>
          </div>

          <div class="form-group">
            <label for="Membresia">Membres&iacute;a</label>
            <div class="input-group input-group-lg">
              <span class="input-group-addon"><span class="glyphicon glyphicon-list" aria-hidden="true"></span></span>
              <select name="Membresia" class="form-control" id="Membresia" required>
                <option value="">Seleccione una membres&iacute;a</option>
                <?php foreach ($planesMembresia as $codigo => $etiqueta) : ?>
                  <option value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, "UTF-8"); ?>" <?php echo $membresia === $codigo ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($etiqueta, ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <button type="submit" class="btn btn-gym btn-lg btn-block">
            <span class="glyphicon glyphicon-user" aria-hidden="true"></span>
            Registrarse
          </button>
        </form>

        <p class="auth-switch">
          &iquest;Ya posee una suscripci&oacute;n?
          <a href="../login/">Iniciar sesi&oacute;n</a>
        </p>
      </section>
    </main>

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../js/main.js"></script>
  </body>
</html>
