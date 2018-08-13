<?php
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

require 'vendor/autoload.php';
include 'config.php';

$app = new \Slim\App();

// C R U D :

// CREATE (INSERT)
$app->post('/insert/{table_name}', function (Request $req, Response $res, array $args) {
  verifyToken();
  global $db;
  $table_name = $args['table_name'];
  $data = $req->getParsedBody();

  $arr = [];
  try {
    $db->$table_name[] = $data;
    $arr['success'] = true;
  } catch (Exception $e) {
    $arr['success'] = false;
    $arr['msg'] = $e;
  } finally {
    return $res->withJson($arr);
  }
});

// READ ALL
$app->post('/get/{table_name}', function (Request $req, Response $res, array $args) {
  // verifyToken();
  global $db;
  $table_name = $args['table_name'];

  $rows = [];
  try {
    $rows = $db->$table_name->select()->run();
  } catch (Exception $e) {
    // do nothing
  } finally {
    return $res->withJson($rows);
  }
});

// (READ) WHERE '='
$app->post('/get/{table_name}/{column_name}/{value}', function (Request $req, Response $res, array $args) {
  verifyToken();
  global $db;
  $table_name = $args['table_name'];
  $column_name = $args['column_name'];
  $value = $args['value'];

  $rows = [];
  try {
    $rows = $db->$table_name
      ->select()
      ->by($column_name, strtolower($value))
      ->run();
  } catch (Exception $e) {
    // do nothing
  } finally {
    return $res->withJson($rows);
  }
});

// READ ONE (by id)
$app->post('/get_one/{table_name}/{id}', function (Request $req, Response $res, array $args) {
  verifyToken();
  global $db;
  $table_name = $args['table_name'];
  $id = $args['id'];

  $row = [];
  try {
    $row = $db->$table_name
      ->select()
      ->one()
      ->by('id', $id)
      ->run();
  } catch (Exception $e) {
    // do nothing
  } finally {
    return $res->withJson($row);
  }
});

// (READ) SEARCH 'LIKE'
$app->post('/search/{table_name}/{column_name}/{value}', function (Request $req, Response $res, array $args) {
  verifyToken();
  global $db;
  $table_name = $args['table_name'];
  $column_name = $args['column_name'];
  $value = $args['value'];

  $rows = [];
  try {
    $rows = $db->$table_name
      ->select()
      ->where('lower(' . $column_name . ') LIKE :search', [':search' => '%' . strtolower($value) . '%'])
      ->run();
  } catch (Exception $e) {
    // do nothing
  } finally {
    return $res->withJson($rows);
  }
});

// UPDATE
$app->post('/update/{table_name}/{id}', function (Request $req, Response $res, array $args) {
  verifyToken();
  global $db;
  $table_name = $args['table_name'];
  $id = $args['id'];
  $data = $req->getParsedBody();

  $arr = [];
  try {
    $db->$table_name[id] = $data;
    $arr['success'] = true;
  } catch (Exception $e) {
    $arr['success'] = false;
    $arr['msg'] = $e;
  } finally {
    return $res->withJson($arr);
  }
});

// DELETE
$app->post('/delete/{table_name}/{id}', function (Request $req, Response $res, array $args) {
  verifyToken();
  global $db;
  $table_name = $args['table_name'];
  $id = $args['id'];

  $arr = [];
  try {
    unset($db->$table_name[id]);
    $arr['success'] = true;
  } catch (Exception $e) {
    $arr['success'] = false;
    $arr['msg'] = $e;
  } finally {
    return $res->withJson($arr);
  }
});

//////////////////////////////////////////////////////////////////////////////////////////

// REGISTER
$app->post('/register', function (Request $req, Response $res, array $args) {
  global $db, $secret_key, $table_users;
  $arr = array();

  // get http request params
  $data = $req->getParsedBody();
  $username = $data['username'];

  try {
    // check if username exists
    $exists = $db->$table_users->count()->by('username', $username)->run();
    if ($exists) {
      $arr['success'] = false;
      $arr['msg'] = 'username already exist';
    } else {
      $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
      $currentDateTime = date('Y-m-d H:i:s');
      $insertData = array(
        'fullname' => $data['fullname'],
        'username' => $username,
        'gender' => $data['gender'],
        'hashed_password' => $hashed_password,
        'created_at' => $currentDateTime,
        'modified_at' => $currentDateTime,
      );
      $db->$table_users[] = $insertData;
      $arr['success'] = true;
    }
  } catch (Exception $e) {
    $arr['success'] = false;
    $arr['msg'] = $e;
  } finally {
    return $res->withJson($arr);
  }

});

// LOGIN
$app->post('/login', function (Request $req, Response $res, array $args) {
  global $db, $secret_key, $table_users;

  // HTTP POST Request with params 'username' & 'password'
  $data = $req->getParsedBody();
  $username = $data['username'];
  $password = $data['password'];

  $user = $db->$table_users->select()->one()->by('username', $username)->run();
  $arr = array(); //prepare return array
  try {
    if ($user) { // if user exists
      $hashed_password = $user->hashed_password;
      if (password_verify($password, $hashed_password)) {
        // username & password matched!
        $jwt = new \Lindelius\JWT\JWT();
        // $jwt->exp = time() + 7200; // expire after 2 hours (7200 seconds)
        $jwt->iat = time(); //

        // YOU CAN ALSO PUT SOME INFO, LIKE:
        $jwt->user_id = $user->id;
        // $jwt->is_admin = $user->is_admin';

        // AND THEN GENERATE THE TOKEN:
        $generated_token = $jwt->encode($secret_key);

        $arr['success'] = true;
        $arr['token'] = $generated_token; // put the token into array
      } else {
        $arr['success'] = false;
        $arr['msg'] = 'Wrong password!';
      }
    } else {
      $arr['success'] = false;
      $arr['msg'] = 'Username is not registered';
    }
  } catch (Exception $e) {
    $arr['success'] = false;
    $arr['msg'] = $e;
  } finally {
    return $res->withJson($arr);
  }
});

$app->run();
