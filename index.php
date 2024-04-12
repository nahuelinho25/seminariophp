<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add( function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json')
    ;
});

$app = AppFactory::create();
$app->addBodyParsingMiddleware();


// Conexion a la base de datos
function getConnection(){
    $dbhost = 'db'; //phpmyadmin
    //$dbhost = "db";//docker

    $dbname = 'seminariophp';
    $dbuser = 'seminariophp';
    $dbpass = 'seminariophp';

    $connection = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $connection;

}

// ACÁ VAN LOS ENDPOINTS

$app->get('/',function(Request $request, Response $response, $args){
    $response->getBody()->write("Hola mundo");
    return $response;
});

//================================[ LOCALIDAD ]=========================================

// Crear localidad
$app->post('/localidades',function(Request $request, Response $response){
    $data = $request->getParsedBody();  ('Solo valido para POST)
    /// verificar si existe el campo nombre
    if (!isset($data['nombre'])){  
        $response->getBody()->write(json_encode(['error'=> 'El campo nombre es requerido']));
        return $response->withStatus(400);
    }else{
        try{
            $connection = getConnection();
            $nombre = $data['nombre'];
            // Posibles errores

            // Verificar si el nombre de la localidad supera los 50 caracteres
            if (strlen($nombre) > 50){
                $response->getBody()->write(json_encode(['error'=> 'El campo nombre excede los caracteres permitidos']));
                return $response->withStatus(400);
            }

            // Verificar si el nombre de la localidad ya existe
            $sql ="SELECT * FROM localidades WHERE nombre = '". $nombre ."'";
            $consulta_repetido = $connection->query($sql);
            if ($consulta_repetido->rowCount()> 0){ 
                $response->getBody()->write(json_encode(['error'=> 'El campo nombre no puede repetirse']));
                return $response->withStatus(400);
                
            // Agrega la localidad
            }else{
                $sql="INSERT INTO localidades (nombre) VALUES (:nombre)";
                $consulta = $connection->prepare($sql);
                $consulta->bindValue("nombre", $nombre);
                $consulta->execute();

                $response->getBody()->write(json_encode(['message'=> 'localidad creada']));
                return $response->withStatus(201);
            }
        
        /// Si hay algun otro error
        }catch (PDOException $e){ 

        $response->getBody()->write(json_encode([
            'status' => "Bad Request",
            'message' => "Error al crear la localidad"]));
            return $response->withStatus(400);
        }
}
});

// Editar Localidad

$app->put('/localidades/:id', function(Request $request, Response $response){
    $id = //Como extraigo el id ?
    $data = $request->getBody();
    $nombre = $data['nombre'];

    $connection = getConnection();

    try{
        $query = $connection->prepare('UPDATE INTO SET "Como pongo el nombre " WHERE como asigno el id');
        $localidades = $query->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $localidades,
            'message' => "Localidad editada correctamente"
        ]);
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar la localidad". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');

});

// Eliminar Localidad

$app->delete('/localidades/:id', function(Request $request, Response $response){
    $id = //Como extraigo el id ?
    $connection = getConnection();
    
    try{
        $query = $connection->prepare('DELETE FROM localiades WHERE como asigno el id');
        $localidades = $query->fetchAll(PDO::FETCH_ASSOC);
    
        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $localidades,
            'message' => "Localidad eliminada correctamente"
        ]);
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al eliminar la localidad". $e->getMessage()
        ]);
    }
    
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

// Listar Localidades

$app->get('/localidades', function(Request $request, Response $response){
    $connection = getConnection();

    try {
        $query = $connection->query('SELECT * FROM localidades');
        $localidades = $query->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $localidades
        ]);
        
    } catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al listar las localidades ". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

//================================[ PROPIEDAD ]=========================================

// Crear Propiedad

$app->post('/tipos_propiedad', function(Request $request, Response $response){
    $data = $request->getParsedBody(); ('Solo valido para POST')

    /// Verificar si existe el campo
    if (!isset($data['nombre'])){
        $response->getBody()->write(json_encode(['error'=> 'El campo nombre es requerido']));
        return $response->withStatus(400);
    }else{
        try{
            $connection = getConnection();
            $nombre = $data['nombre'];

            // Verificar si el nombre del tipo de localidad supera los 50 caracteres
            if (strlen($nombre) > 50){
                $response->getBody()->write(json_encode(['error'=> 'El campo nombre excede los caracteres permitidos']));
                return $response->withStatus(400);
            }
            /// Verificar si el nombre del tipo de la localidad ya existe
            $sql ="SELECT * FROM tipo_propiedades WHERE nombre = '". $nombre ."'";
            $consulta_repetido = $connection->query($sql);
            if ($consulta_repetido->rowCount()> 0){
                $response->getBody()->write(json_encode(['error'=> 'El nombre no puede repetirse']));
                return $response->withStatus(400);

            /// Agrego el tipo de localidad
            }else{
                $sql="INSERT INTO tipo_propiedades (nombre) VALUES (:nombre)";
                $consulta = $connection->prepare($sql);
                $consulta->bindValue("nombre", $nombre);
                $consulta->execute();

                $response->getBody()->write(json_encode(['message'=> 'tipo de propiedad creada']));
                return $response->withStatus(201);
            }
        }catch (PDOException $e){ 

        $response->getBody()->write(json_encode([
            'status' => "Bad Request",
            'message' => "Error al crear el tipo de propiedad". $e->getMessage()]));
            return $response->withStatus(400);
        }
    }
});

// Listar Propiedad
$app->get('/tipo_propiedades', function(Request $request, Response $response){
    $connection = getConnection();  // Me da la conexion a la base de datos
    try {
        $query = $connection->query('SELECT * FROM tipos_propiedad');
        $tipos = $query->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $tipos,
        ]);
        
    } catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al listar las propiedades ". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});


//================================[ INQUILINO ]=========================================

// Crear Inquilino
$app->post('/inquilinos', function(Request $request, Response $response){
    $data = $request->getParsedBody(); ('Solo valido para POST')

    /// Verificar si existen todos los campos
    $campos_requeridos =['nombre_usuario', 'apellido', 'nombre', 'email', 'activo'];
    foreach($campos_requeridos as $campo){
        if(!isset($data[$campo])){
        $response->getBody()->write(json_encode(['error' => 'El campo '. $campo . ' es requerido']));
        return $response->withStatus(400);
        }
    }

    /// Verificar el limite de caracteres de todos los campos
    $max_longitudes = ['nombre_usuario' => 20, 'apellido' => 15, 'nombre' => 25, 'email' => 20];
    foreach($max_longitudes as $campo => $max_longitud){
        if (strlen($data[$campo]) > $max_longitud){
            $response->getBody()->write(json_encode(['error' => 'El campo '.$campo.' supera los caracteres permitidos']));          
            return $response->withStatus(400);
        }
    }

    /// Verificar si el campo 'correo' tiene un formato valido
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
        $response->getBody()->write(json_encode(['error' => ' El campo email tiene un formato no valido']));
        return $response->withStatus(400);
    }

    /// Verificar si el campo 'activo' recibe 1(true) o 0 (false)
    if($data['activo'] !== '1' && $data['activo']!== '0' ){
        $response->getBody()->write(json_encode(['error' => 'El campo activo debe ser 1 (true) o 0 (false)']));
        return $response->withStatus(400);       
    }

    try{
        $connection = getConnection();
        $nombre_usuario = $data['nombre_usuario'];  
        $email = $data['email'];
        /// Inicializar las variables en false
        $emailb = '0';
        $nombreb = '0';

        /// Variable para obtener el dato nombre_usario
        $sql = "SELECT * FROM inquilinos WHERE nombre_usuario = '". $nombre_usuario ."'";
        $nombre_usuario_repetido = $connection->query($sql);
        /// Variable para obtener el dato email
        $sql2 = "SELECT * FROM inquilinos WHERE email = '". $email ."'";
        $email_repetido = $connection->query($sql2);

        /// Verificar si el nombre de usuario ya existe
        if ($nombre_usuario_repetido->rowCount() == 0){
            $nombreb = '1';
        }
        /// Verificar si el email ya esta en uso
        if($email_repetido->rowCount() == 0){
            $emailb = '1';
        }

        /// Verificar si el nombre de usuraio y el email ya estan en uso
        if ($nombreb == '0' && $emailb == '0'){
            $response->getBody()->write(json_encode(['error'=> 'El nombre de usuario y el email ya estan en uso']));
            return $response->withStatus(400);
        /// Verificar si solo el nombre de usuario esta en uso
        }elseif ($nombreb =='0'){
            $response->getBody()->write(json_encode(['error'=> 'El nombre de usuario ya esta en uso']));
            return $response->withStatus(400);
        /// Verificar si solo el email de usuario esta en uso
        }elseif ($emailb == '0'){
            $response->getBody()->write(json_encode(['error'=> 'El email ya esta en uso']));
            return $response->withStatus(400);

        }else{
            /// Agrego el inquilino
            $sql = "INSERT INTO inquilinos (nombre_usuario, apellido, nombre, email, activo) 
            VALUES (:nombre_usuario, :apellido, :nombre, :email, :activo)";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue("nombre_usuario", $data['nombre_usuario']);
            $consulta->bindValue("apellido", $data['apellido']);
            $consulta->bindValue("nombre", $data['nombre']);
            $consulta->bindValue("email", $data['email']);
            $consulta->bindValue("activo", $data['activo']);
            $consulta->execute();

            $response->getBody()->write(json_encode(['message'=> 'Inquilino agregado correctamente']));
            return $response->withStatus(201);
        }
    
    /// Si hay algun otro error
    }catch (PDOException $e){ 
    $response->getBody()->write(json_encode([
        'status' => "Bad Request",
        'message' => "Error al crear el inquilino". $e->getMessage()]));
        return $response->withStatus(400);
    }
});

/// Listar Inquilinos
$app->get('/inquilinos', function(Request $request, Response $response){
    $connection = getConnection();

    try {
        $query = $connection->query('SELECT * FROM inquilinos ORDER BY id');
        $inquilinos = $query->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $inquilinos
        ]);
        
    } catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al listar las inquilinos ". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

// Ver Inquilino
$app->get('/inquilinos/:id', function(Request $request, Response $response){
  // Me da la conexion a la base de datos
    $data = $request->getParsedBody();
    if (!isset($data['id'])){
        $response->getBody()->write(json_encode(['error'=> 'El campo id es requerido']));
        return $response->withStatus(400); 
    }
    try {
        $connection = getConnection();
        $id = $data['id'];
        $sql = "SELECT * FROM inquilinos WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        /// Verificar si existe el campo
         if ($consulto_id->rowCount()> 0){
                $response->getBody()->write(json_encode(['message'=> 'El inquilino existe']));
                $query = $connection->query($sql);
                $inquilinox = $query->fetch(PDO::FETCH_ASSOC);
                $response->getBody()->write(json_encode([$inquilinox]));
                return $response->withStatus(201);

        }else{ $response->getBody()->write(json_encode(['error'=> 'El inquilino no existe']));
                return $response->withStatus(400);
         }  

    }catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al mostrar ese inquilino ". $e->getMessage()
        ]);
    }
});    

$app->run();
