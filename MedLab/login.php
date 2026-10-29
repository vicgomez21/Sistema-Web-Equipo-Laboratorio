<?php
require_once __DIR__ . '/api/core/Auth.php';
header('Cache-Control: no-store');
if (!empty($_SESSION['usuario']) && time() - ($_SESSION['actividad'] ?? 0) <= 1800) { header('Location: index.php'); exit; }
$error = $_SESSION['login_error'] ?? ''; unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Iniciar sesión | MedLab</title><link rel="stylesheet" href="css/styles.css"></head>
<body class="login-page">
<main class="login-shell">
<section class="login-story"><a class="brand" href="login.php"><span class="brand-mark">M<span>+</span></span> MedLab</a><div><span class="eyebrow">GESTIÓN DE LABORATORIO</span><h1>Todo tu laboratorio.<br>En un solo lugar.</h1><p>Organiza tu equipo y conecta a las personas que hacen posible cada descubrimiento.</p><div class="lab-art" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="lab-symbol">⚗</div><span class="art-tag">Precisión en cada proceso</span></div></div><small>Control, organización y confianza.</small></section>
<section class="login-panel"><div class="login-form-wrap"><span class="eyebrow">BIENVENIDO A MEDLAB</span><h2>Inicia sesión</h2><p class="login-description">Ingresa tus credenciales para acceder al sistema.</p>
<?php if ($error): ?><p class="form-error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form action="api/admin/C_Auth.php" method="post" class="login-form">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(Auth::token()) ?>">
<div class="campo"><label for="identificador">Correo o código de usuario</label><input id="identificador" name="identificador" placeholder="Tu correo o código" autocomplete="username" maxlength="150" required autofocus></div>
<div class="campo"><label for="password">Contraseña</label><div class="password-wrap"><input id="password" name="password" type="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" maxlength="1024" required><button id="verPassword" type="button" aria-controls="password" aria-pressed="false">Mostrar</button></div></div>
<button class="btn btn--primario login-submit" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
</form><p class="login-help">¿Necesitas acceso? Contacta al administrador de tu laboratorio.</p><div class="login-security">Acceso exclusivo para personal autorizado</div></div><small class="login-footer">MedLab · Sistema de gestión de equipos de laboratorio</small></section>
</main><script src="js/lib/login.js"></script></body></html>
