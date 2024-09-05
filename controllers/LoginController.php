<?php
namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

class LoginController {
    public static function login(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarLogin();

            if(empty($alertas)) {
                // Comprobar que exista el usuario
                $usuario = Usuario::where('email', $auth->email);

                if($usuario) {
                    // Verificar el password
                    if( $usuario->comprobarPasswordAndVerificado($auth->password) ) {
                        // Autenticar el usuario
                        session_start();

                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre . " " . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        // Redireccionamiento
                        if($usuario->admin === "1") {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cita');
                        }
                    }
                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }

            }
        }

        $alertas = Usuario::getAlertas();
        
        $router->render('auth/login', [
            'alertas' => $alertas
        ]);
    }

    public static function logout() {
        session_start();
        $_SESSION = [];
        header('Location: /');
    }
    public static function olvide(Router $router) {
        $router->render('auth/olvide-password');
    }
    public static function recuperar(Router $router) {
        $router->render('auth/recuperar-password, [
        
        ]');
    }
    public static function crear(Router $router) {
        $usuario = new Usuario;
        // Alertas vacias
        $alertas = [];
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();
             // Revisar que alerta este vacio
            if(empty($alertas)){
                $resultado = $usuario->existeUsuario();

                if($resultado->num_rows) {
                    $alertas = Usuario::getAlertas();
                } else {
                     // Hashear el Password
                     $usuario->hashPassword();

                    // Generar un Token único
                    $usuario->crearToken();
 // Enviar el Email
                    $email = new Email($usuario->nombre, $usuario->email, $usuario->token);
                    $email->enviarConfirmacion();
                    // Crear el usuario
                    $resultado = $usuario->guardar();
                    // debuguear($usuario);
                    if($resultado) {
                        header('Location: /mensaje');
                    }
                }
            }
        }
        $router->render('auth/crear-cuenta',[
           'usuario' => $usuario,
            'alertas' => $alertas
        ]);
    }
    public static function confirmar(Router $router) {
        $router->render('auth/confirmar-cuenta');
    }
    public static function mensaje(Router $router) {
        $alertas = [];
        $token = s($_GET['token']);
        $usuario = Usuario::where('token', $token);

        if(empty($usuario)) {
            // Mostrar mensaje de error
            Usuario::setAlerta('error', 'Token No Válido');
        } else {
            // Modificar a usuario confirmado
            $usuario->confirmado = "1";
            $usuario->token = null;
            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta Comprobada Correctamente');
        }
       
        // Obtener alertas
        $alertas = Usuario::getAlertas();

        // Renderizar la vista
        $router->render('auth/mensaje',[
            
             'alertas' => $alertas
         ]);
    }
}