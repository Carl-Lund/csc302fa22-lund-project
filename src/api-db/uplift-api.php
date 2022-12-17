<?php
// If the file being requested exists, load it. This is for running in
// PHP dev mode.
if(file_exists(".". $_SERVER['REQUEST_URI'])){
    return false;
}

require 'jwt.php';

header('Content-type: application/json');

// For debugging:
error_reporting(E_ALL);
ini_set('display_errors', '1');

// TODO Change this as needed. SQLite will look for a file with this name, or
// create one if it can't find it.
$dbName = 'uplift.db';

session_start();

// Leave this alone. It checks if you have a directory named www-data in
// your home directory (on a *nix server). If so, the database file is
// sought/created there. Otherwise, it uses the current directory.
// The former works on digdug where I've set up the www-data folder for you;
// the latter should work on your computer.
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
$homeDir = count($matches) > 1 ? $matches[1] : '';
$dataDir = "/home/$homeDir/www-data";
if(!file_exists($dataDir)){
    $dataDir = __DIR__;
}
$dbh = new PDO("sqlite:$dataDir/$dbName")   ;
// Set our PDO instance to raise exceptions when errors are encountered.
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//-----------------------------------------------------------------------------
// CODE FROM CLASS THAT HANDLES CREATING DB TABLES, AUTH HEADER, AND RESTFUL API ROUTES

createTables();

// Extract Authorization header if present.
$jwtData = null;
if(array_key_exists('HTTP_AUTHORIZATION', $_SERVER)){
    $jwtData = verifyJWT(
        str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']), $SECRET);
}

$routes = [
    // sign up -- POST /users --> /user/:id
    makeRoute("POST", "#^/users/?(\?.*)?$#", 'addUser'),
    // sign in -- POST /sessions
    makeRoute("POST", "#^/sessions/?(\?.*)?$#", 'signin'),
    // post recipe -- POST /recipes
    makeRoute("POST", "#^/recipes/?(\?.*)?$#", 'addRecipe')

];

//-----------------------------------------------------------------------------
// CODE FROM CLASS THAT HANDLES INCOMING REQUESTS

// Initial request processing.
// If this is being served from a public_html folder, find the prefix (e.g., 
// /~jsmith/path/to/dir).
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
if(count($matches) > 0){
    $matches = [];
    preg_match("#/home/([^/]+)/public_html/(.*$)#", dirname(__FILE__), $matches);
    $prefix = "/~". $matches[1] ."/". $matches[2];
    $uri = preg_replace("#^". $prefix ."/?#", "/", $_SERVER['REQUEST_URI']);
} else {
    $prefix = "";
    $uri = $_SERVER['REQUEST_URI'];
}

// Get the request method; PHP doesn't handle non-GET or POST requests
// well, so we'll mimic them with POST requests with a "_method" param
// set to the method we want to use.
$method = $_SERVER["REQUEST_METHOD"];
$params = $_GET;
if($method == "POST"){
    $params = $_POST;
    if(array_key_exists("_method", $_POST))
        $method = strtoupper($_POST["_method"]);
} 

// Parse the request and send it to the corresponding handler.
$foundMatchingRoute = false;
$match = [];
foreach($routes as $route){
    if($method == $route["method"]){
        preg_match($route["pattern"], $uri, $match);
        if($match){
            error(json_encode($route["controller"]($uri, $match, $params)));
            $foundMatchingRoute = true;
        }
    }
}

if(!$foundMatchingRoute){
    error("No route found for: $method $uri");
}

//-----------------------------------------------------------------------------

function error($message, $responseCode=400){
    http_response_code($responseCode);
    die(json_encode([
        'success' => false, 
        'error' => $message
    ]));
}

/**
 *  Creates a map with three keys pointing at the arguments passed in:
 *      - method => $method
 *      - pattern => $pattern
 *      - controller => $function
 * 
 * @param method The http method for this route.
 * @param pattern The pattern the URI is matched against. Include groupings
 *                around ids, etc.
 * @param function The name of the function to call.
 * @return A map with the key,value pairs described above.
 */
function makeRoute($method, $pattern, $function){
    return [
        "method" => $method,
        "pattern" => $pattern,
        "controller" => $function
    ];
}

function createTables(){
    global $dbh;

    try{
        // Create the Users table.
        $dbh->exec(
            'create table if not exists Users('. 
            'id integer primary key autoincrement, '. 
            'username text unique, '. 
            'password text, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()))'
        );

        // Create the Recipes table.
        $dbh->exec(
            'create table if not exists Recipes('.
            'id integer primary key autoincrement, '.
            'title text, '.
            'ingredients text, '.
            'instructions text, '.
            'votes integer, '.
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()))'
        );



    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error creating the tables: $e"
        ]));
    }
}

function authenticate($username, $password){
    global $dbh;

    // check that username and password are not null.
    if($username == null || $password == null){
        error('Bad request -- both a username and password are required');
    }

    // grab the row from Users that corresponds to $username
    try {
        $statement = $dbh->prepare('select password from Users '.
            'where username = :username');
        $statement->execute([
            ':username' => $username,
        ]);
        $passwordHash = $statement->fetch()[0];
        
        // user password_verify to check the password.
        if(password_verify($password, $passwordHash)){
            return true;
        }
        error('Could not authenticate username and password.', 401);
        

    } catch(Exception $e){
        error('Could not authenticate username and password: '. $e);
    }
}

/**
 * Log a user in.
 * 
 * @param $uri The URI that was requested (unused).
 * @param $matches The list of groups matched in the URI (unused).
 * @param $params A map with the following keys:
 *          - username
 *          - password
 * 
 * Responds with a JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function signin($uri, $matches, $params){
    global $SECRET;

    if(authenticate($params['username'], $params['password'])){
        $user = getUserByUsername($params['username']);
        $jwt = makeJWT([
            'user-id' => $user['id'],
            'username' => $params['username'],
            'exp' => (new DateTime('tomorrow'))->format('c')
        ], $SECRET);

        die(json_encode([
            'username' => $params['username'],
            'userURI' => "/users/${user['id']}",
            'jwt' => $jwt,
            'success' => true
        ]));
    } else {
        error('Username or password not found.', 401);
    }
}

/**
 * Adds a user to the database.
 * 
 * @param $uri The URI that was requested (unused).
 * @param $matches The list of groups matched in the URI (unused).
 * @param $params A map with the following keys:
 *          - username
 *          - password
 * 
 * Responds with a JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the user just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addUser($uri, $matches, $params){
    global $dbh;

    $saltedHash = password_hash($params['password'], PASSWORD_BCRYPT);

    try {
        $statement = $dbh->prepare('insert into Users(username, password) '.
            'values (:username, :password)');
        $statement->execute([
            ':username' => $params['username'],
            ':password' => $saltedHash
        ]);

        $userId = $dbh->lastInsertId();

        created("/users/$userId", [
            'success' => true,
            'id' => $userId
        ]);


    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error adding the user: $e"
        ]));
    }
}

/**
 * Adds a recipe to the database.
 * 
 * @param $uri The URI that was requested (unused).
 * @param $matches The list of groups matched in the URI (unused).
 * @param $params A map with the following keys:
 *          - username
 *          - password
 * 
 * Responds with a JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the user just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addRecipe($uri, $matches, $params){
    global $dbh;

    try {
        $statement = $dbh->prepare('insert into Recipes(username, password) '.
            'values (:username, :password)');
        $statement->execute([
            ':username' => $params['username'],
            ':password' => $saltedHash
        ]);

        created("/recipe/$userId", [
            'success' => true,
            'id' => $userId
        ]);


    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error adding the user: $e"
        ]));
    }
}

/**
 * Looks up a user by their username. 
 * 
 * @param $username The username of the user to look up.
 * @return The user's row in the Users table or null if no user is found.
 */
function getUserByUsername($username){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from Users where username = :username");
        $statement->execute([':username' => $username]);
        // Use fetch here, not fetchAll -- we're only grabbing a single row, at 
        // most.
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row;

    } catch(PDOException $e){
        return null;
    }
}

/**
 * Emits a 201 Created response along with a JSON object with two fields:
 *   - success => true
 *   - data => the data that was passed in as `$data`
 * Sets the "Location" field of the header to the given URI.
 * 
 * @param $uri The URI of the created resource.
 * @param $data The value to assign to the `data` field of the output.
 */
function created($uri, $data){
    http_response_code(201);
    header("Location: $uri");
    $response = ['success' => true];
    if($data){
        $response['data'] = $data;
    }
    die(json_encode($response));
}

?>