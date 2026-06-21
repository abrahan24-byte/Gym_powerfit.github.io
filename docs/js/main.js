$(document).ready(function () {
  var $window = $(window);
  var $body = $("body");
  var $menu = $(".menu");
  var correoExpresion = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function limpiarError($campo) {
    var $grupo = $campo.closest(".form-group");
    $grupo.removeClass("has-error");
    $grupo.find(".help-block.error-message").remove();
  }

  function mostrarError($campo, mensaje) {
    var $grupo = $campo.closest(".form-group");
    var $inputGroup = $campo.closest(".input-group");

    limpiarError($campo);
    $grupo.addClass("has-error");

    if ($inputGroup.length) {
      $inputGroup.after('<span class="help-block error-message">' + mensaje + "</span>");
    } else {
      $campo.after('<span class="help-block error-message">' + mensaje + "</span>");
    }
  }

  function validarFormularioCliente($form) {
    var valido = true;
    var $nombre = $form.find('[name="Nombre"]');
    var $correo = $form.find('[name="Correo"]');
    var $password = $form.find('[name="Password"]');
    var $membresia = $form.find('[name="Membresia"]');
    var nombre = $nombre.length ? $.trim($nombre.val()) : "";
    var correo = $correo.length ? $.trim($correo.val()) : "";
    var password = $password.length ? $.trim($password.val()) : "";

    $form.find(".form-control").each(function () {
      limpiarError($(this));
    });

    if ($nombre.length && nombre === "") {
      mostrarError($nombre, "Ingrese su nombre.");
      valido = false;
    }

    if ($correo.length && correo === "") {
      mostrarError($correo, "Ingrese su correo electronico.");
      valido = false;
    } else if ($correo.length && !correoExpresion.test(correo)) {
      mostrarError($correo, "Ingrese un correo electronico valido.");
      valido = false;
    }

    if ($password.length && password === "") {
      mostrarError($password, "Ingrese su contrasena.");
      valido = false;
    } else if ($password.length && password.length < 8) {
      mostrarError($password, "La contrasena debe tener al menos 8 caracteres.");
      valido = false;
    }

    if ($membresia.length && $.trim($membresia.val()) === "") {
      mostrarError($membresia, "Seleccione una membresia.");
      valido = false;
    }

    return valido;
  }

  function guardarMembresia(membresia) {
    try {
      if (membresia && window.sessionStorage) {
        sessionStorage.setItem("membresiaSeleccionada", membresia);
      }
    } catch (error) {
      return false;
    }

    return true;
  }

  $('[data-validate="cliente"]').on("submit", function (event) {
    if (!validarFormularioCliente($(this))) {
      event.preventDefault();
    }
  });

  $('[data-validate="cliente"] .form-control').on("input change", function () {
    limpiarError($(this));
  });

  $(".btn-suscripcion").on("click", function () {
    guardarMembresia($(this).data("membresia"));
  });

  if ($("#Membresia").length && !$("#Membresia").val()) {
    try {
      var membresiaGuardada = sessionStorage.getItem("membresiaSeleccionada");

      if (membresiaGuardada && $("#Membresia option[value='" + membresiaGuardada + "']").length) {
        $("#Membresia").val(membresiaGuardada);
      }
    } catch (error) {
      // The URL value still keeps the form working.
    }
  }

  $(".navbar-collapse a").on("click", function () {
    if ($.fn.collapse) {
      $(".navbar-collapse.in").collapse("hide");
    }
  });

  $('a[href^="#"]').not("[data-slide]").on("click", function (event) {
    var destino = $(this).attr("href");
    var $destino = destino.length > 1 ? $(destino) : $();

    if ($destino.length) {
      event.preventDefault();
      $("html, body").animate(
        {
          scrollTop: $destino.offset().top - ($menu.length ? $menu.outerHeight() : 0)
        },
        450
      );
    }
  });

  if ($menu.length === 0) return;

  var altura = $menu.offset().top;

  function actualizarMenu() {
    $body.css("--menu-height", $menu.outerHeight() + "px");

    if ($window.scrollTop() > altura) {
      $menu.addClass("menu-fixed");
      $body.addClass("menu-is-fixed");
    } else {
      $menu.removeClass("menu-fixed");
      $body.removeClass("menu-is-fixed");
    }
  }

  $window.on("scroll", actualizarMenu);
  $window.on("resize", function () {
    $menu.removeClass("menu-fixed");
    $body.removeClass("menu-is-fixed");
    altura = $menu.offset().top;
    actualizarMenu();
  });

  actualizarMenu();
});
