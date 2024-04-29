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
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();

        /// verificar si existe el campo nombre
        if (!isset($data['nombre'])){
            $errores['nombre'] = 'El campo nombre es requerido';
            $codeerro['nombre'] = 404;
        }
        else{
            $nombre = $data['nombre'];
            // Verificar si el nombre de la localidad supera los 50 caracteres
            if (isset($data['nombre']) && strlen($data['nombre']) > 50){
                $errores['nombremax'] = 'El campo nombre excede los caracteres permitidos';
                $codeerro['nombremax'] = 400;
            }
            else{
                // Verificar si el nombre de la localidad ya existe
                $sql ="SELECT * FROM localidades WHERE nombre = '". $nombre ."'";
                $consulta_repetido = $connection->query($sql);
                if ($consulta_repetido->rowCount()> 0){ 
                    $errores['nombrerepe'] = 'El  nombre no puede repetirse';
                    $codeerro['nombrerepe'] = 400;
                }
            }    
        } 

        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }

        // Agrega la localidad
        else{
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
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// Editar Localidad
$app->put('/localidades/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    $data = $request->getParsedBody();
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();
        /// verificar si existe el campo nombre
        if (!isset($data['nombre'])){
            $errores['nombre'] = 'El campo nombre con el nuevo nombre es requerido';
            $codeerro['nombre'] = 404;
        }
        else{
            $nombre = $data['nombre'];
            // Verificar si el nombre de la localidad supera los 50 caracteres
            if (isset($data['nombre']) && strlen($data['nombre']) > 50){
                $errores['nombremax'] = 'El campo nombre excede los caracteres permitidos';
                $codeerro['nombremax'] = 400;
            }
            else{
                // Nombre de la localidad ya existe verifico
                $sql ="SELECT * FROM localidades WHERE nombre = '". $nombre ."' AND id != '". $id ."'";
                $consulta_repetido = $connection->query($sql);
                if ($consulta_repetido->rowCount()> 0){ 
                    $errores['nombreya'] = 'El nombre de la localidad ya esta asignado a otra id';
                    $codeerro['nombreya'] = 400;
                }
            }
        }
        /// Verificar si existe el id
        $sql = "SELECT * FROM localidades WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount()  <= 0){
            $errores['id']= 'La localidad con el id: '. $id . ' no existe';
            $codeerro['id'] = 400;
        }
        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }             
        // Edita la localidad
        else {
            $sql = "UPDATE localidades SET nombre = :nombre WHERE id =  '". $id ."'";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":nombre", $nombre);
            $consulta->execute();
            $response->getBody()->write(json_encode(['message' => 'La localidad con el id: '. $id . ' se edito de forma exitosa']));
            return $response->withStatus(201);
        } 
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar la localidad". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

// Eliminar Localidad (no deja eliminar localidad 1)
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
                $code=204;
            }else{ $response->getBody()->write(json_encode(['error'=> 'La localidad a eliminar no existe']));
                $code=404;
            }  
            return $response->withStatus($code);

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar la localidad". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

// ================================[ TIPO PROPIEDAD ]=========================================

// Crear Tipo de Propiedad 
$app->post('/tipos_propiedad', function(Request $request, Response $response){
    $data = $request->getParsedBody();
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();
        /// Verificar si existe el campo
        if (!isset($data['nombre'])){
            $errores['nombre'] = 'El campo nombre es requerido';
            $codeerro['nombre'] = 404;
        }
        else{
            $nombre = $data['nombre'];
            // Verificar si el nombre del tipo de propiedad supera los 50 caracteres
            if(isset($data['nombre']) && strlen($data['nombre']) > 50){
                $errores['nombreexede'] = 'El campo nombre excede los caracteres permitidos';
                $codeerro['nombreexede'] = 400;
            }
            else{
                /// Verificar si el nombre del tipo de propiedad ya existe
                $sql ="SELECT * FROM tipo_propiedades WHERE nombre = '". $nombre ."'";
                $consulta_repetido = $connection->query($sql);
                if ($consulta_repetido->rowCount()> 0){
                    $errores['nombrerepe'] = 'El nombre no puede repetirse';
                    $codeerro['nombrerepe'] = 400;
                }
            }
        }
        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }
        /// Agrego el tipo de propiedad
        else {
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
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// Editar Tipo de propiedad 
$app->put('/tipos_propiedad/{id}', function(Request $request, Response $response, $args){
    $data = $request->getParsedBody();
    $id = $args['id'];
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();
        /// Verificar si existe el campo nombre
        if (!isset($data['nombre'])){
            $errores['nombre'] = 'El campo nombre con el nuevo nombre es requerido';
            $codeerro['nombre'] = 404;
        }
        else{
            $nombre = $data['nombre'];
            // Verificar si el nombre del tipo de propiedad supera los 50 caracteres
            if (isset($data['nombre']) && strlen($data['nombre']) > 50){
                $errores['nomremax'] = 'El campo nombre excede los caracteres permitidos';
                $codeerro['nombremax'] = 400;
            }
            else {
                // Nombre de la localidad ya existe 
                $sql ="SELECT * FROM tipo_propiedades WHERE nombre = '". $nombre ."' AND id != '". $id ."'";
                $consulta_repetido = $connection->query($sql);
                if ($consulta_repetido->rowCount()> 0)
                    $errores['nombreya'] = 'El nombre de la localidad ya esta asignado a otra id';
                    $codeerro['nombreya'] = 400;
            }
        }
        // Verifico si existe el ID
        $sql = "SELECT * FROM tipo_propiedades WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount() <=0){
            $errores['id']= 'El tipo de propiedad con el id: '. $id . ' no existe';
            $codeerro['id'] = 400;
        }    
        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }
        // Edito el tipo de propiedad
        else{
            $sql = "UPDATE tipo_propiedades SET nombre = :nombre WHERE id = '". $id ."'";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":nombre", $nombre);
            $consulta->execute();
            $response->getBody()->write(json_encode(['message' => 'El tipo de propiedad con el id: '. $id . ' se edito de forma exitosa']));
            return $response->withStatus(201);
        }
    }catch(PDOException $e){
    $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar el tipo de propiedad". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});


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
                $code=204;
            }else{ 
                $response->getBody()->write(json_encode(['error'=> 'El tipo de propiedad a eliminar no existe']));
                $code=404;
            }  
            return $response->withStatus($code);
            

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar el tipo de propiedad". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

// ================================[ INQUILINOS ]=========================================

/// Crear Inquilino 
$app->post('/inquilinos', function(Request $request, Response $response){
    $data = $request->getParsedBody();
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();

        /// Verificar si existen todos los campos
        $campos_requeridos =['documento', 'apellido', 'nombre', 'email', 'activo'];
        foreach($campos_requeridos as $campo){
            if(!isset($data[$campo])){
            $errores[$campo] = 'El campo '. $campo . ' es requerido';
            $codeerro[$campo] = 404;
            }
        }

        /// Verificar el limite de caracteres de todos los campos
        $max_longitudes = ['documento' => 20, 'apellido' => 15, 'nombre' => 25, 'email' => 20];
        foreach($max_longitudes as $campo => $max_longitud){
            if (isset($data[$campo]) && strlen($data[$campo]) > $max_longitud){
                $errores[$campo.' limite'] = 'El campo '.$campo.' supera los caracteres permitidos';          
                $codeerro[$campo.' limite'] = 400;
            }
        }
        
        /// Verificaciones campo 'correo'
        if  (isset($data['email'])) {
            $email = $data['email'];
            /// Verificar si el campo 'correo' tiene un formato valido
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
                $errores['email_formato'] = 'El campo email tiene un formato no valido';
                $codeerro['email_formato'] = 404;
            }else{
                $sql2 = "SELECT * FROM inquilinos WHERE email = '". $email ."'";
                $email_repetido = $connection->query($sql2);
                /// Verificar si el email ya esta en uso
                if ($email_repetido->rowCount() > 0){
                    $errores['email_rep'] = 'El email ya esta en uso';
                    $codeerro['email_rep'] = 400;
                }
            }
        }

        /// Verificar si el campo 'activo' recibe 1(true) o 0 (false)
        if(isset($data['activo']) && $data['activo'] !== '1' && $data['activo']!== '0' ){ 
            $errores['activo_formato'] = 'El campo activo debe ser 1 (true) o 0 (false)';       
            $codeerro['activo_formato'] = 404;
        }

        /// Verificaciones campo 'documeto '
        if (isset($data['documento'])) { 
            $documento = $data['documento'];
            $sql = "SELECT * FROM inquilinos WHERE documento = '". $documento ."'";
            $documeto_repetido = $connection->query($sql);
            /// Verificar si el documento ya existe o esta en uso
            if ($documeto_repetido->rowCount() > 0){
                $errores['documento_rep'] = 'El documento ya esta en uso';
                $codeerro['documento_rep'] = 400;
            }
        }      

        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }
        else{
            $activo = $data['activo'];
            $apellido = $data['apellido'];
            $nombre = $data['nombre']; 
            /// Agrego el inquilino
            $sql = "INSERT INTO inquilinos (documento, apellido, nombre, email, activo) 
            VALUES (:documento, :apellido, :nombre, :email, :activo)";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue("documento", $documento);
            $consulta->bindValue("apellido", $apellido);
            $consulta->bindValue("nombre", $nombre);
            $consulta->bindValue("email", $email);
            $consulta->bindValue("activo", $activo);
            $consulta->execute();

            $response->getBody()->write(json_encode(['message'=> 'Inquilino agregado correctamente']));
            return $response->withStatus(201);
        }
    }catch (PDOException $e){ 
    $response->getBody()->write(json_encode([
        'status' => "Bad Request",
        'message' => "Error al crear el inquilino". $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// Editar Inquilino 
$app->put('/inquilinos/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    $data = $request->getParsedBody();
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection(); 
        /// Verificar si existen todos los campos
        $campos_requeridos =['documento', 'apellido', 'nombre', 'email', 'activo'];
        foreach($campos_requeridos as $campo){
            if(!isset($data[$campo])){
            $errores[$campo] = 'El campo '. $campo . ' es requerido';
            $codeerro[$campo] = 404;
            }
        }
        
        /// Verificar el limite de caracteres de todos los campos
        $max_longitudes = ['documento' => 20, 'apellido' => 15, 'nombre' => 25, 'email' => 20];
        foreach($max_longitudes as $campo => $max_longitud){
            if (isset($data[$campo]) && strlen($data[$campo]) > $max_longitud){
                $errores[$$campo.' limite'] = 'El campo '.$campo.' supera los caracteres permitidos';          
                $codeerro[$campo.' limite'] = 400;
            }
        }

        /// Verificaciones campo 'correo'
        if  (isset($data['email'])) {
            $email = $data['email'];
            /// Verificar si el campo 'correo' tiene un formato valido
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
                $errores['email_formato'] = 'El campo email tiene un formato no valido';
                $codeerro['email_formato'] = 404;
            }else{   
                $sql2 ="SELECT * FROM inquilinos WHERE email = '". $email ."' AND id != '". $id ."'";
                $consulta_repetido_email = $connection->query($sql2);
                // Verificar si el correo electrónico ya esta en uso (Excluyendo el ID )
                if ($consulta_repetido_email->rowCount()> 0){ 
                    $errores['email_repetido'] = 'El email ya esta asignado a otra id';
                    $codeerro['email_repetido'] = 400;
                }
            }
        }

        /// Verificar si el campo 'activo' recibe 1(true) o 0 (false)
        if(isset($data['activo']) && $data['activo'] !== '1' && $data['activo']!== '0' ){ 
            $errores['activo'] = 'El campo activo debe ser 1 (true) o 0 (false)';       
            $codeerro['activo_formato'] = 404;
        }

        /// Verificaciones campo 'documeto '
        if(isset($data['documento'])) {
            $documento = $data['documento'];
            $sql ="SELECT * FROM inquilinos WHERE documento = '". $documento ."' AND id != '". $id ."'" ;
            $consulta_repetido_usuario = $connection->query($sql);
            // Verificar si el documento ya esta en uso (Excluyendo el ID )
            if ($consulta_repetido_usuario->rowCount()> 0){ 
                $errores['documento_repetido'] = 'El documento ya esta asignado a otra id';   
                $codeerro['documento_repetido'] = 400;
            }
        }

        // Verificar si el inquilino existe
        $sql = "SELECT * FROM inquilinos WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount()<= 0){
            $errores['inquilino_existe'] = 'El inquilino con el id: '. $id . ' no existe';
            $codeerro['inquilino_existe'] = 400;
        }
        
        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }
        else{
            $activo = $data['activo'];
            $apellido = $data['apellido'];
            $nombre = $data['nombre'];  
            /// Editar Inquilino
            $sql = "UPDATE inquilinos SET documento = :documento, apellido = :apellido, 
                    nombre = :nombre, email = :email, activo = :activo WHERE id = '". $id ."'";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":documento", $documento);
            $consulta->bindValue(":apellido", $apellido);
            $consulta->bindValue(":nombre", $nombre);
            $consulta->bindValue(":email", $email);
            $consulta->bindValue(":activo", $activo);
            $consulta->execute();
            $response->getBody()->write(json_encode(['message' => 'El inquilino con el id: '. $id . ' se edito de forma exitosa']));
            return $response->withStatus(201);
        }
            
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar el inquilino". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
                $code=204;
            }else{ 
                $response->getBody()->write(json_encode(['error'=> 'El inquilino a eliminar no existe']));
                $code=404;
            }  
            return $response->withStatus($code);

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar el inquilino". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

/// Ver Inquilino
$app->get('/inquilinos/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    try {
        $connection = getConnection();
        $sql = "SELECT * FROM inquilinos WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        /// Verificar si existe el campo
         if ($consulto_id->rowCount()> 0){

                $query = $connection->query($sql);
                $inquilinox = $query->fetch(PDO::FETCH_ASSOC);
                $response->getBody()->write(json_encode(['message'=> 'El inquilino existe: ']) . json_encode([$inquilinox]));
                $code=200;

        }else{ 
            $response->getBody()->write(json_encode(['error'=> 'El inquilino con id: '.$id.' no existe']));
            $code=404;
         }  
         return $response->withStatus($code);

    }catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al mostrar los datos del inquilino ". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

/// Historial de reservas de un inquilino
$app->get('/inquilinos/{idInquilino}/reservas', function(Request $request, Response $response, $args){
    $idInquilino = $args['idInquilino'];
    try{
        $connection = getConnection();
        // Verificar si el inquilino existe
        $sql = "SELECT * FROM inquilinos WHERE id = '". $idInquilino ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount()> 0){
            // Consulta para obtener las reservas del inquilino
            $sql = "SELECT * FROM reservas INNER JOIN propiedades ON reservas.propiedad_id = propiedades.id
            WHERE reservas.inquilino_id = '" .$idInquilino. "'";
            $consulta_reservas = $connection->query($sql);
            $reservas = $consulta_reservas->fetchAll(PDO::FETCH_ASSOC);

            /// Limpiar campos null y volver mas estetico
            $mostrar='';
            $num= 0;
            foreach($reservas as $reserva){
                $num++;
                $mostrar .= '--- Reserva: '. $num .' ---' . '<br>';
                foreach($reserva as $campo => $valor){
                    if ($valor != null) $mostrar .= '<br>' .$campo . ':'. $valor . '<br>';
                }
                $mostrar .= '<br>';
            }
            
            // Verificar si el inquilino tiene reservas
            if (!$reservas){
                $response->getBody()->write(json_encode(['message'=> 'El inquilino no tiene reservas']));
                $code=404;
            }
            else {
                $response->getBody()->write(json_encode($mostrar));
                $code=200;
            }
        
        }else{
            $response->getBody()->write(json_encode(['error'=> 'El inquilino con el id: '. $idInquilino . ' no existe']));
            $code=404;
        }
        return $response->withStatus($code);

    }catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al mostrar historial de reservas del inquilino ". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

// ================================[ PROPIEDADES ]=========================================

/// Crear Propiedad
$app->post('/propiedades', function(Request $request, Response $response){
    $data = $request->getParsedBody();
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();

        /// Verificar si existen todos los campos requeridos
        $campos_requeridos =['domicilio', 'localidad_id', 'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'cantidad_dias', 
        'disponible', 'valor_noche', 'tipo_propiedad_id'];
        foreach($campos_requeridos as $campo){
            if(!isset($data[$campo])){
            $errores[$campo] = 'El campo '. $campo . ' es requerido';
            $codeerro[$campo] = 404;
            }
        }

        /// Verificar si el campo 'activo' recibe 1(true) o 0 (false)
        if(isset($data['activo']) && $data['activo'] !== '1' && $data['activo']!== '0' ){
            $errores['activo_formato'] = 'El campo activo debe ser 1 (true) o 0 (false)';          
            $codeerro['activo_formato'] = 404;
        }

        // Verificar si la fecha tiene un formato correcto
        if (isset($data['fecha_inicio_disponibilidad'])) {
            $fecha_inicio_disponibilidad = $data['fecha_inicio_disponibilidad'];
            $formato_correcto = 'Y-m-d';
        
            $fecha_obj = DateTime::createFromFormat($formato_correcto, $fecha_inicio_disponibilidad);
            if ($fecha_obj === false || $fecha_obj->format($formato_correcto) !== $fecha_inicio_disponibilidad) {
                // La fecha no tiene el formato correcto
                $errores['fecha_formato'] = 'La fecha tiene un formato incorrecto. El formato esperado es: ' . $formato_correcto;
                $codeerro['fecha_formato'] = 404;
            }
        }
        /// Verificaciones campo 'domicilio, localidad_id,tipo_propiedad_id'
        if (isset($data['domicilio']) && isset($data['localidad_id']) && isset($data['tipo_propiedad_id'])) {
            /// Variables para verificar las consultas
            $domicilio = $data['domicilio'];
            $localidad_id = $data['localidad_id'];
            $tipo_propiedad_id = $data['tipo_propiedad_id'];
            // Verificar si el nombre del domicilio de la propiedad ya existe
            $sql ="SELECT * FROM propiedades WHERE domicilio = '". $domicilio ."'";
            $consulta_repetido = $connection->query($sql);
            if ($consulta_repetido->rowCount()> 0){
                $errores['domicilio_repetido'] = 'El nombre del domicilio ya esta en uso';
                $codeerro['domicilio_repetido'] = 400;
            }

            /// Verificar si el ID de localidad existe en la tabla localidades
            $sql = "SELECT * FROM localidades WHERE id = '".$localidad_id."'";
            $consulta_localidad = $connection->query($sql);
            if($consulta_localidad->rowCount() == 0){
                $errores['localidad_existe'] = 'El ID '.$localidad_id.' no existe en la tabla localidades';
                $codeerro['localidad_existe'] = 400;
            }

            /// Verificar si el ID de tipo_propiedades existe en la tabla tipo_propiedades
            $sql = "SELECT * FROM tipo_propiedades WHERE id = '".$tipo_propiedad_id."'";
            $consulta_tipo_propiedades = $connection->query($sql);
            if($consulta_tipo_propiedades->rowCount() == 0){
                $errores['tipo_propiedad_existe'] = 'El ID '.$tipo_propiedad_id.' no existe en la tabla tipo_propiedades';
                $codeerro['tipo_propiedad_existe'] = 400;
            }
        }
        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }
        else{
            $cantidad_huespedes = $data['cantidad_huespedes'];
            $cantidad_dias = $data['cantidad_dias'];
            $disponible = $data['disponible'];
            $valor_noche = $data['valor_noche'];
            /// Agrego La Propiedad
            $sql = "INSERT INTO propiedades (domicilio, localidad_id, cantidad_habitaciones, cantidad_banios, cochera, cantidad_huespedes,
                    fecha_inicio_disponibilidad, cantidad_dias, disponible, valor_noche, tipo_propiedad_id, imagen, tipo_imagen) 
                    VALUES (:domicilio, :localidad_id, :cantidad_habitaciones, :cantidad_banios, :cochera, :cantidad_huespedes, 
                    :fecha_inicio_disponibilidad, :cantidad_dias, :disponible, :valor_noche, :tipo_propiedad_id, :imagen, :tipo_imagen)";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":domicilio", $domicilio);
            $consulta->bindValue(":localidad_id", $localidad_id);
            $consulta->bindValue(":cantidad_habitaciones", $data['cantidad_habitaciones']?? null);
            $consulta->bindValue(":cantidad_banios", $data['cantidad_banios']?? null);
            $consulta->bindValue(":cochera", $data['cochera']?? null);
            $consulta->bindValue(":cantidad_huespedes", $cantidad_huespedes);
            $consulta->bindValue(":fecha_inicio_disponibilidad", $fecha_inicio_disponibilidad);
            $consulta->bindValue(":cantidad_dias", $cantidad_dias);
            $consulta->bindValue(":disponible", $disponible);
            $consulta->bindValue(":valor_noche", $valor_noche);
            $consulta->bindValue(":tipo_propiedad_id", $tipo_propiedad_id);
            $consulta->bindValue(":imagen", $data['imagen']?? null);
            $consulta->bindValue(":tipo_imagen", $data['tipo_imagen']?? null);
            $consulta->execute();
            /// En los campos que no son requeridos les asigno null para tener un valor que se pueda vincular a la consulta sql
            $response->getBody()->write(json_encode(['Message'=> 'Propiedad Creada Correctamente']));
            return $response->withStatus(200);
        }

    }catch (PDOException $e){ 
        $response->getBody()->write(json_encode([
            'status' => "Bad Request",
            'message' => "Error al crear el inquilino". $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// Editar Propiedad
$app->put('/propiedades/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    $data = $request->getParsedBody();
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();
        /// Verificar si existen todos los campos
        $campos_requeridos =['domicilio', 'localidad_id', 'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'cantidad_dias', 
        'disponible', 'valor_noche', 'tipo_propiedad_id'];
        foreach($campos_requeridos as $campo){
            if(!isset($data[$campo])){
            $errores[$campo] = 'El campo '. $campo . ' es requerido';
            $codeerro[$campo] = 404;
            }
        }

        // Verificar si la fecha tiene un formato correcto
        if (isset($data['fecha_inicio_disponibilidad'])) {
            $fecha_inicio_disponibilidad = $data['fecha_inicio_disponibilidad'];
            $formato_correcto = 'Y-m-d'; // Define aquí el formato que esperas para la fecha
        
            $fecha_obj = DateTime::createFromFormat($formato_correcto, $fecha_inicio_disponibilidad);
            if ($fecha_obj === false || $fecha_obj->format($formato_correcto) !== $fecha_inicio_disponibilidad) {
                // La fecha no tiene el formato correcto
                $errores['fecha_formato'] = 'La fecha tiene un formato incorrecto. El formato esperado es: ' . $formato_correcto;
                $codeerro['fecha_formato'] = 404;
            }
        }  

        /// Verificaciones campo 'domicilio, localidad_id,tipo_propiedad_id'
        if (isset($data['domicilio']) && isset($data['localidad_id']) && isset($data['tipo_propiedad_id'])) {
            $domicilio = $data['domicilio'];
            $localidad_id = $data['localidad_id']; 
            $tipo_propiedad_id = $data['tipo_propiedad_id'];

            // Verificar si el de domicilio de la propiedad ya esta en uso (Excluyendo el ID )
            $sql ="SELECT * FROM propiedades WHERE domicilio = '". $domicilio ."' AND id != '". $id ."'";
            $consulta_repetido = $connection->query($sql);
            if ($consulta_repetido->rowCount()> 0){
                $errores['domicilio_repetido'] = 'El nombre del domicilio ya esta en uso';
                $codeerro['domicilio_repetido'] = 400;
            }
                
            /// Verificar si el ID de localidad existe en la tabla localidades
            $sql = "SELECT * FROM localidades WHERE id = '".$localidad_id."'";
            $consulta_localidad = $connection->query($sql);
            if($consulta_localidad->rowCount() == 0){
                $errores['localidad_existe'] = 'El ID '.$localidad_id.' no existe en la tabla localidades';
                $codeerro['localidad_existe'] = 400;
            }

            /// Verificar si el ID de tipo_propiedades existe en la tabla tipo_propiedades
            $sql = "SELECT * FROM tipo_propiedades WHERE id = '".$tipo_propiedad_id."'";
            $consulta_tipo_propiedades = $connection->query($sql);
            if($consulta_tipo_propiedades->rowCount() == 0){
                $errores['tipo_propiedad_existe'] = 'El ID '.$tipo_propiedad_id.' no existe en la tabla tipo_propiedades';
                $codeerro['tipo_propiedad_existe'] = 400;
            }
        }

        // Verificar si la propiedad existe
        $sql = "SELECT * FROM propiedades WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount() <= 0){ 
            $errores['propiedad_existe'] = 'La propiedad con el id: '. $id . ' no existe';
            $codeerro['propiedad_existe'] = 400;
        }

        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }
        else {
            $cantidad_huespedes = $data['cantidad_huespedes'];
            $cantidad_dias = $data['cantidad_dias']; 
            $disponible = $data['disponible']; 
            $valor_noche = $data['valor_noche'];
            /// Editar Propiedad
            $sql = "UPDATE propiedades SET domicilio = :domicilio, localidad_id = :localidad_id,
                        cantidad_huespedes = :cantidad_huespedes, 
                        fecha_inicio_disponibilidad = :fecha_inicio_disponibilidad, cantidad_dias = :cantidad_dias, disponible = :disponible, valor_noche = :valor_noche,
                        tipo_propiedad_id = :tipo_propiedad_id WHERE id = '". $id ."'";
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
        }
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar la propiedad". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
}); 

/// Eliminar Propiedad
$app->delete('/propiedades/{id}', function(Request $request, Response $response, $args){
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
            $code=204;
        }else{ 
            $response->getBody()->write(json_encode(['error'=> 'La propiedad con id: '.$id.' no existe']));
            $code=404;
        }  
        return $response->withStatus($code);

    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al eliminar la propiedad". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

/// Listar Propiedades 
$app->get('/propiedades', function(Request $request, Response $response){
    $connection = getConnection();
    $data = $request->getParsedBody();
    try {

        $campofiltros =['localidad_id', 'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'disponible'];
        $sql= "SELECT * FROM propiedades WHERE";
        $primera = true; 
        // Compruebo filtros activos
        foreach($campofiltros as $campo){
            if(isset($data[$campo])){
                $filtros = "";
                if (!$primera) {
                    $filtros .= " AND "; // Agregar AND si no es la primera vez que se agrega un filtro
                } else {
                    $primera = false; // Cambiar el estado para arrancar con los ANDs
                }
                $filtros .=  $campo." = '". $data[$campo] ."'";
                //echo $sql. '<br>'; //para probar code
                $sql .= " $filtros";
            }
        } 
       // echo 'Code final: '. $sql. '<br>'; //para probar code

        if (empty($filtros)) {
            $query = $connection->query('SELECT * FROM propiedades ORDER BY id');
            $propiedades = $query->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($propiedades));
            $code= 200;
        }
        else{
            $query = $connection->query($sql);
            $propiedades = $query->fetchAll(PDO::FETCH_ASSOC);
            if (empty($propiedades)) {
                $response->getBody()->write(json_encode('No hay propiedades que cumplan estos filtros'));
                $code= 404;
            }
            else{
            $response->getBody()->write(json_encode($propiedades));
            $code= 200;
            }
        }
        return $response->withStatus($code);
        
    } catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al listar las propiedades ". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

/// Ver Propiedad
$app->get('/propiedades/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    try {
        $connection = getConnection();
        $sql = "SELECT * FROM propiedades WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        /// Verificar si existe el campo
        if ($consulto_id->rowCount()> 0){
            $propiedadesx = $consulto_id->fetch(PDO::FETCH_ASSOC);
            $response->getBody()->write(json_encode([$propiedadesx]));
            $code=200;

        }else{ 
            $response->getBody()->write(json_encode(['error'=> 'La propiedad con id: '.$id.' no existe']));   
            $code=404;
         }  
         return $response->withStatus($code);

    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al obtener los datos de la propiedad". $e->getMessage()
        ]);
    }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

// ================================[ RESERVAS ]=========================================

/// Crear Reserva
$app->post('/reservas', function(Request $request, Response $response){
    $data = $request->getParsedBody();
    $errores = [];
    $codeerro = [];
    try{
        $connection = getConnection();

        /// Verificar si existen todos los campos requeridos
        $campos_requeridos =['propiedad_id', 'inquilino_id', 'fecha_desde', 'cantidad_noches'];
        foreach($campos_requeridos as $campo){
            if(!isset($data[$campo])){
            $errores[$campo] = 'El campo '. $campo . ' es requerido';
            $codeerro[$campo] = 404;
            }
        }

        // Verificar si la fecha tiene un formato correcto
        if (isset($data['fecha_desde'])) {
            $fecha_desde = $data['fecha_desde'];
            $formato_correcto = 'Y-m-d'; // Define aquí el formato que esperas para la fecha
        
            $fecha_obj = DateTime::createFromFormat($formato_correcto, $fecha_desde);
            if ($fecha_obj === false || $fecha_obj->format($formato_correcto) !== $fecha_desde) {
                // La fecha no tiene el formato correcto
                $errores['fecha_formato'] = 'La fecha tiene un formato incorrecto. El formato esperado es: ' . $formato_correcto;
                $codeerro['fecha_formato'] = 404;
            }
        }
        /// Verificaciones campo 'propiedad_id, inquilino_id
        if (isset($data['propiedad_id']) && isset($data['inquilino_id'])) {
            $propiedad_id = $data['propiedad_id'];
            $inquilino_id = $data['inquilino_id'];
            /// Verificar si el inquilino ya tiene una reserva en la misma propiedad
            $sql = "SELECT * FROM reservas WHERE inquilino_id = '". $inquilino_id ."' AND propiedad_id = '".$propiedad_id."'";
            $consulta_reserva_existente = $connection->query($sql);
            if ($consulta_reserva_existente->rowCount() > 0){
                $errores['inquilino_reserva'] = 'El inquilino ya tiene una reserva en esta propiedad';
                $codeerro['inquilino_reserva'] = 400;
            }
            /// Verificar si la propiedad esta disponible y el inquilino esta activo
            $sql = "SELECT disponible FROM propiedades WHERE id = '". $propiedad_id ."' AND disponible = 1 ";
            $propiedad_disponible = $connection->query($sql);
            if($propiedad_disponible->rowCount() == 0){
                $errores['propiedad_disponible'] = 'La propiedad con id: '.$propiedad_id.' no esta disponible o no existe';
                $codeerro['propiedad_disponible'] = 400;
            }

            $sql = "SELECT activo FROM inquilinos WHERE id = '". $inquilino_id ."' AND activo = 1 ";
            $inquilno_activo = $connection->query($sql);
            if($inquilno_activo->rowCount() == 0){
                $errores['inquilino_activo']= 'El inquilino con id: '.$inquilino_id.' no esta activo o no existe';
                $codeerro['inquilino_activo'] = 400;
            }
        }

       /// Mostrar todos los errores
       if (!empty($errores)){
        $error = "Errores: <br>";
        foreach($errores as $campo =>$value){
            $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
        }
        $response->getBody()->write(json_encode([$error]));
        return $response->withStatus($codeerro[$campo]);
    }
        else{
            $cantidad_noches = $data['cantidad_noches'];
            /// Obtener el valor de la noche en una propiedad
            $propiedad_id = $data['propiedad_id'];
            $sql = "SELECT valor_noche FROM propiedades WHERE id = '". $propiedad_id ."'";
            $consulta_valor = $connection->query($sql);
            $valor_noche_dato = $consulta_valor->fetch(PDO::FETCH_ASSOC);

            $valor_noche = $valor_noche_dato['valor_noche'];
            $valor_total = $valor_noche * $data['cantidad_noches'];

            /// Agrego La Reserva
            $sql = "INSERT INTO reservas (propiedad_id, inquilino_id, fecha_desde, cantidad_noches, valor_total) 
                    VALUES (:propiedad_id, :inquilino_id, :fecha_desde, :cantidad_noches, :valor_total)";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":propiedad_id", $propiedad_id);
            $consulta->bindValue(":inquilino_id", $inquilino_id);
            $consulta->bindValue(":fecha_desde",  $fecha_desde);
            $consulta->bindValue(":cantidad_noches",$cantidad_noches);
            $consulta->bindValue(":valor_total", $valor_total);
            $consulta->execute();
            $response->getBody()->write(json_encode(['Message'=> 'Reserva Creada Correctamente']));
            return $response->withStatus(200);
        }

    }catch (PDOException $e){ 
        $response->getBody()->write(json_encode([
            'status' => "Bad Request",
            'message' => "Error al crear la reserva". $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// Editar Reserva
$app->put('/reservas/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
    $errores = [];
    $codeerro = [];
    $data = $request->getParsedBody();
    try{
        $connection = getConnection(); 
        /// Verificar si existen todos los campos
        $campos_requeridos =['propiedad_id', 'inquilino_id', 'fecha_desde', 'cantidad_noches'];
        foreach($campos_requeridos as $campo){
            if(!isset($data[$campo])){
            $errores[$campo] = 'El campo '. $campo . ' es requerido';
            $codeerro[$campo] = 404;
            }
        }

        // Verificar si la fecha tiene un formato correcto
        if (isset($data['fecha_desde'])) {
            $fecha_desde = $data['fecha_desde'];
            $formato_correcto = 'Y-m-d'; // Define aquí el formato que esperas para la fecha
        
            $fecha_obj = DateTime::createFromFormat($formato_correcto, $fecha_desde);
            if ($fecha_obj === false || $fecha_obj->format($formato_correcto) !== $fecha_desde) {
                // La fecha no tiene el formato correcto
                $errores['fecha_formato'] = 'La fecha tiene un formato incorrecto. El formato esperado es: ' . $formato_correcto;
                $codeerro['fecha_formato'] = 404;
            }
        }


        if (isset($data['propiedad_id']) && isset($data['inquilino_id'])) {
            /// Obtener los datos
            $propiedad_id = $data['propiedad_id'];
            $inquilino_id = $data['inquilino_id'];
            /// Verificar si el ID de inquilinos existe en la tabla de inquilinos
            $sql = "SELECT * FROM inquilinos WHERE id = '".$inquilino_id."'";
            $consulta_inquilino = $connection->query($sql);
            if($consulta_inquilino->rowCount() == 0){
                $errores['inquilino_existe'] = 'El ID '.$inquilino_id.' no existe en la tabla de inquilinos';
                $codeerro['inquilino_existe'] = 400;
            } 

            /// Verificar si el ID de propiedad existe en la tabla de propiedad
            $sql = "SELECT * FROM propiedades WHERE id = '".$propiedad_id."'";
            $consulta_propiedad = $connection->query($sql);
            if($consulta_propiedad->rowCount() == 0){
                $errores['propiedad_existe'] = 'El ID '.$propiedad_id.' no existe en la tabla propiedades';
                $codeerro['propiedad_existe'] = 400;
            } 

            /// Verificar si fecha_desde es menor que la fecha actual
            if (isset($data['fecha_desde'])) {
                $fecha_actual=date("Y-m-d");
                if ($fecha_actual >= $fecha_desde) {
                    $errores['comenzo'] = 'No se puede editar la reserva porque ya comenzo';
                    $codeerro['comenzo'] = 404;
                }
            }
        }
        // Verificar si la reserva existe
        $sql = "SELECT * FROM reservas WHERE id = '". $id ."'";
        $consulto_id = $connection->query($sql);
        if ($consulto_id->rowCount() <= 0){ 
            $errores['reserva_existe'] = 'La reserva con el id: '. $id . ' no existe';
            $codeerro['reserva_existe'] = 400;
        }

        /// Mostrar todos los errores
        if (!empty($errores)){
            $error = "Errores: <br>";
            foreach($errores as $campo =>$value){
                $error .= $value . '<br>'; // Agrega un salto de línea después de cada error
            }
            $response->getBody()->write(json_encode([$error]));
            return $response->withStatus($codeerro[$campo]);
        }
        else{
            $cantidad_noches = $data['cantidad_noches'];
            /// Obtener el valor de la noche en una propiedad
            $propiedad_id = $data['propiedad_id'];
            $sql = "SELECT valor_noche FROM propiedades WHERE id = '". $propiedad_id ."'";
            $consulta_valor = $connection->query($sql);
            $valor_noche_dato = $consulta_valor->fetch(PDO::FETCH_ASSOC);

            $valor_noche = $valor_noche_dato['valor_noche'];
            $valor_total = $valor_noche * $data['cantidad_noches'];

            /// Edito la Reserva
            $sql = "UPDATE reservas SET propiedad_id = :propiedad_id, inquilino_id = :inquilino_id, 
                        fecha_desde = :fecha_desde, cantidad_noches = :cantidad_noches, valor_total = :valor_total WHERE id = '". $id ."'";
            $consulta = $connection->prepare($sql);
            $consulta->bindValue(":propiedad_id", $propiedad_id);
            $consulta->bindValue(":inquilino_id", $inquilino_id);
            $consulta->bindValue(":fecha_desde", $fecha_desde);
            $consulta->bindValue(":cantidad_noches", $cantidad_noches);
            $consulta->bindValue(":valor_total", $valor_total);
            $consulta->execute();
            $response->getBody()->write(json_encode(['message' => 'La reserva con el id: '. $id . ' se edito de forma exitosa']));
            return $response->withStatus(201);
        }
    }catch(PDOException $e){
        $payload = json_encode([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al editar la reserva". $e->getMessage()
        ]);
    }
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

/// Eliminar Reserva 
$app->delete('/reservas/{id}', function(Request $request, Response $response, $args){
    $id = $args['id'];
        try{
            $connection = getConnection();
            $sql = "SELECT * FROM reservas WHERE id = '". $id ."'";
            $reservas_id = $connection->query($sql);
            /// Verificar si existe el id
            if ($reservas_id->rowCount()> 0){    
                /// Verificar si fecha_desde es menor que la fecha actual
                $sql = "SELECT fecha_desde FROM reservas WHERE id = '". $id ."'";
                $consulta_fecha = $connection->query($sql);
                $fecha_actual= $consulta_fecha->fetch(PDO::FETCH_ASSOC);
                if ($fecha_actual>=date("Y-m-d ")) {
                    $response->getBody()->write(json_encode(['error'=> 'No se puede eliminar la reserva porque ya comenzo']));
                    $code=400;
                }           
                else{
                    $sql = "DELETE FROM reservas WHERE id = '". $id ."' ";
                    $query = $connection->query($sql);
                    $query->fetch(PDO::FETCH_ASSOC);
                    $response->getBody()->write(json_encode(['message'=> 'La reserva se elimino correctamente']));
                    $code=204;
                }
            }else{ $response->getBody()->write(json_encode(['error'=> 'La reserva con id: '.$id.' no existe']));
               $code=404;
            }  
            return $response->withStatus($code);

        }catch(PDOException $e){
            $payload = json_encode([
                'status' => "Bad Request",
                'code' => 400,
                'message' => "Error al eliminar la reserva". $e->getMessage()
            ]);
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

/// Listar Reservas
$app->get('/reservas', function(Request $request, Response $response){
    $connection = getConnection();

    try {
        $query = $connection->query('SELECT * FROM reservas ORDER BY id');
        $reservas = $query->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_encode([
            'status' => "success",
            'code' => 200,
            'data' => $reservas
        ]);
        
    } catch (PDOException $e){
        $payload = json_encode ([
            'status' => "Bad Request",
            'code' => 400,
            'message' => "Error al listar las reservas ". $e->getMessage()
        ]);
    }

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});
$app->run();
