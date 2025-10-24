<?php
// PUNTO DE ENTRADA ÚNICO
session_start();

// Cargar router
require_once '../app/core/Router.php';

$router = new Router();

// === RUTAS EXISTENTES ===
$router->addRoute('/', 'DashboardController', 'index');
$router->addRoute('/login', 'AuthController', 'login');
$router->addRoute('/dashboard', 'DashboardController', 'index');

// === MÓDULO PACIENTES ===
$router->addRoute('/pacientes', 'PacienteController', 'listar');
$router->addRoute('/pacientes/crear', 'PacienteController', 'crear');
$router->addRoute('/pacientes/editar', 'PacienteController', 'editar');

// === MÓDULO DIAGNÓSTICOS ===
$router->addRoute('/diagnosticos', 'DiagnosticoController', 'listar');
$router->addRoute('/diagnosticos/crear', 'DiagnosticoController', 'crear');
$router->addRoute('/diagnosticos/editar', 'DiagnosticoController', 'editar');

// === MÓDULO PERSONAL MÉDICO ===
$router->addRoute('/medicos', 'MedicoController', 'listar');
$router->addRoute('/medicos/crear', 'MedicoController', 'crear');
$router->addRoute('/medicos/editar', 'MedicoController', 'editar');

// === MÓDULO MEDICINAS ===
$router->addRoute('/medicinas', 'MedicinaController', 'listar');
$router->addRoute('/medicinas/crear', 'MedicinaController', 'crear');
$router->addRoute('/medicinas/editar', 'MedicinaController', 'editar');

// === MÓDULO REPORTES ===
$router->addRoute('/reportes', 'ReporteController', 'listar');
$router->addRoute('/reportes/generar', 'ReporteController', 'generar');

// === MÓDULO USUARIOS ===
$router->addRoute('/usuarios', 'UsuarioController', 'listar');
$router->addRoute('/usuarios/crear', 'UsuarioController', 'crear');
$router->addRoute('/usuarios/editar', 'UsuarioController', 'editar');

// Manejar la solicitud
$router->handleRequest();
?>