<?php
session_start();

require_once dirname(__DIR__) . "/config/database.php";

if (empty($_SESSION["user_id"])) {
    header("Location: ../login/");
    exit;
}

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function formatoFecha($fecha) {
    if (!$fecha) {
        return "No disponible";
    }

    return date("d/m/Y", strtotime($fecha));
}

function obtenerDatosUsuario($conexion, $usuarioId) {
    $consulta = $conexion->prepare(
        "SELECT u.id, u.nombre, u.correo, u.estado, u.fecha_vencimiento, u.fecha_registro,
                m.codigo AS codigo_membresia, m.nombre AS membresia,
                m.precio, m.descripcion
         FROM usuarios u
         INNER JOIN membresias m ON m.id = u.membresia_id
         WHERE u.id = :id
         LIMIT 1"
    );
    $consulta->execute(array(":id" => $usuarioId));

    return $consulta->fetch();
}

function obtenerPagosUsuario($conexion, $usuarioId) {
    $consulta = $conexion->prepare(
        "SELECT p.monto, p.metodo, p.estado, p.referencia, p.fecha_pago,
                m.nombre AS membresia
         FROM pagos p
         INNER JOIN membresias m ON m.id = p.membresia_id
         WHERE p.usuario_id = :usuario_id
         ORDER BY p.fecha_pago DESC
         LIMIT 10"
    );
    $consulta->execute(array(":usuario_id" => $usuarioId));

    return $consulta->fetchAll();
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
    $usuario = obtenerDatosUsuario($conexion, $_SESSION["user_id"]);

    if (!$usuario) {
        session_destroy();
        header("Location: ../login/");
        exit;
    }

    $pagos = obtenerPagosUsuario($conexion, $_SESSION["user_id"]);
} catch (Exception $e) {
    header("Location: ../login/error.html");
    exit;
}

$fechaVencimiento = strtotime($usuario["fecha_vencimiento"]);
$diasRestantes = (int) ceil(($fechaVencimiento - time()) / 86400);
$estadoMembresia = $usuario["estado"] === "activa" && $diasRestantes >= 0 ? "Activa" : "Vencida";
$claseEstado = $estadoMembresia === "Activa" ? "success" : "danger";
$diasTexto = $diasRestantes >= 0 ? $diasRestantes . " dias restantes" : abs($diasRestantes) . " dias vencida";
$inicial = strtoupper(substr($usuario["nombre"], 0, 1));
$ultimoPago = count($pagos) > 0 ? $pagos[0] : null;

$_SESSION["user_name"] = $usuario["nombre"];
$_SESSION["membership"] = $usuario["membresia"];
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - PowerFit Gym</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/stilo.css">
  </head>

  <body class="dashboard-page">
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#dashboardNavbar" aria-controls="dashboardNavbar" aria-expanded="false">
            <span class="sr-only">Abrir navegaci&oacute;n</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="../index.html#inicio">PowerFit Gym</a>
        </div>

        <div class="collapse navbar-collapse" id="dashboardNavbar">
          <ul class="nav navbar-nav navbar-right">
            <li class="active"><a href="../dashboard/">Dashboard</a></li>
            <li><a href="../index.html#membresias">Membres&iacute;as</a></li>
            <li><a href="../index.html#contacto">Contacto</a></li>
            <li><a href="logout.php">Cerrar sesi&oacute;n</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="dashboard-wrap">
      <section class="dashboard-hero">
        <div class="container">
          <div class="row">
            <div class="col-md-8">
              <p class="dashboard-kicker">Panel del cliente</p>
              <h1>Hola, <?php echo escapar($usuario["nombre"]); ?></h1>
              <p>Este es el resumen de su membres&iacute;a y actividad dentro de PowerFit Gym.</p>
            </div>
            <div class="col-md-4">
              <div class="dashboard-profile">
                <div class="dashboard-avatar" aria-hidden="true"><?php echo escapar($inicial); ?></div>
                <div>
                  <strong><?php echo escapar($usuario["nombre"]); ?></strong>
                  <span><?php echo escapar($usuario["correo"]); ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="container dashboard-content">
        <div class="row dashboard-metrics">
          <div class="col-md-3 col-sm-6">
            <article class="dashboard-card metric-card">
              <span class="glyphicon glyphicon-credit-card" aria-hidden="true"></span>
              <p>Membres&iacute;a</p>
              <h2><?php echo escapar($usuario["membresia"]); ?></h2>
            </article>
          </div>

          <div class="col-md-3 col-sm-6">
            <article class="dashboard-card metric-card">
              <span class="glyphicon glyphicon-usd" aria-hidden="true"></span>
              <p>Pago mensual</p>
              <h2>$<?php echo escapar(number_format((float) $usuario["precio"], 2)); ?></h2>
            </article>
          </div>

          <div class="col-md-3 col-sm-6">
            <article class="dashboard-card metric-card">
              <span class="glyphicon glyphicon-calendar" aria-hidden="true"></span>
              <p>Vence el</p>
              <h2><?php echo escapar(formatoFecha($usuario["fecha_vencimiento"])); ?></h2>
            </article>
          </div>

          <div class="col-md-3 col-sm-6">
            <article class="dashboard-card metric-card">
              <span class="glyphicon glyphicon-ok-circle" aria-hidden="true"></span>
              <p>Estado</p>
              <h2><span class="label label-<?php echo escapar($claseEstado); ?>"><?php echo escapar($estadoMembresia); ?></span></h2>
            </article>
          </div>
        </div>

        <div class="row">
          <div class="col-md-7">
            <article class="dashboard-card dashboard-membership">
              <div class="dashboard-card-header">
                <div>
                  <p>Plan actual</p>
                  <h3><?php echo escapar($usuario["membresia"]); ?></h3>
                </div>
                <span class="membership-badge membership-<?php echo escapar($usuario["codigo_membresia"]); ?>">
                  <?php echo escapar($diasTexto); ?>
                </span>
              </div>

              <p class="dashboard-description"><?php echo escapar($usuario["descripcion"]); ?></p>

              <dl class="membership-details">
                <dt>Fecha de registro</dt>
                <dd><?php echo escapar(formatoFecha($usuario["fecha_registro"])); ?></dd>
                <dt>Pr&oacute;xima renovaci&oacute;n</dt>
                <dd><?php echo escapar(formatoFecha($usuario["fecha_vencimiento"])); ?></dd>
                <dt>Correo registrado</dt>
                <dd><?php echo escapar($usuario["correo"]); ?></dd>
              </dl>

              <div class="dashboard-actions">
                <a href="../index.html#membresias" class="btn btn-gym">
                  <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
                  Cambiar plan
                </a>
                <a href="../index.html#contacto" class="btn btn-default">
                  <span class="glyphicon glyphicon-envelope" aria-hidden="true"></span>
                  Contactar gimnasio
                </a>
              </div>
            </article>
          </div>

          <div class="col-md-5">
            <article class="dashboard-card">
              <div class="dashboard-card-header">
                <div>
                  <p>Pagos</p>
                  <h3>Estado del pago</h3>
                </div>
              </div>

              <?php if ($ultimoPago) : ?>
                <dl class="membership-details payment-details">
                  <dt>&Uacute;ltimo pago</dt>
                  <dd>$<?php echo escapar(number_format((float) $ultimoPago["monto"], 2)); ?></dd>
                  <dt>Membres&iacute;a</dt>
                  <dd><?php echo escapar($ultimoPago["membresia"]); ?></dd>
                  <dt>Estado</dt>
                  <dd><span class="label label-<?php echo escapar(etiquetaPago($ultimoPago["estado"])); ?>"><?php echo escapar(ucfirst($ultimoPago["estado"])); ?></span></dd>
                  <dt>Fecha</dt>
                  <dd><?php echo escapar(formatoFecha($ultimoPago["fecha_pago"])); ?></dd>
                </dl>
              <?php else : ?>
                <p class="dashboard-description">Todav&iacute;a no hay pagos registrados en su cuenta.</p>
              <?php endif; ?>

              <div class="dashboard-actions">
                <a href="../index.html#contacto" class="btn btn-default">
                  <span class="glyphicon glyphicon-envelope" aria-hidden="true"></span>
                  Consultar pago
                </a>
              </div>
            </article>
          </div>
        </div>

        <article class="dashboard-card admin-table-card">
          <div class="dashboard-card-header">
            <div>
              <p>Historial financiero</p>
              <h3>Mis pagos</h3>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped admin-table">
              <thead>
                <tr>
                  <th>Membres&iacute;a</th>
                  <th>Monto</th>
                  <th>M&eacute;todo</th>
                  <th>Estado</th>
                  <th>Referencia</th>
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
                    <td><?php echo escapar($pago["membresia"]); ?></td>
                    <td>$<?php echo escapar(number_format((float) $pago["monto"], 2)); ?></td>
                    <td><?php echo escapar(ucfirst($pago["metodo"])); ?></td>
                    <td><span class="label label-<?php echo escapar(etiquetaPago($pago["estado"])); ?>"><?php echo escapar(ucfirst($pago["estado"])); ?></span></td>
                    <td><?php echo escapar($pago["referencia"] ? $pago["referencia"] : "No aplica"); ?></td>
                    <td><?php echo escapar(formatoFecha($pago["fecha_pago"])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>
    </main>

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../js/main.js"></script>
  </body>
</html>
