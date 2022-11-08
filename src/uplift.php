<?php
header('Content-type: application/json');

// For debugging:
error_reporting(E_ALL);
ini_set('display_errors', '1');

// TODO Change this as needed. SQLite will look for a file with this name, or
// create one if it can't find it.
$dbName = 'uplift.db';

session_start();

// Leave this alone. It checks if you have a directory named www-data in
// you home directory (on a *nix server). If so, the database file is
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

// Put your other code here.

createTables();

//// For ad hoc API
// $supportedActions = [
//     'addUser', 'addQuiz', 'addQuizItem', 'removeQuizItem', 
//     'updateQuizItem', 'submitResponses', 'signin', 'signout'
// ];


// // Handle incoming requests.
// if(array_key_exists('action', $_POST)){
//     $action = $_POST['action'];
//     // if($action == 'addUser'){
//     //     addUser($_POST);
//     // } else if($action == 'addQuiz'){
//     //     addQuiz($_POST);
//     // } else if($action == 'addQuizItem'){
//     //     addQuizItem($_POST);
//     // } else if($action == 'removeQuizItem'){
//     //     removeQuizItem($_POST);
//     // } else if($action == 'updateQuizItem'){
//     //     updateQuizItem($_POST);
//     // } else if($action == 'submitResponses'){
//     //     submitResponses($_POST);
//     if(array_search($_POST['action'], $supportedActions) !== false){
//         $_POST['action']($_POST);
//     } else {
//         die(json_encode([
//             'success' => false, 
//             'error' => 'Invalid action: '. $action
//         ]));
//     }
// }

// Routes.
$routes = [
    makeRoute("PATCH", "#^/quizzes/(\d+)/quiz_items/(\d+)/question/?(\?.*)?$#", updateQuizItem)

    // TODO Make the other routes.
];


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

function createTables(){
    global $dbh;

    try{
        // Create the Users table.
        $dbh->exec('create table if not exists Users('. 
            'id integer primary key autoincrement, '. 
            'username text unique, '. 
            'password text, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()))');

        // Create the Quizzes table.
        $dbh->exec('create table if not exists Quizzes('. 
            'id integer primary key autoincrement, '. 
            'authorId integer, '. 
            'name text, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '.
            'foreign key (authorId) references Users(id))');

        // Create the QuizItems table.
        $dbh->exec('create table if not exists QuizItems('. 
            'id integer primary key autoincrement, '. 
            'quizId integer, '. 
            'question text, '. 
            'answer text, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '. 
            'foreign key (quizId) references Quizzes(id))');

        // Create the Submissions table.
        $dbh->exec('create table if not exists Submissions('. 
            'id integer primary key autoincrement, '. 
            'quizId integer, '. 
            'submitterId integer, '. 
            'numCorrect integer, '. 
            'score real, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '. 
            'foreign key (quizId) references Quizzes(id), '.
            'foreign key (submitterId) references Users(id))');

        // Create the QuizItemResponses table.
        $dbh->exec('create table if not exists QuizItemResponses('. 
            'quizItemId integer, '. 
            'submissionId integer, '. 
            'response text, '. 
            'isCorrect bool, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '. 
            'primary key (quizItemId, submissionId), '.
            'foreign key (quizItemId) references QuizItems(id), '.
            'foreign key (submissionId) references Submissions(id))');

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error creating the tables: $e"
        ]));
    }
}

function error($message, $responseCode=400){
    http_response_code($responseCode);
    die(json_encode([
        'success' => false, 
        'error' => $message
    ]));
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
 * Checks if the user is signed in; if not, emits a 403 error.
 */
function mustBeSignedIn(){
    if(!(key_exists('signedin', $_SESSION) && $_SESSION['signedin'])){
        error("You must be signed in to perform that action.", 401);
    }
}

/**
 * Log a user in. Requires the parameters:
 *  - username
 *  - password
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function signin($data){
    if(authenticate($data['username'], $data['password'])){
        $_SESSION['signedin'] = true;
        $_SESSION['user-id'] = getUserByUsername($data['username'])['id'];
        $_SESSION['username'] = $data['username']; 

        die(json_encode([
            'success' => true
        ]));
    } else {
        error('Username or password not found.', 401);
    }
}

/**
 * Logs the user out if they are logged in.
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function signout($data){
    session_destroy();
    die(json_encode([
        'success' => true
    ]));
}


/**
 * Adds a user to the database. Requires the parameters:
 *  - username
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the user just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addUser($data){
    global $dbh;

    $saltedHash = password_hash($data['password'], PASSWORD_BCRYPT);

    try {
        $statement = $dbh->prepare('insert into Users(username, password) '.
            'values (:username, :password)');
        $statement->execute([
            ':username' => $data['username'],
            ':password' => $saltedHash
        ]);

        $userId = $dbh->lastInsertId();
        die(json_encode([
            'success' => true,
            'id' => $userId
        ]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error adding the user: $e"
        ]));
    }
}

/**
 * Adds a quiz to the database. Requires the parameters:
 *  - authorUsername
 *  - name (of quiz) 
 *
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the quiz just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addQuiz($data){
    global $dbh;

    // authenticate($data['username'], $data['password']);
    mustBeSignedIn();

    // Look up userid first.
    #$user = getUserByUsername($data['username']);
    
    try {
        $statement = $dbh->prepare('insert into Quizzes'. 
            '(authorId, name) values (:authorId, :name)');
        $statement->execute([
            ':authorId' => $_SESSION['user-id'], 
            ':name' => $data['name']
        ]);

        die(json_encode([
            'success' => true,
            'id' => $dbh->lastInsertId()
        ]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error adding the quiz: $e"
        ]));
    }
}

/**
 * Adds a quiz item to the database. Requires the parameters:
 *  - quizId
 *  - question
 *  - answer
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the quiz item just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addQuizItem($data){
    global $dbh;

    // authenticate($data['username'], $data['password']);
    mustBeSignedIn();

    $quizData = getTableRow('Quizzes', ['id' => $data['quizId']]);

    if($quizData === false || $_SESSION['user-id'] != $quizData['authorId']){
        error('You are unauthorized to access that quiz.', 403);
    }

    try {
        $statement = $dbh->prepare('insert into QuizItems'. 
            '(quizId, question, answer) values (:quizId, :question, :answer)');
        $statement->execute([
            ':quizId' => $data['quizId'], 
            ':question' => $data['question'],
            ':answer' => $data['answer']
        ]);

        die(json_encode([
            'success' => true,
            'id' => $dbh->lastInsertId()
        ]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error adding the quiz item: $e"
        ]));
    }
}



/**
 * Removes a quiz item from the database. Requires the parameters:
 *  - quizItemId
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function removeQuizItem($data){
    global $dbh;

    // authenticate($data['username'], $data['password']);
    mustBeSignedIn();

    try {
        $statement = $dbh->prepare('delete from QuizItems '. 
            'where id = :id');
        $statement->execute([
            ':id' => $data['quiItemId']]);

        die(json_encode(['success' => true]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error removing the quiz item: $e"
        ]));
    }
}

/**
 * Updates a quiz item in the database. Requires the parameters:
 *  - quizItemId
 *  - question
 *  - answer
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function updateQuizItem($data){
    global $dbh;

    // authenticate($data['username'], $data['password']);
    mustBeSignedIn();

    hasRequiredParams($data, ['quizItemId', 'question', 'answer']);

    try {
        $statement = $dbh->prepare('update QuizItems set '. 
            'question = :question, '.
            'answer = :answer, '.
            'updatedAt = datetime() '.
            'where id = :id');
        $statement->execute([
            ':question' => $data['question'],
            ':answer' => $data['answer'],
            ':id' => $data['quizItemId']
        ]);

        die(json_encode(['success' => true]));

    } catch(Exception $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error updating the quiz item: $e"
        ]));
    }
}

function hasRequiredParams($data, $params){
    foreach($params as $param){
        if(!array_key_exists($param, $data)){
            error("Missing required parameter: $param", 400);
        }
    }
}


/**
 * Updates a quiz item in the database. Requires the parameters:
 *  - submitterUsername
 *  - quizId
 *  - responses:
 *    * quizItemId
 *    * response
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function submitResponses($data){
    global $dbh;

    // authenticate($data['username'], $data['password']);
    mustBeSignedIn();

    //$user = getUserByUsername($data['submitterUsername']);

    try {
        // Strategy: 
        // 1. grab all of the item that go with this quiz
        // 2. grade the responses
        // 3. create a new submission entry
        // 4. create a new entry for each response

        $dbh->beginTransaction();

        // 1. Grab all of the item that go with this quiz
        $statement = $dbh->prepare('select id, answer from QuizItems '. 
            'where quizId = :quizId');
        $statement->execute([
            ':quizId' => $data['quizId']
        ]);
        $quizItems = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Put them into a nicer lookup.
        $quizItemAnswerLookup = [];
        foreach($quizItems as $quizItem){
            $quizItemAnswerLookup[$quizItem['id']] = $quizItem['answer'];
        }

        // 2. Grade the responses. 
        $responses = [];
        $numCorrect = 0;
        foreach($data['responses'] as $response){
            $isCorrect = false;
            if($quizItemAnswerLookup[$response['quizItemId']] == $response['response']){
                $isCorrect = true;
                $numCorrect += 1;
            }

            array_push($responses, [
                'quizItemId' => $response['quizItemId'],
                'response' => $response['response'],
                'isCorrect' => $isCorrect
            ]);
        }

        // 3. Create a new submission entry.
        $statement = $dbh->prepare('insert into Submissions('. 
            'quizId, submitterId, numCorrect, score) values ('. 
            ':quizId, :submitterId, :numCorrect, :score)');
        $statement->execute([
            ':quizId' => $data['quizId'],
            ':submitterId' => $_SESSION['user-id'],
            ':numCorrect' => $numCorrect,
            ':score' => ($numCorrect/count($responses))
        ]);

        $submissionId = $dbh->lastInsertId();
        
        // 4. Create a new entry for each response.
        $statementText = 'insert into QuizItemResponses('. 
            'quizItemId, submissionId, response, isCorrect) values ';
        $statementData = [];

        for($i = 0; $i < count($responses); $i++){
            $statementText .= "(?, ?, ?, ?)";
            if($i < count($responses)-1){
                $statementText .= ', ';
            }
            array_push($statementData, 
                $responses[$i]['quizItemId'],
                $submissionId,
                $responses[$i]['response'],
                $responses[$i]['isCorrect']
            
            );
        }

        $statement = $dbh->prepare($statementText);
        $statement->execute($statementData);
        
        $dbh->commit();

        die(json_encode([
            'success' => true,
            'id' => $submissionId
        ]));

    } catch(PDOException $e){
        $dbh->rollBack();

        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error submitting the responses: $e"
        ]));
    }
}

/**
 * Outputs the row of the given table that matches the given id.
 */
function getTableRow($table, $data){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from $table where id = :id");
        $statement->execute([':id' => $data['id']]);
        // Use fetch here, not fetchAll -- we're only grabbing a single row, at 
        // most.
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        //die(json_encode(['success' => true, 'data' => $row]));
        return $row;

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error fetching rows from table $table: $e"
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
 * Outputs all the values of a database table. 
 * 
 * @param table The name of the table to display.
 */
function getTable($table){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from $table");
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        die(json_encode(['success' => true, 'data' => $rows]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error fetching rows from table $table: $e"
        ]));
    }
}



/**
 *  Creates a map with three keys pointing the the arguments passed in:
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
?>