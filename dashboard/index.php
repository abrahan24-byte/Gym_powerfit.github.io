<?php
session_start();

require_once dirname(__DIR__) . "/config/database.php";

if (empty($_SESSION["user_id"])) {
    header("Location: ../login/");
    exit;
}

if (empty($_SESSION["dashboard_csrf_token"])) {
    $_SESSION["dashboard_csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["dashboard_csrf_token"];
$mensajePago = "";
$tipoMensajePago = "danger";
$formularioPagoEnviado = $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["pagar_membresia"]);
$mostrarPanelPago = isset($_GET["pago"]) || $formularioPagoEnviado;
$mostrarMenuMembresias = isset($_GET["cambiar_plan"]) || isset($_GET["membresia_id"]) || $formularioPagoEnviado;
$membresiaSeleccionadaId = isset($_POST["membresia_id"]) ? (int) $_POST["membresia_id"] : (isset($_GET["membresia_id"]) ? (int) $_GET["membresia_id"] : 0);
$numeroTarjeta = isset($_POST["numero_tarjeta"]) ? trim($_POST["numero_tarjeta"]) : "";
$fechaTarjeta = isset($_POST["fecha_tarjeta"]) ? trim($_POST["fecha_tarjeta"]) : "";
$cvvTarjeta = isset($_POST["cvv_tarjeta"]) ? trim($_POST["cvv_tarjeta"]) : "";
$titularTarjeta = isset($_POST["titular_tarjeta"]) ? trim($_POST["titular_tarjeta"]) : "";

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
                u.membresia_id,
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

function obtenerMembresias($conexion) {
    return $conexion->query("SELECT id, codigo, nombre, precio, descripcion FROM membresias ORDER BY precio ASC")->fetchAll();
}

function obtenerPagosUsuario($conexion, $usuarioId) {
    $consulta = $conexion->prepare(
        "SELECT p.monto, p.metodo, p.estado, p.referencia, p.tipo_pago,
                p.tarjeta_ultimos4, p.fecha_pago,
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

    if ($estado === "reembolsado") {
        return "info";
    }

    return "warning";
}

function soloDigitos($valor) {
    return preg_replace("/\D+/", "", $valor);
}

function generarReferenciaPago($usuarioId) {
    return "SIM-" . date("YmdHis") . "-" . (int) $usuarioId;
}

try {
    $conexion = obtenerConexion();
    $usuario = obtenerDatosUsuario($conexion, $_SESSION["user_id"]);
    $membresias = obtenerMembresias($conexion);
    $membresiasPorId = array();

    foreach ($membresias as $membresia) {
        $membresiasPorId[(int) $membresia["id"]] = $membresia;
    }

    if (!$usuario) {
        session_destroy();
        header("Location: ../login/");
        exit;
    }

    if ($membresiaSeleccionadaId <= 0) {
        $membresiaSeleccionadaId = (int) $usuario["membresia_id"];
    }

    $membresiaSeleccionada = isset($membresiasPorId[$membresiaSeleccionadaId]) ? $membresiasPorId[$membresiaSeleccionadaId] : null;

    if ($formularioPagoEnviado) {
        $digitosTarjeta = soloDigitos($numeroTarjeta);
        $digitosCvv = soloDigitos($cvvTarjeta);

        if (!isset($_POST["csrf_token"]) || !hash_equals($csrfToken, $_POST["csrf_token"])) {
            $mensajePago = "La sesion expiro. Recargue la pagina e intente de nuevo.";
        } elseif (!$membresiaSeleccionada) {
            $mensajePago = "Seleccione una membresia valida.";
        } elseif ($numeroTarjeta !== "" && (strlen($digitosTarjeta) < 12 || strlen($digitosTarjeta) > 19)) {
            $mensajePago = "Revise el numero de tarjeta de prueba.";
        } elseif ($fechaTarjeta !== "" && !preg_match("/^(0[1-9]|1[0-2])\/\d{2}$/", $fechaTarjeta)) {
            $mensajePago = "Use el formato MM/AA para la fecha de prueba.";
        } elseif ($cvvTarjeta !== "" && (strlen($digitosCvv) < 3 || strlen($digitosCvv) > 4)) {
            $mensajePago = "Revise el CVV de prueba.";
        } elseif (strlen($titularTarjeta) > 100) {
            $mensajePago = "El nombre del titular es demasiado largo.";
        } else {
            $referenciaPago = generarReferenciaPago($usuario["id"]);
            $ultimos4 = strlen($digitosTarjeta) >= 4 ? substr($digitosTarjeta, -4) : null;

            $conexion->beginTransaction();

            $consultaPago = $conexion->prepare(
                "INSERT INTO pagos (usuario_id, membresia_id, monto, metodo, estado, referencia, tipo_pago, tarjeta_ultimos4, titular_tarjeta)
                 VALUES (:usuario_id, :membresia_id, :monto, 'tarjeta', 'aprobado', :referencia, 'tarjeta_simulada', :tarjeta_ultimos4, :titular_tarjeta)"
            );
            $consultaPago->execute(array(
                ":usuario_id" => $usuario["id"],
                ":membresia_id" => $membresiaSeleccionada["id"],
                ":monto" => $membresiaSeleccionada["precio"],
                ":referencia" => $referenciaPago,
                ":tarjeta_ultimos4" => $ultimos4,
                ":titular_tarjeta" => $titularTarjeta !== "" ? $titularTarjeta : null
            ));

            $consultaUsuario = $conexion->prepare(
                "UPDATE usuarios
                 SET membresia_id = :membresia_id,
                     estado = 'activa',
                     fecha_vencimiento = DATE_ADD(GREATEST(CURRENT_DATE, COALESCE(fecha_vencimiento, CURRENT_DATE)), INTERVAL 30 DAY)
                 WHERE id = :usuario_id"
            );
            $consultaUsuario->execute(array(
                ":membresia_id" => $membresiaSeleccionada["id"],
                ":usuario_id" => $usuario["id"]
            ));

            $conexion->commit();

            $mensajePago = "Pago simulado aprobado. Su membresia fue actualizada a " . $membresiaSeleccionada["nombre"] . " y renovada por 30 dias.";
            $tipoMensajePago = "success";
            $mostrarPanelPago = true;
            $numeroTarjeta = "";
            $fechaTarjeta = "";
            $cvvTarjeta = "";
            $titularTarjeta = "";
            $usuario = obtenerDatosUsuario($conexion, $_SESSION["user_id"]);
            $membresiaSeleccionadaId = (int) $usuario["membresia_id"];
            $membresiaSeleccionada = isset($membresiasPorId[$membresiaSeleccionadaId]) ? $membresiasPorId[$membresiaSeleccionadaId] : null;
        }
    }

    $pagos = obtenerPagosUsuario($conexion, $_SESSION["user_id"]);
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

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
$tieneAtraso = $diasRestantes < 0 || $usuario["estado"] !== "activa";
$membresiaActual = array(
    "id" => $usuario["membresia_id"],
    "codigo" => $usuario["codigo_membresia"],
    "nombre" => $usuario["membresia"],
    "precio" => $usuario["precio"],
    "descripcion" => $usuario["descripcion"]
);

if (empty($membresiaSeleccionada)) {
    $membresiaSeleccionada = $membresiaActual;
}

$montoMembresiaActual = number_format((float) $usuario["precio"], 2);
$montoPago = number_format((float) $membresiaSeleccionada["precio"], 2);
$referenciaVista = "SIM-" . date("Ymd") . "-" . (int) $usuario["id"];

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
        <?php if ($mensajePago !== "") : ?>
          <div class="alert alert-<?php echo escapar($tipoMensajePago); ?>" role="alert">
            <?php echo escapar($mensajePago); ?>
          </div>
        <?php endif; ?>

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
                <button type="button" class="btn btn-gym" data-toggle="collapse" data-target="#paymentPanel" aria-expanded="<?php echo $mostrarPanelPago ? 'true' : 'false'; ?>" aria-controls="paymentPanel">
                  <span class="glyphicon glyphicon-credit-card" aria-hidden="true"></span>
                  Pagar Membresia
                </button>
                <button type="button" class="btn btn-gym" data-toggle="collapse" data-target="#membershipMenu" aria-expanded="<?php echo $mostrarMenuMembresias ? 'true' : 'false'; ?>" aria-controls="membershipMenu">
                  <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
                  Cambiar plan
                </button>
                <a href="../index.html#contacto" class="btn btn-default">
                  <span class="glyphicon glyphicon-envelope" aria-hidden="true"></span>
                  Contactar gimnasio
                </a>
              </div>

              <div id="membershipMenu" class="membership-change-menu collapse <?php echo $mostrarMenuMembresias ? 'in' : ''; ?>">
                <div class="membership-change-heading">
                  <strong>Tipos de membres&iacute;a</strong>
                  <span>Plan seleccionado: <?php echo escapar($membresiaSeleccionada["nombre"]); ?></span>
                </div>

                <div class="membership-change-options">
                  <?php foreach ($membresias as $membresia) : ?>
                    <?php
                      $esActual = (int) $usuario["membresia_id"] === (int) $membresia["id"];
                      $estaSeleccionada = (int) $membresiaSeleccionada["id"] === (int) $membresia["id"];
                    ?>
                    <a href="index.php?membresia_id=<?php echo escapar($membresia["id"]); ?>&amp;pago=1#paymentPanel" class="membership-change-option <?php echo $estaSeleccionada ? 'is-selected' : ''; ?>">
                      <span class="membership-change-name">
                        <?php echo escapar($membresia["nombre"]); ?>
                        <?php if ($esActual) : ?>
                          <small>Actual</small>
                        <?php endif; ?>
                      </span>
                      <strong>$<?php echo escapar(number_format((float) $membresia["precio"], 2)); ?> USD</strong>
                      <em><?php echo escapar($membresia["descripcion"]); ?></em>
                    </a>
                  <?php endforeach; ?>
                </div>
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

        <section id="paymentPanel" class="payment-checkout collapse <?php echo $mostrarPanelPago ? 'in' : ''; ?>">
          <div class="payment-checkout-grid">
            <aside class="payment-receipt">
              <div class="payment-brand">PowerFit Gym</div>
              <p class="payment-receipt-plan"><?php echo escapar($membresiaSeleccionada["nombre"]); ?></p>
              <div class="payment-amount">
                <span><?php echo escapar($montoPago); ?></span>
                <small>USD</small>
              </div>

              <dl>
                <dt>ID de pago</dt>
                <dd><?php echo escapar($referenciaVista); ?></dd>
                <dt>Cliente</dt>
                <dd><?php echo escapar($usuario["nombre"]); ?></dd>
                <dt>Plan seleccionado</dt>
                <dd><?php echo escapar($membresiaSeleccionada["nombre"]); ?></dd>
                <dt>Renovaci&oacute;n</dt>
                <dd>30 d&iacute;as de membres&iacute;a</dd>
              </dl>
            </aside>

            <article class="payment-form-panel">
              <div class="dashboard-card-header payment-form-header">
                <div>
                  <p>Pago simulado</p>
                  <h3>Seleccionar m&eacute;todo de pago</h3>
                </div>
                <span class="payment-language">SPA</span>
              </div>

              <div class="payment-methods">
                <button type="button" class="payment-method is-muted" disabled>Apple Pay</button>
                <button type="button" class="payment-method is-muted" disabled>Google Pay</button>
                <button type="button" class="payment-method is-active" disabled>
                  <span class="glyphicon glyphicon-credit-card" aria-hidden="true"></span>
                  Tarjeta
                </button>
              </div>

              <form action="index.php#paymentPanel" method="POST" class="simulated-card-form" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo escapar($csrfToken); ?>">
                <input type="hidden" name="pagar_membresia" value="1">
                <input type="hidden" name="membresia_id" value="<?php echo escapar($membresiaSeleccionada["id"]); ?>">

                <div class="form-group">
                  <label for="NumeroTarjeta">N&uacute;mero de tarjeta</label>
                  <input name="numero_tarjeta" type="text" inputmode="numeric" maxlength="23" class="form-control input-lg" id="NumeroTarjeta" placeholder="4567 7809 3027 2132" value="<?php echo escapar($numeroTarjeta); ?>">
                </div>

                <div class="payment-card-row">
                  <div class="form-group">
                    <label for="FechaTarjeta">Vencimiento</label>
                    <input name="fecha_tarjeta" type="text" maxlength="5" class="form-control input-lg" id="FechaTarjeta" placeholder="12/23" value="<?php echo escapar($fechaTarjeta); ?>">
                  </div>

                  <div class="form-group">
                    <label for="CvvTarjeta">CVV</label>
                    <input name="cvv_tarjeta" type="text" inputmode="numeric" maxlength="4" class="form-control input-lg" id="CvvTarjeta" placeholder="130" value="<?php echo escapar($cvvTarjeta); ?>">
                  </div>
                </div>

                <div class="form-group">
                  <label for="TitularTarjeta">Nombre del titular</label>
                  <input name="titular_tarjeta" type="text" maxlength="100" class="form-control input-lg" id="TitularTarjeta" placeholder="Nombre del titular de la tarjeta" value="<?php echo escapar($titularTarjeta); ?>">
                </div>

                <p class="payment-note">Los datos de tarjeta son opcionales y de prueba. No se guarda el n&uacute;mero completo.</p>

                <button type="submit" class="btn btn-gym btn-lg btn-block payment-submit">
                  Pagar <?php echo escapar($montoPago); ?> USD
                  <span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>
                </button>
              </form>
            </article>
          </div>
        </section>

        <article class="dashboard-card overdue-card">
          <div class="dashboard-card-header">
            <div>
              <p>Atrasos</p>
              <h3>Estado de atrasos de pago</h3>
            </div>
            <span class="label label-<?php echo $tieneAtraso ? 'danger' : 'success'; ?>">
              <?php echo $tieneAtraso ? 'Atenci&oacute;n requerida' : 'Al d&iacute;a'; ?>
            </span>
          </div>

          <?php if ($tieneAtraso) : ?>
            <dl class="membership-details payment-details">
              <dt>D&iacute;as de atraso</dt>
              <dd><?php echo escapar(abs($diasRestantes)); ?> d&iacute;as</dd>
              <dt>Monto pendiente</dt>
              <dd>$<?php echo escapar($montoMembresiaActual); ?></dd>
              <dt>Fecha vencida</dt>
              <dd><?php echo escapar(formatoFecha($usuario["fecha_vencimiento"])); ?></dd>
            </dl>
          <?php else : ?>
            <p class="dashboard-description">No tiene pagos atrasados. Su pr&oacute;xima renovaci&oacute;n vence el <?php echo escapar(formatoFecha($usuario["fecha_vencimiento"])); ?>.</p>
          <?php endif; ?>
        </article>

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
                    <td>
                      <?php echo escapar(ucfirst($pago["metodo"])); ?>
                      <?php if (!empty($pago["tarjeta_ultimos4"])) : ?>
                        <br><small>**** <?php echo escapar($pago["tarjeta_ultimos4"]); ?></small>
                      <?php endif; ?>
                    </td>
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
