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
$app->addErrorMiddleware(true,true,true);


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

//================================[ LOCALIDAD ]=========================================

// Crear localidad
$app->post('/localidades',function(Request $request, Response $response){
    $data = $request->getParsedBody();

    /// verificar si existe el campo nombre
    if (!isset($data['nombre'])){  
        $response->getBody()->write(json_encode(['error'=> 'El campo nombre es requerido']));
        return $response->withStatus(400);
    }

     // Verificar si el nombre de la localidad supera los 50 caracteres
    if (strlen($data['nombre']) > 50){
        $response->getBody()->write(json_encode(['error'=> 'El campo nombre excede los caracteres permitidos']));
        return $response->withStatus(400);
    }

    try{
        $connection = getConnection();
        $nombre = $data['nombre'];

        // Verificar si el nombre de la localidad ya existe
        $sql ="SELECT * FROM localidades WHERE nombre = '". $nombre ."'";
        $consulta_repetido = $connection->query($sql);
        if ($consulta_repetido->rowCount()> 0){ 
            $response->getBody()->write(json_encode(['error'=> 'El nombre no puede repetirse']));
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
});

// Editar Localidad
$app->put('/localidades/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    $data = $request->getParsedBody();
    
    /// verificar si existe el campo nombre
    if (!isset($data['nombre'])){
        $response->getBody()->write(json_encode(['error'=> 'El campo nombre con el nuevo nombre es requerido']));
         return $response->withStatus(400);
    }else{
        try{
            $connection = getConnection();
            $nombre = $data['nombre'];

            // Nombre de la localidad supera los 50 caracteres
            if (strlen($nombre) > 50){
                $response->getBody()->write(json_encode(['error'=> 'El campo nombre excede los caracteres permitidos']));
                return $response->withStatus(400);
            }
            
            // Nombre de la localidad ya existe (EXLUYENDO EN LA POSICION ID)
            $sql ="SELECT * FROM localidades WHERE nombre = '". $nombre ."' AND id != '". $id ."'";
            $consulta_repetido = $connection->query($sql);
            if ($consulta_repetido->rowCount()> 0){ 
                $response->getBody()->write(json_encode(['error'=> 'El nombre de la localidad ya esta asignado a otra id']));
                return $response->withStatus(400);

            // Edita la localidad
            }else{
                $sql = "SELECT * FROM localidades WHERE id = '". $id ."'";
                $consulto_id = $connection->query($sql);
                /// Verificar si existe el campo y modificar
                if ($consulto_id->rowCount()> 0){
                     $sql = "UPDATE localidades SET nombre = :nombre WHERE id =  '". $id ."'";
                     $consulta = $connection->prepare($sql);
                     $consulta->bindValue(":nombre", $nombre);
                     $consulta->execute();
                     $response->getBody()->write(json_encode(['message' => 'La localidad con el id: '. $id . ' se edito de forma exitosa']));
                     return $response->withStatus(201);
    
                }else{ $response->getBody()->write(json_encode(['error'=> 'La localidad con el id: '. $id . ' no existe']));
                        return $response->withStatus(404);
                }
            }   
        }catch(PDOException $e){
            $payload = json_encode([
                 'status' => "Bad Request",
                 'code' => 400,
                 'message' => "Error al editar la localidad". $e->getMessage()
            ]);
        }
    $response->getBody()->write($payload);
    return $response->withStatus(400);
}});

// Eliminar Localidad
$app->delete('/localidades/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
        try{
            $connection = getConnection();
            $sql = "SELECT * FROM localidades WHERE id = '". $id ."'";
            $consulto_id = $connection->query($sql);
            /// Verificar si existe el id
            if ($consulto_id->rowCount()> 0){
                $sql = "DELETE FROM localidades WHERE id = '". $id ."' ";
                $query = $connection->query($sql);
                $query->fetch(PDO::FETCH_ASSOC);
                $response->getBody()->write(json_encode(['message'=> 'La localidad se elimino correctamente']));
                return $response->withStatus(201);
            }else{ $response->getBody()->write(json_encode(['error'=> 'La localidad a eliminar no existe']));
                return $response->withStatus(404);
            }  

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar la localidad". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withStatus(400);
});

// Listar Localidades
$app->get('/localidades', function(Request $request, Response $response){
    $connection = getConnection();

    try {
        $query = $connection->query('SELECT * FROM localidades ORDER BY id');
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

// ================================[ TIPO PROPIEDAD ]=========================================

// Crear Tipo de Propiedad
$app->post('/tipos_propiedad', function(Request $request, Response $response){
    $data = $request->getParsedBody();

    /// Verificar si existe el campo
    if (!isset($data['nombre'])){
        $response->getBody()->write(json_encode(['error'=> 'El campo nombre es requerido']));
        return $response->withStatus(400);
    }else{
        try{
            $connection = getConnection();
            $nombre = $data['nombre'];

            // Verificar si el nombre del tipo de propiedad supera los 50 caracteres
            if (strlen($nombre) > 50){
                $response->getBody()->write(json_encode(['error'=> 'El campo nombre excede los caracteres permitidos']));
                return $response->withStatus(400);
            }

            /// Verificar si el nombre del tipo de propiedad ya existe
            $sql ="SELECT * FROM tipo_propiedades WHERE nombre = '". $nombre ."'";
            $consulta_repetido = $connection->query($sql);
            if ($consulta_repetido->rowCount()> 0){
                $response->getBody()->write(json_encode(['error'=> 'El nombre no puede repetirse']));
                return $response->withStatus(400);

            /// Agrego el tipo de propiedad
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

// Editar Tipo de propiedad
$app->put('/tipos_propiedad/{id}', function(Request $request, Response $response, $args){
    $data = $request->getParsedBody();
    $id = $args['id'];

    /// verificar si existe el campo nombre
    if (!isset($data['nombre'])){
        $response->getBody()->write(json_encode(['error'=> 'El campo nombre con el nuevo nombre es requerido']));
         return $response->withStatus(400);
    }else{
        try{
            $connection = getConnection();
            $nombre = $data['nombre'];

            // Nombre del tipo de propiedad supera los 50 caracteres
            if (strlen($nombre) > 50){
                $response->getBody()->write(json_encode(['error'=> 'El campo nombre excede los caracteres permitidos']));
                return $response->withStatus(400);
            }
                    
            // Nombre de la localidad ya existe
            $sql ="SELECT * FROM tipo_propiedades WHERE nombre = '". $nombre ."' AND id != '". $id ."'";
            $consulta_repetido = $connection->query($sql);
            if ($consulta_repetido->rowCount()> 0){ 
                $response->getBody()->write(json_encode(['error'=> 'El nombre del tipo de localidad ya esta asignada a otra id']));
                return $response->withStatus(400);

            // Edita el tipo de propiedad
            }else{
                $sql = "SELECT * FROM tipo_propiedades WHERE id = '". $id ."'";
                $consulto_id = $connection->query($sql);
                /// Verificar si existe el campo y modificar
                if ($consulto_id->rowCount()> 0){
                     $sql = "UPDATE tipo_propiedades SET nombre = :nombre WHERE id = :id";
                     $consulta = $connection->prepare($sql);
                     $consulta->bindValue(":nombre", $nombre);
                     $consulta->execute();
                     $response->getBody()->write(json_encode(['message' => 'El tipo de propiedad con el id: '. $id . ' se edito de forma exitosa']));
                     return $response->withStatus(201);
    
                }else{ $response->getBody()->write(json_encode(['error'=> 'El tipo de propiedad con el id: '. $id . ' no existe']));
                        return $response->withStatus(404);
                }
            }   
        }catch(PDOException $e){
            $payload = json_encode([
                 'status' => "Bad Request",
                 'code' => 400,
                 'message' => "Error al editar el tipo de propiedad". $e->getMessage()
            ]);
        }
    $response->getBody()->write($payload);
    return $response->withStatus(400);
}});

// Eliminar Tipo de Propiedad
$app->delete('/tipos_propiedad/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
        try{
            $connection = getConnection();
            $sql = "SELECT * FROM tipo_propiedades WHERE id = '". $id ."'";
            $consulto_id = $connection->query($sql);
            /// Verificar si existe el id
            if ($consulto_id->rowCount()> 0){
                $sql = "DELETE FROM tipo_propiedades WHERE id = '". $id ."' ";
                $query = $connection->query($sql);
                $query->fetch(PDO::FETCH_ASSOC);
                $response->getBody()->write(json_encode(['message'=> 'El tipo de propiedad se elimino correctamente']));
                return $response->withStatus(201);
            }else{ $response->getBody()->write(json_encode(['error'=> 'El tipo de propiedad a eliminar no existe']));
                return $response->withStatus(404);
            }  

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar el tipo de propiedad". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
});

// Listar Tipo de Propiedad
$app->get('/tipos_propiedad', function(Request $request, Response $response){
    try {
        $connection = getConnection();

        $query = $connection->query('SELECT * FROM tipo_propiedades ORDER BY nombre');

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

// ================================[ INQUILINOS ]=========================================

/// Crear Inquilino
$app->post('/inquilinos', function(Request $request, Response $response){
    $data = $request->getParsedBody();

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
        /// Verificar si solo el email esta en uso
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

// Editar Inquilino
$app->put('/inquilinos/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    $data = $request->getParsedBody();

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
        /// Obtener los datos
        $nombre_usuario = $data['nombre_usuario'];
        $apellido = $data['apellido'];
        $nombre = $data['nombre'];
        $email = $data['email'];
        $activo = $data['activo']; 

        // Verificar si el inquilino existe
        $sql = "SELECT * FROM inquilinos WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount()> 0){
           
            // Verificar si el nombre de usuario ya esta en uso (Excluyendo el ID )
            $sql ="SELECT * FROM inquilinos WHERE nombre_usuario = '". $nombre_usuario ."' AND id != '". $id ."'" ;
            $consulta_repetido_usuario = $connection->query($sql);
            if ($consulta_repetido_usuario->rowCount()> 0){ 
                $response->getBody()->write(json_encode(['error'=> 'El nombre de usuario ya esta asignado a otra id']));
                return $response->withStatus(400);
            }

            // Verificar si el correo electrónico ya esta en uso (Excluyendo el ID )
            $sql ="SELECT * FROM inquilinos WHERE email = '". $email ."' AND id != '". $id ."'";
            $consulta_repetido_email = $connection->query($sql);
            if ($consulta_repetido_email->rowCount()> 0){ 
                $response->getBody()->write(json_encode(['error'=> 'El email de usuario ya esta asignado a otra id']));
                return $response->withStatus(400);
            }
            
            /// Editar Inquilino
            $sql = "UPDATE inquilinos SET nombre_usuario = :nombre_usuario, apellido = :apellido, 
                    nombre = :nombre, email = :email, activo = :activo WHERE id = '". $id ."'";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":nombre_usuario", $nombre_usuario);
            $consulta->bindValue(":apellido", $apellido);
            $consulta->bindValue(":nombre", $nombre);
            $consulta->bindValue(":email", $email);
            $consulta->bindValue(":activo", $activo);
            $consulta->execute();
            $response->getBody()->write(json_encode(['message' => 'El inquilino con el id: '. $id . ' se edito de forma exitosa']));
            return $response->withStatus(201);
            
       }else{ $response->getBody()->write(json_encode(['error'=> 'El inquilino con el id: '. $id . ' no existe']));
            return $response->withStatus(404);
        } 
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar el inquilino". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

/// Eliminar Inquilino
$app->delete('/inquilinos/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
        try{
            $connection = getConnection();
            $sql = "SELECT * FROM inquilinos WHERE id = '". $id ."'";
            $consulto_id = $connection->query($sql);
            /// Verificar si existe el id
            if ($consulto_id->rowCount()> 0){
                $sql = "DELETE FROM inquilinos WHERE id = '". $id ."' ";
                $query = $connection->query($sql);
                $query->fetch(PDO::FETCH_ASSOC);
                $response->getBody()->write(json_encode(['message'=> 'El inquilino se elimino correctamente']));
                return $response->withStatus(201);
            }else{ $response->getBody()->write(json_encode(['error'=> 'El inquilino a eliminar no existe']));
                return $response->withStatus(404);
            }  

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar el inquilino". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
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
            'message' => "Error al listar los inquilino ". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

/// Ver Inquilino
$app->get('/inquilinos/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];

    /// Verificar si el campo id recibe un valor numerico
    if (!is_numeric($id)){
        $response->getBody()->write(json_encode(['error'=> 'El campo ID debe ser un valor numerico']));
        return $response->withStatus(400); 
    }

    try {
        $connection = getConnection();
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
                return $response->withStatus(404);
         }  

    }catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al mostrar ese inquilino ". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withStatus(400);
});

/// Historial de reservas de un inquilino (falta completar)
$app->get('/inquilinos/$idInquilino/reservas', function(Request $request, Response $response){
});

// ================================[ PROPIEDADES ]=========================================

/// Crear Propiedad
$app->post('/propiedades', function(Request $request, Response $response){
    $data = $request->getParsedBody();

    /// Verificar si existen todos los campos requeridos
    $campos_requeridos =['domicilio', 'localidad_id', 'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'cantidad_días', 
                        'disponible', 'valor_noche', 'tipo_propiedad_id'];
    foreach($campos_requeridos as $campo){
        if (!isset($data[$campo])){
            $response->getBody()->write(json_encode(['error' => 'El campo '. $campo . ' es requerido']));
            return $response->withStatus(400);
        }
    }

    /// Verificar si el campo 'disponible' recibe 1(true) o 0 (false)
    if($data['disponible'] !== '1' && $data['disponible']!== '0' ){
        $response->getBody()->write(json_encode(['error' => 'El campo disponible debe ser 1 (true) o 0 (false)']));
        return $response->withStatus(400);     
        
    }try{
        $connection = getConnection();
        /// Variables para verificar si las IDs existen
        $localidad_id = $data['localidad_id'];
        $tipo_propiedad_id = $data['tipo_propiedad_id'];
        
        /// Verificar si el ID de localidad existe en la tabla localidades
        $sql = "SELECT * FROM localidades WHERE id = '".$localidad_id."'";
        $consulta_localidad = $connection()->query($sql);
        if($consulta_localidad->rowCount() == 0){
            $response->getBody()->write(json_encode(['error'=> 'El ID no existe en la tabla localidades']));
            return $response->withStatus(400);
        }

        /// Verificar si el ID de tipo_propiedades existe en la tabla tipo_propiedades
        $sql = "SELECT * FROM tipo_propiedades WHERE id = '".$tipo_propiedad_id."'";
        $consulta_tipo_propiedades = $connection()->query($sql);
        if($consulta_tipo_propiedades->rowCount() == 0){
            $response->getBody()->write(json_encode(['error'=> 'El ID no existe en la tabla tipo_propiedades']));
            return $response->withStatus(400);
        }
    
        /// Agrego La Propiedad
        $sql = "INSERT INTO propiedades (domicilio, localidad_id, cantidad_habitaciones, cantidad_banios, cochera, cantidad_huespedes,
                fecha_inicio_disponibilidad, cantidad_días, disponible, valor_noche, tipo_propiedad_id, imagen, tipo_imagen) 
                VALUES (:domicilio, :localidad_id, :cantidad_habitaciones, :cantidad_banios, :cochera, :cantidad_huespedes, 
                :fecha_inicio_disponibilidad, :cantidad_días, :disponible, :valor_noche, :tipo_propiedad_id, :imagen, :tipo_imagen)";
        $consulta = $connection->prepare($sql);
        $consulta->bindValue(":domicilio", $data['domicilio']);
        $consulta->bindValue(":localidad_id", $data['localidad_id']);
        $consulta->bindValue(":cantidad_habitaciones", $data['cantidad_habitaciones']?? null);
        $consulta->bindValue(":cantidad_banios", $data['cantidad_banios']?? null);
        $consulta->bindValue(":cochera", $data['cochera']?? null);
        $consulta->bindValue(":cantidad_huespedes", $data['cantidad_huespedes']);
        $consulta->bindValue(":fecha_inicio_disponibilidad", $data['fecha_inicio_disponibilidad']);
        $consulta->bindValue(":cantidad_días", $data['cantidad_días']);
        $consulta->bindValue(":disponible", $data['disponible']);
        $consulta->bindValue(":valor_noche", $data['valor_noche']);
        $consulta->bindValue(":tipo_propiedad_id", $data['tipo_propiedad_id']);
        $consulta->bindValue(":imagen", $data['imagen']?? null);
        $consulta->bindValue(":tipo_imagen", $data['tipo_imagen']?? null);
        $consulta->execute();
        /// En los campos que no son requeridos les asigno null para tener un valor que se pueda vincular a la consulta sql
        $response->getBody()->write(json_encode(['Message'=> 'Propiedad Creada Correctamente']));
        return $response->withStatus(200);

    }catch (PDOException $e){ 
        $response->getBody()->write(json_encode([
            'status' => "Bad Request",
            'message' => "Error al crear el inquilino". $e->getMessage()]));
            return $response->withStatus(400);
    }
});

// Editar Propiedad
$app->put('/propiedades/{id}', function(Request $request, Response $response, $args){

    $id = $args['id'];
    $data = $request->getParsedBody();

    /// Verificar si existen todos los campos
    $campos_requeridos =['domicilio', 'localidad_id', 'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'cantidad_dias', 
    'disponible', 'valor_noche', 'tipo_propiedad_id'];
    foreach($campos_requeridos as $campo){
        if(!isset($data[$campo])){
            $response->getBody()->write(json_encode(['error' => 'El campo '. $campo . ' es requerido']));
            return $response->withStatus(400);
        }
    }

    /// Verificar si el campo 'disponible' recibe 1(true) o 0 (false) (no es necesario esto)
    if($data['disponible'] !== '1' && $data['disponible']!== '0' ){
        $response->getBody()->write(json_encode(['error' => 'El campo disponible debe ser 1 (true) o 0 (false)']));
        return $response->withStatus(400);       
    }

    try{
        $connection = getConnection(); 
        /// Obtener los datos
        $domicilio = $data['domicilio'];
        $localidad_id = $data['localidad_id'];
        $cantidad_huespedes = $data['cantidad_huespedes'];
        $fecha_inicio_disponibilidad = $data['fecha_inicio_disponibilidad'];
        $cantidad_dias = $data['cantidad_dias']; 
        $disponible = $data['disponible']; 
        $valor_noche = $data['valor_noche']; 
        $tipo_propiedad_id = $data['tipo_propiedad_id']; 


        // Verificar si la propiedad existe
        $sql = "SELECT * FROM propiedades WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount()> 0){   
            /// Editar Propiedad
            $sql = "UPDATE propiedades SET domicilio = :domicilio, localidad_id = :localidad_id, 
                    cantidad_huespedes = :cantidad_huespedes, fecha_inicio_disponibilidad = :fecha_inicio_disponibilidad,
                    cantidad_dias = :cantidad_dias, disponible = :disponible, valor_noche = :valor_noche,
                    tipo_propiedad_id = :tipo_propiedad_id  WHERE id = '". $id ."'";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":domicilio", $domicilio);
            $consulta->bindValue(":localidad_id", $localidad_id);
            $consulta->bindValue(":cantidad_huespedes", $cantidad_huespedes);
            $consulta->bindValue(":fecha_inicio_disponibilidad", $fecha_inicio_disponibilidad);
            $consulta->bindValue(":cantidad_dias", $cantidad_dias);
            $consulta->bindValue(":disponible", $disponible);
            $consulta->bindValue(":valor_noche", $valor_noche);
            $consulta->bindValue(":tipo_propiedad_id", $tipo_propiedad_id);
            $consulta->execute();
            $response->getBody()->write(json_encode(['message' => 'La propiedad con el id: '. $id . ' se edito de forma exitosa']));
            return $response->withStatus(201);
            
       }else{ $response->getBody()->write(json_encode(['error'=> 'La propiedad con el id: '. $id . ' no existe']));
            return $response->withStatus(404);
        } 
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar la propiedad". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});    

/// Eliminar Propiedad
$app->delete('/propiedades/{id}', function(Request $request, Response $response){
    $id = $args['id'];
        try{
            $connection = getConnection();
            $sql = "SELECT * FROM propiedades WHERE id = '". $id ."'";
            $consulto_id = $connection->query($sql);
            /// Verificar si existe el id
            if ($consulto_id->rowCount()> 0){
                $sql = "DELETE FROM propiedades WHERE id = '". $id ."' ";
                $query = $connection->query($sql);
                $query->fetch(PDO::FETCH_ASSOC);
                $response->getBody()->write(json_encode(['message'=> 'La propiedad se elimino correctamente']));
                return $response->withStatus(201);
            }else{ $response->getBody()->write(json_encode(['error'=> 'La propiedad a eliminar no existe']));
                return $response->withStatus(404);
            }  

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar la propiedad". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
});

/// Listar Propiedades
$app->get('/propiedades', function(Request $request, Response $response){
    $connection = getConnection();

    try {
        $query = $connection->query('SELECT * FROM propiedades ORDER BY id');
        $propiedades = $query->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $propiedades
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

/// Ver Propiedad
$app->get('/propiedades/{id}', function(Request $request, Response $response, $args){
    $connection = getConnection();
    $id = $args['id'];
    try {
        $sql = "SELECT * FROM propiedades WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        /// Verificar si existe el campo
        if ($consulto_id->rowCount()> 0){
            $propiedadx = $consulto_id->fetch(PDO::FETCH_ASSOC);
            $response->getBody()->write(json_encode([$propiedadx]));
            return $response->withStatus(201);

        }else{ $response->getBody()->write(json_encode(['error'=> 'La propiedad no existe']));
            return $response->withStatus(404);
        }  
    } catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al mostrar la propiedad". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

// ================================[ RESERVAS ]=========================================

/// Crear Reserva (falta hacer)
$app->post('/reservas', function(Request $request, Response $response){
    $data = $request->getParsedBody();


    try{
        $connection = getConnection();

        /// Verificar si existen todos los campos requeridos
        $campos_requeridos =['id', 'propiedad_id', 'inquilino_id', 'fecha_desde', 'cantidad_noches', 
        'valor_total'];
        foreach($campos_requeridos as $campo){
            if (!isset($data[$campo])){
            $response->getBody()->write(json_encode(['error' => 'El campo '. $campo . ' es requerido']));
            return $response->withStatus(400);
            }
        }
        /// Variables para verificar si las IDs existen
        $propiedad_id = $data['propiedad_id'];
        $inquilino_id = $data['inquilino_id'];

        /// Verificar si el ID de propiedad existe en la tabla propiedad
        $sql = "SELECT * FROM propiedad WHERE id = '". $propiedad_id ."'";
        $consulta_propiedad = $connection()->query($sql);
        if($consulta_propiedad->rowCount() == 0){
            $response->getBody()->write(json_encode(['error'=> 'El ID no existe en la tabla propiedades']));
            return $response->withStatus(400);
        }

        /// Verificar si el ID de inquilino existe en la tabla inquilino
        $sql = "SELECT * FROM tipo_propiedades WHERE id = '". $inquilino_id ."'";
        $consulta_inqulino_id = $connection()->query($sql);
        if($consulta_inqulino_id->rowCount() == 0){
            $response->getBody()->write(json_encode(['error'=> 'El ID no existe en la tabla inquilino']));
            return $response->withStatus(400);
        }
    
        /// Agrego La Reserva
        $sql = "INSERT INTO reservas (id, propiedad_id, inquilino_id, fecha_desde, cantidad_noches, valor_total) 
                VALUES (:domicilio, :localidad_id, :cantidad_habitaciones, :cantidad_banios, :cochera, :cantidad_huespedes, 
                :fecha_inicio_disponibilidad, :cant0idad_días, :disponible, :valor_noche, :moneda_id, :tipo_propiedad_id, :imagen, :tipo_imagen)";
        $consulta = $connection->prepare($sql);
        $consulta->bindValue(":domicilio", $data['domicilio']);
        $consulta->bindValue(":localidad_id", $data['localidad_id']);
        $consulta->bindValue(":cantidad_habitaciones", $data['cantidad_habitaciones']?? null);
        $consulta->bindValue(":cantidad_banios", $data['cantidad_banios']?? null);
        $consulta->bindValue(":cochera", $data['cochera']?? null);
        $consulta->bindValue(":cantidad_huespedes", $data['cantidad_huespedes']);
        $consulta->bindValue(":fecha_inicio_disponibilidad", $data['fecha_inicio_disponibilidad']);
        $consulta->bindValue(":cantidad_días", $data['cantidad_días']);
        $consulta->bindValue(":disponible", $data['disponible']);
        $consulta->bindValue(":valor_noche", $data['valor_noche']);
        $consulta->bindValue(":moneda_id", $data['moneda_id']);
        $consulta->bindValue(":tipo_propiedad_id", $data['tipo_propiedad_id']);
        $consulta->bindValue(":imagen", $data['imagen']?? null);
        $consulta->bindValue(":tipo_imagen", $data['tipo_imagen']?? null);
        $consulta->execute();
        /// En los campos que no son requeridos les asigno null para tener un valor que se pueda vincular a la consulta sql
        $response->getBody()->write(json_encode(['Message'=> 'Reserva Creada Correctamente']));
        return $response->withStatus(200);

    }catch (PDOException $e){ 
        $response->getBody()->write(json_encode([
            'status' => "Bad Request",
            'message' => "Error al crear la reserva". $e->getMessage()]));
            return $response->withStatus(400);
    }
});

// Editar Reserva (falta hacer)
$app->put('/reservas/{id}', function(Request $request, Response $response, $args){

    $id = $args['id'];
    $data = $request->getParsedBody();

    /// Verificar si existen todos los campos
    $campos_requeridos =['domicilio', 'localidad_id', 'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'cantidad_dias', 
    'disponible', 'valor_noche', 'tipo_propiedad_id'];
    foreach($campos_requeridos as $campo){
        if(!isset($data[$campo])){
            $response->getBody()->write(json_encode(['error' => 'El campo '. $campo . ' es requerido']));
            return $response->withStatus(400);
        }
    }

    /// Verificar si el campo 'disponible' recibe 1(true) o 0 (false) (no es necesario esto)
    if($data['disponible'] !== '1' && $data['disponible']!== '0' ){
        $response->getBody()->write(json_encode(['error' => 'El campo disponible debe ser 1 (true) o 0 (false)']));
        return $response->withStatus(400);       
    }

    try{
        $connection = getConnection(); 
        /// Obtener los datos
        $domicilio = $data['domicilio'];
        $localidad_id = $data['localidad_id'];
        $cantidad_huespedes = $data['cantidad_huespedes'];
        $fecha_inicio_disponibilidad = $data['fecha_inicio_disponibilidad'];
        $cantidad_dias = $data['cantidad_dias']; 
        $disponible = $data['disponible']; 
        $valor_noche = $data['valor_noche']; 
        $tipo_propiedad_id = $data['tipo_propiedad_id']; 


        // Verificar si la propiedad existe
        $sql = "SELECT * FROM propiedades WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount()> 0){   
            /// Editar Propiedad
            $sql = "UPDATE propiedades SET domicilio = :domicilio, localidad_id = :localidad_id, 
                    cantidad_huespedes = :cantidad_huespedes, fecha_inicio_disponibilidad = :fecha_inicio_disponibilidad,
                    cantidad_dias = :cantidad_dias, disponible = :disponible, valor_noche = :valor_noche,
                    tipo_propiedad_id = :tipo_propiedad_id  WHERE id = '". $id ."'";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":domicilio", $domicilio);
            $consulta->bindValue(":localidad_id", $localidad_id);
            $consulta->bindValue(":cantidad_huespedes", $cantidad_huespedes);
            $consulta->bindValue(":fecha_inicio_disponibilidad", $fecha_inicio_disponibilidad);
            $consulta->bindValue(":cantidad_días", $cantidad_dias);
            $consulta->bindValue(":disponible", $disponible);
            $consulta->bindValue(":valor_noche", $valor_noche);
            $consulta->bindValue(":tipo_propiedad_id", $tipo_propiedad_id);
            $consulta->execute();
            $response->getBody()->write(json_encode(['message' => 'La propiedad con el id: '. $id . ' se edito de forma exitosa']));
            return $response->withStatus(201);
            
       }else{ $response->getBody()->write(json_encode(['error'=> 'La propiedad con el id: '. $id . ' no existe']));
            return $response->withStatus(404);
        } 
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar la propiedad". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});    

/// Eliminar Reserva (falta hacer)
$app->delete('/reservas/{id}', function(Request $request, Response $response){
    $id = $args['id'];
        try{
            $connection = getConnection();
            $sql = "SELECT * FROM propiedades WHERE id = '". $id ."'";
            $consulto_id = $connection->query($sql);
            /// Verificar si existe el id
            if ($consulto_id->rowCount()> 0){
                $sql = "DELETE FROM propiedades WHERE id = '". $id ."' ";
                $query = $connection->query($sql);
                $query->fetch(PDO::FETCH_ASSOC);
                $response->getBody()->write(json_encode(['message'=> 'La propiedad se elimino correctamente']));
                return $response->withStatus(201);
            }else{ $response->getBody()->write(json_encode(['error'=> 'La propiedad a eliminar no existe']));
                return $response->withStatus(404);
            }  

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar la propiedad". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
});

/// Listar Reservas (falta hacer)
$app->get('/reservas', function(Request $request, Response $response){
    $connection = getConnection();

    try {
        $query = $connection->query('SELECT * FROM propiedades ORDER BY id');
        $propiedades = $query->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $propiedades
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

$app->run();


/*         /// Seteo en null los campos  no requeridos
        $campos_norequeridos =['id', 'propiedad_id', 'inquilino_id', 'fecha_desde', 'cantidad_noches', 
        'valor_total'];
        foreach($campos_requeridos as $campo){
            if (!isset($data[$campo])){
            $response->getBody()->write(json_encode(['error' => 'El campo '. $campo . ' es requerido']));
            return $response->withStatus(400);
            }
        }
*/
