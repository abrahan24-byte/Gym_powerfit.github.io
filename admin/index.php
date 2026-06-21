<?php
session_start();

require_once dirname(__DIR__) . "/config/database.php";

define("ADMIN_CORREO", "admin@powerfitgym.com");
define("ADMIN_PASSWORD", "Admin12345");

if (empty($_SESSION["admin_csrf_token"])) {
    $_SESSION["admin_csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["admin_csrf_token"];
$mensaje = "";
$tipoMensaje = "danger";
$formularioEnviado = $_SERVER["REQUEST_METHOD"] === "POST";

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function fechaAdmin($fecha) {
    if (!$fecha) {
        return "No disponible";
    }

    return date("d/m/Y", strtotime($fecha));
}

function obtenerMembresias($conexion) {
    return $conexion->query("SELECT id, codigo, nombre, precio FROM membresias ORDER BY precio ASC")->fetchAll();
}

function obtenerUsuarios($conexion) {
    return $conexion->query(
        "SELECT u.id, u.nombre, u.correo, u.estado, u.fecha_registro, u.fecha_vencimiento,
                m.id AS membresia_id, m.codigo AS codigo_membresia, m.nombre AS membresia, m.precio
         FROM usuarios u
         INNER JOIN membresias m ON m.id = u.membresia_id
         ORDER BY u.fecha_registro DESC"
    )->fetchAll();
}

function obtenerPagos($conexion) {
    return $conexion->query(
        "SELECT p.id, p.monto, p.metodo, p.estado, p.referencia, p.fecha_pago,
                u.nombre AS usuario, u.correo,
                m.nombre AS membresia
         FROM pagos p
         INNER JOIN usuarios u ON u.id = p.usuario_id
         INNER JOIN membresias m ON m.id = p.membresia_id
         ORDER BY p.fecha_pago DESC
         LIMIT 25"
    )->fetchAll();
}

function obtenerResumenPagos($conexion) {
    $resumen = array(
        "aprobados" => 0,
        "pendientes" => 0,
        "rechazados" => 0
    );

    $consulta = $conexion->query(
        "SELECT
            COALESCE(SUM(CASE WHEN estado = 'aprobado' THEN monto ELSE 0 END), 0) AS aprobados,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) AS rechazados
         FROM pagos"
    );
    $fila = $consulta->fetch();

    if ($fila) {
        $resumen["aprobados"] = (float) $fila["aprobados"];
        $resumen["pendientes"] = (int) $fila["pendientes"];
        $resumen["rechazados"] = (int) $fila["rechazados"];
    }

    return $resumen;
}

function obtenerMembresiaPorId($conexion, $membresiaId) {
    $consulta = $conexion->prepare("SELECT id, precio FROM membresias WHERE id = :id LIMIT 1");
    $consulta->execute(array(":id" => $membresiaId));

    return $consulta->fetch();
}

function obtenerResumen($usuarios) {
    $resumen = array(
        "total" => count($usuarios),
        "activas" => 0,
        "vencidas" => 0,
        "ingreso" => 0
    );

    foreach ($usuarios as $usuario) {
        $vencida = strtotime($usuario["fecha_vencimiento"]) < strtotime(date("Y-m-d"));

        if ($usuario["estado"] === "activa" && !$vencida) {
            $resumen["activas"]++;
            $resumen["ingreso"] += (float) $usuario["precio"];
        } else {
            $resumen["vencidas"]++;
        }
    }

    return $resumen;
}

function etiquetaPago($estado) {
    if ($estado === "aprobado") {
        return "success";
    }

    if ($estado === "rechazado") {
        return "danger";
    }

    return "warning";
}

try {
    $conexion = obtenerConexion();

    if ($formularioEnviado && isset($_POST["admin_login"])) {
        $correo = isset($_POST["Correo"]) ? trim($_POST["Correo"]) : "";
        $password = isset($_POST["Password"]) ? $_POST["Password"] : "";

        if (!isset($_POST["csrf_token"]) || !hash_equals($csrfToken, $_POST["csrf_token"])) {
            $mensaje = "La sesion expiro. Recargue la pagina e intente de nuevo.";
        } elseif (strtolower($correo) === strtolower(ADMIN_CORREO) && $password === ADMIN_PASSWORD) {
            session_regenerate_id(true);
            $_SESSION["admin_auth"] = true;
            $_SESSION["admin_name"] = "Administrador";
            header("Location: index.php");
            exit;
        } else {
            $mensaje = "Credenciales de administrador incorrectas.";
        }
    }

    if (!empty($_SESSION["admin_auth"]) && $formularioEnviado && isset($_POST["actualizar_usuario"])) {
        if (!isset($_POST["csrf_token"]) || !hash_equals($csrfToken, $_POST["csrf_token"])) {
            $mensaje = "La sesion expiro. Recargue la pagina e intente de nuevo.";
        } else {
            $usuarioId = isset($_POST["usuario_id"]) ? (int) $_POST["usuario_id"] : 0;
            $membresiaId = isset($_POST["membresia_id"]) ? (int) $_POST["membresia_id"] : 0;
            $estado = isset($_POST["estado"]) ? trim($_POST["estado"]) : "";
            $renovar = isset($_POST["renovar"]);

            if ($usuarioId <= 0 || $membresiaId <= 0 || !in_array($estado, array("activa", "suspendida"), true)) {
                $mensaje = "Revise los datos del usuario antes de guardar.";
            } else {
                $sql = "UPDATE usuarios SET membresia_id = :membresia_id, estado = :estado";
                $params = array(
                    ":membresia_id" => $membresiaId,
                    ":estado" => $estado,
                    ":id" => $usuarioId
                );

                if ($renovar) {
                    $sql .= ", fecha_vencimiento = DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)";
                }

                $sql .= " WHERE id = :id";
                $consulta = $conexion->prepare($sql);
                $consulta->execute($params);

                $mensaje = "Usuario actualizado correctamente.";
                $tipoMensaje = "success";
            }
        }
    }

    if (!empty($_SESSION["admin_auth"]) && $formularioEnviado && isset($_POST["registrar_pago"])) {
        if (!isset($_POST["csrf_token"]) || !hash_equals($csrfToken, $_POST["csrf_token"])) {
            $mensaje = "La sesion expiro. Recargue la pagina e intente de nuevo.";
        } else {
            $usuarioId = isset($_POST["usuario_id"]) ? (int) $_POST["usuario_id"] : 0;
            $membresiaId = isset($_POST["membresia_id"]) ? (int) $_POST["membresia_id"] : 0;
            $metodo = isset($_POST["metodo"]) ? trim($_POST["metodo"]) : "";
            $estadoPago = isset($_POST["estado_pago"]) ? trim($_POST["estado_pago"]) : "";
            $referencia = isset($_POST["referencia"]) ? trim($_POST["referencia"]) : "";
            $metodosValidos = array("efectivo", "transferencia", "tarjeta");
            $estadosValidos = array("pendiente", "aprobado", "rechazado");
            $membresiaPago = obtenerMembresiaPorId($conexion, $membresiaId);

            if ($usuarioId <= 0 || !$membresiaPago || !in_array($metodo, $metodosValidos, true) || !in_array($estadoPago, $estadosValidos, true)) {
                $mensaje = "Revise los datos del pago antes de guardar.";
            } elseif (strlen($referencia) > 100) {
                $mensaje = "La referencia del pago es demasiado larga.";
            } else {
                $conexion->beginTransaction();

                $consultaPago = $conexion->prepare(
                    "INSERT INTO pagos (usuario_id, membresia_id, monto, metodo, estado, referencia)
                     VALUES (:usuario_id, :membresia_id, :monto, :metodo, :estado, :referencia)"
                );
                $consultaPago->execute(array(
                    ":usuario_id" => $usuarioId,
                    ":membresia_id" => $membresiaId,
                    ":monto" => $membresiaPago["precio"],
                    ":metodo" => $metodo,
                    ":estado" => $estadoPago,
                    ":referencia" => $referencia !== "" ? $referencia : null
                ));

                if ($estadoPago === "aprobado") {
                    $consultaUsuario = $conexion->prepare(
                        "UPDATE usuarios
                         SET membresia_id = :membresia_id,
                             estado = 'activa',
                             fecha_vencimiento = DATE_ADD(GREATEST(CURRENT_DATE, COALESCE(fecha_vencimiento, CURRENT_DATE)), INTERVAL 30 DAY)
                         WHERE id = :usuario_id"
                    );
                    $consultaUsuario->execute(array(
                        ":membresia_id" => $membresiaId,
                        ":usuario_id" => $usuarioId
                    ));
                }

                $conexion->commit();

                $mensaje = $estadoPago === "aprobado" ? "Pago aprobado y membresia renovada correctamente." : "Pago registrado correctamente.";
                $tipoMensaje = "success";
            }
        }
    }

    $membresias = obtenerMembresias($conexion);
    $usuarios = obtenerUsuarios($conexion);
    $pagos = obtenerPagos($conexion);
    $resumen = obtenerResumen($usuarios);
    $resumenPagos = obtenerResumenPagos($conexion);
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $membresias = array();
    $usuarios = array();
    $pagos = array();
    $resumen = array("total" => 0, "activas" => 0, "vencidas" => 0, "ingreso" => 0);
    $resumenPagos = array("aprobados" => 0, "pendientes" => 0, "rechazados" => 0);
    $mensaje = "No se pudo cargar el panel administrativo.";
}

$adminAutenticado = !empty($_SESSION["admin_auth"]);
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - PowerFit Gym</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/stilo.css">
  </head>

  <body class="<?php echo $adminAutenticado ? 'dashboard-page' : 'auth-page'; ?>">
    <?php if (!$adminAutenticado) : ?>
      <main class="auth-wrap admin-login-wrap">
        <section class="auth-visual admin-visual" aria-label="Administraci&oacute;n PowerFit Gym">
          <div class="auth-visual-content">
            <p class="auth-kicker">PowerFit Gym</p>
            <h1>Panel administrativo</h1>
            <p>Acceso interno para gestionar clientes y membres&iacute;as.</p>
          </div>
        </section>

        <section class="auth-card" aria-labelledby="adminLoginTitle">
          <div class="auth-card-header">
            <h2 id="adminLoginTitle">Login de administrador</h2>
            <p>Ingrese las credenciales internas del gimnasio.</p>
          </div>

          <?php if ($formularioEnviado && $mensaje !== "") : ?>
            <div class="alert alert-<?php echo escapar($tipoMensaje); ?>" role="alert"><?php echo escapar($mensaje); ?></div>
          <?php endif; ?>

          <form action="index.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrfToken); ?>">
            <input type="hidden" name="admin_login" value="1">

            <div class="form-group">
              <label for="Correo">Correo administrador</label>
              <div class="input-group input-group-lg">
                <span class="input-group-addon"><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span></span>
                <input name="Correo" type="email" class="form-control" id="Correo" placeholder="admin@powerfitgym.com" required>
              </div>
            </div>

            <div class="form-group">
              <label for="Password">Contrase&ntilde;a</label>
              <div class="input-group input-group-lg">
                <span class="input-group-addon"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span></span>
                <input name="Password" type="password" class="form-control" id="Password" placeholder="Contrase&ntilde;a" required>
              </div>
            </div>

            <button type="submit" class="btn btn-gym btn-lg btn-block">
              <span class="glyphicon glyphicon-log-in" aria-hidden="true"></span>
              Entrar al panel
            </button>
          </form>
        </section>
      </main>
    <?php else : ?>
      <nav class="navbar navbar-default">
        <div class="container-fluid">
          <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false">
              <span class="sr-only">Abrir navegaci&oacute;n</span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php">PowerFit Admin</a>
          </div>

          <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="nav navbar-nav navbar-right">
              <li class="active"><a href="index.php">Clientes</a></li>
              <li><a href="../dashboard/">Dashboard cliente</a></li>
              <li><a href="../index.html#inicio">Sitio</a></li>
              <li><a href="logout.php">Cerrar sesi&oacute;n</a></li>
            </ul>
          </div>
        </div>
      </nav>

      <main class="dashboard-wrap">
        <section class="dashboard-hero admin-hero">
          <div class="container">
            <p class="dashboard-kicker">Administraci&oacute;n</p>
            <h1>Panel de clientes</h1>
            <p>Controle membres&iacute;as, renovaciones y estados de los usuarios registrados.</p>
          </div>
        </section>

        <section class="container dashboard-content">
          <?php if ($mensaje !== "") : ?>
            <div class="alert alert-<?php echo escapar($tipoMensaje); ?>" role="alert"><?php echo escapar($mensaje); ?></div>
          <?php endif; ?>

          <div class="row dashboard-metrics">
            <div class="col-md-3 col-sm-6">
              <article class="dashboard-card metric-card">
                <span class="glyphicon glyphicon-user" aria-hidden="true"></span>
                <p>Total clientes</p>
                <h2><?php echo escapar($resumen["total"]); ?></h2>
              </article>
            </div>
            <div class="col-md-3 col-sm-6">
              <article class="dashboard-card metric-card">
                <span class="glyphicon glyphicon-ok-circle" aria-hidden="true"></span>
                <p>Activas</p>
                <h2><?php echo escapar($resumen["activas"]); ?></h2>
              </article>
            </div>
            <div class="col-md-3 col-sm-6">
              <article class="dashboard-card metric-card">
                <span class="glyphicon glyphicon-alert" aria-hidden="true"></span>
                <p>Vencidas/suspendidas</p>
                <h2><?php echo escapar($resumen["vencidas"]); ?></h2>
              </article>
            </div>
            <div class="col-md-3 col-sm-6">
              <article class="dashboard-card metric-card">
                <span class="glyphicon glyphicon-usd" aria-hidden="true"></span>
                <p>Ingreso estimado</p>
                <h2>$<?php echo escapar(number_format($resumen["ingreso"], 2)); ?></h2>
              </article>
            </div>
          </div>

          <div class="row">
            <div class="col-md-5">
              <article class="dashboard-card admin-payment-card">
                <div class="dashboard-card-header">
                  <div>
                    <p>Pagos</p>
                    <h3>Registrar pago</h3>
                  </div>
                </div>

                <form action="index.php" method="POST" class="admin-payment-form">
                  <input type="hidden" name="csrf_token" value="<?php echo escapar($csrfToken); ?>">
                  <input type="hidden" name="registrar_pago" value="1">

                  <div class="form-group">
                    <label for="PagoUsuario">Cliente</label>
                    <select name="usuario_id" class="form-control" id="PagoUsuario" required>
                      <option value="">Seleccione un cliente</option>
                      <?php foreach ($usuarios as $usuario) : ?>
                        <option value="<?php echo escapar($usuario["id"]); ?>">
                          <?php echo escapar($usuario["nombre"]); ?> - <?php echo escapar($usuario["correo"]); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="PagoMembresia">Membres&iacute;a pagada</label>
                    <select name="membresia_id" class="form-control" id="PagoMembresia" required>
                      <option value="">Seleccione una membres&iacute;a</option>
                      <?php foreach ($membresias as $membresia) : ?>
                        <option value="<?php echo escapar($membresia["id"]); ?>">
                          <?php echo escapar($membresia["nombre"]); ?> - $<?php echo escapar(number_format((float) $membresia["precio"], 2)); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="admin-payment-grid">
                    <div class="form-group">
                      <label for="PagoMetodo">M&eacute;todo</label>
                      <select name="metodo" class="form-control" id="PagoMetodo" required>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label for="PagoEstado">Estado</label>
                      <select name="estado_pago" class="form-control" id="PagoEstado" required>
                        <option value="aprobado">Aprobado</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="rechazado">Rechazado</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="PagoReferencia">Referencia</label>
                    <input name="referencia" type="text" maxlength="100" class="form-control" id="PagoReferencia" placeholder="Numero de recibo o transferencia">
                  </div>

                  <button type="submit" class="btn btn-gym btn-block">
                    <span class="glyphicon glyphicon-credit-card" aria-hidden="true"></span>
                    Guardar pago
                  </button>
                </form>
              </article>
            </div>

            <div class="col-md-7">
              <article class="dashboard-card">
                <div class="dashboard-card-header">
                  <div>
                    <p>Resumen de pagos</p>
                    <h3>Movimientos registrados</h3>
                  </div>
                </div>

                <div class="payment-summary">
                  <div>
                    <span class="glyphicon glyphicon-ok-circle" aria-hidden="true"></span>
                    <p>Aprobados</p>
                    <strong>$<?php echo escapar(number_format($resumenPagos["aprobados"], 2)); ?></strong>
                  </div>
                  <div>
                    <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
                    <p>Pendientes</p>
                    <strong><?php echo escapar($resumenPagos["pendientes"]); ?></strong>
                  </div>
                  <div>
                    <span class="glyphicon glyphicon-remove-circle" aria-hidden="true"></span>
                    <p>Rechazados</p>
                    <strong><?php echo escapar($resumenPagos["rechazados"]); ?></strong>
                  </div>
                </div>
              </article>
            </div>
          </div>

          <article class="dashboard-card admin-table-card">
            <div class="dashboard-card-header">
              <div>
                <p>Usuarios registrados</p>
                <h3>Gestión de membres&iacute;as</h3>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-striped admin-table">
                <thead>
                  <tr>
                    <th>Cliente</th>
                    <th>Membres&iacute;a</th>
                    <th>Estado</th>
                    <th>Vencimiento</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($usuarios) === 0) : ?>
                    <tr>
                      <td colspan="5" class="text-center">Todav&iacute;a no hay usuarios registrados.</td>
                    </tr>
                  <?php endif; ?>

                  <?php foreach ($usuarios as $usuario) : ?>
                    <?php
                      $vencida = strtotime($usuario["fecha_vencimiento"]) < strtotime(date("Y-m-d"));
                      $estadoVisible = $usuario["estado"] === "activa" && !$vencida ? "Activa" : ($usuario["estado"] === "suspendida" ? "Suspendida" : "Vencida");
                      $labelEstado = $estadoVisible === "Activa" ? "success" : "danger";
                    ?>
                    <tr>
                      <td>
                        <strong><?php echo escapar($usuario["nombre"]); ?></strong>
                        <span><?php echo escapar($usuario["correo"]); ?></span>
                      </td>
                      <td>
                        <?php echo escapar($usuario["membresia"]); ?><br>
                        <small>$<?php echo escapar(number_format((float) $usuario["precio"], 2)); ?> mensual</small>
                      </td>
                      <td><span class="label label-<?php echo escapar($labelEstado); ?>"><?php echo escapar($estadoVisible); ?></span></td>
                      <td><?php echo escapar(fechaAdmin($usuario["fecha_vencimiento"])); ?></td>
                      <td>
                        <form action="index.php" method="POST" class="admin-user-form">
                          <input type="hidden" name="csrf_token" value="<?php echo escapar($csrfToken); ?>">
                          <input type="hidden" name="actualizar_usuario" value="1">
                          <input type="hidden" name="usuario_id" value="<?php echo escapar($usuario["id"]); ?>">

                          <select name="membresia_id" class="form-control input-sm">
                            <?php foreach ($membresias as $membresia) : ?>
                              <option value="<?php echo escapar($membresia["id"]); ?>" <?php echo (int) $usuario["membresia_id"] === (int) $membresia["id"] ? "selected" : ""; ?>>
                                <?php echo escapar($membresia["nombre"]); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>

                          <select name="estado" class="form-control input-sm">
                            <option value="activa" <?php echo $usuario["estado"] === "activa" ? "selected" : ""; ?>>Activa</option>
                            <option value="suspendida" <?php echo $usuario["estado"] === "suspendida" ? "selected" : ""; ?>>Suspendida</option>
                          </select>

                          <label class="admin-renew">
                            <input type="checkbox" name="renovar" value="1">
                            Renovar 30 d&iacute;as
                          </label>

                          <button type="submit" class="btn btn-gym btn-sm">
                            <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>
                            Guardar
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>

          <article class="dashboard-card admin-table-card">
            <div class="dashboard-card-header">
              <div>
                <p>Historial financiero</p>
                <h3>Pagos recientes</h3>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-striped admin-table">
                <thead>
                  <tr>
                    <th>Cliente</th>
                    <th>Membres&iacute;a</th>
                    <th>Monto</th>
                    <th>M&eacute;todo</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($pagos) === 0) : ?>
                    <tr>
                      <td colspan="6" class="text-center">Todav&iacute;a no hay pagos registrados.</td>
                    </tr>
                  <?php endif; ?>

                  <?php foreach ($pagos as $pago) : ?>
                    <tr>
                      <td>
                        <strong><?php echo escapar($pago["usuario"]); ?></strong>
                        <span><?php echo escapar($pago["correo"]); ?></span>
                      </td>
                      <td>
                        <?php echo escapar($pago["membresia"]); ?>
                        <?php if ($pago["referencia"]) : ?>
                          <br><small>Ref: <?php echo escapar($pago["referencia"]); ?></small>
                        <?php endif; ?>
                      </td>
                      <td>$<?php echo escapar(number_format((float) $pago["monto"], 2)); ?></td>
                      <td><?php echo escapar(ucfirst($pago["metodo"])); ?></td>
                      <td><span class="label label-<?php echo escapar(etiquetaPago($pago["estado"])); ?>"><?php echo escapar(ucfirst($pago["estado"])); ?></span></td>
                      <td><?php echo escapar(fechaAdmin($pago["fecha_pago"])); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        </section>
      </main>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../js/main.js"></script>
  </body>
</html>
