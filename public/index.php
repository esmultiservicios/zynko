<?php
$root = dirname(__DIR__);
if (!is_file($root.'/storage/installed.lock')) { header('Location: install.php'); exit; }
session_start();
$page = $_GET['page'] ?? (isset($_SESSION['demo_user']) ? 'dashboard' : 'login');
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='login') { $_SESSION['demo_user']=['name'=>'Sandra','company'=>'ES MULTISERVICIOS']; header('Location: ?page=dashboard'); exit; }
if ($page==='logout') { session_destroy(); header('Location: ?page=login'); exit; }
$allowed=['login','dashboard','inbox','channels','users','chatbot','integrations','billing','settings','onboarding']; if(!in_array($page,$allowed,true)) $page='dashboard';
if($page!=='login' && !isset($_SESSION['demo_user'])) { header('Location: ?page=login'); exit; }
require __DIR__.'/../app/Views/'.$page.'.php';
